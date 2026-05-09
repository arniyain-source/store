<?php
/**
 * API: Cart - Get Cart Contents
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

$action = $_GET['action'] ?? 'get';

try {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if ($action === 'remove') {
        $key = sanitize($_GET['key'] ?? '');
        if (isset($_SESSION['cart'][$key])) {
            unset($_SESSION['cart'][$key]);
        }
    }

    if ($action === 'update') {
        $key = sanitize($_GET['key'] ?? sanitize($_POST['key'] ?? ''));
        $qty = max(0, (int)($_GET['qty'] ?? $_POST['qty'] ?? 0));
        if (isset($_SESSION['cart'][$key])) {
            if ($qty === 0) {
                unset($_SESSION['cart'][$key]);
            } else {
                $_SESSION['cart'][$key]['qty'] = $qty;
            }
        }
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
    }

    $cart      = array_values($_SESSION['cart']);
    $totalItems = array_sum(array_column($cart, 'qty'));
    $subtotal   = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
    $shipping   = $subtotal >= 999 ? 0 : 80;

    jsonResponse([
        'success'    => true,
        'cart'       => $cart,
        'cart_count' => $totalItems,
        'subtotal'   => $subtotal,
        'shipping'   => $shipping,
        'total'      => $subtotal + $shipping,
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
