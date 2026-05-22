<?php
/**
 * FMHY Clone - Main Application Shell
 * Pure PHP, HTML, CSS, Vanilla JS, powered by a local MySQL database.
 */

// 1. Initialize DB Connection
require_once __DIR__ . '/db.php';

// 2. Helper function to slugify names for HTML anchors
function slugify($text) {
    // Replace non-letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // Transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // Trim
    $text = trim($text, '-');
    // Remove duplicate -
    $text = preg_replace('~-+~', '-', $text);
    // Lowercase
    $text = strtolower($text);
    if (empty($text)) {
        return 'n-a';
    }
    return $text;
}

// 3. Inline markdown helper for inner formatting (bold, italics, inline code)
function parse_inline_markdown_inner($text) {
    // Inline code: `code`
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    
    // Bold: **Text**
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    
    // Italics: *Text* or _Text_
    $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/_([^_]+)_/', '<em>$1</em>', $text);
    
    return $text;
}

// Inline markdown parser for link descriptions (links, bold, italics, inline code)
function parse_inline_markdown($text) {
    if (empty($text)) return '';
    
    // First, escape html safely but keep basic formatting
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // Store links in a temporary placeholder array using non-underscore prefixes
    $links = [];
    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($m) use (&$links) {
        $name = $m[1];
        $url = htmlspecialchars_decode($m[2]);
        $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        
        // Recursively parse inline markdown for the link text itself (e.g. bold/italics inside the link name)
        $parsed_name = parse_inline_markdown_inner($name);
        
        $placeholder = "LINKPLACEHOLDER" . count($links);
        $links[$placeholder] = "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\">{$parsed_name}</a>";
        
        return $placeholder;
    }, $text);
    
    // Apply bold, italics, inline code to the text containing placeholders
    $text = parse_inline_markdown_inner($text);
    
    // Restore the links
    foreach ($links as $placeholder => $html_tag) {
        $text = str_replace($placeholder, $html_tag, $text);
    }
    
    return $text;
}

// 4. Line-by-line markdown parser for guide pages
function parse_markdown($markdown) {
    if (empty($markdown)) return '';
    
    // Normalize newlines
    $markdown = str_replace("\r\n", "\n", $markdown);
    $lines = explode("\n", $markdown);
    $html = '';
    
    $in_list = false;
    $list_type = ''; // 'ul' or 'ol'
    $in_code = false;
    $code_lang = '';
    $code_block = '';
    $in_blockquote = false;
    $blockquote_content = '';
    $in_table = false;
    $table_headers = [];
    $table_rows = [];
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // --- 4.1 Fenced Code Blocks ---
        if (preg_match('/^```(\w*)/', $trimmed, $m)) {
            if ($in_code) {
                $html .= "<pre><code class=\"language-" . htmlspecialchars($code_lang) . "\">" . htmlspecialchars($code_block) . "</code></pre>\n";
                $in_code = false;
                $code_block = '';
            } else {
                $in_code = true;
                $code_lang = $m[1];
            }
            continue;
        }
        
        if ($in_code) {
            $code_block .= $line . "\n";
            continue;
        }
        
        // --- 4.2 Blockquotes ---
        if (strpos($trimmed, '>') === 0) {
            $bq_line = ltrim(substr($trimmed, 1));
            if ($in_blockquote) {
                $blockquote_content .= "\n" . $bq_line;
            } else {
                $in_blockquote = true;
                $blockquote_content = $bq_line;
            }
            continue;
        } else {
            if ($in_blockquote) {
                $html .= "<blockquote>" . parse_markdown($blockquote_content) . "</blockquote>\n";
                $in_blockquote = false;
                $blockquote_content = '';
            }
        }
        
        // --- 4.3 Lists (Unordered & Ordered) ---
        $is_ul = preg_match('/^[\*\-]\s+(.*)$/', $trimmed, $m);
        $is_ol = preg_match('/^\d+\.\s+(.*)$/', $trimmed, $m_ol);
        
        if ($is_ul || $is_ol) {
            $item_content = $is_ul ? $m[1] : $m_ol[1];
            $current_type = $is_ul ? 'ul' : 'ol';
            
            if ($in_list && $list_type !== $current_type) {
                $html .= "</$list_type>\n";
                $in_list = false;
            }
            
            if (!$in_list) {
                $html .= "<$current_type>\n";
                $in_list = true;
                $list_type = $current_type;
            }
            
            $html .= "  <li>" . parse_inline_markdown($item_content) . "</li>\n";
            continue;
        } else {
            if ($in_list) {
                $html .= "</$list_type>\n";
                $in_list = false;
            }
        }
        
        // --- 4.4 Tables ---
        if (preg_match('/^\|(.*)\|$/', $trimmed, $m)) {
            $columns = array_map('trim', explode('|', trim($m[1], '|')));
            
            $is_separator = true;
            foreach ($columns as $col) {
                if ($col !== '' && !preg_match('/^:?-+:?$/', $col)) {
                    $is_separator = false;
                    break;
                }
            }
            
            if ($is_separator) {
                continue;
            }
            
            if (!$in_table) {
                $in_table = true;
                $table_headers = $columns;
            } else {
                $table_rows[] = $columns;
            }
            continue;
        } else {
            if ($in_table) {
                $html .= "<table>\n<thead>\n<tr>\n";
                foreach ($table_headers as $th) {
                    $html .= "  <th>" . parse_inline_markdown($th) . "</th>\n";
                }
                $html .= "</tr>\n</thead>\n<tbody>\n";
                foreach ($table_rows as $row) {
                    $html .= "<tr>\n";
                    foreach ($row as $td) {
                        $html .= "  <td>" . parse_inline_markdown($td) . "</td>\n";
                    }
                    $html .= "</tr>\n";
                }
                $html .= "</tbody>\n</table>\n";
                $in_table = false;
                $table_headers = [];
                $table_rows = [];
            }
        }
        
        // --- 4.5 Headings ---
        if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $m)) {
            $level = strlen($m[1]);
            $text = trim($m[2]);
            if (stripos($text, 'back to wiki index') !== false || empty($text)) {
                continue;
            }
            $slug = slugify($text);
            $html .= "<h{$level} id=\"{$slug}\">" . parse_inline_markdown($text) . "</h{$level}>\n";
            continue;
        }
        
        // --- 4.6 Paragraphs & Admonitions ---
        if ($trimmed !== '') {
            if (preg_match('/^!!!(note|info|warning)\s+(.*)$/i', $trimmed, $callout_m)) {
                $type = strtolower($callout_m[1]);
                $content = trim($callout_m[2]);
                $class = "callout-box callout-" . $type;
                $html .= "<div class=\"{$class}\"><span class=\"callout-badge\">" . htmlspecialchars($type) . "</span> " . parse_inline_markdown($content) . "</div>\n";
            } else {
                $html .= "<p>" . parse_inline_markdown($trimmed) . "</p>\n";
            }
        }
    }
    
    // Close hanging structures
    if ($in_code) {
        $html .= "<pre><code class=\"language-" . htmlspecialchars($code_lang) . "\">" . htmlspecialchars($code_block) . "</code></pre>\n";
    }
    if ($in_blockquote) {
        $html .= "<blockquote>" . parse_markdown($blockquote_content) . "</blockquote>\n";
    }
    if ($in_list) {
        $html .= "</$list_type>\n";
    }
    if ($in_table) {
        $html .= "<table>\n<thead>\n<tr>\n";
        foreach ($table_headers as $th) {
            $html .= "  <th>" . parse_inline_markdown($th) . "</th>\n";
        }
        $html .= "</tr>\n</thead>\n<tbody>\n";
        foreach ($table_rows as $row) {
            $html .= "<tr>\n";
            foreach ($row as $td) {
                $html .= "  <td>" . parse_inline_markdown($td) . "</td>\n";
            }
            $html .= "</tr>\n";
        }
        $html .= "</tbody>\n</table>\n";
    }
    
    return $html;
}

// 5. Dynamic TOC generator for Categories
function generate_category_toc($sections) {
    if (empty($sections)) return '';
    $html = '<div class="toc-container">';
    $html .= '<div class="toc-title">Jump to section</div>';
    $html .= '<div class="toc-grid">';
    foreach ($sections as $sec) {
        $slug = slugify($sec['name']);
        $html .= "<a href=\"#{$slug}\" class=\"toc-anchor-link\">" . htmlspecialchars($sec['name']) . "</a>";
    }
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

// 6. Dynamic TOC generator for Guide Pages
function generate_page_toc($content) {
    preg_match_all('/^(#{1,3})\s+(.+)$/m', $content, $matches, PREG_SET_ORDER);
    if (empty($matches)) {
        return '';
    }
    
    $html = '<div class="toc-container">';
    $html .= '<div class="toc-title">On this page</div>';
    $html .= '<div class="toc-grid">';
    foreach ($matches as $match) {
        $level = strlen($match[1]);
        $text = trim($match[2]);
        if (stripos($text, 'back to wiki index') !== false || empty($text)) {
            continue;
        }
        $slug = slugify($text);
        $indent = str_repeat('&nbsp;&nbsp;', ($level - 1) * 2);
        $html .= "<a href=\"#{$slug}\" class=\"toc-anchor-link\">{$indent}{$text}</a>";
    }
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

// 7. Route and retrieve page inputs
$page_slug = trim($_GET['page'] ?? '');
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

$page_title = 'FMHY HUB';
$page_description = 'The largest collection of free stuff on the internet. Block ads, stream movies, download gaming, AI, books, audio, educational resources, and more.';
$content_html = '';

// Check category table first
$stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
$stmt->execute([$page_slug]);
$category = $stmt->fetch();

if ($category) {
    $page_title = $category['icon'] . ' ' . $category['name'] . ' | FMHY';
    $page_description = 'Browse ' . htmlspecialchars($category['name']) . ' tools, software, links and media recommendations on FMHY.';
    
    // Fetch sections
    $stmt_sec = $pdo->prepare("SELECT * FROM sections WHERE category_id = ? ORDER BY sort_order ASC");
    $stmt_sec->execute([$category['id']]);
    $sections = $stmt_sec->fetchAll();
    
    // Fetch links
    $links_by_section = [];
    if (!empty($sections)) {
        $section_ids = array_column($sections, 'id');
        $in_clause = implode(',', array_fill(0, count($section_ids), '?'));
        
        $stmt_links = $pdo->prepare("SELECT * FROM links WHERE section_id IN ($in_clause) ORDER BY is_starred DESC, sort_order ASC");
        $stmt_links->execute($section_ids);
        $all_links = $stmt_links->fetchAll();
        
        foreach ($all_links as $link) {
            $links_by_section[$link['section_id']][] = $link;
        }
    }
    
    // Render Category Link page
    ob_start();
    ?>
    <div class="category-page">
        <div class="category-header-banner">
            <div class="category-header-icon"><?= htmlspecialchars($category['icon'] ?? '📂') ?></div>
            <div class="category-header-info">
                <h1><?= htmlspecialchars($category['name']) ?></h1>
                <div class="category-stats-bar">
                    <span>📁 <?= count($sections) ?> sections</span>
                    <span>•</span>
                    <?php
                    $total_links_in_cat = 0;
                    foreach ($links_by_section as $sec_links) {
                        $total_links_in_cat += count($sec_links);
                    }
                    ?>
                    <span>🔗 <?= number_format($total_links_in_cat) ?> resources</span>
                </div>
            </div>
        </div>
        
        <?= generate_category_toc($sections) ?>
        
        <?php foreach ($sections as $section): ?>
            <?php 
            $sec_slug = slugify($section['name']);
            $sec_links = $links_by_section[$section['id']] ?? [];
            ?>
            <div class="section-wrapper" id="<?= $sec_slug ?>">
                <div class="section-head">
                    <h2><?= htmlspecialchars($section['name']) ?></h2>
                </div>
                
                <?php if (!empty($section['description'])): ?>
                    <div class="section-note-box">
                        <?= parse_markdown($section['description']) ?>
                    </div>
                <?php endif; ?>
                
                <div class="links-grid">
                    <?php foreach ($sec_links as $link): ?>
                        <div class="link-card">
                            <div class="link-title-container">
                                <a href="<?= htmlspecialchars($link['url']) ?>" class="link-title-anchor" target="_blank" rel="noopener noreferrer">
                                    <?php if ($link['is_starred']): ?>
                                        <span class="badge-star">⭐</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($link['name']) ?>
                                </a>
                                <div class="link-badge-container">
                                    <?php if ($link['is_unsafe']): ?>
                                        <span class="badge-unsafe">Unsafe</span>
                                    <?php endif; ?>
                                    <?php if (strncasecmp($link['url'], 'base64:', 7) === 0): ?>
                                        <span class="badge-base64">Base64</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($link['description'])): ?>
                                <div class="link-desc">
                                    <?= parse_inline_markdown($link['description']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    $content_html = ob_get_clean();

} else {
    // Check pages table (guides, faq, selfhosting, etc.)
    $stmt_page = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
    $stmt_page->execute([$page_slug]);
    $db_page = $stmt_page->fetch();
    
    if ($db_page) {
        $page_title = $db_page['title'] . ' | FMHY';
        $page_description = 'Read the official ' . htmlspecialchars($db_page['title']) . ' on FMHY clone, converted to dynamic php.';
        
        ob_start();
        ?>
        <div class="prose-page">
            <h1><?= htmlspecialchars($db_page['title']) ?></h1>
            <?= generate_page_toc($db_page['content']) ?>
            <?= parse_markdown($db_page['content']) ?>
        </div>
        <?php
        $content_html = ob_get_clean();
        
    } elseif (empty($page_slug) || $page_slug === 'index' || $page_slug === 'home') {
        // Render landing dashboard page
        $page_title = 'FMHY - Freemediaheckyeah';
        
        // Fetch all categories and counts for feature cards
        $stmt_home_cats = $pdo->query("
            SELECT c.id, c.name, c.slug, c.type, c.icon,
                   COUNT(DISTINCT s.id) AS section_count,
                   COUNT(l.id) AS link_count
            FROM categories c
            LEFT JOIN sections s ON s.category_id = c.id
            LEFT JOIN links l ON l.section_id = s.id
            GROUP BY c.id
            ORDER BY c.sort_order ASC
        ");
        $home_categories = $stmt_home_cats->fetchAll();
        
        $category_descriptions = [
            'privacy' => 'Block ads, trackers, and other online threats.',
            'ai' => 'Artificial intelligence, chat tools, and image generation.',
            'video' => 'Stream or download movies, TV shows, and anime.',
            'audio' => 'Music streaming, downloads, podcasts, and radio.',
            'gaming' => 'PC games, roms, emulation, and console gaming.',
            'reading' => 'E-books, audiobooks, comics, manga, and magazines.',
            'downloading' => 'General download hosts, managers, and search engines.',
            'torrenting' => 'Torrents, clients, search indexes, and trackers.',
            'educational' => 'Courses, books, academic tools, and science links.',
            'mobile' => 'Android and iOS apps, jailbreaking, and modifications.',
            'linux-macos' => 'Software, scripts, and customizing Linux and macOS.',
            'non-english' => 'International and regional sites in other languages.',
            'misc' => 'Fun sites, tools, recipes, travel, and shopping.',
            'system-tools' => 'Utilities for managing and checking PC hardware.',
            'file-tools' => 'File converters, editors, compressors, and uploaders.',
            'internet-tools' => 'Browser utilities, URL shorteners, and mail checkers.',
            'social-media-tools' => 'Downloaders and tools for social networks.',
            'text-tools' => 'Text editors, notes, OCR, translating, and fonts.',
            'gaming-tools' => 'Stats checkers, game mods, cheats, and game maps.',
            'image-tools' => 'Image hosts, editors, optimizers, and capture tools.',
            'video-tools' => 'Video editing, conversion, downloading, and players.',
            'developer-tools' => 'APIs, web dev resources, hostings, and icons.',
            'unsafe' => 'Websites and programs to avoid or handle with caution.',
            'recently-removed' => 'Links that were recently deprecated or removed.',
            'storage' => 'Cloud drives, backup hosts, and sync tools.'
        ];
        
        ob_start();
        ?>
        <div class="hero-section">
            <div class="hero-banner">
                <div class="hero-glow"></div>
                <img src="public/test.png" class="hero-logo image-src" alt="FMHY Logo">
            </div>
            
            <div>
                <a href="?page=beginners-guide" class="hero-announcement">
                    <span>Keep Android Open 🔓</span>
                </a>
            </div>
            
            <h1 class="hero-title">FMHY HUB</h1>
            <p class="hero-tagline">The largest collection of free stuff on the internet, backed by a dynamic MySQL database!</p>
            
            <div class="hero-actions">
                <a href="?page=beginners-guide" class="btn btn-primary">📖 See Beginners Guide</a>
                <a href="?page=faq" class="btn btn-secondary">❓ FAQs</a>
                <a href="https://github.com/fmhy/FMHY" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">💻 GitHub Repo</a>
                <a href="#" id="open-decoder" class="btn btn-secondary">🔓 Base64 Decoder</a>
            </div>
            
            <h2 style="font-size: 1.75rem; text-align: left; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem;">Browse Categories</h2>
            
            <div class="features-grid">
                <?php foreach ($home_categories as $cat): ?>
                    <?php 
                    $desc = $category_descriptions[$cat['slug']] ?? 'Discover and download media resources for this category.';
                    ?>
                    <a href="?page=<?= htmlspecialchars($cat['slug']) ?>" class="feature-card-link">
                        <div class="feature-card">
                            <div class="feature-icon-wrapper">
                                <?= htmlspecialchars($cat['icon'] ?? '📂') ?>
                            </div>
                            <div class="feature-title"><?= htmlspecialchars($cat['name']) ?></div>
                            <div class="feature-desc"><?= htmlspecialchars($desc) ?></div>
                            <div class="feature-stats">
                                <span class="feature-stat-item">📁 <?= $cat['section_count'] ?> Sections</span>
                                <span class="feature-stat-item">🔗 <?= number_format($cat['link_count']) ?> Links</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        $content_html = ob_get_clean();
        
    } else {
        // Page Not Found (404)
        header("HTTP/1.0 404 Not Found");
        $page_title = 'Page Not Found | FMHY';
        
        ob_start();
        ?>
        <div style="text-align: center; padding: 5rem 1rem;">
            <h1 style="font-size: 4rem; color: var(--accent); margin-bottom: 1rem;">404</h1>
            <h2>Page Not Found</h2>
            <p style="color: var(--text-secondary); margin-bottom: 2rem;">The category or guide you are looking for does not exist in our database.</p>
            <a href="index.php" class="btn btn-primary">Go back home</a>
        </div>
        <?php
        $content_html = ob_get_clean();
    }
}

// 8. If AJAX request, return the raw content block and stop
if ($is_ajax) {
    echo $content_html;
    exit;
}

// 9. Fetch categories for full sidebar assembly
$stmt_all_cats = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC");
$sidebar_categories = $stmt_all_cats->fetchAll();

$wiki_cats = [];
$tool_cats = [];
$other_cats = [];

foreach ($sidebar_categories as $cat) {
    if ($cat['type'] === 'wiki') {
        $wiki_cats[] = $cat;
    } elseif ($cat['type'] === 'tools') {
        $tool_cats[] = $cat;
    } else {
        $other_cats[] = $cat;
    }
}

// 10. Guides page list for sidebar
$sidebar_guides = [
    ['title' => 'Beginners Guide', 'slug' => 'beginners-guide', 'icon' => '📖'],
    ['title' => 'FAQ', 'slug' => 'faq', 'icon' => '❓'],
    ['title' => 'Backups', 'slug' => 'backups', 'icon' => '💾'],
    ['title' => 'Selfhosting', 'slug' => 'selfhosting', 'icon' => '🏠'],
    ['title' => 'Wallpapers', 'slug' => 'wallpapers', 'icon' => '🖼️'],
    ['title' => 'Contribute', 'slug' => 'contributing', 'icon' => '🤝'],
    ['title' => 'Feedback', 'slug' => 'feedback', 'icon' => '💬']
];
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="public/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="public/favicon.ico">
    
    <!-- Style Sheet -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Youtube-style page progress bar -->
    <div id="page-loader" class="page-loader"></div>

    <div class="app-container">
        
        <!-- Sidebar Navigation -->
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="logo-link">
                    <img src="public/test.png" class="logo-image" alt="FMHY Logo">
                    <span>FMHY <span class="logo-text-gradient">HUB</span></span>
                </a>
                <button type="button" id="sidebar-toggle-close" class="sidebar-toggle-close">&times;</button>
            </div>
            
            <nav class="sidebar-nav">
                <!-- Guides & More -->
                <div class="sidebar-group">
                    <div class="sidebar-group-title">Guides & Pages</div>
                    <ul class="sidebar-links">
                        <?php foreach ($sidebar_guides as $guide): ?>
                            <li>
                                <a href="?page=<?= htmlspecialchars($guide['slug']) ?>" class="sidebar-link <?= ($page_slug === $guide['slug']) ? 'active' : '' ?>">
                                    <span><?= htmlspecialchars($guide['icon']) ?></span>
                                    <span><?= htmlspecialchars($guide['title']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Wiki Categories -->
                <div class="sidebar-group">
                    <div class="sidebar-group-title">Wiki Categories</div>
                    <ul class="sidebar-links">
                        <?php foreach ($wiki_cats as $cat): ?>
                            <li>
                                <a href="?page=<?= htmlspecialchars($cat['slug']) ?>" class="sidebar-link <?= ($page_slug === $cat['slug']) ? 'active' : '' ?>">
                                    <span><?= htmlspecialchars($cat['icon'] ?? '📂') ?></span>
                                    <span><?= htmlspecialchars($cat['name']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Tools Categories -->
                <div class="sidebar-group">
                    <div class="sidebar-group-title">Tools Categories</div>
                    <ul class="sidebar-links">
                        <?php foreach ($tool_cats as $cat): ?>
                            <li>
                                <a href="?page=<?= htmlspecialchars($cat['slug']) ?>" class="sidebar-link <?= ($page_slug === $cat['slug']) ? 'active' : '' ?>">
                                    <span><?= htmlspecialchars($cat['icon'] ?? '🛠️') ?></span>
                                    <span><?= htmlspecialchars($cat['name']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Other Categories -->
                <div class="sidebar-group">
                    <div class="sidebar-group-title">Other Links</div>
                    <ul class="sidebar-links">
                        <?php foreach ($other_cats as $cat): ?>
                            <li>
                                <a href="?page=<?= htmlspecialchars($cat['slug']) ?>" class="sidebar-link <?= ($page_slug === $cat['slug']) ? 'active' : '' ?>">
                                    <span><?= htmlspecialchars($cat['icon'] ?? '📎') ?></span>
                                    <span><?= htmlspecialchars($cat['name']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <button type="button" id="sidebar-footer-decode" class="sidebar-footer-btn">
                    <span>🔓</span> Decode Link
                </button>
            </div>
        </aside>
        
        <!-- Main Wrapper -->
        <div class="main-wrapper">
            
            <!-- Main Header -->
            <header class="header">
                <div class="header-left">
                    <button type="button" id="sidebar-toggle" class="sidebar-toggle-btn" aria-label="Toggle Sidebar">
                        <!-- Hamburger Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-menu"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                    </button>
                    
                    <!-- Search Input -->
                    <div class="search-wrapper">
                        <div class="search-input-container">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                            <input type="text" id="search-input" class="search-input" placeholder="Type to search 16,000+ links..." autocomplete="off">
                        </div>
                        <div id="search-results" class="search-results"></div>
                    </div>
                </div>
                
                <div class="header-right">
                    <!-- Theme Toggle -->
                    <button type="button" id="theme-toggle" class="theme-toggle-btn" aria-label="Toggle Theme">
                        <!-- Sun Icon -->
                        <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                        <!-- Moon Icon -->
                        <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                    </button>
                    
                    <!-- Social Links -->
                    <div class="social-links">
                        <a href="https://www.reddit.com/r/FREEMEDIAHECKYEAH/" target="_blank" rel="noopener noreferrer" class="social-link" title="Reddit Subreddit">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M17 12a5 5 0 0 0-10 0"/></svg>
                        </a>
                        <a href="https://github.com/fmhy/FMHY" target="_blank" rel="noopener noreferrer" class="social-link" title="GitHub Repository">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg>
                        </a>
                    </div>
                </div>
            </header>
            
            <!-- Main Content Area Container -->
            <div class="content-area-wrapper">
                <main id="content-area">
                    <?= $content_html ?>
                </main>
            </div>
            
            <!-- Footer -->
            <footer class="main-footer">
                <div class="footer-content">
                    <p class="footer-text">
                        &copy; <?= date('Y') ?> FMHY HUB. Co-coded with Antigravity. Converted to dynamic PHP & MySQL.
                    </p>
                    <div class="footer-links">
                        <a href="?page=beginners-guide" class="footer-link-item">Beginners Guide</a>
                        <a href="?page=faq" class="footer-link-item">FAQs</a>
                        <a href="?page=feedback" class="footer-link-item">Feedback</a>
                    </div>
                </div>
            </footer>
            
        </div>
        
    </div>

    <!-- Base64 Decoder Modal Overlay -->
    <div id="decoder-modal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">🔓 Base64 Link Decoder</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p style="color: var(--text-secondary); font-size: 0.9rem;">
                    Many links in FMHY are base64-encoded to prevent link decay or DMCA issues. Paste the encoded string below to reveal the original URL.
                </p>
                <div class="form-group">
                    <label for="base64-input">Encoded String</label>
                    <textarea id="base64-input" class="form-input" placeholder="e.g. aHR0cHM6Ly9nb29nbGUuY29t" autocomplete="off"></textarea>
                </div>
                <button type="button" id="decode-btn" class="btn btn-primary" style="align-self: flex-start;">Decode Now</button>
                
                <div id="decoded-output"></div>
            </div>
        </div>
    </div>

    <!-- Client-side Javascript application logic -->
    <script src="app.js"></script>
    <script>
        // Additional hook for the footer decoder button
        document.getElementById('sidebar-footer-decode')?.addEventListener('click', () => {
            if (typeof window.showDecoder === 'function') {
                window.showDecoder();
            } else {
                // Fallback: Dispatch trigger or manually open modal
                const modal = document.getElementById('decoder-modal');
                if (modal) {
                    modal.classList.add('active');
                    const base64Input = document.getElementById('base64-input');
                    if (base64Input) base64Input.focus();
                }
            }
        });
        
        // Mobile sidebar close button
        document.getElementById('sidebar-toggle-close')?.addEventListener('click', () => {
            document.getElementById('sidebar')?.classList.remove('active');
        });
    </script>
</body>
</html>
