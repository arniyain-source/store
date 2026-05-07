<?php
/**
 * API: Customers
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

$action = $_GET['action'] ?? 'list';

try {
    $db = getDB();
    
    switch ($action) {
        case 'list':
            $page = (int)($_GET['page'] ?? 1);
            $search = sanitize($_GET['search'] ?? '');
            $userType = sanitize($_GET['user_type'] ?? '');
            $status = sanitize($_GET['status'] ?? '');
            
            $where = ["1=1"];
            $params = [];
            
            if ($search) {
                $where[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            if ($userType) {
                $where[] = "c.user_type = ?";
                $params[] = $userType;
            }
            if ($status === 'active') {
                $where[] = "c.is_active = 1";
            } elseif ($status === 'inactive') {
                $where[] = "c.is_active = 0";
            } elseif ($status === 'verified') {
                $where[] = "c.is_verified = 1";
            }
            
            $whereClause = implode(' AND ', $where);
            $query = "SELECT c.*, (SELECT COUNT(*) FROM orders WHERE customer_id = c.id) as orders_count, (SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_id = c.id AND payment_status = 'paid') as total_spent FROM customers c WHERE $whereClause ORDER BY c.created_at DESC";
            
            $result = paginate($query, $params, $page, ADMIN_PER_PAGE);
            jsonResponse(['success' => true, 'data' => $result]);
            break;
            
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch();
            if ($customer) {
                // Get addresses
                $stmt = $db->prepare("SELECT * FROM addresses WHERE customer_id = ?");
                $stmt->execute([$id]);
                $customer['addresses'] = $stmt->fetchAll();
                // Get recent orders
                $stmt = $db->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 10");
                $stmt->execute([$id]);
                $customer['recent_orders'] = $stmt->fetchAll();
                jsonResponse(['success' => true, 'customer' => $customer]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Customer not found'], 404);
            }
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
