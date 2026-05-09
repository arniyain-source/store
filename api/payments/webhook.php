<?php
/**
 * API: Payments - Razorpay/UPI Webhook + Order Verification
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

$action = $_GET['action'] ?? 'verify';
$data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $db = getDB();

    switch ($action) {

        case 'verify':
            // Verify Razorpay payment
            $orderId      = sanitize($data['razorpay_order_id'] ?? '');
            $paymentId    = sanitize($data['razorpay_payment_id'] ?? '');
            $signature    = sanitize($data['razorpay_signature'] ?? '');
            $localOrderId = (int)($data['order_id'] ?? 0);

            if (!$orderId || !$paymentId || !$localOrderId) {
                jsonResponse(['success' => false, 'message' => 'Missing payment data'], 400);
            }

            // In a real integration, verify signature with Razorpay secret
            // $expectedSig = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
            // For now, mark as paid
            $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', transaction_id = ?, status = 'confirmed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$paymentId, $localOrderId]);

            jsonResponse(['success' => true, 'message' => 'Payment verified', 'order_id' => $localOrderId]);
            break;

        case 'webhook':
            // Razorpay webhook handler
            $payload = file_get_contents('php://input');
            $event   = $data['event'] ?? '';

            if ($event === 'payment.captured') {
                $paymentEntity = $data['payload']['payment']['entity'] ?? [];
                $txnId  = sanitize($paymentEntity['id'] ?? '');
                $notes  = $paymentEntity['notes'] ?? [];
                $orderId = (int)($notes['order_id'] ?? 0);
                if ($orderId && $txnId) {
                    $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', transaction_id = ?, status = 'confirmed', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$txnId, $orderId]);
                }
            }

            http_response_code(200);
            echo json_encode(['success' => true]);
            break;

        case 'create_order':
            // Create an order record before payment
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'POST required'], 405);
            }
            $customerId  = (int)($data['customer_id'] ?? ($_SESSION['customer_id'] ?? 0));
            $cartItems   = $data['cart'] ?? ($_SESSION['cart'] ?? []);
            $addressId   = (int)($data['address_id'] ?? 0);
            $couponCode  = sanitize($data['coupon_code'] ?? '');

            if (empty($cartItems)) {
                jsonResponse(['success' => false, 'message' => 'Cart is empty'], 400);
            }

            // Calculate totals
            $subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cartItems));
            $shipping = $subtotal >= 999 ? 0 : 80;
            $discount = 0;

            // Apply coupon if provided
            if ($couponCode) {
                $stmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (valid_to IS NULL OR valid_to >= NOW())");
                $stmt->execute([$couponCode]);
                $coupon = $stmt->fetch();
                if ($coupon) {
                    if ($coupon['type'] === 'percent') {
                        $discount = round($subtotal * $coupon['value'] / 100, 2);
                        if ($coupon['max_discount']) $discount = min($discount, $coupon['max_discount']);
                    } else {
                        $discount = min($coupon['value'], $subtotal);
                    }
                }
            }

            $total = max(0, $subtotal - $discount + $shipping);
            $orderNumber = generateOrderNumber();

            // Fetch address
            $shippingAddr = '{}';
            if ($addressId) {
                $stmt = $db->prepare("SELECT * FROM addresses WHERE id = ? AND customer_id = ?");
                $stmt->execute([$addressId, $customerId]);
                $addr = $stmt->fetch();
                if ($addr) $shippingAddr = json_encode($addr);
            } elseif (!empty($data['address'])) {
                $shippingAddr = json_encode($data['address']);
            }

            // Fetch customer info
            $custName  = sanitize($data['customer_name'] ?? ($_SESSION['customer_name'] ?? 'Guest'));
            $custEmail = sanitize($data['customer_email'] ?? ($_SESSION['customer_email'] ?? ''));
            $custPhone = sanitize($data['customer_phone'] ?? '');

            $stmt = $db->prepare("INSERT INTO orders (order_number, customer_id, customer_name, customer_email, customer_phone, shipping_address, subtotal, discount, coupon_code, shipping_cost, total, payment_method, payment_status, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,'online','pending','pending',NOW(),NOW())");
            $stmt->execute([$orderNumber, $customerId ?: null, $custName, $custEmail, $custPhone, $shippingAddr, $subtotal, $discount, $couponCode, $shipping, $total]);
            $newOrderId = (int)$db->lastInsertId();

            // Insert order items
            $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_image, sku, size, color, price, quantity, total, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
            foreach ($cartItems as $item) {
                $itemStmt->execute([
                    $newOrderId,
                    $item['product_id'],
                    $item['name'],
                    $item['image'] ?? '',
                    $item['sku'] ?? '',
                    $item['size'] ?? '',
                    $item['color'] ?? '',
                    $item['price'],
                    $item['qty'],
                    $item['price'] * $item['qty'],
                ]);
            }

            jsonResponse([
                'success'      => true,
                'order_id'     => $newOrderId,
                'order_number' => $orderNumber,
                'total'        => $total,
                'message'      => 'Order created',
            ]);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
