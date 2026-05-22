<?php
/**
 * FMHY Live Search API
 * Returns JSON results matching search query.
 */

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    // 1. Format queries
    $like_query = '%' . $query . '%';
    
    // Prepare fulltext query (+term* for prefix matches)
    $words = preg_split('/\s+/', $query);
    $ft_terms = [];
    foreach ($words as $word) {
        $word = preg_replace('/[+\-><\(\)~*\"@]+/u', '', $word); // Clean special chars
        if (strlen($word) >= 2) {
            $ft_terms[] = '+' . $word . '*';
        }
    }
    $ft_query = implode(' ', $ft_terms);
    
    // 2. Perform search
    if (!empty($ft_query)) {
        // Use Fulltext with a fallback to LIKE for smaller words or partial matches
        $sql = "SELECT l.id, l.name, l.url, l.description, l.is_starred, l.is_unsafe,
                       s.name AS section_name, c.name AS category_name, c.slug AS category_slug
                FROM links l
                JOIN sections s ON l.section_id = s.id
                JOIN categories c ON s.category_id = c.id
                WHERE (MATCH(l.name, l.description) AGAINST(:ft_query IN BOOLEAN MODE))
                   OR l.name LIKE :like_query_1
                   OR l.description LIKE :like_query_2
                ORDER BY l.is_starred DESC, l.name ASC
                LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'ft_query' => $ft_query,
            'like_query_1' => $like_query,
            'like_query_2' => $like_query
        ]);
    } else {
        // Fallback to purely LIKE query
        $sql = "SELECT l.id, l.name, l.url, l.description, l.is_starred, l.is_unsafe,
                       s.name AS section_name, c.name AS category_name, c.slug AS category_slug
                FROM links l
                JOIN sections s ON l.section_id = s.id
                JOIN categories c ON s.category_id = c.id
                WHERE l.name LIKE :like_query_1
                   OR l.description LIKE :like_query_2
                ORDER BY l.is_starred DESC, l.name ASC
                LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'like_query_1' => $like_query,
            'like_query_2' => $like_query
        ]);
    }
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Output results
    echo json_encode($results);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
