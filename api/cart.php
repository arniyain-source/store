<?php
require_once __DIR__ . '/../includes/core/app.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$response = ['success' => false, 'message' => 'Invalid request'];
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
            $size = filter_input(INPUT_POST, 'size', FILTER_SANITIZE_STRING) ?: null;
            $color = filter_input(INPUT_POST, 'color', FILTER_SANITIZE_STRING) ?: null;

            if ($product_id && $quantity > 0) {
                $cart_item_key = $product_id . '-' . $size . '-' . $color;

                if (isset($_SESSION['cart'][$cart_item_key])) {
                    $_SESSION['cart'][$cart_item_key]['quantity'] += $quantity;
                } else {
                    $_SESSION['cart'][$cart_item_key] = ['product_id' => $product_id, 'quantity' => $quantity, 'size' => $size, 'color' => $color];
                }
                $response = ['success' => true, 'message' => 'Product added to cart'];
            } else {
                $response = ['success' => false, 'message' => 'Invalid product data'];
            }
            break;

        case 'update':
            $item_key = filter_input(INPUT_POST, 'item_key', FILTER_SANITIZE_STRING);
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

            if ($item_key && $quantity > 0 && isset($_SESSION['cart'][$item_key])) {
                $_SESSION['cart'][$item_key]['quantity'] = $quantity;
                $response = ['success' => true];
            }
            break;

        case 'remove':
            $item_key = filter_input(INPUT_POST, 'item_key', FILTER_SANITIZE_STRING);
            if ($item_key && isset($_SESSION['cart'][$item_key])) {
                unset($_SESSION['cart'][$item_key]);
                $response = ['success' => true];
            }
            break;

        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] == 'get') {
    $cart_items = [];
    $total = 0;

    if (!empty($_SESSION['cart'])) {
        $product_ids = array_column($_SESSION['cart'], 'product_id');
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $stmt = $db->prepare("SELECT id, name, price, main_image FROM products WHERE id IN ($placeholders)");
        $stmt->execute($product_ids);
        $products = $stmt->fetchAll(PDO::FETCH_KEY_PAIR | PDO::FETCH_GROUP);

        foreach ($_SESSION['cart'] as $key => $item) {
            if (isset($products[$item['product_id']])) {
                 $product = $products[$item['product_id']][0]; // Fetch first element since it's grouped
                 $item_total = $product['price'] * $item['quantity'];
                 $cart_items[$key] = array_merge($item, $product, ['item_total' => $item_total]);
                 $total += $item_total;
            }
        }
    }
    
    $response = ['success' => true, 'cart' => ['items' => $cart_items, 'total' => $total]];
}

echo json_encode($response);
