<?php
/**
 * FMHY Database Importer Script
 * Parses markdown wiki pages and text pages, inserting them into MySQL.
 */

// Set CLI-only execution or standard execution
if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}

echo "[*] Initializing database connection...\n";
require_once __DIR__ . '/db.php';

// 1. Reset database schema
echo "[*] Resetting database using schema.sql...\n";
if (!file_exists(__DIR__ . '/schema.sql')) {
    die("[!] Error: schema.sql not found in " . __DIR__ . "\n");
}

$schema_sql = file_get_contents(__DIR__ . '/schema.sql');
// Remove comments and split by semicolon
$queries = explode(';', $schema_sql);
foreach ($queries as $query) {
    $trimmed = trim($query);
    if (!empty($trimmed)) {
        try {
            $pdo->exec($trimmed);
        } catch (PDOException $e) {
            die("[!] SQL Error executing statement: " . substr($trimmed, 0, 100) . "...\nReason: " . $e->getMessage() . "\n");
        }
    }
}
echo "[+] Database schema reset successfully.\n";

// 2. Define Category mapping
$categories = [
    // Wiki pages
    [
        'name' => 'Adblocking / Privacy',
        'slug' => 'privacy',
        'file' => 'privacy.md',
        'type' => 'wiki',
        'icon' => '🛡️',
        'sort_order' => 1
    ],
    [
        'name' => 'Artificial Intelligence',
        'slug' => 'ai',
        'file' => 'ai.md',
        'type' => 'wiki',
        'icon' => '🤖',
        'sort_order' => 2
    ],
    [
        'name' => 'Movies / TV / Anime',
        'slug' => 'video',
        'file' => 'video.md',
        'type' => 'wiki',
        'icon' => '📺',
        'sort_order' => 3
    ],
    [
        'name' => 'Music / Podcasts / Radio',
        'slug' => 'audio',
        'file' => 'audio.md',
        'type' => 'wiki',
        'icon' => '🎵',
        'sort_order' => 4
    ],
    [
        'name' => 'Gaming / Emulation',
        'slug' => 'gaming',
        'file' => 'gaming.md',
        'type' => 'wiki',
        'icon' => '🎮',
        'sort_order' => 5
    ],
    [
        'name' => 'Books / Comics / Manga',
        'slug' => 'reading',
        'file' => 'reading.md',
        'type' => 'wiki',
        'icon' => '📖',
        'sort_order' => 6
    ],
    [
        'name' => 'Downloading',
        'slug' => 'downloading',
        'file' => 'downloading.md',
        'type' => 'wiki',
        'icon' => '💾',
        'sort_order' => 7
    ],
    [
        'name' => 'Torrenting',
        'slug' => 'torrenting',
        'file' => 'torrenting.md',
        'type' => 'wiki',
        'icon' => '🌀',
        'sort_order' => 8
    ],
    [
        'name' => 'Educational',
        'slug' => 'educational',
        'file' => 'educational.md',
        'type' => 'wiki',
        'icon' => '🧠',
        'sort_order' => 9
    ],
    [
        'name' => 'Android / iOS',
        'slug' => 'mobile',
        'file' => 'mobile.md',
        'type' => 'wiki',
        'icon' => '📱',
        'sort_order' => 10
    ],
    [
        'name' => 'Linux / macOS',
        'slug' => 'linux-macos',
        'file' => 'linux-macos.md',
        'type' => 'wiki',
        'icon' => '🐧',
        'sort_order' => 11
    ],
    [
        'name' => 'Non-English',
        'slug' => 'non-english',
        'file' => 'non-english.md',
        'type' => 'wiki',
        'icon' => '🌏',
        'sort_order' => 12
    ],
    [
        'name' => 'Miscellaneous',
        'slug' => 'misc',
        'file' => 'misc.md',
        'type' => 'wiki',
        'icon' => '📂',
        'sort_order' => 13
    ],

    // Tools pages
    [
        'name' => 'System Tools',
        'slug' => 'system-tools',
        'file' => 'system-tools.md',
        'type' => 'tools',
        'icon' => '💻',
        'sort_order' => 14
    ],
    [
        'name' => 'File Tools',
        'slug' => 'file-tools',
        'file' => 'file-tools.md',
        'type' => 'tools',
        'icon' => '🗃️',
        'sort_order' => 15
    ],
    [
        'name' => 'Internet Tools',
        'slug' => 'internet-tools',
        'file' => 'internet-tools.md',
        'type' => 'tools',
        'icon' => '📎',
        'sort_order' => 16
    ],
    [
        'name' => 'Social Media Tools',
        'slug' => 'social-media-tools',
        'file' => 'social-media-tools.md',
        'type' => 'tools',
        'icon' => '💬',
        'sort_order' => 17
    ],
    [
        'name' => 'Text Tools',
        'slug' => 'text-tools',
        'file' => 'text-tools.md',
        'type' => 'tools',
        'icon' => '📝',
        'sort_order' => 18
    ],
    [
        'name' => 'Gaming Tools',
        'slug' => 'gaming-tools',
        'file' => 'gaming-tools.md',
        'type' => 'tools',
        'icon' => '👾',
        'sort_order' => 19
    ],
    [
        'name' => 'Image Tools',
        'slug' => 'image-tools',
        'file' => 'image-tools.md',
        'type' => 'tools',
        'icon' => '📷',
        'sort_order' => 20
    ],
    [
        'name' => 'Video Tools',
        'slug' => 'video-tools',
        'file' => 'video-tools.md',
        'type' => 'tools',
        'icon' => '📼',
        'sort_order' => 21
    ],
    [
        'name' => 'Developer Tools',
        'slug' => 'developer-tools',
        'file' => 'developer-tools.md',
        'type' => 'tools',
        'icon' => '👨‍💻',
        'sort_order' => 22
    ],

    // More pages
    [
        'name' => 'Unsafe Sites',
        'slug' => 'unsafe',
        'file' => 'unsafe.md',
        'type' => 'other',
        'icon' => '⚠️',
        'sort_order' => 23
    ],
    [
        'name' => 'Recently Removed',
        'slug' => 'recently-removed',
        'file' => 'recently-removed.md',
        'type' => 'other',
        'icon' => '🗑️',
        'sort_order' => 24
    ],
    [
        'name' => 'Storage',
        'slug' => 'storage',
        'file' => 'storage.md',
        'type' => 'other',
        'icon' => '📦',
        'sort_order' => 25
    ]
];

// Helper to clean section names
function clean_section_name($name) {
    // Strip bullet prefix, unicode emojis, and headings markers
    $name = preg_replace('/[#►▷]/u', '', $name);
    // Replace markdown link format [Text](URL) with Text
    $name = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $name);
    // Remove markdown styles
    $name = preg_replace('/[\*_]/', '', $name);
    return trim($name);
}

// Helper to parse bullet line
function parse_link_line($line, $default_unsafe = false) {
    $line = trim($line);
    
    // Check if it starts with bullet character (* or -)
    if (!preg_match('/^[\*\-]\s+(.*)$/u', $line, $m)) {
        return null;
    }
    $content = trim($m[1]);
    
    // Check for Note/Warning lines
    if (preg_match('/^(?:(?:\*\*Note\*\*|\*\*Warning\*\*|Note|Warning)\s*-\s*|!{3}\w+\s+)(.+)$/ui', $content, $note_m)) {
        return [
            'type' => 'note',
            'content' => trim($note_m[1])
        ];
    }
    
    // Check for star indicator
    $is_starred = 0;
    if (preg_match('/^(?:⭐|:star:)\s*(.*)$/ui', $content, $star_m)) {
        $is_starred = 1;
        $content = trim($star_m[1]);
    }
    
    // Check and remove other indicators (like globe 🌐 or loop ↪️)
    $content = preg_replace('/^(?:🌐|↪️|:globe-with-meridians:|:repeat-button:)\s*/ui', '', $content);
    
    $name = '';
    $url = '';
    $desc = '';
    
    // Match markdown links: **[Name](URL)** or [Name](URL) at the start
    if (preg_match('/^(?:\*\*\[([^\]]+)\]\(([^)]+)\)\*\*|\[([^\]]+)\]\(([^)]+)\))(.*)$/ui', $content, $link_m)) {
        if (!empty($link_m[1])) {
            $name = $link_m[1];
            $url = $link_m[2];
        } else {
            $name = $link_m[3];
            $url = $link_m[4];
        }
        $remainder = $link_m[5];
        // Clean leading separators from description
        $desc = preg_replace('/^[\s\*\-\/—:]+/', '', $remainder);
    } else {
        // No anchor tags, split by the first divider
        $parts = preg_split('/\s+(?:-\s*|—\s*|:\s*)\s*/u', $content, 2);
        if (count($parts) == 2) {
            $name = $parts[0];
            $desc = $parts[1];
        } else {
            $name = $content;
            $desc = '';
        }
        $url = '#';
    }
    
    // Strip bold/italics markers from name
    $name = preg_replace('/[\*_]/', '', $name);
    
    return [
        'type' => 'link',
        'name' => trim($name),
        'url' => trim($url),
        'description' => trim($desc),
        'is_starred' => $is_starred,
        'is_unsafe' => $default_unsafe ? 1 : 0
    ];
}

// 3. Import structured categories, sections, and links
$docs_dir = __DIR__ . '/docs';
$category_stmt = $pdo->prepare("INSERT INTO categories (name, slug, type, icon, sort_order) VALUES (?, ?, ?, ?, ?)");
$section_stmt = $pdo->prepare("INSERT INTO sections (category_id, name, description, sort_order) VALUES (?, ?, ?, ?)");
$link_stmt = $pdo->prepare("INSERT INTO links (section_id, name, url, description, is_starred, is_unsafe, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");

$total_categories = 0;
$total_sections = 0;
$total_links = 0;

foreach ($categories as $cat) {
    $file_path = $docs_dir . '/' . $cat['file'];
    if (!file_exists($file_path)) {
        echo "[!] Warning: File not found: {$cat['file']} - Skipping.\n";
        continue;
    }
    
    echo "[*] Importing Category: {$cat['name']} (from {$cat['file']})...\n";
    
    // Insert category
    $category_stmt->execute([
        $cat['name'],
        $cat['slug'],
        $cat['type'],
        $cat['icon'],
        $cat['sort_order']
    ]);
    $category_id = $pdo->lastInsertId();
    $total_categories++;
    
    // Parse Markdown file
    $content = file_get_contents($file_path);
    // Strip YAML frontmatter if present
    $content = preg_replace('/^---\s*$.*?^---\s*$/ms', '', $content);
    
    $lines = explode("\n", $content);
    $current_section_id = null;
    $section_sort_order = 0;
    $link_sort_order = 0;
    
    // Maintain cache of active section data to update description on notes
    $current_section_name = '';
    $current_section_desc = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        
        // Check for Heading/Section
        // We match "# ", "## ", "### " and also specialized wiki dividers like "# ►" or "## ▷"
        if (preg_match('/^(?:#{1,3})\s+(.*)$/u', $line, $h_m)) {
            $heading_text = clean_section_name($h_m[1]);
            
            // Skip back to index header links
            if (stripos($heading_text, 'back to wiki index') !== false || empty($heading_text)) {
                continue;
            }
            
            // Create Section
            $section_sort_order++;
            $section_stmt->execute([
                $category_id,
                $heading_text,
                null, // Description starts as null, can be updated by notes
                $section_sort_order
            ]);
            $current_section_id = $pdo->lastInsertId();
            $current_section_name = $heading_text;
            $current_section_desc = '';
            $link_sort_order = 0;
            $total_sections++;
            continue;
        }
        
        // Parse list/bullet item
        $parsed = parse_link_line($line, ($cat['slug'] === 'unsafe'));
        if ($parsed) {
            // Ensure we have a default section if one hasn't been created yet
            if (!$current_section_id) {
                $section_sort_order++;
                $section_stmt->execute([
                    $category_id,
                    'General',
                    null,
                    $section_sort_order
                ]);
                $current_section_id = $pdo->lastInsertId();
                $current_section_name = 'General';
                $current_section_desc = '';
                $link_sort_order = 0;
                $total_sections++;
            }
            
            if ($parsed['type'] === 'note') {
                // Append note to current section description
                $note = $parsed['content'];
                $current_section_desc = empty($current_section_desc) ? $note : $current_section_desc . "\n" . $note;
                
                // Update in DB
                $update_stmt = $pdo->prepare("UPDATE sections SET description = ? WHERE id = ?");
                $update_stmt->execute([$current_section_desc, $current_section_id]);
            } elseif ($parsed['type'] === 'link') {
                // Insert Link
                $link_sort_order++;
                $link_stmt->execute([
                    $current_section_id,
                    $parsed['name'],
                    $parsed['url'],
                    $parsed['description'],
                    $parsed['is_starred'],
                    $parsed['is_unsafe'],
                    $link_sort_order
                ]);
                $total_links++;
            }
        }
    }
}

// 4. Import custom/text pages dynamically
$pages = [];
$category_files = array_map('strtolower', array_column($categories, 'file'));
$ignore_files = ['index.md', 'posts.md', 'sandbox.md', 'startpage.md'];

// Scan docs/ root
$docs_root_files = glob($docs_dir . '/*.md');
if ($docs_root_files) {
    foreach ($docs_root_files as $file_path) {
        $filename = basename($file_path);
        if (in_array(strtolower($filename), $category_files) || in_array(strtolower($filename), $ignore_files)) {
            continue;
        }
        $pages[] = [
            'slug' => strtolower(basename($filename, '.md')),
            'file' => $filename
        ];
    }
}

// Scan docs/other/
$docs_other_files = glob($docs_dir . '/other/*.md');
if ($docs_other_files) {
    foreach ($docs_other_files as $file_path) {
        $filename = basename($file_path);
        $pages[] = [
            'slug' => strtolower(basename($filename, '.md')),
            'file' => 'other/' . $filename
        ];
    }
}

// Scan docs/posts/
$docs_posts_files = glob($docs_dir . '/posts/*.md');
if ($docs_posts_files) {
    foreach ($docs_posts_files as $file_path) {
        $filename = basename($file_path);
        $pages[] = [
            'slug' => strtolower(basename($filename, '.md')),
            'file' => 'posts/' . $filename
        ];
    }
}

$page_stmt = $pdo->prepare("INSERT INTO pages (title, slug, content) VALUES (?, ?, ?)");
$total_pages = 0;

foreach ($pages as $p) {
    $file_path = $docs_dir . '/' . $p['file'];
    if (!file_exists($file_path)) {
        echo "[!] Warning: Page file not found: {$p['file']} - Skipping.\n";
        continue;
    }
    
    $content = file_get_contents($file_path);
    
    // Extract title from YAML frontmatter if present
    $title = '';
    if (preg_match('/^---\s*\n(.*?)\n---\s*/s', $content, $frontmatter_m)) {
        $frontmatter = $frontmatter_m[1];
        if (preg_match('/^title:\s*(.*)$/m', $frontmatter, $title_m)) {
            $title = trim($title_m[1]);
            $title = trim($title, '"\'');
        }
    }
    
    // Fallback: title from filename
    if (empty($title)) {
        $base = basename($p['file'], '.md');
        $title = preg_replace('/(?<!^)(?=[A-Z])/', ' ', $base);
        $title = str_replace('-', ' ', $title);
        $title = ucwords($title);
    }
    
    // Strip YAML frontmatter if present
    $content = preg_replace('/^---\s*$.*?^---\s*$/ms', '', $content);
    
    // Specific override: Contributing slug title should be 'Contribute'
    if ($p['slug'] === 'contributing') {
        $title = 'Contribute';
    }
    
    echo "[*] Importing Page: {$title} (slug: {$p['slug']})...\n";
    
    try {
        $page_stmt->execute([
            $title,
            $p['slug'],
            trim($content)
        ]);
        $total_pages++;
    } catch (PDOException $e) {
        echo "[!] Warning: Failed to import page {$title} (slug: {$p['slug']}). Reason: " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo "===================================================\n";
echo "[+] Import Summary:\n";
echo "    - Categories imported: {$total_categories}\n";
echo "    - Sections created: {$total_sections}\n";
echo "    - Links imported: {$total_links}\n";
echo "    - Guides/Pages imported: {$total_pages}\n";
echo "===================================================\n";

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
}
?>
