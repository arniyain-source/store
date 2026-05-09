<?php
ini_set('display_errors', 0); // Disable error display for production
ini_set('log_errors', 1); // Enable error logging

require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// -----------------------------------------------------------------------------
// AUTHENTICATION
// -----------------------------------------------------------------------------
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'get_all';

// -----------------------------------------------------------------------------
// API ROUTER
// -----------------------------------------------------------------------------
try {
    $db = getDB();
    switch ($action) {
        case 'get_all':
            handleGetAll($db, $userId);
            break;
        case 'get_order':
            handleGetOrder($db, $userId);
            break;
        default:
            throw new Exception('Invalid action specified.');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// -----------------------------------------------------------------------------
// HANDLER: Get All Dashboard Data
// -----------------------------------------------------------------------------
function handleGetAll($db, $userId) {
    // 1. Fetch User
    $stmt = $db->prepare("SELECT id, name, email, phone, user_type, created_at FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found.');
    }
    $user['user_type_formatted'] = ucfirst(str_replace('_', ' ', $user['user_type']));

    // 2. Fetch Orders
    $stmt = $db->prepare("
        SELECT o.id, o.order_number, o.total, o.status, o.payment_method, o.payment_status, o.created_at, COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.customer_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Addresses
    $stmt = $db->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC");
    $stmt->execute([$userId]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch Stats
    $stmt = $db->prepare("SELECT COUNT(id) as total_orders, COALESCE(SUM(total), 0) as total_spent FROM orders WHERE customer_id = ? AND status != 'cancelled'");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_spent'] = (float) $stats['total_spent'];

    // 5. Combine and Return
    echo json_encode([
        'success' => true,
        'user' => $user,
        'orders' => $orders,
        'addresses' => $addresses,
        'stats' => $stats
    ]);
}

// -----------------------------------------------------------------------------
// HANDLER: Get Single Order Details
// -----------------------------------------------------------------------------
function handleGetOrder($db, $userId) {
    $orderId = $_GET['order_id'] ?? null;
    if (!$orderId) {
        throw new Exception('Order ID is required.');
    }

    // 1. Fetch Order
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        throw new Exception('Order not found or access denied.');
    }

    // 2. Fetch Order Items
    $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode JSON address fields
    $order['shipping_address'] = json_decode($order['shipping_address'], true);
    $order['billing_address'] = json_decode($order['billing_address'], true);

    // 3. Return combined data
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);
}
?>