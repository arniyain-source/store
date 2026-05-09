<?php
/**
 * API: Cart - Add to Cart (Session-based for guests, DB for logged-in users)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$productId = (int)($data['product_id'] ?? 0);
$qty       = max(1, (int)($data['quantity'] ?? 1));
$size      = sanitize($data['size'] ?? '');
$color     = sanitize($data['color'] ?? '');

if (!$productId) {
    jsonResponse(['success' => false, 'message' => 'Product ID required'], 400);
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, price, stock, main_image, sku FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        jsonResponse(['success' => false, 'message' => 'Product not found'], 404);
    }
    if ($product['stock'] < $qty) {
        jsonResponse(['success' => false, 'message' => 'Insufficient stock'], 400);
    }

    // Session cart
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $key = $productId . '_' . $size . '_' . $color;
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['qty'] += $qty;
    } else {
        $_SESSION['cart'][$key] = [
            'product_id' => $productId,
            'name'       => $product['name'],
            'price'      => (float)$product['price'],
            'image'      => $product['main_image'],
            'sku'        => $product['sku'],
            'size'       => $size,
            'color'      => $color,
            'qty'        => $qty,
        ];
    }

    $totalItems = array_sum(array_column($_SESSION['cart'], 'qty'));
    $subtotal   = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));

    jsonResponse([
        'success'     => true,
        'message'     => 'Added to cart!',
        'cart_count'  => $totalItems,
        'subtotal'    => $subtotal,
        'cart'        => array_values($_SESSION['cart']),
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
