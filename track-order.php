<?php
/**
 * Track Order - DesiVastra Public Page
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Track Your Order - DesiVastra";
$extraCSS = '
<style>
    .track-container {
        max-width: 600px;
        margin: 40px auto;
        padding: 20px;
    }
    .track-card {
        background: var(--bg-card-solid);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .track-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .track-header h1 {
        font-size: 24px;
        margin-bottom: 10px;
        color: var(--gold-light);
        font-weight: 700;
    }
    .track-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .track-input {
        background: #16161a;
        border: 1px solid var(--glass-border);
        padding: 15px;
        border-radius: 8px;
        color: #fff;
        font-size: 16px;
        width: 100%;
        outline: none;
        transition: border-color 0.3s;
    }
    .track-input:focus {
        border-color: var(--gold-primary);
    }
    .track-results {
        margin-top: 40px;
    }
    .order-meta-box {
        border-bottom: 1px solid var(--glass-border);
        padding-bottom: 20px;
        margin-bottom: 25px;
    }
    .meta-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    .meta-label {
        color: var(--text-secondary);
        font-size: 13px;
    }
    .meta-value {
        color: #fff;
        font-weight: 600;
        font-size: 14px;
    }
    .meta-value.gold {
        color: var(--gold-light);
    }
    .timeline {
        position: relative;
        margin-top: 25px;
        padding-left: 45px;
    }
    .timeline::before {
        content: "";
        position: absolute;
        left: 20px;
        top: 5px;
        bottom: 5px;
        width: 2px;
        background: var(--glass-border);
    }
    .timeline-item {
        position: relative;
        padding-bottom: 30px;
    }
    .timeline-item:last-child {
        padding-bottom: 0;
    }
    .timeline-dot {
        position: absolute;
        left: -45px;
        top: 0;
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
    }
    .dot-circle {
        width: 12px;
        height: 12px;
        background: #2a2a2e;
        border: 2px solid var(--text-secondary);
        border-radius: 50%;
    }
    .timeline-item.completed .dot-circle {
        background: var(--gold-primary);
        border-color: var(--gold-primary);
        box-shadow: 0 0 10px rgba(184, 137, 42, 0.4);
    }
    .timeline-item.active .dot-circle {
        background: #fff;
        border-color: var(--gold-light);
        animation: pulse-gold 2s infinite;
    }
    @keyframes pulse-gold {
        0% { box-shadow: 0 0 0 0 rgba(229, 195, 90, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(229, 195, 90, 0); }
        100% { box-shadow: 0 0 0 0 rgba(229, 195, 90, 0); }
    }
    .timeline-content h4 {
        font-size: 15px;
        margin: 0;
        color: #fff;
        font-weight: 600;
    }
    .timeline-item.completed .timeline-content h4 {
        color: var(--gold-light);
    }
    .timeline-content p {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 4px 0 0;
    }
    .error-box {
        background: rgba(207, 102, 121, 0.1);
        border: 1px solid var(--danger);
        color: var(--danger);
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        margin-top: 30px;
    }
    .whatsapp-btn-track {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: #25D366;
        color: #fff;
        padding: 12px 24px;
        border-radius: 8px;
        margin-top: 15px;
        font-weight: 700;
        width: 100%;
        transition: transform 0.2s;
    }
    .whatsapp-btn-track:hover {
        transform: scale(1.02);
        color: #fff;
    }
</style>
';

$trackingId = trim($_GET['id'] ?? '');
$order = null;
$notFound = false;

if ($trackingId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? OR awb_number = ? LIMIT 1");
        $stmt->execute([$trackingId, $trackingId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            $notFound = true;
        }
    } catch (Exception $e) {
        $notFound = true;
    }
}

include 'templates/head.php';
?>

<div class="app-container">
    <?php include 'templates/header.php'; ?>

    <main class="scroll-area">
        <div class="track-container">
            <div class="track-card">
                <div class="track-header">
                    <h1>Track Your Order</h1>
                    <p style="color: var(--text-secondary); font-size: 14px;">Enter your Order ID or AWB number to see current status.</p>
                </div>

                <form method="GET" action="track-order.php" class="track-form">
                    <input type="text" name="id" class="track-input" placeholder="e.g. DV-20230507-123456" value="<?php echo htmlspecialchars($trackingId); ?>" required>
                    <button type="submit" class="gold-btn full-width">Track Now</button>
                </form>

                <?php if ($notFound): ?>
                    <div class="error-box">
                        <i class="fa-solid fa-circle-exclamation fa-2x" style="margin-bottom: 10px;"></i>
                        <p style="font-weight: 600;">No tracking details found!</p>
                        <p style="font-size: 13px; margin-top: 5px;">We couldn't find an order with that ID. Please check and try again or contact our support team.</p>
                        <a href="https://wa.me/919876543210" class="whatsapp-btn-track">
                            <i class="fa-brands fa-whatsapp"></i> Chat with Support
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($order): ?>
                    <div class="track-results">
                        <div class="order-meta-box">
                            <div class="meta-row">
                                <span class="meta-label">Order Number</span>
                                <span class="meta-value">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Current Status</span>
                                <span class="meta-value gold"><?php echo strtoupper(htmlspecialchars($order['status'])); ?></span>
                            </div>
                            <?php if ($order['courier_name']): ?>
                            <div class="meta-row">
                                <span class="meta-label">Courier Partner</span>
                                <span class="meta-value"><?php echo htmlspecialchars($order['courier_name']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($order['awb_number']): ?>
                            <div class="meta-row">
                                <span class="meta-label">AWB Number</span>
                                <span class="meta-value"><?php echo htmlspecialchars($order['awb_number']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <h3 style="font-size: 16px; margin-bottom: 20px; font-weight: 700;">Tracking Timeline</h3>
                        <div class="timeline">
                            <?php
                            $status = $order['status'];
                            $statuses = ['pending', 'confirmed', 'shipped', 'delivered'];
                            $currentIndex = array_search($status, $statuses);
                            if ($currentIndex === false && $status === 'processing') $currentIndex = 1;
                            
                            $displayStatuses = [
                                ['key' => 'pending', 'label' => 'Order Placed', 'time' => date('d M Y, h:i A', strtotime($order['created_at']))],
                                ['key' => 'confirmed', 'label' => 'Order Confirmed', 'time' => 'Package is being prepared'],
                                ['key' => 'shipped', 'label' => 'Shipped', 'time' => $order['awb_number'] ? 'AWB: ' . $order['awb_number'] : 'Awaiting dispatch'],
                                ['key' => 'delivered', 'label' => 'Delivered', 'time' => $order['delivered_at'] ? date('d M Y', strtotime($order['delivered_at'])) : 'Expected soon']
                            ];

                            foreach ($displayStatuses as $idx => $ds):
                                $isCompleted = ($currentIndex >= $idx || $status === 'delivered');
                                $isActive = ($currentIndex === $idx && $status !== 'delivered');
                                $class = $isCompleted ? 'completed' : ($isActive ? 'active' : '');
                            ?>
                                <div class="timeline-item <?php echo $class; ?>">
                                    <div class="timeline-dot"><div class="dot-circle"></div></div>
                                    <div class="timeline-content">
                                        <h4><?php echo $ds['label']; ?></h4>
                                        <p><?php echo $ds['time']; ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 30px; text-align: center; border-top: 1px solid var(--glass-border); padding-top: 20px;">
                            <p style="font-size: 12px; color: var(--text-secondary);">Need further assistance with your shipment?</p>
                            <a href="https://wa.me/919876543210?text=Hi, I need help tracking my order <?php echo $order['order_number']; ?>" class="whatsapp-btn-track" style="background: rgba(255,255,255,0.05); border: 1px solid #25D366; color: #25D366;">
                                <i class="fa-brands fa-whatsapp"></i> WhatsApp Support
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'templates/footer.php'; ?>
</div>

</body>
</html>