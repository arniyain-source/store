<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Reports Page - DesiVastra Admin
 * Comprehensive analytics with charts, tables, and CSV export
 */
require_once __DIR__ . '/includes/layout.php';

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $reportType = clean($_GET['report_type'] ?? 'sales');
    $dateFrom = clean($_GET['date_from'] ?? '');
    $dateTo = clean($_GET['date_to'] ?? '');
    $days = (int)($_GET['days'] ?? 30);

    $filename = 'desivastra_' . $reportType . '_report_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    $db = getDB();

    $whereDate = '';
    $dateParams = [];
    if ($dateFrom && $dateTo) {
        $whereDate = " AND DATE(o.created_at) BETWEEN ? AND ?";
        $dateParams = [$dateFrom, $dateTo];
    } elseif ($dateFrom) {
        $whereDate = " AND DATE(o.created_at) >= ?";
        $dateParams = [$dateFrom];
    } elseif ($dateTo) {
        $whereDate = " AND DATE(o.created_at) <= ?";
        $dateParams = [$dateTo];
    } else {
        $whereDate = " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
        $dateParams = [$days];
    }

    switch ($reportType) {
        case 'sales':
            fputcsv($output, ['Date', 'Orders', 'Revenue', 'Avg Order Value']);
            $stmt = $db->prepare("
                SELECT DATE(o.created_at) as date,
                       COUNT(*) as orders,
                       COALESCE(SUM(o.total), 0) as revenue,
                       COALESCE(AVG(o.total), 0) as avg_value
                FROM orders o
                WHERE o.payment_status = 'paid' {$whereDate}
                GROUP BY DATE(o.created_at)
                ORDER BY date ASC
            ");
            $stmt->execute($dateParams);
            while ($row = $stmt->fetch()) {
                fputcsv($output, [$row['date'], $row['orders'], $row['revenue'], round($row['avg_value'], 2)]);
            }
            break;

        case 'products':
            fputcsv($output, ['Product Name', 'SKU', 'Units Sold', 'Revenue', 'Avg Rating', 'Stock']);
            $stmt = $db->prepare("
                SELECT p.name, p.sku, COALESCE(SUM(oi.quantity), 0) as units_sold,
                       COALESCE(SUM(oi.total), 0) as revenue,
                       COALESCE(p.rating, 0) as avg_rating, p.stock
                FROM products p
                LEFT JOIN order_items oi ON oi.product_id = p.id
                LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled'
                WHERE 1=1
                GROUP BY p.id
                ORDER BY units_sold DESC
            ");
            $stmt->execute([]);
            while ($row = $stmt->fetch()) {
                fputcsv($output, [$row['name'], $row['sku'], $row['units_sold'], $row['revenue'], $row['avg_rating'], $row['stock']]);
            }
            break;

        case 'customers':
            fputcsv($output, ['Customer Name', 'Email', 'Type', 'Total Orders', 'Total Spent', 'Joined Date']);
            $stmt = $db->prepare("
                SELECT c.name, c.email, c.user_type,
                       COALESCE(order_stats.total_orders, 0) as total_orders,
                       COALESCE(order_stats.total_spent, 0) as total_spent,
                       c.created_at
                FROM customers c
                LEFT JOIN (
                    SELECT customer_id, COUNT(*) as total_orders, SUM(total) as total_spent
                    FROM orders WHERE payment_status = 'paid'
                    GROUP BY customer_id
                ) order_stats ON order_stats.customer_id = c.id
                ORDER BY total_spent DESC
            ");
            $stmt->execute();
            while ($row = $stmt->fetch()) {
                fputcsv($output, [$row['name'], $row['email'], $row['user_type'], $row['total_orders'], $row['total_spent'], $row['created_at']]);
            }
            break;

        case 'orders':
            fputcsv($output, ['Order #', 'Customer', 'Total', 'Payment Method', 'Payment Status', 'Order Status', 'Date']);
            $query = "
                SELECT o.order_number, o.customer_name, o.total, o.payment_method,
                       o.payment_status, o.status, o.created_at
                FROM orders o
                WHERE 1=1 {$whereDate}
                ORDER BY o.created_at DESC
            ";
            $stmt = $db->prepare($query);
            $stmt->execute($dateParams);
            while ($row = $stmt->fetch()) {
                fputcsv($output, [$row['order_number'], $row['customer_name'], $row['total'], $row['payment_method'], $row['payment_status'], $row['status'], $row['created_at']]);
            }
            break;

        default:
            fputcsv($output, ['No data available for this report type']);
    }

    fclose($output);
    exit;
}

// Date range parameters
$dateFrom = clean($_GET['date_from'] ?? '');
$dateTo = clean($_GET['date_to'] ?? '');
$days = (int)($_GET['days'] ?? 30);
$activeTab = clean($_GET['tab'] ?? 'sales');
$sortCol = clean($_GET['sort'] ?? 'units_sold');
$sortDir = clean($_GET['dir'] ?? 'desc');

if (!in_array($activeTab, ['sales', 'products', 'customers', 'orders'])) {
    $activeTab = 'sales';
}

// Validate sort
$allowedSortCols = ['name', 'units_sold', 'revenue', 'avg_rating', 'stock'];
if (!in_array($sortCol, $allowedSortCols)) {
    $sortCol = 'units_sold';
}
if (!in_array($sortDir, ['asc', 'desc'])) {
    $sortDir = 'desc';
}

$db = getDB();

// Build date conditions
$dateWhere = '';
$dateParams = [];
if ($dateFrom && $dateTo) {
    $dateWhere = " AND DATE(o.created_at) BETWEEN ? AND ?";
    $dateParams = [$dateFrom, $dateTo];
} elseif ($dateFrom) {
    $dateWhere = " AND DATE(o.created_at) >= ?";
    $dateParams = [$dateFrom];
} elseif ($dateTo) {
    $dateWhere = " AND DATE(o.created_at) <= ?";
    $dateParams = [$dateTo];
}

// ========= SALES OVERVIEW DATA =========
// Total Revenue
$revenueQuery = "SELECT COALESCE(SUM(total), 0) as total_revenue FROM orders o WHERE payment_status = 'paid'";
$revParams = [];
if ($dateFrom && $dateTo) {
    $revenueQuery .= " AND DATE(o.created_at) BETWEEN ? AND ?";
    $revParams = [$dateFrom, $dateTo];
} elseif ($dateFrom) {
    $revenueQuery .= " AND DATE(o.created_at) >= ?";
    $revParams = [$dateFrom];
} elseif ($dateTo) {
    $revenueQuery .= " AND DATE(o.created_at) <= ?";
    $revParams = [$dateTo];
} else {
    $revenueQuery .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $revParams = [$days];
}
$stmt = $db->prepare($revenueQuery);
$stmt->execute($revParams);
$totalRevenue = (float)$stmt->fetch()['total_revenue'];

// Previous period revenue for comparison
$prevRevenue = 0;
$revChange = 0;
try {
    $prevDays = $days * 2;
    $prevRevenueQuery = "SELECT COALESCE(SUM(total), 0) as prev_revenue FROM orders o WHERE payment_status = 'paid' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND o.created_at < DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $stmt = $db->prepare($prevRevenueQuery);
    $stmt->execute([$prevDays, $days]);
    $prevRevenue = (float)$stmt->fetch()['prev_revenue'];
    if ($prevRevenue > 0) {
        $revChange = round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1);
    }
} catch (Exception $e) {
    $revChange = 0;
}

// Total Orders
$ordersQuery = "SELECT COUNT(*) as total_orders FROM orders o WHERE 1=1";
$ordParams = [];
if ($dateFrom && $dateTo) {
    $ordersQuery .= " AND DATE(o.created_at) BETWEEN ? AND ?";
    $ordParams = [$dateFrom, $dateTo];
} elseif ($dateFrom) {
    $ordersQuery .= " AND DATE(o.created_at) >= ?";
    $ordParams = [$dateFrom];
} elseif ($dateTo) {
    $ordersQuery .= " AND DATE(o.created_at) <= ?";
    $ordParams = [$dateTo];
} else {
    $ordersQuery .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $ordParams = [$days];
}
$stmt = $db->prepare($ordersQuery);
$stmt->execute($ordParams);
$totalOrders = (int)$stmt->fetch()['total_orders'];

// Average Order Value
$avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// Total Refunds
$refundsQuery = "SELECT COALESCE(SUM(total), 0) as total_refunds FROM orders o WHERE payment_status = 'refunded'";
$refParams = [];
if ($dateFrom && $dateTo) {
    $refundsQuery .= " AND DATE(o.created_at) BETWEEN ? AND ?";
    $refParams = [$dateFrom, $dateTo];
} elseif ($dateFrom) {
    $refundsQuery .= " AND DATE(o.created_at) >= ?";
    $refParams = [$dateFrom];
} elseif ($dateTo) {
    $refundsQuery .= " AND DATE(o.created_at) <= ?";
    $refParams = [$dateTo];
} else {
    $refundsQuery .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $refParams = [$days];
}
$stmt = $db->prepare($refundsQuery);
$stmt->execute($refParams);
$totalRefunds = (float)$stmt->fetch()['total_refunds'];

// Revenue chart data
$chartDays = $days;
$chartData = getSalesChartData($chartDays);

// Top 5 selling products
$topProducts = getTopSellingProducts(5);

// ========= PRODUCT PERFORMANCE DATA =========
$sortMapping = [
    'name' => 'p.name',
    'units_sold' => 'units_sold',
    'revenue' => 'total_revenue',
    'avg_rating' => 'p.rating',
    'stock' => 'p.stock'
];
$orderBy = $sortMapping[$sortCol] ?? 'units_sold';
$sortSql = $sortDir === 'asc' ? 'ASC' : 'DESC';

$productPerformanceQuery = "
    SELECT p.id, p.name, p.sku, p.main_image, p.price, p.stock,
           p.low_stock_threshold, p.rating,
           COALESCE(SUM(oi.quantity), 0) as units_sold,
           COALESCE(SUM(oi.total), 0) as total_revenue
    FROM products p
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled'
    WHERE p.is_active = 1
    GROUP BY p.id
    ORDER BY {$orderBy} {$sortSql}
    LIMIT 50
";
$stmt = $db->prepare($productPerformanceQuery);
$stmt->execute();
$productPerformance = $stmt->fetchAll();

// ========= CUSTOMER ANALYTICS DATA =========
// New vs Returning customers
$newVsReturning = [];
try {
    $stmt = $db->query("
        SELECT
            CASE
                WHEN order_count = 1 THEN 'New'
                ELSE 'Returning'
            END as customer_type,
            COUNT(*) as count
        FROM (
            SELECT customer_id, COUNT(*) as order_count
            FROM orders
            WHERE customer_id IS NOT NULL
            GROUP BY customer_id
        ) sub
        GROUP BY customer_type
    ");
    while ($row = $stmt->fetch()) {
        $newVsReturning[$row['customer_type']] = (int)$row['count'];
    }
} catch (Exception $e) {
    $newVsReturning = ['New' => 0, 'Returning' => 0];
}

// New vs returning monthly data for chart
$customerTrend = [];
try {
    $stmt = $db->query("
        SELECT DATE_FORMAT(o.created_at, '%Y-%m') as month,
               COUNT(DISTINCT CASE WHEN first_order.month = DATE_FORMAT(o.created_at, '%Y-%m') THEN o.customer_id END) as new_customers,
               COUNT(DISTINCT CASE WHEN first_order.month < DATE_FORMAT(o.created_at, '%Y-%m') THEN o.customer_id END) as returning_customers
        FROM orders o
        LEFT JOIN (
            SELECT customer_id, DATE_FORMAT(MIN(created_at), '%Y-%m') as month
            FROM orders
            WHERE customer_id IS NOT NULL
            GROUP BY customer_id
        ) first_order ON first_order.customer_id = o.customer_id
        WHERE o.customer_id IS NOT NULL
        AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    while ($row = $stmt->fetch()) {
        $customerTrend[] = $row;
    }
} catch (Exception $e) {
    $customerTrend = [];
}

// Top customers by spending
$topCustomers = [];
try {
    $stmt = $db->query("
        SELECT c.id, c.name, c.email, c.user_type, c.city,
               COALESCE(order_stats.total_orders, 0) as total_orders,
               COALESCE(order_stats.total_spent, 0) as total_spent
        FROM customers c
        LEFT JOIN (
            SELECT customer_id, COUNT(*) as total_orders, SUM(total) as total_spent
            FROM orders WHERE payment_status = 'paid'
            GROUP BY customer_id
        ) order_stats ON order_stats.customer_id = c.id
        WHERE order_stats.total_orders IS NOT NULL
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $topCustomers = $stmt->fetchAll();
} catch (Exception $e) {
    $topCustomers = [];
}

// Customer type distribution
$customerTypeDist = [];
try {
    $stmt = $db->query("
        SELECT user_type, COUNT(*) as count
        FROM customers
        WHERE is_active = 1
        GROUP BY user_type
        ORDER BY count DESC
    ");
    while ($row = $stmt->fetch()) {
        $customerTypeDist[$row['user_type']] = (int)$row['count'];
    }
} catch (Exception $e) {
    $customerTypeDist = [];
}

// ========= ORDER STATUS DATA =========
$statusCounts = getOrderStatusCounts();

// Payment method distribution
$paymentMethods = [];
try {
    $stmt = $db->query("
        SELECT COALESCE(payment_method, 'N/A') as method, COUNT(*) as count
        FROM orders
        GROUP BY payment_method
        ORDER BY count DESC
    ");
    while ($row = $stmt->fetch()) {
        $paymentMethods[$row['method']] = (int)$row['count'];
    }
} catch (Exception $e) {
    $paymentMethods = [];
}

// Average delivery time
$avgDeliveryTime = 'N/A';
try {
    $stmt = $db->query("
        SELECT AVG(DATEDIFF(delivered_at, created_at)) as avg_days
        FROM orders
        WHERE status = 'delivered' AND delivered_at IS NOT NULL
    ");
    $result = $stmt->fetch();
    if ($result && $result['avg_days'] !== null) {
        $avgDeliveryTime = round($result['avg_days'], 1) . ' days';
    }
} catch (Exception $e) {
    $avgDeliveryTime = 'N/A';
}

// Total delivered orders
$totalDelivered = $statusCounts['delivered'] ?? 0;

// Total cancelled
$totalCancelled = $statusCounts['cancelled'] ?? 0;

$csrf = generateCSRF();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        /* Reports-specific styles */
        .report-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .report-controls .control-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .report-controls label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
            white-space: nowrap;
        }

        .report-controls input[type="date"],
        .report-controls select {
            padding: 7px 12px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 12px;
            font-family: inherit;
            transition: var(--transition);
        }

        .report-controls input[type="date"]:focus,
        .report-controls select:focus {
            outline: none;
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(212, 168, 83, 0.1);
        }

        .report-controls input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(0.7);
            cursor: pointer;
        }

        /* Tab pills style */
        .report-tabs {
            display: flex;
            gap: 4px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 6px;
            margin-bottom: 24px;
            overflow-x: auto;
        }

        .report-tab {
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .report-tab:hover {
            background: rgba(212, 168, 83, 0.06);
            color: var(--text-primary);
        }

        .report-tab.active {
            background: var(--gold-gradient);
            color: #0a0a0f;
            border-color: var(--gold-primary);
        }

        .report-tab i {
            font-size: 14px;
        }

        /* Tab content */
        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        /* Period selector pills */
        .period-selector {
            display: flex;
            gap: 4px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 3px;
        }

        .period-btn {
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            background: transparent;
            cursor: pointer;
            transition: var(--transition);
        }

        .period-btn:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.04);
        }

        .period-btn.active {
            background: var(--gold-gradient);
            color: #0a0a0f;
        }

        /* Chart wrapper */
        .chart-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .chart-card .chart-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .chart-card .chart-header h3 {
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-card .chart-header h3 i {
            color: var(--gold-primary);
        }

        .chart-card .chart-body {
            padding: 20px;
        }

        /* Top products list */
        .top-product-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .top-product-item:last-child {
            border-bottom: none;
        }

        .top-product-item:hover {
            background: rgba(212, 168, 83, 0.03);
            margin: 0 -20px;
            padding-left: 20px;
            padding-right: 20px;
        }

        .top-product-rank {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .top-product-rank.gold {
            background: var(--gold-gradient);
            color: #0a0a0f;
        }

        .top-product-rank.silver {
            background: linear-gradient(135deg, #c0c0c0, #e8e8e8, #c0c0c0);
            color: #333;
        }

        .top-product-rank.bronze {
            background: linear-gradient(135deg, #cd7f32, #e8a862, #cd7f32);
            color: #fff;
        }

        .top-product-rank.default {
            background: var(--bg-input);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }

        .top-product-img {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .top-product-info {
            flex: 1;
            min-width: 0;
        }

        .top-product-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .top-product-sold {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .top-product-revenue {
            font-size: 14px;
            font-weight: 700;
            color: var(--gold-primary);
            white-space: nowrap;
        }

        /* Sortable table headers */
        .sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 18px;
        }

        .sortable:hover {
            color: var(--gold-primary);
        }

        .sortable::after {
            content: '\f0dc';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 10px;
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .sortable.asc::after {
            content: '\f0de';
            color: var(--gold-primary);
        }

        .sortable.desc::after {
            content: '\f0dd';
            color: var(--gold-primary);
        }

        /* Stock status badges */
        .stock-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .stock-badge.in-stock {
            background: var(--success-bg);
            color: var(--success);
        }

        .stock-badge.low-stock {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .stock-badge.out-of-stock {
            background: var(--danger-bg);
            color: var(--danger);
        }

        /* Customer top list */
        .customer-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .customer-item:last-child {
            border-bottom: none;
        }

        .customer-avatar-lg {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gold-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            color: #0a0a0f;
            flex-shrink: 0;
        }

        .customer-item-info {
            flex: 1;
            min-width: 0;
        }

        .customer-item-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .customer-item-meta {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .customer-item-spent {
            text-align: right;
        }

        .customer-item-amount {
            font-size: 14px;
            font-weight: 700;
            color: var(--gold-primary);
        }

        .customer-item-orders {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Stat metric card for order status */
        .metric-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            transition: var(--transition);
        }

        .metric-card:hover {
            border-color: var(--gold-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .metric-card .metric-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin: 0 auto 14px;
        }

        .metric-card .metric-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .metric-card .metric-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        /* Two column layout for charts */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .charts-grid-full {
            margin-bottom: 24px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .report-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .report-controls .control-group {
                flex-wrap: wrap;
            }

            .report-controls input[type="date"],
            .report-controls select {
                width: 100%;
            }

            .report-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .report-tab {
                flex-shrink: 0;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">

    <!-- Sidebar + Header included via layout.php -->

    
        <div class="page-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator">/</span>
                <span>Reports</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-chart-line" style="color:var(--gold-primary);margin-right:8px"></i>Reports</h1>
                    <p class="subtitle">Comprehensive analytics and performance insights</p>
                </div>
                <div class="report-controls">
                    <div class="control-group">
                        <label>From</label>
                        <input type="date" id="dateFrom" value="<?php echo clean($dateFrom); ?>">
                    </div>
                    <div class="control-group">
                        <label>To</label>
                        <input type="date" id="dateTo" value="<?php echo clean($dateTo); ?>">
                    </div>
                    <button class="btn btn-secondary btn-sm" onclick="applyDateFilter()">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="clearDateFilter()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="exportReport()">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
            </div>

            <!-- Report Tabs -->
            <div class="report-tabs">
                <a href="javascript:void(0)" class="report-tab <?php echo $activeTab === 'sales' ? 'active' : ''; ?>" onclick="switchTab('sales')" data-tab="sales">
                    <i class="fas fa-chart-area"></i> Sales Overview
                </a>
                <a href="javascript:void(0)" class="report-tab <?php echo $activeTab === 'products' ? 'active' : ''; ?>" onclick="switchTab('products')" data-tab="products">
                    <i class="fas fa-box-open"></i> Product Performance
                </a>
                <a href="javascript:void(0)" class="report-tab <?php echo $activeTab === 'customers' ? 'active' : ''; ?>" onclick="switchTab('customers')" data-tab="customers">
                    <i class="fas fa-users"></i> Customer Analytics
                </a>
                <a href="javascript:void(0)" class="report-tab <?php echo $activeTab === 'orders' ? 'active' : ''; ?>" onclick="switchTab('orders')" data-tab="orders">
                    <i class="fas fa-shipping-fast"></i> Order Status
                </a>
            </div>

            <!-- ===================== SALES OVERVIEW TAB ===================== -->
            <div class="tab-panel <?php echo $activeTab === 'sales' ? 'active' : ''; ?>" id="tab-sales">
                <!-- Summary Cards -->
                <div class="stats-grid">
                    <div class="stat-card gold">
                        <div class="stat-icon"><i class="fas fa-indian-rupee-sign"></i></div>
                        <div class="stat-value"><?php echo formatIndianPrice($totalRevenue); ?></div>
                        <div class="stat-label">Total Revenue</div>
                        <?php if ($revChange != 0): ?>
                            <div class="stat-change <?php echo $revChange > 0 ? 'up' : 'down'; ?>">
                                <i class="fas fa-arrow-<?php echo $revChange > 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($revChange); ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                        <div class="stat-value"><?php echo number_format($totalOrders); ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                        <div class="stat-value"><?php echo formatIndianPrice($avgOrderValue); ?></div>
                        <div class="stat-label">Avg Order Value</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-icon"><i class="fas fa-undo"></i></div>
                        <div class="stat-value"><?php echo formatIndianPrice($totalRefunds); ?></div>
                        <div class="stat-label">Refunds</div>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="chart-card charts-grid-full">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-line"></i> Revenue Trend</h3>
                        <div class="period-selector">
                            <button class="period-btn <?php echo $days === 30 ? 'active' : ''; ?>" onclick="changePeriod(30)">30 Days</button>
                            <button class="period-btn <?php echo $days === 60 ? 'active' : ''; ?>" onclick="changePeriod(60)">60 Days</button>
                            <button class="period-btn <?php echo $days === 90 ? 'active' : ''; ?>" onclick="changePeriod(90)">90 Days</button>
                        </div>
                    </div>
                    <div class="chart-body">
                        <div class="chart-container" style="height:350px">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top 5 Selling Products -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-trophy"></i> Top 5 Selling Products</h3>
                    </div>
                    <div class="chart-body">
                        <?php if (empty($topProducts)): ?>
                            <div class="empty-state" style="padding:30px">
                                <i class="fas fa-box-open"></i>
                                <h3>No sales data yet</h3>
                                <p>Top products will appear here once orders are placed.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($topProducts as $index => $product): ?>
                                <?php
                                    $rankClass = 'default';
                                    if ($index === 0) $rankClass = 'gold';
                                    elseif ($index === 1) $rankClass = 'silver';
                                    elseif ($index === 2) $rankClass = 'bronze';
                                ?>
                                <div class="top-product-item">
                                    <div class="top-product-rank <?php echo $rankClass; ?>"><?php echo $index + 1; ?></div>
                                    <?php if ($product['main_image']): ?>
                                        <img src="<?php echo SITE_URL . '/' . $product['main_image']; ?>" alt="<?php echo clean($product['name']); ?>" class="top-product-img">
                                    <?php else: ?>
                                        <div class="top-product-img" style="display:flex;align-items:center;justify-content:center;color:var(--text-muted)">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="top-product-info">
                                        <div class="top-product-name"><?php echo clean($product['name']); ?></div>
                                        <div class="top-product-sold"><?php echo number_format((int)$product['total_sold']); ?> units sold</div>
                                    </div>
                                    <div class="top-product-revenue"><?php echo formatIndianPrice($product['total_revenue']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ===================== PRODUCT PERFORMANCE TAB ===================== -->
            <div class="tab-panel <?php echo $activeTab === 'products' ? 'active' : ''; ?>" id="tab-products">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-box-open" style="color:var(--gold-primary);margin-right:8px"></i>
                            Product Performance
                            <span style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:8px">(<?php echo count($productPerformance); ?> products)</span>
                        </h3>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table" id="productTable">
                            <thead>
                                <tr>
                                    <th class="sortable <?php echo $sortCol === 'name' ? $sortDir : ''; ?>" onclick="sortProductTable('name')">
                                        Product
                                    </th>
                                    <th class="sortable <?php echo $sortCol === 'units_sold' ? $sortDir : ''; ?>" onclick="sortProductTable('units_sold')">
                                        Units Sold
                                    </th>
                                    <th class="sortable <?php echo $sortCol === 'revenue' ? $sortDir : ''; ?>" onclick="sortProductTable('revenue')">
                                        Revenue
                                    </th>
                                    <th class="sortable <?php echo $sortCol === 'avg_rating' ? $sortDir : ''; ?>" onclick="sortProductTable('avg_rating')">
                                        Avg Rating
                                    </th>
                                    <th class="sortable <?php echo $sortCol === 'stock' ? $sortDir : ''; ?>" onclick="sortProductTable('stock')">
                                        Stock Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($productPerformance)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;padding:48px">
                                            <div style="color:var(--text-muted)">
                                                <i class="fas fa-box-open" style="font-size:36px;margin-bottom:12px;display:block"></i>
                                                <h3 style="font-size:16px;color:var(--text-secondary);margin-bottom:4px">No products found</h3>
                                                <p style="font-size:13px">Products will appear here once added to the catalog.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($productPerformance as $product): ?>
                                        <?php
                                            $stockStatus = 'in-stock';
                                            $stockLabel = 'In Stock';
                                            $stockIcon = 'fa-check-circle';
                                            if ($product['stock'] <= 0) {
                                                $stockStatus = 'out-of-stock';
                                                $stockLabel = 'Out of Stock';
                                                $stockIcon = 'fa-times-circle';
                                            } elseif ($product['stock'] <= $product['low_stock_threshold']) {
                                                $stockStatus = 'low-stock';
                                                $stockLabel = 'Low Stock (' . $product['stock'] . ')';
                                                $stockIcon = 'fa-exclamation-triangle';
                                            }

                                            $ratingStars = '';
                                            $rating = (float)$product['rating'];
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= floor($rating)) {
                                                    $ratingStars .= '<i class="fas fa-star" style="color:var(--gold-primary);font-size:11px"></i>';
                                                } elseif ($i - 0.5 <= $rating) {
                                                    $ratingStars .= '<i class="fas fa-star-half-alt" style="color:var(--gold-primary);font-size:11px"></i>';
                                                } else {
                                                    $ratingStars .= '<i class="far fa-star" style="color:var(--text-muted);font-size:11px"></i>';
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="product-cell">
                                                    <?php if ($product['main_image']): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $product['main_image']; ?>" alt="<?php echo clean($product['name']); ?>" class="product-img">
                                                    <?php else: ?>
                                                        <div class="product-img" style="display:flex;align-items:center;justify-content:center;color:var(--text-muted)">
                                                            <i class="fas fa-image"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="product-name"><?php echo clean($product['name']); ?></div>
                                                        <?php if ($product['sku']): ?>
                                                            <div class="product-sku">SKU: <?php echo clean($product['sku']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="font-weight:700;color:var(--text-primary)"><?php echo number_format((int)$product['units_sold']); ?></span>
                                            </td>
                                            <td>
                                                <span style="font-weight:700;color:var(--gold-primary)"><?php echo formatIndianPrice($product['total_revenue']); ?></span>
                                            </td>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:6px">
                                                    <?php echo $ratingStars; ?>
                                                    <span style="font-size:12px;color:var(--text-muted)">(<?php echo number_format($rating, 1); ?>)</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="stock-badge <?php echo $stockStatus; ?>">
                                                    <i class="fas <?php echo $stockIcon; ?>"></i>
                                                    <?php echo $stockLabel; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ===================== CUSTOMER ANALYTICS TAB ===================== -->
            <div class="tab-panel <?php echo $activeTab === 'customers' ? 'active' : ''; ?>" id="tab-customers">
                <!-- New vs Returning Chart + Pie Chart side by side -->
                <div class="charts-grid">
                    <!-- New vs Returning Trend -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3><i class="fas fa-user-plus"></i> New vs Returning Customers</h3>
                        </div>
                        <div class="chart-body">
                            <div class="chart-container" style="height:300px">
                                <canvas id="customerTrendChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Type Distribution -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3><i class="fas fa-pie-chart"></i> Customer Type Distribution</h3>
                        </div>
                        <div class="chart-body">
                            <div class="chart-container" style="height:300px">
                                <canvas id="customerTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Customers -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-crown"></i> Top Customers by Spending</h3>
                    </div>
                    <div class="chart-body">
                        <?php if (empty($topCustomers)): ?>
                            <div class="empty-state" style="padding:30px">
                                <i class="fas fa-users"></i>
                                <h3>No customer data yet</h3>
                                <p>Top customers will appear here once they place orders.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($topCustomers as $index => $customer): ?>
                                <div class="customer-item">
                                    <div class="customer-avatar-lg">
                                        <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                                    </div>
                                    <div class="customer-item-info">
                                        <div class="customer-item-name"><?php echo clean($customer['name']); ?></div>
                                        <div class="customer-item-meta">
                                            <?php echo clean($customer['email']); ?>
                                            <?php if ($customer['city']): ?>
                                                &middot; <?php echo clean($customer['city']); ?>
                                            <?php endif; ?>
                                            <span class="badge badge-primary" style="margin-left:6px"><?php echo ucfirst(clean($customer['user_type'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="customer-item-spent">
                                        <div class="customer-item-amount"><?php echo formatIndianPrice($customer['total_spent']); ?></div>
                                        <div class="customer-item-orders"><?php echo (int)$customer['total_orders']; ?> order<?php echo $customer['total_orders'] != 1 ? 's' : ''; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ===================== ORDER STATUS TAB ===================== -->
            <div class="tab-panel <?php echo $activeTab === 'orders' ? 'active' : ''; ?>" id="tab-orders">
                <!-- Metric cards -->
                <div class="stats-grid" style="margin-bottom:24px">
                    <div class="metric-card">
                        <div class="metric-icon" style="background:var(--success-bg);color:var(--success)">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="metric-value"><?php echo number_format($totalDelivered); ?></div>
                        <div class="metric-label">Delivered Orders</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon" style="background:var(--danger-bg);color:var(--danger)">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="metric-value"><?php echo number_format($totalCancelled); ?></div>
                        <div class="metric-label">Cancelled Orders</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon" style="background:rgba(212,168,83,0.15);color:var(--gold-primary)">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="metric-value"><?php echo $avgDeliveryTime; ?></div>
                        <div class="metric-label">Avg Delivery Time</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon" style="background:var(--info-bg);color:var(--info)">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="metric-value">
                            <?php
                                $totalAll = array_sum($statusCounts);
                                echo $totalAll > 0 ? round(($totalDelivered / $totalAll) * 100, 1) . '%' : '0%';
                            ?>
                        </div>
                        <div class="metric-label">Delivery Rate</div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="charts-grid">
                    <!-- Order Status Distribution -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3><i class="fas fa-circle-notch"></i> Order Status Distribution</h3>
                        </div>
                        <div class="chart-body">
                            <div class="chart-container" style="height:320px">
                                <canvas id="orderStatusChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method Distribution -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3><i class="fas fa-credit-card"></i> Payment Method Distribution</h3>
                        </div>
                        <div class="chart-body">
                            <div class="chart-container" style="height:320px">
                                <canvas id="paymentMethodChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- end page-content -->
    </main>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
// ============================================
// GLOBAL CHART THEME CONFIG
// ============================================
const chartColors = {
    gold: '#d4a853',
    goldLight: '#e8c97a',
    success: '#2ecc71',
    danger: '#e74c3c',
    warning: '#f1c40f',
    info: '#3498db',
    purple: '#9b59b6',
    dark: '#6b6b85',
    bgCard: '#1a1a2e',
    border: '#2a2a4a',
    textPrimary: '#f0f0f5',
    textMuted: '#6b6b85',
    goldAlpha20: 'rgba(212, 168, 83, 0.2)',
    goldAlpha40: 'rgba(212, 168, 83, 0.4)',
    goldAlpha60: 'rgba(212, 168, 83, 0.6)',
};

Chart.defaults.color = chartColors.textMuted;
Chart.defaults.borderColor = chartColors.border;
Chart.defaults.font.family = "'Inter', 'Segoe UI', -apple-system, sans-serif";

// ============================================
// TAB SWITCHING
// ============================================
function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.report-tab').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.report-tab[data-tab="' + tabName + '"]').classList.add('active');

    // Update tab panels
    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');

    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.replaceState({}, '', url);

    // Re-render charts for the active tab (canvas sizing fix)
    setTimeout(() => {
        if (tabName === 'sales' && window.revenueChartInstance) {
            window.revenueChartInstance.resize();
        }
        if (tabName === 'customers') {
            if (window.customerTrendChartInstance) window.customerTrendChartInstance.resize();
            if (window.customerTypeChartInstance) window.customerTypeChartInstance.resize();
        }
        if (tabName === 'orders') {
            if (window.orderStatusChartInstance) window.orderStatusChartInstance.resize();
            if (window.paymentMethodChartInstance) window.paymentMethodChartInstance.resize();
        }
    }, 100);
}

// ============================================
// DATE FILTER & NAVIGATION
// ============================================
function applyDateFilter() {
    const url = new URL(window.location);
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;
    if (from) url.searchParams.set('date_from', from);
    else url.searchParams.delete('date_from');
    if (to) url.searchParams.set('date_to', to);
    else url.searchParams.delete('date_to');
    url.searchParams.delete('days');
    window.location = url.toString();
}

function clearDateFilter() {
    const url = new URL(window.location);
    url.searchParams.delete('date_from');
    url.searchParams.delete('date_to');
    url.searchParams.delete('days');
    window.location = url.toString();
}

function changePeriod(d) {
    const url = new URL(window.location);
    url.searchParams.set('days', d);
    url.searchParams.delete('date_from');
    url.searchParams.delete('date_to');
    window.location = url.toString();
}

// ============================================
// SORTING
// ============================================
function sortProductTable(column) {
    const url = new URL(window.location);
    const currentSort = url.searchParams.get('sort') || 'units_sold';
    const currentDir = url.searchParams.get('dir') || 'desc';
    const newDir = (currentSort === column && currentDir === 'desc') ? 'asc' : 'desc';
    url.searchParams.set('sort', column);
    url.searchParams.set('dir', newDir);
    url.searchParams.set('tab', 'products');
    window.location = url.toString();
}

// ============================================
// CSV EXPORT
// ============================================
function exportReport() {
    const activeTabEl = document.querySelector('.report-tab.active');
    const reportType = activeTabEl ? activeTabEl.dataset.tab : 'sales';
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;
    const urlParams = new URLSearchParams(window.location.search);
    const days = urlParams.get('days') || 30;

    let url = 'reports.php?export=csv&report_type=' + reportType;
    if (from) url += '&date_from=' + from;
    if (to) url += '&date_to=' + to;
    if (!from && !to) url += '&days=' + days;

    window.location = url;
}

// ============================================
// CHARTS INITIALIZATION
// ============================================

// ---------- Revenue Chart (Sales Overview) ----------
(function() {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    const chartData = <?php echo json_encode($chartData); ?>;

    const labels = chartData.map(d => {
        const date = new Date(d.date);
        return date.toLocaleDateString('en-IN', { month: 'short', day: 'numeric' });
    });
    const revenueData = chartData.map(d => parseFloat(d.revenue));
    const orderData = chartData.map(d => parseInt(d.orders));

    window.revenueChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Revenue (₹)',
                    data: revenueData,
                    borderColor: chartColors.gold,
                    backgroundColor: (context) => {
                        const chart = context.chart;
                        const {ctx: c, chartArea} = chart;
                        if (!chartArea) return chartColors.goldAlpha20;
                        const gradient = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        gradient.addColorStop(0, 'rgba(212, 168, 83, 0.35)');
                        gradient.addColorStop(1, 'rgba(212, 168, 83, 0.02)');
                        return gradient;
                    },
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointBackgroundColor: chartColors.gold,
                    pointBorderColor: chartColors.gold,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: chartColors.goldLight,
                    pointHoverBorderWidth: 2,
                    yAxisID: 'y'
                },
                {
                    label: 'Orders',
                    data: orderData,
                    borderColor: chartColors.purple,
                    backgroundColor: 'rgba(155, 89, 182, 0.1)',
                    fill: false,
                    tension: 0.4,
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointRadius: 2,
                    pointBackgroundColor: chartColors.purple,
                    pointHoverRadius: 5,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        color: chartColors.textMuted,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(26, 26, 46, 0.95)',
                    borderColor: chartColors.border,
                    borderWidth: 1,
                    titleColor: chartColors.textPrimary,
                    bodyColor: chartColors.textMuted,
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 0) {
                                return ' Revenue: ₹' + context.parsed.y.toLocaleString('en-IN');
                            }
                            return ' Orders: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(42, 42, 74, 0.4)',
                        drawBorder: false
                    },
                    ticks: {
                        color: chartColors.textMuted,
                        font: { size: 11 },
                        maxRotation: 45,
                        maxTicksLimit: 15
                    }
                },
                y: {
                    position: 'left',
                    grid: {
                        color: 'rgba(42, 42, 74, 0.4)',
                        drawBorder: false
                    },
                    ticks: {
                        color: chartColors.textMuted,
                        font: { size: 11 },
                        callback: function(value) {
                            if (value >= 100000) return '₹' + (value / 100000).toFixed(1) + 'L';
                            if (value >= 1000) return '₹' + (value / 1000).toFixed(1) + 'K';
                            return '₹' + value;
                        }
                    }
                },
                y1: {
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        color: chartColors.purple,
                        font: { size: 11 }
                    }
                }
            }
        }
    });
})();

// ---------- Customer Trend Chart ----------
(function() {
    const ctx = document.getElementById('customerTrendChart');
    if (!ctx) return;

    const trendData = <?php echo json_encode($customerTrend); ?>;

    const labels = trendData.map(d => {
        const [year, month] = d.month.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleDateString('en-IN', { month: 'short', year: '2-digit' });
    });
    const newData = trendData.map(d => parseInt(d.new_customers || 0));
    const returningData = trendData.map(d => parseInt(d.returning_customers || 0));

    // If no data, create placeholder months
    if (labels.length === 0) {
        const now = new Date();
        for (let i = 5; i >= 0; i--) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            labels.push(d.toLocaleDateString('en-IN', { month: 'short', year: '2-digit' }));
            newData.push(0);
            returningData.push(0);
        }
    }

    window.customerTrendChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'New Customers',
                    data: newData,
                    backgroundColor: 'rgba(212, 168, 83, 0.7)',
                    borderColor: chartColors.gold,
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.7
                },
                {
                    label: 'Returning Customers',
                    data: returningData,
                    backgroundColor: 'rgba(155, 89, 182, 0.7)',
                    borderColor: chartColors.purple,
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 16,
                        color: chartColors.textMuted,
                        font: { size: 11 }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(26, 26, 46, 0.95)',
                    borderColor: chartColors.border,
                    borderWidth: 1,
                    titleColor: chartColors.textPrimary,
                    bodyColor: chartColors.textMuted,
                    padding: 10,
                    cornerRadius: 8
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(42, 42, 74, 0.4)', drawBorder: false },
                    ticks: { color: chartColors.textMuted, font: { size: 11 } }
                },
                y: {
                    grid: { color: 'rgba(42, 42, 74, 0.4)', drawBorder: false },
                    ticks: { color: chartColors.textMuted, font: { size: 11 }, stepSize: 1 },
                    beginAtZero: true
                }
            }
        }
    });
})();

// ---------- Customer Type Pie Chart ----------
(function() {
    const ctx = document.getElementById('customerTypeChart');
    if (!ctx) return;

    const typeData = <?php echo json_encode($customerTypeDist); ?>;
    const typeLabels = Object.keys(typeData).map(k => k.charAt(0).toUpperCase() + k.slice(1));
    const typeValues = Object.values(typeData);

    const pieColors = [
        chartColors.gold,
        chartColors.purple,
        chartColors.info,
        chartColors.success,
        chartColors.warning
    ];

    // If no data, show placeholder
    if (typeLabels.length === 0) {
        typeLabels.push('No Data');
        typeValues.push(1);
    }

    window.customerTypeChartInstance = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: typeLabels,
            datasets: [{
                data: typeValues,
                backgroundColor: pieColors.slice(0, typeLabels.length),
                borderColor: chartColors.bgCard,
                borderWidth: 3,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 16,
                        color: chartColors.textMuted,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(26, 26, 46, 0.95)',
                    borderColor: chartColors.border,
                    borderWidth: 1,
                    titleColor: chartColors.textPrimary,
                    bodyColor: chartColors.textMuted,
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return ' ' + context.label + ': ' + context.parsed + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
})();

// ---------- Order Status Doughnut Chart ----------
(function() {
    const ctx = document.getElementById('orderStatusChart');
    if (!ctx) return;

    const statusData = <?php echo json_encode($statusCounts); ?>;

    const statusLabels = Object.keys(statusData).map(k => k.charAt(0).toUpperCase() + k.slice(1));
    const statusValues = Object.values(statusData);

    const statusColors = {
        Pending: chartColors.warning,
        Confirmed: chartColors.info,
        Processing: chartColors.gold,
        Shipped: chartColors.purple,
        Delivered: chartColors.success,
        Cancelled: chartColors.danger,
        Returned: '#6b6b85'
    };

    const colors = Object.keys(statusData).map(k => statusColors[k.charAt(0).toUpperCase() + k.slice(1)] || chartColors.dark);

    if (statusLabels.length === 0) {
        statusLabels.push('No Orders');
        statusValues.push(1);
        colors.push(chartColors.dark);
    }

    window.orderStatusChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusValues,
                backgroundColor: colors,
                borderColor: chartColors.bgCard,
                borderWidth: 3,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 14,
                        color: chartColors.textMuted,
                        font: { size: 11 }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(26, 26, 46, 0.95)',
                    borderColor: chartColors.border,
                    borderWidth: 1,
                    titleColor: chartColors.textPrimary,
                    bodyColor: chartColors.textMuted,
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return ' ' + context.label + ': ' + context.parsed + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
})();

// ---------- Payment Method Doughnut Chart ----------
(function() {
    const ctx = document.getElementById('paymentMethodChart');
    if (!ctx) return;

    const paymentData = <?php echo json_encode($paymentMethods); ?>;

    const payLabels = Object.keys(paymentData).map(k => {
        if (k === 'razorpay') return 'Razorpay';
        if (k === 'cod') return 'Cash on Delivery';
        if (k === 'bank_transfer') return 'Bank Transfer';
        if (k === 'upi') return 'UPI';
        if (k === 'N/A' || k === '' || k === null) return 'Unknown';
        return k.charAt(0).toUpperCase() + k.slice(1).replace(/_/g, ' ');
    });
    const payValues = Object.values(paymentData);

    const payColors = [chartColors.gold, chartColors.success, chartColors.info, chartColors.purple, chartColors.warning, chartColors.danger];

    if (payLabels.length === 0) {
        payLabels.push('No Data');
        payValues.push(1);
    }

    window.paymentMethodChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: payLabels,
            datasets: [{
                data: payValues,
                backgroundColor: payColors.slice(0, payLabels.length),
                borderColor: chartColors.bgCard,
                borderWidth: 3,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 14,
                        color: chartColors.textMuted,
                        font: { size: 11 }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(26, 26, 46, 0.95)',
                    borderColor: chartColors.border,
                    borderWidth: 1,
                    titleColor: chartColors.textPrimary,
                    bodyColor: chartColors.textMuted,
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return ' ' + context.label + ': ' + context.parsed + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
})();

// ============================================
// CLOSE DROPDOWNS ON OUTSIDE CLICK
// ============================================
document.addEventListener('click', function(e) {
    if (!e.target.closest('.status-dropdown-wrap')) {
        document.querySelectorAll('.status-dropdown').forEach(dd => dd.classList.remove('show'));
    }
});
</script>

</body>
</html>
