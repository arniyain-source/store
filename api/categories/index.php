<?php
/**
 * API: Categories
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

$action = $_GET['action'] ?? 'list';

try {
    $db = getDB();
    
    switch ($action) {
        case 'list':
            $stmt = $db->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id AND is_active = 1) as product_count, (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as sub_count FROM categories c ORDER BY c.sort_order ASC, c.name ASC");
            $categories = $stmt->fetchAll();
            jsonResponse(['success' => true, 'categories' => $categories]);
            break;
            
        case 'tree':
            $stmt = $db->query("SELECT id, name, slug, parent_id, icon FROM categories WHERE status = 1 ORDER BY sort_order ASC");
            $categories = $stmt->fetchAll();
            $tree = buildCategoryTree($categories, null);
            jsonResponse(['success' => true, 'tree' => $tree]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function buildCategoryTree($categories, $parentId) {
    $tree = [];
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parentId) {
            $cat['children'] = buildCategoryTree($categories, $cat['id']);
            $tree[] = $cat;
        }
    }
    return $tree;
}
