<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Orders Management Page - DesiVastra Admin
 */
require_once __DIR__ . '/includes/layout.php';

// Handle status update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_status') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = sanitize($_POST['status'] ?? '');
        $csrfToken = $_POST['csrf_token'] ?? '';
        
        if (!verifyCSRF($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
            exit;
        }
        
        $allowedStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!$orderId || !in_array($newStatus, $allowedStatuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            exit;
        }
        
        try {
            $db = getDB();
            
            // Fetch current order
            $stmt = $db->prepare("SELECT id, status, order_number FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if (!$order) {
                echo json_encode(['success' => false, 'message' => 'Order not found.']);
                exit;
            }
            
            $oldStatus = $order['status'];
            
            // Build update query with timestamp fields
            $updateFields = "status = ?, updated_at = NOW()";
            $params = [$newStatus];
            
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
            
            logActivity('update_status', 'order', $orderId, [
                'order_number' => $order['order_number'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => "Order #{$order['order_number']} status updated to " . ucfirst($newStatus),
                'badge' => getStatusBadge($newStatus),
                'status' => $newStatus
            ]);
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// Handle GET parameters for filtering
$statusFilter = sanitize($_GET['status'] ?? '');
$searchQuery = sanitize($_GET['search'] ?? '');
$paymentFilter = sanitize($_GET['payment_status'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Validate status filter
$validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
if ($statusFilter && !in_array($statusFilter, $validStatuses)) {
    $statusFilter = '';
}

// Validate payment status filter
$validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
if ($paymentFilter && !in_array($paymentFilter, $validPaymentStatuses)) {
    $paymentFilter = '';
}

// Get order status counts for tabs
$statusCounts = getOrderStatusCounts();
$totalOrders = array_sum($statusCounts);

// Build the base query with LEFT JOIN for item counts
$baseQuery = "
    SELECT o.id, o.order_number, o.customer_name, o.customer_email, 
           o.total, o.payment_method, o.payment_status, o.status, 
           o.created_at, o.updated_at,
           COALESCE(oi.item_count, 0) as items_count
    FROM orders o
    LEFT JOIN (
        SELECT order_id, COUNT(*) as item_count 
        FROM order_items 
        GROUP BY order_id
    ) oi ON oi.order_id = o.id
";

$whereClauses = [];
$params = [];

if ($statusFilter) {
    $whereClauses[] = "o.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $whereClauses[] = "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($paymentFilter) {
    $whereClauses[] = "o.payment_status = ?";
    $params[] = $paymentFilter;
}

if ($dateFrom) {
    $whereClauses[] = "DATE(o.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereClauses[] = "DATE(o.created_at) <= ?";
    $params[] = $dateTo;
}

if (!empty($whereClauses)) {
    $baseQuery .= " WHERE " . implode(" AND ", $whereClauses);
}

$baseQuery .= " ORDER BY o.created_at DESC";

// Get paginated results
$pagination = paginate($baseQuery, $params, $currentPage, ADMIN_PER_PAGE);
$orders = $pagination['data'];

// Flash message
$flash = getFlash();
$csrf = generateCSRF();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        /* Orders-specific styles */
        .status-tabs {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 6px;
        }

        .status-tab {
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-tab:hover {
            background: rgba(212, 168, 83, 0.06);
            color: var(--text-primary);
        }

        .status-tab.active {
            background: var(--gold-gradient);
            color: #0a0a0f;
            border-color: var(--gold-primary);
        }

        .status-tab .tab-count {
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
        }

        .status-tab.active .tab-count {
            background: rgba(10, 10, 15, 0.2);
            color: #0a0a0f;
        }

        .status-tab:not(.active) .tab-count {
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-muted);
        }

        .filter-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .filter-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-row .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .filter-row .filter-group label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .filter-row .filter-group input,
        .filter-row .filter-group select {
            padding: 8px 12px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 13px;
            font-family: inherit;
            transition: var(--transition);
        }

        .filter-row .filter-group input:focus,
        .filter-row .filter-group select:focus {
            outline: none;
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(212, 168, 83, 0.1);
        }

        .filter-row .filter-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%239a9ab0' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 30px;
            cursor: pointer;
        }

        .filter-row .search-wrap {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .filter-row .search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 13px;
        }

        .filter-row .search-wrap input {
            padding-left: 36px;
            width: 100%;
        }

        .order-number {
            font-weight: 700;
            color: var(--gold-primary);
            font-size: 13px;
        }

        .customer-info .customer-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
        }

        .customer-info .customer-email {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .order-total {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 14px;
        }

        .order-date {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .order-date .time-ago {
            font-size: 11px;
            color: var(--text-muted);
            display: block;
            margin-top: 1px;
        }

        .items-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .items-badge i {
            font-size: 10px;
            color: var(--text-muted);
        }

        .action-btns {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .action-btns .btn-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            font-size: 12px;
        }

        .action-btns .btn-icon:hover {
            border-color: var(--gold-dark);
            color: var(--gold-primary);
            background: rgba(212, 168, 83, 0.08);
        }

        .action-btns .btn-icon.view:hover {
            border-color: var(--info);
            color: var(--info);
            background: var(--info-bg);
        }

        .action-btns .btn-icon.delete:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: var(--danger-bg);
        }

        /* Quick status dropdown in table */
        .status-dropdown-wrap {
            position: relative;
            display: inline-block;
        }

        .status-trigger {
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .status-trigger:hover {
            filter: brightness(1.2);
        }

        .status-trigger::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 8px;
            margin-left: 4px;
            opacity: 0.6;
        }

        .status-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            margin-top: 4px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-lg);
            z-index: 100;
            min-width: 160px;
            display: none;
            overflow: hidden;
        }

        .status-dropdown.show {
            display: block;
            animation: fadeIn 0.15s ease;
        }

        .status-dropdown .status-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .status-dropdown .status-option:hover {
            background: rgba(212, 168, 83, 0.06);
            color: var(--text-primary);
        }

        .status-dropdown .status-option.current {
            background: rgba(212, 168, 83, 0.1);
            color: var(--gold-primary);
        }

        .status-dropdown .status-option .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .dot-pending { background: var(--warning); }
        .dot-confirmed { background: var(--info); }
        .dot-processing { background: var(--gold-primary); }
        .dot-shipped { background: var(--purple); }
        .dot-delivered { background: var(--success); }
        .dot-cancelled { background: var(--danger); }

        /* Summary bar */
        .summary-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .summary-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 12px;
            color: var(--text-secondary);
        }

        .summary-chip strong {
            color: var(--text-primary);
            font-weight: 700;
        }

        .summary-chip i {
            font-size: 12px;
        }

        /* Modal order detail styling */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .detail-item .detail-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .detail-item .detail-value {
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .detail-divider {
            height: 1px;
            background: var(--border-color);
            margin: 16px 0;
        }

        .detail-section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-section-title i {
            color: var(--gold-primary);
            font-size: 14px;
        }

        .address-block {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 14px;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.7;
        }

        .address-block .name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .detail-items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .detail-items-table th {
            padding: 8px 12px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
            text-align: left;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid var(--border-color);
        }

        .detail-items-table td {
            padding: 10px 12px;
            font-size: 13px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .detail-items-table .item-product {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-items-table .item-thumb {
            width: 36px;
            height: 36px;
            border-radius: 4px;
            object-fit: cover;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }

        .detail-items-table .item-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .detail-items-table .item-variant {
            font-size: 11px;
            color: var(--text-muted);
        }

        .detail-total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .detail-total-row.grand {
            font-size: 16px;
            font-weight: 800;
            color: var(--gold-primary);
            padding-top: 10px;
            margin-top: 4px;
            border-top: 1px solid var(--border-color);
        }

        .status-update-select {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 13px;
            font-family: inherit;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%239a9ab0' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        .status-update-select:focus {
            outline: none;
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(212, 168, 83, 0.1);
        }

        .admin-notes-textarea {
            width: 100%;
            min-height: 80px;
            padding: 10px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 13px;
            font-family: inherit;
            resize: vertical;
        }

        .admin-notes-textarea:focus {
            outline: none;
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(212, 168, 83, 0.1);
        }

        .no-results-row td {
            text-align: center;
            padding: 48px 16px !important;
        }

        .no-results-row .no-results-icon {
            font-size: 40px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .no-results-row h3 {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .no-results-row p {
            font-size: 13px;
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .status-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                padding: 4px;
            }
            .status-tab {
                flex-shrink: 0;
            }
            .filter-row {
                flex-direction: column;
            }
            .filter-row .search-wrap {
                min-width: 100%;
            }
            .filter-row .filter-group {
                width: 100%;
            }
            .filter-row .filter-group input,
            .filter-row .filter-group select {
                width: 100%;
            }
            .summary-bar {
                gap: 8px;
            }
            .summary-chip {
                flex: 1;
                min-width: calc(50% - 8px);
            }
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">

    <!-- Sidebar + Header already included via layout.php -->

    
        <div class="page-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator">/</span>
                <span>Orders</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-shopping-bag" style="color:var(--gold-primary);margin-right:8px"></i>Orders</h1>
                    <p class="subtitle">Manage and track all customer orders</p>
                </div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-secondary btn-sm" onclick="exportOrders()" data-tooltip="Export CSV">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>

            <!-- Flash Message -->
            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                    <?php echo clean($flash['message']); ?>
                    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Status Filter Tabs -->
            <div class="status-tabs">
                <a href="orders.php" class="status-tab <?php echo !$statusFilter ? 'active' : ''; ?>">
                    All <span class="tab-count"><?php echo $totalOrders; ?></span>
                </a>
                <?php foreach ($validStatuses as $st): ?>
                    <a href="orders.php?status=<?php echo $st; ?>" class="status-tab <?php echo $statusFilter === $st ? 'active' : ''; ?>">
                        <?php echo ucfirst($st); ?>
                        <span class="tab-count"><?php echo $statusCounts[$st] ?? 0; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Filter Bar -->
            <div class="filter-card">
                <form method="GET" action="orders.php" id="filterForm">
                    <?php if ($statusFilter): ?>
                        <input type="hidden" name="status" value="<?php echo clean($statusFilter); ?>">
                    <?php endif; ?>
                    
                    <div class="filter-row">
                        <div class="filter-group search-wrap">
                            <label>Search</label>
                            <div style="position:relative">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Order # or customer name..." value="<?php echo clean($searchQuery); ?>">
                            </div>
                        </div>
                        
                        <div class="filter-group">
                            <label>Date From</label>
                            <input type="date" name="date_from" value="<?php echo clean($dateFrom); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>Date To</label>
                            <input type="date" name="date_to" value="<?php echo clean($dateTo); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>Payment Status</label>
                            <select name="payment_status">
                                <option value="">All Payments</option>
                                <option value="pending" <?php echo $paymentFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo $paymentFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="failed" <?php echo $paymentFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="refunded" <?php echo $paymentFilter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                        </div>
                        
                        <div class="filter-group" style="flex-direction:row;align-items:flex-end;gap:6px">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="orders.php<?php echo $statusFilter ? '?status=' . clean($statusFilter) : ''; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Summary Bar (only when filters active) -->
            <?php if ($searchQuery || $paymentFilter || $dateFrom || $dateTo): ?>
                <div class="summary-bar">
                    <div class="summary-chip">
                        <i class="fas fa-filter" style="color:var(--gold-primary)"></i>
                        Showing <strong><?php echo $pagination['total']; ?></strong> result<?php echo $pagination['total'] !== 1 ? 's' : ''; ?>
                    </div>
                    <?php if ($searchQuery): ?>
                        <div class="summary-chip">
                            <i class="fas fa-search" style="color:var(--info)"></i>
                            Search: <strong>"<?php echo clean($searchQuery); ?>"</strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($paymentFilter): ?>
                        <div class="summary-chip">
                            <i class="fas fa-credit-card" style="color:var(--success)"></i>
                            Payment: <strong><?php echo ucfirst($paymentFilter); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($dateFrom || $dateTo): ?>
                        <div class="summary-chip">
                            <i class="fas fa-calendar" style="color:var(--purple)"></i>
                            <?php echo $dateFrom ? clean($dateFrom) : '...'; ?> — <?php echo $dateTo ? clean($dateTo) : '...'; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Orders Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list" style="color:var(--gold-primary);margin-right:8px"></i>
                        <?php echo $statusFilter ? ucfirst($statusFilter) . ' Orders' : 'All Orders'; ?>
                        <span style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:8px">(<?php echo $pagination['total']; ?>)</span>
                    </h3>
                </div>
                
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr class="no-results-row">
                                    <td colspan="8">
                                        <div class="no-results-icon"><i class="fas fa-box-open"></i></div>
                                        <h3>No orders found</h3>
                                        <p>
                                            <?php if ($statusFilter || $searchQuery || $paymentFilter || $dateFrom || $dateTo): ?>
                                                Try adjusting your filters or search terms.
                                            <?php else: ?>
                                                Orders will appear here once customers start placing them.
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr id="order-row-<?php echo $order['id']; ?>">
                                        <td>
                                            <span class="order-number"><?php echo clean($order['order_number']); ?></span>
                                        </td>
                                        <td>
                                            <div class="customer-info">
                                                <div class="customer-name"><?php echo clean($order['customer_name'] ?? 'N/A'); ?></div>
                                                <?php if ($order['customer_email']): ?>
                                                    <div class="customer-email"><?php echo clean($order['customer_email']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="items-badge">
                                                <i class="fas fa-box"></i>
                                                <?php echo (int)$order['items_count']; ?> item<?php echo $order['items_count'] != 1 ? 's' : ''; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="order-total"><?php echo formatIndianPrice($order['total']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getStatusBadge($order['payment_status']); ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="status-dropdown-wrap">
                                                <span class="badge <?php echo getStatusBadge($order['status']); ?> status-trigger" 
                                                      onclick="toggleStatusDropdown(event, <?php echo $order['id']; ?>)"
                                                      id="status-badge-<?php echo $order['id']; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                                <div class="status-dropdown" id="status-dropdown-<?php echo $order['id']; ?>">
                                                    <?php foreach ($validStatuses as $st): ?>
                                                        <div class="status-option <?php echo $order['status'] === $st ? 'current' : ''; ?>"
                                                             onclick="updateOrderStatus(<?php echo $order['id']; ?>, '<?php echo $st; ?>', '<?php echo clean($order['order_number']); ?>')">
                                                            <span class="dot dot-<?php echo $st; ?>"></span>
                                                            <?php echo ucfirst($st); ?>
                                                            <?php if ($order['status'] === $st): ?>
                                                                <i class="fas fa-check" style="margin-left:auto;font-size:10px;color:var(--gold-primary)"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="order-date">
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                                <span class="time-ago"><?php echo timeAgo($order['created_at']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <button class="btn-icon view" onclick="viewOrder(<?php echo $order['id']; ?>)" data-tooltip="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-icon" onclick="updateStatusModal(<?php echo $order['id']; ?>, '<?php echo clean($order['order_number']); ?>', '<?php echo $order['status']; ?>')" data-tooltip="Update Status">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="card-footer">
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                            <div style="font-size:12px;color:var(--text-muted)">
                                Showing <?php echo (($pagination['page'] - 1) * $pagination['per_page']) + 1; ?>–<?php echo min($pagination['page'] * $pagination['per_page'], $pagination['total']); ?> 
                                of <?php echo $pagination['total']; ?> orders
                            </div>
                            <div class="pagination" style="margin-top:0">
                                <?php 
                                    $queryParams = [];
                                    if ($statusFilter) $queryParams['status'] = $statusFilter;
                                    if ($searchQuery) $queryParams['search'] = $searchQuery;
                                    if ($paymentFilter) $queryParams['payment_status'] = $paymentFilter;
                                    if ($dateFrom) $queryParams['date_from'] = $dateFrom;
                                    if ($dateTo) $queryParams['date_to'] = $dateTo;
                                    $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                                ?>
                                
                                <?php if ($pagination['has_prev']): ?>
                                    <a href="orders.php?page=<?php echo $pagination['page'] - 1; ?><?php echo $queryString; ?>" class="page-btn">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="page-btn" disabled><i class="fas fa-chevron-left"></i></button>
                                <?php endif; ?>
                                
                                <?php 
                                    $startPage = max(1, $pagination['page'] - 2);
                                    $endPage = min($pagination['total_pages'], $pagination['page'] + 2);
                                    
                                    if ($startPage > 1): ?>
                                        <a href="orders.php?page=1<?php echo $queryString; ?>" class="page-btn">1</a>
                                        <?php if ($startPage > 2): ?>
                                            <span class="page-btn" style="border:none;background:none;color:var(--text-muted)">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                        <a href="orders.php?page=<?php echo $p; ?><?php echo $queryString; ?>" 
                                           class="page-btn <?php echo $p === $pagination['page'] ? 'active' : ''; ?>">
                                            <?php echo $p; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($endPage < $pagination['total_pages']): ?>
                                        <?php if ($endPage < $pagination['total_pages'] - 1): ?>
                                            <span class="page-btn" style="border:none;background:none;color:var(--text-muted)">...</span>
                                        <?php endif; ?>
                                        <a href="orders.php?page=<?php echo $pagination['total_pages']; ?><?php echo $queryString; ?>" class="page-btn">
                                            <?php echo $pagination['total_pages']; ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                <?php if ($pagination['has_next']): ?>
                                    <a href="orders.php?page=<?php echo $pagination['page'] + 1; ?><?php echo $queryString; ?>" class="page-btn">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="page-btn" disabled><i class="fas fa-chevron-right"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- View Order Detail Modal -->
<div class="modal-overlay" id="viewOrderModal">
    <div class="modal modal-lg" id="viewOrderModalContent">
        <div class="modal-header">
            <h3><i class="fas fa-shopping-bag" style="color:var(--gold-primary);margin-right:8px"></i>Order Details</h3>
            <button class="modal-close" onclick="closeModal('viewOrderModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="viewOrderModalBody">
            <!-- Dynamically loaded -->
            <div style="text-align:center;padding:40px">
                <i class="fas fa-spinner fa-spin" style="font-size:24px;color:var(--gold-primary)"></i>
                <p style="margin-top:12px;color:var(--text-muted);font-size:13px">Loading order details...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('viewOrderModal')">Close</button>
            <button class="btn btn-primary" id="modalEditStatusBtn" onclick="">
                <i class="fas fa-edit"></i> Update Status
            </button>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal-overlay" id="updateStatusModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-exchange-alt" style="color:var(--gold-primary);margin-right:8px"></i>Update Order Status</h3>
            <button class="modal-close" onclick="closeModal('updateStatusModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Order</label>
                <div id="statusModalOrderInfo" style="font-size:14px;font-weight:600;color:var(--text-primary)"></div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Current Status</label>
                <div id="statusModalCurrentBadge"></div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="statusSelect">New Status</label>
                <select class="status-update-select" id="statusSelect">
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="adminNotes">Admin Notes (optional)</label>
                <textarea class="admin-notes-textarea" id="adminNotes" placeholder="Add a note about this status change..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('updateStatusModal')">Cancel</button>
            <button class="btn btn-primary" id="confirmStatusBtn" onclick="confirmStatusUpdate()">
                <i class="fas fa-check"></i> Update Status
            </button>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" style="
    position:fixed;
    top:24px;
    right:24px;
    padding:14px 20px;
    border-radius:var(--radius-sm);
    font-size:13px;
    font-weight:600;
    z-index:9999;
    display:none;
    align-items:center;
    gap:10px;
    box-shadow:var(--shadow-lg);
    animation:fadeIn 0.3s ease;
    max-width:400px;
"></div>

<script>
// Global vars
let currentStatusOrderId = null;
let currentStatusOrderNumber = null;
let activeDropdown = null;

// CSRF token
const CSRF_TOKEN = '<?php echo $csrf; ?>';

// ==========================================
// STATUS DROPDOWN IN TABLE
// ==========================================

function toggleStatusDropdown(event, orderId) {
    event.stopPropagation();
    const dropdown = document.getElementById('status-dropdown-' + orderId);
    
    // Close any other open dropdown
    if (activeDropdown && activeDropdown !== dropdown) {
        activeDropdown.classList.remove('show');
    }
    
    dropdown.classList.toggle('show');
    activeDropdown = dropdown.classList.contains('show') ? dropdown : null;
}

// Close dropdown on outside click
document.addEventListener('click', function() {
    if (activeDropdown) {
        activeDropdown.classList.remove('show');
        activeDropdown = null;
    }
});

// ==========================================
// QUICK STATUS UPDATE (from dropdown)
// ==========================================

function updateOrderStatus(orderId, newStatus, orderNumber) {
    // Close dropdown
    if (activeDropdown) {
        activeDropdown.classList.remove('show');
        activeDropdown = null;
    }
    
    // Get current status from badge
    const badge = document.getElementById('status-badge-' + orderId);
    const currentStatus = badge.textContent.trim().toLowerCase();
    
    if (currentStatus === newStatus) return;
    
    if (!confirm(`Change order #${orderNumber} status from "${currentStatus}" to "${newStatus}"?`)) return;
    
    fetch('orders.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'update_status',
            order_id: orderId,
            status: newStatus,
            csrf_token: CSRF_TOKEN
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Update the badge in the row
            badge.className = 'badge ' + data.badge + ' status-trigger';
            badge.innerHTML = data.status.charAt(0).toUpperCase() + data.status.slice(1);
            
            // Update the dropdown current indicator
            const dropdown = document.getElementById('status-dropdown-' + orderId);
            dropdown.querySelectorAll('.status-option').forEach(opt => {
                opt.classList.remove('current');
                const checkIcon = opt.querySelector('.fa-check');
                if (checkIcon) checkIcon.remove();
                
                if (opt.textContent.trim().toLowerCase().startsWith(data.status)) {
                    opt.classList.add('current');
                    opt.insertAdjacentHTML('beforeend', '<i class="fas fa-check" style="margin-left:auto;font-size:10px;color:var(--gold-primary)"></i>');
                }
            });
            
            showToast(data.message, 'success');
        } else {
            showToast(data.message || 'Failed to update status.', 'error');
        }
    })
    .catch(err => {
        showToast('Network error. Please try again.', 'error');
    });
}

// ==========================================
// STATUS UPDATE MODAL
// ==========================================

function updateStatusModal(orderId, orderNumber, currentStatus) {
    currentStatusOrderId = orderId;
    currentStatusOrderNumber = orderNumber;
    
    document.getElementById('statusModalOrderInfo').textContent = '#' + orderNumber;
    document.getElementById('statusModalCurrentBadge').innerHTML = 
        `<span class="badge <?php echo getStatusBadge("' + currentStatus + '"); ?>">${currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1)}</span>`;
    
    // Wait, we need a mapping. Let me do it manually:
    const badgeMap = {
        'pending': 'badge-warning',
        'confirmed': 'badge-info',
        'processing': 'badge-primary',
        'shipped': 'badge-purple',
        'delivered': 'badge-success',
        'cancelled': 'badge-danger'
    };
    document.getElementById('statusModalCurrentBadge').innerHTML = 
        `<span class="badge ${badgeMap[currentStatus] || 'badge-secondary'}">${currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1)}</span>`;
    
    document.getElementById('statusSelect').value = currentStatus;
    document.getElementById('adminNotes').value = '';
    
    openModal('updateStatusModal');
}

function confirmStatusUpdate() {
    if (!currentStatusOrderId) return;
    
    const newStatus = document.getElementById('statusSelect').value;
    const adminNotes = document.getElementById('adminNotes').value;
    
    fetch('orders.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'update_status',
            order_id: currentStatusOrderId,
            status: newStatus,
            csrf_token: CSRF_TOKEN
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Update the badge in the row
            const badge = document.getElementById('status-badge-' + currentStatusOrderId);
            if (badge) {
                badge.className = 'badge ' + data.badge + ' status-trigger';
                badge.innerHTML = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                
                // Update dropdown current indicators
                const dropdown = document.getElementById('status-dropdown-' + currentStatusOrderId);
                if (dropdown) {
                    dropdown.querySelectorAll('.status-option').forEach(opt => {
                        opt.classList.remove('current');
                        const checkIcon = opt.querySelector('.fa-check');
                        if (checkIcon) checkIcon.remove();
                        
                        if (opt.textContent.trim().toLowerCase().startsWith(data.status)) {
                            opt.classList.add('current');
                            opt.insertAdjacentHTML('beforeend', '<i class="fas fa-check" style="margin-left:auto;font-size:10px;color:var(--gold-primary)"></i>');
                        }
                    });
                }
            }
            
            closeModal('updateStatusModal');
            showToast(data.message, 'success');
        } else {
            showToast(data.message || 'Failed to update status.', 'error');
        }
    })
    .catch(err => {
        showToast('Network error. Please try again.', 'error');
    });
}

// ==========================================
// VIEW ORDER DETAIL MODAL
// ==========================================

function viewOrder(orderId) {
    openModal('viewOrderModal');
    
    const body = document.getElementById('viewOrderModalBody');
    body.innerHTML = `
        <div style="text-align:center;padding:40px">
            <i class="fas fa-spinner fa-spin" style="font-size:24px;color:var(--gold-primary)"></i>
            <p style="margin-top:12px;color:var(--text-muted);font-size:13px">Loading order details...</p>
        </div>
    `;
    
    // Fetch order details via AJAX
    fetch('order-detail.php?id=' + orderId)
        .then(res => {
            // If the page exists, redirect to it
            // Otherwise, load inline data
            if (res.ok) {
                window.location.href = 'order-detail.php?id=' + orderId;
                return null;
            }
            throw new Error('Not found');
        })
        .catch(() => {
            // Fallback: build detail view from current page data
            buildOrderDetailView(orderId);
        });
    
    // Set up the edit status button in the modal
    document.getElementById('modalEditStatusBtn').onclick = function() {
        closeModal('viewOrderModal');
        const row = document.getElementById('order-row-' + orderId);
        if (row) {
            const orderNum = row.querySelector('.order-number').textContent;
            const statusBadge = row.querySelector('.status-trigger');
            const currentSt = statusBadge ? statusBadge.textContent.trim().toLowerCase() : 'pending';
            updateStatusModal(orderId, orderNum, currentSt);
        }
    };
}

function buildOrderDetailView(orderId) {
    const body = document.getElementById('viewOrderModalBody');
    
    // Find order data from the table
    const row = document.getElementById('order-row-' + orderId);
    if (!row) {
        body.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Order not found</h3></div>`;
        return;
    }
    
    const orderNumber = row.querySelector('.order-number').textContent;
    const customerName = row.querySelector('.customer-name').textContent;
    const customerEmail = row.querySelector('.customer-email')?.textContent || 'N/A';
    const total = row.querySelector('.order-total').textContent;
    const itemsCount = row.querySelector('.items-badge').textContent.trim();
    const statusBadge = row.querySelector('.status-trigger').innerHTML;
    const statusBadgeClass = row.querySelector('.status-trigger').className.replace('status-trigger', '').trim();
    const paymentBadge = row.querySelector('td:nth-child(5) .badge').innerHTML;
    const paymentBadgeClass = row.querySelector('td:nth-child(5) .badge').className.replace('badge', '').trim();
    const dateText = row.querySelector('.order-date').innerHTML;
    
    body.innerHTML = `
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Order Number</span>
                <span class="detail-value" style="color:var(--gold-primary);font-weight:700">${orderNumber}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Order Date</span>
                <span class="detail-value">${dateText}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Customer</span>
                <span class="detail-value">${customerName}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value">${customerEmail}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Order Status</span>
                <span class="badge ${statusBadgeClass}">${statusBadge}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Payment Status</span>
                <span class="badge ${paymentBadgeClass}">${paymentBadge}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Items</span>
                <span class="detail-value">${itemsCount}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Total</span>
                <span class="detail-value" style="color:var(--gold-primary);font-size:18px;font-weight:800">${total}</span>
            </div>
        </div>
        
        <div class="detail-divider"></div>
        
        <div class="detail-section-title">
            <i class="fas fa-info-circle"></i> Quick View
        </div>
        <div style="background:var(--bg-input);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:16px;font-size:13px;color:var(--text-secondary);line-height:1.7">
            <p style="margin-bottom:8px">For complete order details including items, shipping address, and payment information, please visit the full order detail page.</p>
            <a href="order-detail.php?id=${orderId}" class="btn btn-primary btn-sm" style="margin-top:4px">
                <i class="fas fa-external-link-alt"></i> View Full Details
            </a>
        </div>
    `;
}

// ==========================================
// MODAL HELPERS
// ==========================================

function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    document.body.style.overflow = '';
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(m => {
            m.classList.remove('show');
        });
        document.body.style.overflow = '';
    }
});

// ==========================================
// TOAST NOTIFICATION
// ==========================================

function showToast(message, type) {
    const toast = document.getElementById('toast');
    const colors = {
        success: { bg: 'var(--success-bg)', border: 'rgba(46,204,113,0.3)', color: 'var(--success)', icon: 'fa-check-circle' },
        error: { bg: 'var(--danger-bg)', border: 'rgba(231,76,60,0.3)', color: 'var(--danger)', icon: 'fa-exclamation-circle' },
        warning: { bg: 'var(--warning-bg)', border: 'rgba(241,196,15,0.3)', color: 'var(--warning)', icon: 'fa-exclamation-triangle' },
        info: { bg: 'var(--info-bg)', border: 'rgba(52,152,219,0.3)', color: 'var(--info)', icon: 'fa-info-circle' }
    };
    const c = colors[type] || colors.info;
    
    toast.style.background = c.bg;
    toast.style.border = `1px solid ${c.border}`;
    toast.style.color = c.color;
    toast.innerHTML = `<i class="fas ${c.icon}"></i> ${message}`;
    toast.style.display = 'flex';
    
    setTimeout(() => {
        toast.style.display = 'none';
    }, 4000);
}

// ==========================================
// EXPORT ORDERS
// ==========================================

function exportOrders() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = 'orders.php?' + params.toString();
}
</script>

</body>
</html>
