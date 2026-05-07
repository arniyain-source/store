<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Order Detail Page - DesiVastra Admin
 * Shows full order details when accessed via order-detail.php?id=X
 */
require_once __DIR__ . '/includes/layout.php';

// ============================================
// VALIDATE ORDER ID
// ============================================
$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) {
    setFlash('error', 'Invalid order ID.');
    redirect('orders.php');
}

// ============================================
// HANDLE STATUS UPDATE (POST)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($csrfToken)) {
        setFlash('error', 'Invalid security token. Please try again.');
        redirect('order-detail.php?id=' . $orderId);
    }

    $newStatus  = sanitize($_POST['status'] ?? '');
    $adminNotes = sanitize($_POST['admin_notes'] ?? '');
    $allowedStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];

    if (!in_array($newStatus, $allowedStatuses)) {
        setFlash('error', 'Invalid status selected.');
        redirect('order-detail.php?id=' . $orderId);
    }

    try {
        $db = getDB();

        // Fetch current order
        $stmt = $db->prepare("SELECT id, order_number, status FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $currentOrder = $stmt->fetch();

        if (!$currentOrder) {
            setFlash('error', 'Order not found.');
            redirect('orders.php');
        }

        $oldStatus = $currentOrder['status'];

        // Build update query with timestamp fields
        $updateFields = "status = ?, admin_notes = ?, updated_at = NOW()";
        $params = [$newStatus, $adminNotes];

        if ($newStatus === 'shipped') {
            $updateFields .= ", shipped_at = NOW()";
        } elseif ($newStatus === 'delivered') {
            $updateFields .= ", delivered_at = NOW()";
        } elseif ($newStatus === 'cancelled') {
            $updateFields .= ", cancelled_at = NOW()";
        }

        $params[] = $orderId;
        $stmt = $db->prepare("UPDATE orders SET {$updateFields} WHERE id = ?");
        $stmt->execute($params);

        // Log activity
        logActivity('update_status', 'order', $orderId, [
            'order_number' => $currentOrder['order_number'],
            'old_status'   => $oldStatus,
            'new_status'   => $newStatus,
            'admin_notes'  => $adminNotes
        ]);

        setFlash('success', "Order #{$currentOrder['order_number']} status updated from " . ucfirst($oldStatus) . " to " . ucfirst($newStatus) . ".");
        redirect('order-detail.php?id=' . $orderId);

    } catch (Exception $e) {
        setFlash('error', 'Database error: ' . $e->getMessage());
        redirect('order-detail.php?id=' . $orderId);
    }
}

// ============================================
// FETCH ORDER DATA
// ============================================
try {
    $db = getDB();

    // Fetch order details
    $stmt = $db->prepare("
        SELECT o.*, c.name as customer_full_name, c.email as customer_full_email, c.phone as customer_full_phone, c.id as customer_real_id
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        setFlash('error', 'Order not found.');
        redirect('orders.php');
    }

    // Fetch order items
    $stmt = $db->prepare("
        SELECT oi.*, p.main_image, p.slug
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll();

    // Fetch order timeline from activity_log
    $stmt = $db->prepare("
        SELECT al.*, adm.name as admin_name
        FROM activity_log al
        LEFT JOIN admins adm ON al.admin_id = adm.id
        WHERE al.entity_type = 'order' AND al.entity_id = ?
        ORDER BY al.created_at DESC
    ");
    $stmt->execute([$orderId]);
    $timelineEntries = $stmt->fetchAll();

} catch (Exception $e) {
    setFlash('error', 'Database error: ' . $e->getMessage());
    redirect('orders.php');
}

// Parse shipping address JSON
$shippingAddress = json_decode($order['shipping_address'], true);
$billingAddress  = json_decode($order['billing_address'], true);

// Flash message
$flash = getFlash();
$csrf  = generateCSRF();

// Order status options
$statusOptions = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo clean($order['order_number']); ?> - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        /* Order Detail Page Styles */

        /* Order Status Header */
        .order-status-header {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        .order-status-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gold-gradient);
        }

        .order-status-header .osh-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .order-status-header .osh-left h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .order-status-header .osh-left h1 .order-num {
            color: var(--gold-primary);
        }

        .order-status-header .osh-left .osh-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .order-status-header .osh-left .osh-meta .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .order-status-header .osh-left .osh-meta .meta-item i {
            font-size: 12px;
            color: var(--text-muted);
        }

        .order-status-header .osh-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .status-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 24px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge-large .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .status-badge-large.badge-pending-lg   { background: var(--warning-bg); color: var(--warning); }
        .status-badge-large.badge-pending-lg .dot   { background: var(--warning); }
        .status-badge-large.badge-confirmed-lg  { background: var(--info-bg); color: var(--info); }
        .status-badge-large.badge-confirmed-lg .dot  { background: var(--info); }
        .status-badge-large.badge-processing-lg { background: rgba(212,168,83,0.15); color: var(--gold-primary); }
        .status-badge-large.badge-processing-lg .dot { background: var(--gold-primary); }
        .status-badge-large.badge-shipped-lg    { background: var(--purple-bg); color: var(--purple); }
        .status-badge-large.badge-shipped-lg .dot    { background: var(--purple); }
        .status-badge-large.badge-delivered-lg  { background: var(--success-bg); color: var(--success); }
        .status-badge-large.badge-delivered-lg .dot  { background: var(--success); }
        .status-badge-large.badge-cancelled-lg  { background: var(--danger-bg); color: var(--danger); }
        .status-badge-large.badge-cancelled-lg .dot  { background: var(--danger); }
        .status-badge-large.badge-returned-lg   { background: rgba(100,100,120,0.2); color: var(--text-secondary); }
        .status-badge-large.badge-returned-lg .dot   { background: var(--text-secondary); }

        /* Two-Column Layout */
        .order-detail-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 24px;
            align-items: start;
        }

        .order-detail-left,
        .order-detail-right {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Order Items Card */
        .order-item-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .order-item-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .order-item-row:first-child {
            padding-top: 0;
        }

        .order-item-img {
            width: 72px;
            height: 72px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .order-item-img-placeholder {
            width: 72px;
            height: 72px;
            border-radius: var(--radius-sm);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 20px;
        }

        .order-item-info {
            flex: 1;
            min-width: 0;
        }

        .order-item-info .item-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .order-item-info .item-sku {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .order-item-info .item-variants {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .order-item-info .variant-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 11px;
            color: var(--text-secondary);
        }

        .variant-tag .variant-label {
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.5px;
        }

        .order-item-pricing {
            text-align: right;
            flex-shrink: 0;
        }

        .order-item-pricing .item-price {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .order-item-pricing .item-qty {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .order-item-pricing .item-total {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
            margin-top: 4px;
        }

        /* Order Summary Card */
        .summary-rows {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .summary-row.discount {
            color: var(--success);
        }

        .summary-row.total {
            font-size: 18px;
            font-weight: 800;
            color: var(--gold-primary);
            padding-top: 12px;
            margin-top: 4px;
            border-top: 1px solid var(--border-color);
        }

        .summary-row .label {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .summary-row .label i {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Payment Info Card */
        .payment-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .payment-info-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .payment-info-item .pi-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .payment-info-item .pi-value {
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 500;
        }

        /* Customer Info Card */
        .customer-detail-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 16px;
        }

        .customer-avatar-lg {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--gold-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            color: #0a0a0f;
            flex-shrink: 0;
        }

        .customer-detail-info .cd-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .customer-detail-info .cd-email {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .customer-contact-rows {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .contact-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .contact-row i {
            width: 18px;
            text-align: center;
            font-size: 12px;
            color: var(--gold-primary);
        }

        .contact-row a {
            color: var(--text-secondary);
        }

        .contact-row a:hover {
            color: var(--gold-primary);
        }

        /* Shipping Address Card */
        .address-display {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 16px;
            line-height: 1.8;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .address-display .addr-name {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 14px;
        }

        .address-display .addr-phone {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .address-display .addr-phone i {
            font-size: 10px;
            color: var(--gold-primary);
        }

        /* Update Status Card */
        .update-status-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .update-status-card .card-header {
            background: rgba(212, 168, 83, 0.04);
        }

        .status-progress {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 20px;
            padding: 0 4px;
        }

        .status-step {
            flex: 1;
            height: 4px;
            border-radius: 2px;
            background: var(--border-color);
            position: relative;
            transition: var(--transition);
        }

        .status-step.completed {
            background: var(--gold-primary);
        }

        .status-step.current {
            background: var(--gold-gradient);
            box-shadow: 0 0 8px rgba(212, 168, 83, 0.4);
        }

        /* Timeline enhancements for order-detail */
        .order-timeline .timeline-item .timeline-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .order-timeline .timeline-item .timeline-desc {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .order-timeline .timeline-item .timeline-by {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .order-timeline .timeline-item.cancelled::before {
            background: var(--danger);
            box-shadow: 0 0 8px rgba(231, 76, 60, 0.4);
        }

        .order-timeline .timeline-item.delivered::before {
            background: var(--success);
            box-shadow: 0 0 8px rgba(46, 204, 113, 0.4);
        }

        /* Print Invoice Button */
        .print-invoice-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-size: 12px;
            cursor: pointer;
            transition: var(--transition);
        }

        .print-invoice-btn:hover {
            border-color: var(--gold-dark);
            color: var(--gold-primary);
            background: rgba(212, 168, 83, 0.06);
        }

        /* Back button */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 16px;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--gold-primary);
        }

        /* Coupon display */
        .coupon-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: rgba(52, 152, 219, 0.1);
            border: 1px dashed rgba(52, 152, 219, 0.3);
            border-radius: 4px;
            font-size: 11px;
            color: var(--info);
            font-weight: 600;
        }

        /* Transaction ID */
        .txn-id {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: var(--bg-input);
            padding: 4px 8px;
            border-radius: 4px;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        /* Empty timeline */
        .empty-timeline {
            text-align: center;
            padding: 24px 16px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .empty-timeline i {
            font-size: 28px;
            display: block;
            margin-bottom: 8px;
        }

        /* Notes display */
        .notes-display {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-top: 12px;
        }

        .notes-display .notes-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 4px;
        }

        /* Customer detail link */
        .view-customer-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--gold-primary);
            margin-top: 12px;
            padding: 6px 12px;
            background: rgba(212, 168, 83, 0.06);
            border: 1px solid rgba(212, 168, 83, 0.15);
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .view-customer-link:hover {
            background: rgba(212, 168, 83, 0.12);
            border-color: var(--gold-dark);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .order-detail-grid {
                grid-template-columns: 1fr;
            }

            .order-detail-right {
                order: -1;
            }
        }

        @media (max-width: 768px) {
            .order-status-header .osh-top {
                flex-direction: column;
            }

            .order-status-header .osh-left h1 {
                font-size: 22px;
            }

            .order-item-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .order-item-pricing {
                text-align: left;
                display: flex;
                align-items: center;
                gap: 16px;
                width: 100%;
                justify-content: space-between;
            }

            .payment-info-grid {
                grid-template-columns: 1fr;
            }

            .status-badge-large {
                font-size: 12px;
                padding: 6px 14px;
            }
        }

        @media (max-width: 480px) {
            .order-status-header {
                padding: 16px;
            }

            .order-status-header .osh-left h1 {
                font-size: 18px;
            }

            .order-item-img,
            .order-item-img-placeholder {
                width: 56px;
                height: 56px;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">

    
        <div class="page-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator">/</span>
                <a href="orders.php">Orders</a>
                <span class="separator">/</span>
                <span>Order #<?php echo clean($order['order_number']); ?></span>
            </div>

            <!-- Back Link -->
            <a href="orders.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>

            <!-- Flash Message -->
            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                    <?php echo clean($flash['message']); ?>
                    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Order Status Header -->
            <div class="order-status-header">
                <div class="osh-top">
                    <div class="osh-left">
                        <h1>Order <span class="order-num">#<?php echo clean($order['order_number']); ?></span></h1>
                        <div class="osh-meta">
                            <div class="meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('M j, Y \a\t g:i A', strtotime($order['created_at'])); ?>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-box"></i>
                                <?php echo count($orderItems); ?> item<?php echo count($orderItems) !== 1 ? 's' : ''; ?>
                            </div>
                            <?php if ($order['payment_method']): ?>
                                <div class="meta-item">
                                    <i class="fas fa-credit-card"></i>
                                    <?php echo clean(str_replace('_', ' ', $order['payment_method'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="osh-right">
                        <span class="status-badge-large badge-<?php echo $order['status']; ?>-lg">
                            <span class="dot"></span>
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                        <button class="print-invoice-btn" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </div>

            <!-- Two-Column Layout -->
            <div class="order-detail-grid">

                <!-- ==================== LEFT COLUMN ==================== -->
                <div class="order-detail-left">

                    <!-- Order Items Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-shopping-bag" style="color:var(--gold-primary);margin-right:8px"></i>Order Items</h3>
                            <span style="font-size:12px;color:var(--text-muted)"><?php echo count($orderItems); ?> item<?php echo count($orderItems) !== 1 ? 's' : ''; ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($orderItems)): ?>
                                <div class="empty-state" style="padding:32px">
                                    <i class="fas fa-box-open" style="font-size:32px;color:var(--text-muted)"></i>
                                    <p style="margin-top:12px;color:var(--text-muted);font-size:13px">No items found for this order.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($orderItems as $item): ?>
                                    <div class="order-item-row">
                                        <!-- Product Image -->
                                        <?php
                                            $imgSrc = '';
                                            if (!empty($item['product_image'])) {
                                                $imgSrc = SITE_URL . '/' . $item['product_image'];
                                            } elseif (!empty($item['main_image'])) {
                                                $imgSrc = SITE_URL . '/' . $item['main_image'];
                                            }
                                        ?>
                                        <?php if ($imgSrc): ?>
                                            <img src="<?php echo clean($imgSrc); ?>" alt="<?php echo clean($item['product_name']); ?>" class="order-item-img">
                                        <?php else: ?>
                                            <div class="order-item-img-placeholder">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Product Info -->
                                        <div class="order-item-info">
                                            <div class="item-name"><?php echo clean($item['product_name']); ?></div>
                                            <?php if (!empty($item['sku'])): ?>
                                                <div class="item-sku">SKU: <?php echo clean($item['sku']); ?></div>
                                            <?php endif; ?>
                                            <div class="item-variants">
                                                <?php if (!empty($item['size'])): ?>
                                                    <span class="variant-tag">
                                                        <span class="variant-label">Size</span> <?php echo clean($item['size']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($item['color'])): ?>
                                                    <span class="variant-tag">
                                                        <span class="variant-label">Color</span> <?php echo clean($item['color']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($item['finish'])): ?>
                                                    <span class="variant-tag">
                                                        <span class="variant-label">Finish</span> <?php echo clean($item['finish']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Pricing -->
                                        <div class="order-item-pricing">
                                            <div class="item-price"><?php echo formatIndianPrice($item['price']); ?> each</div>
                                            <div class="item-qty">Qty: <?php echo (int)$item['quantity']; ?></div>
                                            <div class="item-total"><?php echo formatIndianPrice($item['total']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Order Summary Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-receipt" style="color:var(--gold-primary);margin-right:8px"></i>Order Summary</h3>
                        </div>
                        <div class="card-body">
                            <div class="summary-rows">
                                <div class="summary-row">
                                    <span class="label"><i class="fas fa-tag"></i> Subtotal</span>
                                    <span><?php echo formatIndianPrice($order['subtotal']); ?></span>
                                </div>
                                <?php if ((float)$order['discount'] > 0): ?>
                                    <div class="summary-row discount">
                                        <span class="label">
                                            <i class="fas fa-percent"></i> Discount
                                            <?php if (!empty($order['coupon_code'])): ?>
                                                <span class="coupon-badge"><i class="fas fa-tag" style="font-size:9px"></i> <?php echo clean($order['coupon_code']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span>-<?php echo formatIndianPrice($order['discount']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="summary-row">
                                    <span class="label"><i class="fas fa-truck"></i> Shipping</span>
                                    <span>
                                        <?php if ((float)$order['shipping_cost'] > 0): ?>
                                            <?php echo formatIndianPrice($order['shipping_cost']); ?>
                                        <?php else: ?>
                                            <span style="color:var(--success);font-weight:600">Free</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ((float)$order['tax'] > 0): ?>
                                    <div class="summary-row">
                                        <span class="label"><i class="fas fa-percentage"></i> Tax</span>
                                        <span><?php echo formatIndianPrice($order['tax']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="summary-row total">
                                    <span class="label"><i class="fas fa-coins"></i> Total</span>
                                    <span><?php echo formatIndianPrice($order['total']); ?></span>
                                </div>
                            </div>

                            <?php if (!empty($order['notes'])): ?>
                                <div class="notes-display">
                                    <div class="notes-label"><i class="fas fa-sticky-note" style="margin-right:4px"></i> Customer Notes</div>
                                    <?php echo nl2br(clean($order['notes'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payment Info Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-credit-card" style="color:var(--gold-primary);margin-right:8px"></i>Payment Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="payment-info-grid">
                                <div class="payment-info-item">
                                    <span class="pi-label">Payment Method</span>
                                    <span class="pi-value">
                                        <?php if ($order['payment_method']): ?>
                                            <i class="fas fa-<?php echo $order['payment_method'] === 'cod' ? 'money-bill-wave' : 'credit-card'; ?>" style="margin-right:4px;font-size:11px;color:var(--gold-primary)"></i>
                                            <?php echo clean(str_replace('_', ' ', strtoupper($order['payment_method']))); ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted)">N/A</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="payment-info-item">
                                    <span class="pi-label">Payment Status</span>
                                    <span class="pi-value">
                                        <span class="badge <?php echo getStatusBadge($order['payment_status']); ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </span>
                                </div>
                                <?php if (!empty($order['transaction_id'])): ?>
                                    <div class="payment-info-item" style="grid-column:1/-1">
                                        <span class="pi-label">Transaction ID</span>
                                        <span class="pi-value">
                                            <span class="txn-id"><?php echo clean($order['transaction_id']); ?></span>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ==================== RIGHT COLUMN ==================== -->
                <div class="order-detail-right">

                    <!-- Customer Info Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-user" style="color:var(--gold-primary);margin-right:8px"></i>Customer</h3>
                        </div>
                        <div class="card-body">
                            <div class="customer-detail-card">
                                <div class="customer-avatar-lg">
                                    <?php echo strtoupper(substr($order['customer_name'] ?? $order['customer_full_name'] ?? 'C', 0, 1)); ?>
                                </div>
                                <div class="customer-detail-info">
                                    <div class="cd-name"><?php echo clean($order['customer_name'] ?? $order['customer_full_name'] ?? 'Guest Customer'); ?></div>
                                    <?php if ($order['customer_email'] ?? $order['customer_full_email']): ?>
                                        <div class="cd-email"><?php echo clean($order['customer_email'] ?? $order['customer_full_email']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="customer-contact-rows">
                                <?php if ($order['customer_phone'] ?? $order['customer_full_phone']): ?>
                                    <div class="contact-row">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo clean($order['customer_phone'] ?? $order['customer_full_phone']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($order['customer_email'] ?? $order['customer_full_email']): ?>
                                    <div class="contact-row">
                                        <i class="fas fa-envelope"></i>
                                        <a href="mailto:<?php echo clean($order['customer_email'] ?? $order['customer_full_email']); ?>">
                                            <?php echo clean($order['customer_email'] ?? $order['customer_full_email']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($order['customer_id'] && $order['customer_real_id']): ?>
                                <a href="customers.php?search=<?php echo urlencode($order['customer_full_email'] ?? $order['customer_name']); ?>" class="view-customer-link">
                                    <i class="fas fa-external-link-alt"></i> View Customer Details
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Shipping Address Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-map-marker-alt" style="color:var(--gold-primary);margin-right:8px"></i>Shipping Address</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($shippingAddress): ?>
                                <div class="address-display">
                                    <?php if (!empty($shippingAddress['name'])): ?>
                                        <div class="addr-name"><?php echo clean($shippingAddress['name']); ?></div>
                                    <?php endif; ?>
                                    <?php
                                        $addrParts = [];
                                        if (!empty($shippingAddress['address_line1'])) $addrParts[] = clean($shippingAddress['address_line1']);
                                        if (!empty($shippingAddress['address_line2'])) $addrParts[] = clean($shippingAddress['address_line2']);
                                        if (!empty($shippingAddress['city'])) $addrParts[] = clean($shippingAddress['city']);
                                        if (!empty($shippingAddress['state'])) $addrParts[] = clean($shippingAddress['state']);
                                        if (!empty($shippingAddress['pincode'])) $addrParts[] = clean($shippingAddress['pincode']);
                                        echo implode('<br>', $addrParts);
                                    ?>
                                    <?php if (!empty($shippingAddress['phone'])): ?>
                                        <div class="addr-phone">
                                            <i class="fas fa-phone"></i>
                                            <?php echo clean($shippingAddress['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($order['customer_name'] || $order['customer_phone']): ?>
                                <div class="address-display">
                                    <?php if ($order['customer_name']): ?>
                                        <div class="addr-name"><?php echo clean($order['customer_name']); ?></div>
                                    <?php endif; ?>
                                    <div style="color:var(--text-muted);font-size:12px;margin-top:8px">
                                        <i class="fas fa-info-circle" style="margin-right:4px"></i>
                                        Detailed address not available for this order.
                                    </div>
                                    <?php if ($order['customer_phone']): ?>
                                        <div class="addr-phone">
                                            <i class="fas fa-phone"></i>
                                            <?php echo clean($order['customer_phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px">
                                    <i class="fas fa-map-marker-alt" style="font-size:24px;display:block;margin-bottom:8px"></i>
                                    No shipping address available
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Order Timeline Card -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-history" style="color:var(--gold-primary);margin-right:8px"></i>Order Timeline</h3>
                        </div>
                        <div class="card-body" style="max-height:400px;overflow-y:auto">
                            <div class="order-timeline">
                                <?php if (empty($timelineEntries)): ?>
                                    <div class="empty-timeline">
                                        <i class="fas fa-clock"></i>
                                        No activity recorded yet
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($timelineEntries as $i => $entry): ?>
                                        <?php
                                            $details = json_decode($entry['details'], true);
                                            $isFirst = $i === 0;
                                            $itemClass = $isFirst ? 'active' : '';

                                            // Add special classes for certain statuses
                                            if (!empty($details['new_status'])) {
                                                if ($details['new_status'] === 'cancelled') $itemClass .= ' cancelled';
                                                if ($details['new_status'] === 'delivered') $itemClass .= ' delivered';
                                            }
                                        ?>
                                        <div class="timeline-item <?php echo $itemClass; ?>">
                                            <?php if (!empty($details['new_status'])): ?>
                                                <div class="timeline-title">
                                                    Status changed to
                                                    <span class="badge <?php echo getStatusBadge($details['new_status']); ?>" style="font-size:10px;padding:2px 8px">
                                                        <?php echo ucfirst($details['new_status']); ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($details['old_status'])): ?>
                                                    <div class="timeline-desc">
                                                        From <span style="color:var(--text-muted)"><?php echo ucfirst($details['old_status']); ?></span>
                                                        to <span style="color:var(--gold-primary);font-weight:600"><?php echo ucfirst($details['new_status']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php elseif ($entry['action'] === 'login'): ?>
                                                <div class="timeline-title">Admin logged in</div>
                                            <?php else: ?>
                                                <div class="timeline-title"><?php echo clean(ucfirst(str_replace('_', ' ', $entry['action']))); ?></div>
                                            <?php endif; ?>

                                            <?php if (!empty($entry['admin_name'])): ?>
                                                <div class="timeline-by">
                                                    <i class="fas fa-user" style="font-size:9px;margin-right:4px"></i>
                                                    <?php echo clean($entry['admin_name']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="timeline-date">
                                                <?php echo date('M j, Y \a\t g:i A', strtotime($entry['created_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Update Status Card -->
                    <div class="update-status-card">
                        <div class="card-header">
                            <h3><i class="fas fa-edit" style="color:var(--gold-primary);margin-right:8px"></i>Update Status</h3>
                        </div>
                        <div class="card-body">
                            <!-- Status Progress Bar -->
                            <?php
                                $statusFlow = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
                                $currentIdx = array_search($order['status'], $statusFlow);
                                $isCancelled = $order['status'] === 'cancelled';
                                $isReturned = $order['status'] === 'returned';
                            ?>
                            <?php if (!$isCancelled && !$isReturned && $currentIdx !== false): ?>
                                <div class="status-progress">
                                    <?php foreach ($statusFlow as $si => $sf): ?>
                                        <div class="status-step <?php echo $si < $currentIdx ? 'completed' : ($si === $currentIdx ? 'current' : ''); ?>"
                                             data-tooltip="<?php echo ucfirst($sf); ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="order-detail.php?id=<?php echo $orderId; ?>" id="updateStatusForm">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                                <div class="form-group">
                                    <label class="form-label">Order Status</label>
                                    <select name="status" class="form-control" id="statusSelect">
                                        <?php foreach ($statusOptions as $opt): ?>
                                            <option value="<?php echo $opt; ?>" <?php echo $order['status'] === $opt ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($opt); ?>
                                                <?php if ($order['status'] === $opt): ?> (Current)<?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Admin Notes</label>
                                    <textarea name="admin_notes" class="form-control" rows="3" placeholder="Add notes about this status change..."><?php echo clean($order['admin_notes'] ?? ''); ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary" style="width:100%" id="updateBtn">
                                    <i class="fas fa-save"></i> Update Order Status
                                </button>
                            </form>

                            <?php if (!empty($order['admin_notes'])): ?>
                                <div class="notes-display">
                                    <div class="notes-label"><i class="fas fa-sticky-note" style="margin-right:4px"></i> Current Admin Notes</div>
                                    <?php echo nl2br(clean($order['admin_notes'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>
            <!-- /order-detail-grid -->

        </div>
    </main>

</div>

<script>
// Confirm before status change to destructive states
document.getElementById('updateStatusForm').addEventListener('submit', function(e) {
    const selected = document.getElementById('statusSelect').value;
    const current = '<?php echo $order['status']; ?>';

    if (selected === current) {
        e.preventDefault();
        alert('The selected status is the same as the current status. No changes will be made.');
        return;
    }

    if (selected === 'cancelled') {
        if (!confirm('Are you sure you want to CANCEL this order? This action will set the cancelled_at timestamp.')) {
            e.preventDefault();
            return;
        }
    }

    if (selected === 'delivered' && current !== 'shipped') {
        if (!confirm('Marking as Delivered without being Shipped first. Are you sure?')) {
            e.preventDefault();
            return;
        }
    }

    // Show loading state on button
    const btn = document.getElementById('updateBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
});

// Auto-dismiss flash messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const flashMsg = document.querySelector('.flash-message');
    if (flashMsg) {
        setTimeout(function() {
            flashMsg.style.opacity = '0';
            flashMsg.style.transform = 'translateY(-10px)';
            flashMsg.style.transition = 'all 0.3s ease';
            setTimeout(function() { flashMsg.remove(); }, 300);
        }, 5000);
    }
});
</script>

</body>
</html>
