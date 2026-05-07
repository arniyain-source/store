<?php
/**
 * API: Products CRUD
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

try {
    $db = getDB();
    
    switch ($action) {
        case 'list':
            $page = (int)($_GET['page'] ?? 1);
            $search = sanitize($_GET['search'] ?? '');
            $category = (int)($_GET['category'] ?? 0);
            $status = $_GET['status'] ?? '';
            
            $where = ["p.is_active != 2"];
            $params = [];
            
            if ($search) {
                $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            if ($category) {
                $where[] = "p.category_id = ?";
                $params[] = $category;
            }
            if ($status !== '') {
                $where[] = "p.is_active = ?";
                $params[] = (int)$status;
            }
            
            $whereClause = implode(' AND ', $where);
            $query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE $whereClause ORDER BY p.created_at DESC";
            
            $result = paginate($query, $params, $page, ADMIN_PER_PAGE);
            jsonResponse(['success' => true, 'data' => $result]);
            break;
            
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            if ($product) {
                // Decode JSON fields
                foreach (['images', 'sizes', 'colors', 'finishes', 'features', 'tags'] as $field) {
                    if ($product[$field]) {
                        $product[$field] = json_decode($product[$field], true);
                    }
                }
                jsonResponse(['success' => true, 'product' => $product]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Product not found'], 404);
            }
            break;
            
        case 'update_stock':
            if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $stock = (int)($data['stock'] ?? 0);
            $stmt = $db->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $stmt->execute([$stock, $id]);
            jsonResponse(['success' => true, 'message' => 'Stock updated']);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
