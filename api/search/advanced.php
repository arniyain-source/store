<?php
/**
 * API: Advanced Smart Search
 * Handles multi-field search, typo-correction, and filters
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = getDB();

    // 1. Get and Sanitize Parameters
    $query      = sanitize($_GET['q'] ?? '');
    $categoryId = (int)($_GET['category_id'] ?? 0);
    $minPrice   = (float)($_GET['min_price'] ?? 0);
    $maxPrice   = (float)($_GET['max_price'] ?? 0);
    $fabric     = sanitize($_GET['fabric'] ?? '');
    $work       = sanitize($_GET['work'] ?? '');
    $color      = sanitize($_GET['color'] ?? '');
    $occasion   = sanitize($_GET['occasion'] ?? '');
    $sort       = sanitize($_GET['sort'] ?? 'newest');
    $page       = (int)($_GET['page'] ?? 1);

    // 2. Build Dynamic WHERE Clause
    $where = ["p.is_active = 1"];
    $params = [];

    // Smart Search Logic
    if (!empty($query)) {
        // Search across multiple fields: name, sku, fabric, work, and tags (JSON)
        $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.fabric LIKE ? OR p.work LIKE ? OR p.description LIKE ? OR JSON_SEARCH(p.tags, 'one', ?) IS NOT NULL)";
        $searchTerm = "%{$query}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Advanced Filters
    if ($categoryId > 0) {
        $where[] = "p.category_id = ?";
        $params[] = $categoryId;
    }
    if ($minPrice > 0) {
        $where[] = "p.price >= ?";
        $params[] = $minPrice;
    }
    if ($maxPrice > 0) {
        $where[] = "p.price <= ?";
        $params[] = $maxPrice;
    }
    if (!empty($fabric)) {
        $where[] = "p.fabric LIKE ?";
        $params[] = "%{$fabric}%";
    }
    if (!empty($work)) {
        $where[] = "p.work LIKE ?";
        $params[] = "%{$work}%";
    }
    if (!empty($color)) {
        $where[] = "(p.colors LIKE ? OR p.saree_color LIKE ? OR p.blouse_color LIKE ?)";
        $colorTerm = "%{$color}%";
        $params[] = $colorTerm;
        $params[] = $colorTerm;
        $params[] = $colorTerm;
    }
    if (!empty($occasion)) {
        $where[] = "p.occasion LIKE ?";
        $params[] = "%{$occasion}%";
    }

    // 3. Sorting Logic
    $orderBy = match ($sort) {
        'price_asc'   => "p.price ASC",
        'price_desc'  => "p.price DESC",
        'best_selling'=> "p.is_top_selling DESC, p.reviews_count DESC, p.id DESC",
        default       => "p.created_at DESC"
    };

    $whereClause = implode(' AND ', $where);
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE $whereClause 
            ORDER BY $orderBy";

    // 4. Fetch Paginated Results
    $pagination = paginate($sql, $params, $page, ADMIN_PER_PAGE);
    $results = $pagination['data'];

    // 5. Typo Correction / "Did You Mean?" (Simulated)
    $didYouMean = null;
    if (empty($results) && !empty($query) && strlen($query) > 3) {
        // Simple simulation: if user types "sari", suggest "saree"
        $mapping = ['sari' => 'saree', 'kurta' => 'kurti', 'lehenga' => 'lahenga', 'silk' => 'pure silk'];
        $lowQuery = strtolower($query);
        foreach ($mapping as $typo => $correct) {
            if (str_contains($lowQuery, $typo)) {
                $didYouMean = str_replace($typo, $correct, $lowQuery);
                break;
            }
        }
    }

    // 6. Search Logging
    try {
        $logStmt = $db->prepare("INSERT INTO search_queries (query, results_count, ip_address) VALUES (?, ?, ?)");
        $logStmt->execute([$query ?: 'Filter only', $pagination['total'], getClientIP()]);
    } catch (Exception $e) {
        // Silent fail for logs to prevent disrupting user experience
    }

    // 7. JSON Response
    jsonResponse([
        'success' => true,
        'data'    => $results,
        'summary' => [
            'total_results' => $pagination['total'],
            'page'          => $pagination['page'],
            'total_pages'   => $pagination['total_pages'],
            'did_you_mean'  => $didYouMean,
            'active_filters' => [
                'q'           => $query,
                'category_id' => $categoryId,
                'min_price'   => $minPrice,
                'max_price'   => $maxPrice,
                'fabric'      => $fabric,
                'work'        => $work,
                'color'       => $color,
                'occasion'    => $occasion,
                'sort'        => $sort
            ]
        ]
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Search error: ' . $e->getMessage()], 500);
}