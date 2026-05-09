<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {
    $db = getDB();

    // Fetch all active products
    $product_stmt = $db->query("SELECT p.*, c.name as category_name, COUNT(r.id) as reviews_count FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN reviews r ON p.id = r.product_id WHERE p.is_active = 1 GROUP BY p.id");
    $products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all active categories
    $category_stmt = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
    $categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'products' => $products,
        'categories' => $categories
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
