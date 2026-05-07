<?php
/**
 * API: Orders CRUD
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

$action = $_GET['action'] ?? 'list';

try {
    $db = getDB();
    
    switch ($action) {
        case 'list':
            $page = (int)($_GET['page'] ?? 1);
            $status = sanitize($_GET['status'] ?? '');
            $search = sanitize($_GET['search'] ?? '');
            $paymentStatus = sanitize($_GET['payment_status'] ?? '');
            
            $where = ["1=1"];
            $params = [];
            
            if ($status) {
                $where[] = "o.status = ?";
                $params[] = $status;
            }
            if ($search) {
                $where[] = "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            if ($paymentStatus) {
                $where[] = "o.payment_status = ?";
                $params[] = $paymentStatus;
            }
            
            $whereClause = implode(' AND ', $where);
            $query = "SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count FROM orders o WHERE $whereClause ORDER BY o.created_at DESC";
            
            $result = paginate($query, $params, $page, ADMIN_PER_PAGE);
            jsonResponse(['success' => true, 'data' => $result]);
            break;
            
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT o.*, c.name as customer_name_full, c.email as customer_email_full, c.phone as customer_phone FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ?");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            if ($order) {
                // Get order items
                $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
                $stmt->execute([$id]);
                $order['items'] = $stmt->fetchAll();
                jsonResponse(['success' => true, 'order' => $order]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Order not found'], 404);
            }
            break;
            
        case 'update_status':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $newStatus = sanitize($data['status'] ?? '');
            $adminNotes = sanitize($data['admin_notes'] ?? '');
            
            $validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
            if (!in_array($newStatus, $validStatuses)) {
                jsonResponse(['success' => false, 'message' => 'Invalid status'], 400);
            }
            
            $updateFields = ["status = ?", "admin_notes = ?"];
            $params = [$newStatus, $adminNotes];
            
            if ($newStatus === 'shipped') { $updateFields[] = "shipped_at = NOW()"; }
            if ($newStatus === 'delivered') { $updateFields[] = "delivered_at = NOW()"; }
            if ($newStatus === 'cancelled') { $updateFields[] = "cancelled_at = NOW()"; }
            
            $params[] = $id;
            $sql = "UPDATE orders SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            jsonResponse(['success' => true, 'message' => 'Order status updated']);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
