/**
 * FMHY Client Application Logic
 * Implements AJAX Navigation, Dark Mode, Live Search, and Base64 Decoding.
 */

document.addEventListener('DOMContentLoaded', () => {
    // --- 1. Theme Configuration ---
    const initTheme = () => {
        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
            document.documentElement.classList.add('dark');
            document.documentElement.classList.remove('light');
        } else {
            document.documentElement.classList.add('light');
            document.documentElement.classList.remove('dark');
        }
    };
    initTheme();

    const toggleTheme = () => {
        const isDark = document.documentElement.classList.contains('dark');
        if (isDark) {
            document.documentElement.classList.remove('dark');
            document.documentElement.classList.add('light');
            localStorage.setItem('theme', 'light');
        } else {
            document.documentElement.classList.remove('light');
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
    };

    const themeToggleBtn = document.getElementById('theme-toggle');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', toggleTheme);
    }

    // --- 2. Mobile Sidebar Control ---
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mainContent = document.querySelector('main');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('active');
            }
        });
    }

    // --- 3. AJAX Single-Page-Feel Navigation ---
    const contentArea = document.getElementById('content-area');
    const loader = document.getElementById('page-loader');

    const showLoader = () => {
        if (loader) loader.classList.add('active');
    };

    const hideLoader = () => {
        if (loader) loader.classList.remove('active');
    };

    const navigateTo = async (url, pushState = true) => {
        showLoader();
        try {
            // Append ajax parameter
            const urlObj = new URL(url, window.location.origin);
            urlObj.searchParams.set('ajax', '1');
            
            const response = await fetch(urlObj.toString());
            if (!response.ok) throw new Error('Network response was not ok');
            
            const html = await response.text();
            
            // Update page content
            if (contentArea) {
                contentArea.innerHTML = html;
                contentArea.scrollTop = 0;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            
            // Update URL
            if (pushState) {
                window.history.pushState({}, '', url);
            }
            
            // Highlight active sidebar item
            updateActiveSidebarItem(url);
            
            // Re-bind actions (e.g. decoding tools, sidebar triggers)
            bindDynamicElements();
            
            // Close mobile sidebar if open
            if (sidebar) sidebar.classList.remove('active');

        } catch (error) {
            console.error('AJAX Navigation failed:', error);
            // Fallback: full page reload
            window.location.href = url;
        } finally {
            hideLoader();
        }
    };

    const updateActiveSidebarItem = (url) => {
        const urlObj = new URL(url, window.location.origin);
        const pageParam = urlObj.searchParams.get('page') || '';
        
        document.querySelectorAll('.sidebar-link').forEach(link => {
            const linkUrl = new URL(link.getAttribute('href'), window.location.origin);
            const linkPageParam = linkUrl.searchParams.get('page') || '';
            
            if (pageParam === linkPageParam) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    };

    // Handle back/forward buttons
    window.addEventListener('popstate', () => {
        navigateTo(window.location.href, false);
    });

    // Intercept clicks on links for local navigation
    document.addEventListener('click', (e) => {
        const anchor = e.target.closest('a');
        if (!anchor) return;
        
        const href = anchor.getAttribute('href');
        
        // Only intercept internal links, e.g. starting with "index.php" or "?page="
        if (href && (href.startsWith('index.php') || href.startsWith('?page='))) {
            e.preventDefault();
            navigateTo(href);
        }
    });

    // --- 4. Live Autocomplete Search ---
    const searchInput = document.getElementById('search-input');
    const searchResults = document.getElementById('search-results');
    let searchDebounceTimeout = null;
    let selectedResultIndex = -1;

    if (searchInput && searchResults) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounceTimeout);
            selectedResultIndex = -1;
            const query = searchInput.value.trim();
            
            if (query.length < 2) {
                searchResults.innerHTML = '';
                searchResults.classList.remove('active');
                return;
            }
            
            searchDebounceTimeout = setTimeout(async () => {
                try {
                    const res = await fetch(`search.php?q=${encodeURIComponent(query)}`);
                    if (!res.ok) return;
                    const items = await res.json();
                    
                    if (items.length === 0) {
                        searchResults.innerHTML = '<div class="search-no-results">No matches found.</div>';
                        searchResults.classList.add('active');
                        return;
                    }
                    
                    let html = '';
                    items.forEach((item, index) => {
                        const starHtml = item.is_starred == 1 ? '<span class="result-star">⭐</span>' : '';
                        const unsafeHtml = item.is_unsafe == 1 ? '<span class="result-unsafe">⚠️ Unsafe</span>' : '';
                        const desc = item.description ? `<p class="result-desc">${escapeHtml(item.description)}</p>` : '';
                        
                        html += `
                            <div class="search-result-item" data-index="${index}" data-url="${escapeHtml(item.url)}">
                                <div class="result-header">
                                    <span class="result-name">${starHtml} ${escapeHtml(item.name)}</span>
                                    ${unsafeHtml}
                                </div>
                                ${desc}
                                <div class="result-footer">
                                    <span class="result-category">${escapeHtml(item.category_name)}</span>
                                    <span class="result-separator">›</span>
                                    <span class="result-section">${escapeHtml(item.section_name)}</span>
                                </div>
                            </div>
                        `;
                    });
                    
                    searchResults.innerHTML = html;
                    searchResults.classList.add('active');
                    
                    // Add click listeners to items
                    searchResults.querySelectorAll('.search-result-item').forEach(item => {
                        item.addEventListener('click', () => {
                            const url = item.getAttribute('data-url');
                            openLink(url);
                            searchResults.classList.remove('active');
                            searchInput.value = '';
                        });
                    });

                } catch (err) {
                    console.error('Search fetch failed:', err);
                }
            }, 200);
        });

        // Close search list on clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.remove('active');
            }
        });

        // Keyboard navigation for search
        searchInput.addEventListener('keydown', (e) => {
            const items = searchResults.querySelectorAll('.search-result-item');
            if (items.length === 0) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedResultIndex = (selectedResultIndex + 1) % items.length;
                highlightResultItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedResultIndex = (selectedResultIndex - 1 + items.length) % items.length;
                highlightResultItem(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedResultIndex >= 0 && selectedResultIndex < items.length) {
                    const activeItem = items[selectedResultIndex];
                    const url = activeItem.getAttribute('data-url');
                    openLink(url);
                    searchResults.classList.remove('active');
                    searchInput.value = '';
                }
            } else if (e.key === 'Escape') {
                searchResults.classList.remove('active');
            }
        });
    }

    const highlightResultItem = (items) => {
        items.forEach((item, index) => {
            if (index === selectedResultIndex) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    };

    // --- 5. Base64 Link Decoding Utility ---
    const decoderModal = document.getElementById('decoder-modal');
    const modalClose = document.querySelector('.modal-close');
    const decodeBtn = document.getElementById('decode-btn');
    const base64Input = document.getElementById('base64-input');
    const decodedOutput = document.getElementById('decoded-output');
    const openDecoderTrigger = document.getElementById('open-decoder');

    const showDecoder = (initialVal = '') => {
        if (!decoderModal) return;
        decoderModal.classList.add('active');
        if (base64Input) {
            base64Input.value = initialVal;
            if (initialVal) {
                handleDecode();
            } else {
                if (decodedOutput) decodedOutput.innerHTML = '';
            }
            base64Input.focus();
        }
    };

    const hideDecoder = () => {
        if (decoderModal) decoderModal.classList.remove('active');
    };

    if (openDecoderTrigger) {
        openDecoderTrigger.addEventListener('click', (e) => {
            e.preventDefault();
            showDecoder();
        });
    }

    if (modalClose) {
        modalClose.addEventListener('click', hideDecoder);
    }

    if (decoderModal) {
        decoderModal.addEventListener('click', (e) => {
            if (e.target === decoderModal) hideDecoder();
        });
    }

    const isBase64 = (str) => {
        try {
            return btoa(atob(str)) === str;
        } catch (err) {
            return false;
        }
    };

    const handleDecode = () => {
        const raw = base64Input.value.trim();
        if (!raw) {
            decodedOutput.innerHTML = '<span class="error">Input is empty.</span>';
            return;
        }
        
        try {
            // Clean prefix if present (e.g. base64: or Base64:)
            const cleanBase64 = raw.replace(/^(base64|Base64):?\s*/i, '');
            const decoded = atob(cleanBase64);
            
            // Check if it is a link
            if (decoded.startsWith('http://') || decoded.startsWith('https://')) {
                decodedOutput.innerHTML = `
                    <div class="decoded-success">
                        <p><strong>Decoded URL:</strong></p>
                        <a href="${escapeHtml(decoded)}" target="_blank" class="decoded-link">${escapeHtml(decoded)}</a>
                        <button type="button" class="btn btn-primary copy-decoded" data-clipboard="${escapeHtml(decoded)}">Copy link</button>
                    </div>
                `;
                
                decodedOutput.querySelector('.copy-decoded').addEventListener('click', () => {
                    navigator.clipboard.writeText(decoded);
                    const btn = decodedOutput.querySelector('.copy-decoded');
                    btn.textContent = 'Copied!';
                    setTimeout(() => btn.textContent = 'Copy link', 2000);
                });
            } else {
                decodedOutput.innerHTML = `
                    <div class="decoded-success">
                        <p><strong>Decoded text:</strong></p>
                        <pre class="decoded-text">${escapeHtml(decoded)}</pre>
                    </div>
                `;
            }
        } catch (err) {
            decodedOutput.innerHTML = '<span class="error">Invalid Base64 format! Check characters and padding.</span>';
        }
    };

    if (decodeBtn) {
        decodeBtn.addEventListener('click', handleDecode);
    }

    if (base64Input) {
        base64Input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleDecode();
            }
        });
    }

    // --- 6. Intercept external links with special patterns ---
    const openLink = (url) => {
        if (!url) return;
        
        // Base64 direct pattern
        if (url.startsWith('base64:') || url.startsWith('Base64:')) {
            const clean = url.replace(/^(base64|Base64):?\s*/i, '');
            showDecoder(clean);
            return;
        }
        
        // Standard URLs
        if (url !== '#') {
            window.open(url, '_blank', 'noopener,noreferrer');
        }
    };

    const bindDynamicElements = () => {
        // Find and add listeners to Base64 labels or cards
        document.querySelectorAll('.link-card').forEach(card => {
            const linkHref = card.querySelector('.link-title-anchor')?.getAttribute('href') || '';
            
            // Check if link is a Base64 link
            const isB64 = linkHref.startsWith('base64:') || linkHref.startsWith('Base64:');
            
            if (isB64) {
                card.classList.add('base64-card');
                const titleAnchor = card.querySelector('.link-title-anchor');
                if (titleAnchor) {
                    titleAnchor.addEventListener('click', (e) => {
                        e.preventDefault();
                        const b64Val = linkHref.replace(/^(base64|Base64):?\s*/i, '');
                        showDecoder(b64Val);
                    });
                }
            }
        });
        
        // Initialize dynamic anchor scrolling for Category TOC
        document.querySelectorAll('.toc-anchor-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = link.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    };

    // Helper functions
    const escapeHtml = (text) => {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    };

    // Initial binding
    bindDynamicElements();
    updateActiveSidebarItem(window.location.href);
});
