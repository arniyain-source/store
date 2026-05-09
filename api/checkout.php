<?php
require_once __DIR__ . '/../includes/core/app.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    if (empty($_SESSION['cart'])) {
        echo json_encode(['success' => false, 'message' => 'Your cart is empty.']);
        exit;
    }

    $customer_name = filter_input(INPUT_POST, 'customer_name', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);

    // Basic validation
    if (empty($customer_name) || empty($phone) || empty($address) || empty($payment_method)) {
        echo json_encode(['success' => false, 'message' => 'Please provide all required information.']);
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Calculate total amount
        $total = 0;
        $product_ids = array_column($_SESSION['cart'], 'product_id');
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $stmt = $db->prepare("SELECT id, price FROM products WHERE id IN ($placeholders)");
        $stmt->execute($product_ids);
        $products = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach($_SESSION['cart'] as $item) {
            if(isset($products[$item['product_id']])){
                 $total += $products[$item['product_id']] * $item['quantity'];
            }
        }

        // 2. Create the order
        $stmt = $db->prepare("INSERT INTO orders (customer_name, phone, address, payment_method, total_amount, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$customer_name, $phone, $address, $payment_method, $total]);
        $order_id = $db->lastInsertId();

        // 3. Insert order items
        $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, size, color) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($_SESSION['cart'] as $item) {
            $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $products[$item['product_id']], $item['size'], $item['color']]);
        }

        $db->commit();

        // 4. Clear the cart
        $_SESSION['cart'] = [];

        echo json_encode(['success' => true, 'order_id' => $order_id]);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'An error occurred while placing your order.']);
    }
}
