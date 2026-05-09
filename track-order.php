<?php
require_once __DIR__ . '/includes/core/app.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$order = null;
$order_items = [];

if ($order_id > 0) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if ($order) {
        $items_stmt = $db->prepare("
            SELECT oi.*, p.name as product_name, p.main_image 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $items_stmt->execute([$order_id]);
        $order_items = $items_stmt->fetchAll();
    }
}

$pageTitle = "Track Order";
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/track-order.css">

<div class="track-order-container">
    <h1>Track Your Order</h1>

    <div class="track-order-form">
        <form method="GET" action="track-order.php">
            <input type="text" name="order_id" placeholder="Enter your order ID (e.g., 123)" value="<?php echo htmlspecialchars($order_id > 0 ? $order_id : ''); ?>">
            <button type="submit" class="btn btn-primary">Track</button>
        </form>
    </div>

    <?php if ($order_id > 0): ?>
        <?php if ($order): ?>
            <div class="order-status-card">
                <h2>Order #<?php echo $order['id']; ?></h2>
                <div class="status-timeline">
                    <div class="status-step active">
                        <span class="dot"></span>
                        <span class="label">Pending</span>
                    </div>
                    <div class="status-step <?php echo in_array($order['status'], ['Shipped', 'Delivered']) ? 'active' : ''; ?>">
                        <span class="dot"></span>
                        <span class="label">Shipped</span>
                    </div>
                    <div class="status-step <?php echo $order['status'] === 'Delivered' ? 'active' : ''; ?>">
                        <span class="dot"></span>
                        <span class="label">Delivered</span>
                    </div>
                </div>
                <p class="current-status">Current Status: <strong><?php echo htmlspecialchars($order['status']); ?></strong></p>
                <hr>
                <!-- Details from order confirmation can be repeated here -->
                 <div class="order-details-section">
                    <h2>Order Summary</h2>
                    <div class="order-items-list">
                        <?php foreach ($order_items as $item): ?>
                            <div class="order-item">
                                <img src="<?php echo htmlspecialchars($item['main_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="item-image">
                                <div class="item-info">
                                    <p class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></p>
                                </div>
                                <p class="item-price">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                 </div>
            </div>
        <?php else: ?>
            <p class="error-message">Order not found. Please check the ID and try again.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
