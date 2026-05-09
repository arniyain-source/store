<?php
require_once __DIR__ . '/includes/core/app.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id === 0) {
    header('Location: index.php');
    exit;
}

$db = getDB();

// Fetch order details
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: index.php');
    exit;
}

// Fetch order items
$items_stmt = $db->prepare("
    SELECT oi.*, p.name as product_name, p.main_image 
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll();

$pageTitle = "Order Confirmation";
require __DIR__ . '/includes/header.php';
?>

<div class="order-confirmation-container">
    <div class="confirmation-card">
        <div class="card-header">
            <i class="fas fa-check-circle success-icon"></i>
            <h1>Thank you for your order!</h1>
            <p>Your order has been placed successfully. You will receive a confirmation email shortly.</p>
            <p>Order ID: <strong>#<?php echo $order['id']; ?></strong></p>
        </div>

        <div class="order-details-section">
            <h2>Order Summary</h2>
            
            <div class="order-items-list">
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <img src="<?php echo htmlspecialchars($item['main_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="item-image">
                        <div class="item-info">
                            <p class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></p>
                            <p class="item-meta">
                                Qty: <?php echo $item['quantity']; ?>
                                <?php if ($item['size']): ?> | Size: <?php echo htmlspecialchars($item['size']); ?> <?php endif; ?>
                                <?php if ($item['color']): ?> | Color: <?php echo htmlspecialchars($item['color']); ?> <?php endif; ?>
                            </p>
                        </div>
                        <p class="item-price">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="totals-summary">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span>FREE</span>
                </div>
                <div class="summary-row grand-total">
                    <span>Total</span>
                    <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <div class="customer-details-section">
            <h2>Customer Information</h2>
            <div class="details-grid">
                <div>
                    <strong>Shipping Address</strong>
                    <p><?php echo htmlspecialchars($order['address']); ?></p>
                </div>
                <div>
                    <strong>Payment Method</strong>
                    <p><?php echo htmlspecialchars($order['payment_method']); ?></p>
                </div>
                 <div>
                    <strong>Customer Name</strong>
                    <p><?php echo htmlspecialchars($order['customer_name']); ?></p>
                </div>
                 <div>
                    <strong>Contact</strong>
                    <p><?php echo htmlspecialchars($order['phone']); ?></p>
                </div>
            </div>
        </div>

        <div class="confirmation-actions">
            <a href="shop.php" class="btn btn-primary">Continue Shopping</a>
            <a href="track-order.php?order_id=<?php echo $order['id']; ?>" class="btn btn-secondary">Track Your Order</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
