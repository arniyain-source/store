<?php
/**
 * Customer Dashboard - DesiVastra
 */
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$customerId = $_SESSION['customer_id'];

// Fetch customer data
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch Stats
$stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE customer_id = ?");
$stmt->execute([$customerId]);
$totalOrders = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as processing FROM orders WHERE customer_id = ? AND status IN ('pending', 'confirmed', 'processing', 'shipped')");
$stmt->execute([$customerId]);
$processingOrders = $stmt->fetch()['processing'];

$stmt = $db->prepare("SELECT COUNT(*) as wishlist_count FROM wishlist WHERE customer_id = ?");
$stmt->execute([$customerId]);
$wishlistCount = $stmt->fetch()['wishlist_count'];

// Fetch Recent Orders
$stmt = $db->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$customerId]);
$recentOrders = $stmt->fetchAll();

$pageTitle = "My Dashboard - DesiVastra";
$extraCSS = '<link rel="stylesheet" href="assets/css/dashboard.css">';

include 'templates/head.php';
?>
<div class="app-container">
    <?php include 'templates/header.php'; ?>

    <main class="scroll-area">
        <div class="dash-wrap" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
            
            <!-- Overview Header -->
            <div class="dash-panel-head" style="margin-bottom: 30px;">
                <div>
                    <h1 style="font-size: 24px; font-weight: 700; color: #fff;">Welcome back, <?php echo clean($customer['name']); ?>!</h1>
                    <span class="badge" style="background: var(--gold-gradient); color: #000; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; display: inline-block; margin-top: 8px;">
                        <?php echo ucfirst($customer['user_type']); ?>
                    </span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="dash-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="dash-stat" style="background: var(--bg-card-solid); border: 1px solid var(--glass-border); padding: 20px; border-radius: 12px; text-align: center;">
                    <div class="dash-stat-label" style="color: var(--text-secondary); font-size: 12px; text-transform: uppercase; margin-bottom: 8px;">Total Orders</div>
                    <div class="dash-stat-value" style="font-size: 24px; font-weight: 800; color: var(--gold-light);"><?php echo $totalOrders; ?></div>
                </div>
                <div class="dash-stat" style="background: var(--bg-card-solid); border: 1px solid var(--glass-border); padding: 20px; border-radius: 12px; text-align: center;">
                    <div class="dash-stat-label" style="color: var(--text-secondary); font-size: 12px; text-transform: uppercase; margin-bottom: 8px;">Processing</div>
                    <div class="dash-stat-value" style="font-size: 24px; font-weight: 800; color: var(--gold-light);"><?php echo $processingOrders; ?></div>
                </div>
                <div class="dash-stat" style="background: var(--bg-card-solid); border: 1px solid var(--glass-border); padding: 20px; border-radius: 12px; text-align: center;">
                    <div class="dash-stat-label" style="color: var(--text-secondary); font-size: 12px; text-transform: uppercase; margin-bottom: 8px;">In Wishlist</div>
                    <div class="dash-stat-value" style="font-size: 24px; font-weight: 800; color: var(--gold-light);"><?php echo $wishlistCount; ?></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;" class="dash-grid-layout">
                
                <!-- Recent Orders Table -->
                <div class="card" style="background: var(--bg-card-solid); border: 1px solid var(--glass-border); border-radius: 12px; overflow: hidden;">
                    <div class="card-header" style="padding: 20px; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 16px; font-weight: 700; color: #fff;"><i class="fa-solid fa-box-open" style="color: var(--gold-primary); margin-right: 10px;"></i>Recent Orders</h3>
                        <a href="#" style="color: var(--gold-light); font-size: 12px; font-weight: 600;">View All</a>
                    </div>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="background: rgba(255,255,255,0.03); color: var(--text-secondary); text-align: left;">
                                    <th style="padding: 15px;">Order ID</th>
                                    <th style="padding: 15px;">Date</th>
                                    <th style="padding: 15px;">Total</th>
                                    <th style="padding: 15px;">Status</th>
                                </tr>
                            </thead>
                            <tbody style="color: #fff;">
                                <?php if(empty($recentOrders)): ?>
                                    <tr>
                                        <td colspan="4" style="padding: 40px; text-align: center; color: var(--text-secondary);">No orders found yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($recentOrders as $order): ?>
                                    <tr style="border-bottom: 1px solid var(--glass-border);">
                                        <td style="padding: 15px; font-weight: 700; color: var(--gold-primary);">#<?php echo $order['order_number']; ?></td>
                                        <td style="padding: 15px;"><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                                        <td style="padding: 15px; font-weight: 600;">₹<?php echo number_format($order['total'], 2); ?></td>
                                        <td style="padding: 15px;">
                                            <span class="badge <?php echo getStatusBadge($order['status']); ?>" style="padding: 4px 10px; border-radius: 4px; font-size: 11px;">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="quick-links">
                    <h3 style="font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 20px;">Quick Actions</h3>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <a href="track-order.php" class="gold-btn" style="text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <i class="fa-solid fa-truck-fast"></i> Track Order
                        </a>
                        <a href="#" class="outline-btn" style="text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <i class="fa-solid fa-location-dot"></i> Edit Address
                        </a>
                        <a href="#" class="outline-btn" style="text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <i class="fa-solid fa-headset"></i> Contact Support
                        </a>
                    </div>
                </div>

            </div>
        </div>
        <div class="pb-100"></div>
    </main>

    <?php include 'templates/footer.php'; ?>
</div>

<style>
    @media (max-width: 768px) {
        .dash-grid-layout {
            grid-template-columns: 1fr !important;
        }
    }
</style>