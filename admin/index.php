<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Admin Dashboard - DesiVastra E-Commerce
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="admin-layout">

<?php require_once __DIR__ . '/includes/layout.php'; ?>



<div class="page-content">

    <!-- Flash Messages -->
    <?php
    $flash = getFlash();
    if ($flash):
    ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
            <?php echo clean($flash['message']); ?>
            <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- 1. PAGE HEADER WITH BREADCRUMB               -->
    <!-- ============================================ -->
    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>Dashboard</span>
            </div>
            <h1>Dashboard</h1>
            <p class="subtitle">Welcome back! Here's an overview of your store performance.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="reports.php" class="btn btn-secondary">
                <i class="fas fa-chart-line"></i> View Reports
            </a>
            <a href="product-form.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Product
            </a>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 2. STATS CARDS (6 Cards)                     -->
    <!-- ============================================ -->
    <?php
    // Fetch dashboard stats with graceful error handling
    $stats = [
        'total_revenue'   => 0,
        'today_revenue'   => 0,
        'total_orders'    => 0,
        'pending_orders'  => 0,
        'total_products'  => 0,
        'total_customers' => 0,
        'low_stock'       => 0,
        'total_coupons'   => 0,
    ];

    try {
        $stats = getDashboardStats();
    } catch (Exception $e) {
        // Database not set up yet - use defaults
    }
    ?>

    <div class="stats-grid">
        <!-- Total Revenue - Gold -->
        <div class="stat-card gold">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-value"><?php echo formatIndianPrice($stats['total_revenue'] ?? 0); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>

        <!-- Today's Revenue - Success -->
        <div class="stat-card success">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-value"><?php echo formatIndianPrice($stats['today_revenue'] ?? 0); ?></div>
            <div class="stat-label">Today's Revenue</div>
        </div>

        <!-- Total Orders - Info -->
        <div class="stat-card info">
            <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-value"><?php echo number_format($stats['total_orders'] ?? 0); ?></div>
            <div class="stat-label">Total Orders</div>
            <?php if (($stats['pending_orders'] ?? 0) > 0): ?>
                <div class="stat-change up"><i class="fas fa-clock"></i> <?php echo $stats['pending_orders']; ?> pending</div>
            <?php endif; ?>
        </div>

        <!-- Total Products - Purple -->
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-box-open"></i></div>
            <div class="stat-value"><?php echo number_format($stats['total_products'] ?? 0); ?></div>
            <div class="stat-label">Total Products</div>
        </div>

        <!-- Total Customers - Warning -->
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?php echo number_format($stats['total_customers'] ?? 0); ?></div>
            <div class="stat-label">Total Customers</div>
        </div>

        <!-- Low Stock Alert - Danger -->
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-value"><?php echo number_format($stats['low_stock'] ?? 0); ?></div>
            <div class="stat-label">Low Stock Alert</div>
            <?php if (($stats['low_stock'] ?? 0) > 0): ?>
                <div class="stat-change down"><i class="fas fa-arrow-down"></i> Needs attention</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 3. SALES CHART (Last 30 Days)                -->
    <!-- ============================================ -->
    <?php
    $chartData = [];
    try {
        $chartData = getSalesChartData(30);
    } catch (Exception $e) {
        // Database not set up yet - empty chart
    }

    // Build chart arrays — fill in missing dates with zero
    $chartLabels = [];
    $chartRevenue = [];
    $chartOrders = [];

    if (!empty($chartData)) {
        // Create a map of date => data
        $dataMap = [];
        foreach ($chartData as $row) {
            $dataMap[$row['date']] = $row;
        }

        // Fill last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $chartLabels[] = date('d M', strtotime($date));
            $chartRevenue[] = isset($dataMap[$date]) ? (float)$dataMap[$date]['revenue'] : 0;
            $chartOrders[]  = isset($dataMap[$date]) ? (int)$dataMap[$date]['orders'] : 0;
        }
    } else {
        // No data — show empty chart with last 30 day labels
        for ($i = 29; $i >= 0; $i--) {
            $chartLabels[] = date('d M', strtotime("-{$i} days"));
            $chartRevenue[] = 0;
            $chartOrders[]  = 0;
        }
    }
    ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px;" class="dashboard-grid">
        <!-- Sales Chart Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-area" style="color:var(--gold-primary);margin-right:8px;"></i> Sales Overview (Last 30 Days)</h3>
                <select id="chartPeriod" class="form-control" style="width:auto;padding:6px 32px 6px 12px;font-size:12px;" onchange="updateChart()">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                </select>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt" style="color:var(--gold-primary);margin-right:8px;"></i> Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="product-form.php" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Product</span>
                    </a>
                    <a href="coupons.php" class="quick-action-btn">
                        <i class="fas fa-percent"></i>
                        <span>Add Coupon</span>
                    </a>
                    <a href="orders.php" class="quick-action-btn">
                        <i class="fas fa-shopping-bag"></i>
                        <span>View Orders</span>
                    </a>
                    <a href="reports.php" class="quick-action-btn">
                        <i class="fas fa-chart-bar"></i>
                        <span>View Reports</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 4. RECENT ORDERS TABLE                       -->
    <!-- ============================================ -->
    <?php
    $recentOrders = [];
    try {
        $recentOrders = getRecentOrders(8);
    } catch (Exception $e) {
        // Database not set up yet
    }
    ?>

    <div style="display:grid;grid-template-columns:1.2fr 0.8fr;gap:20px;margin-bottom:24px;" class="dashboard-grid">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-shopping-bag" style="color:var(--info);margin-right:8px;"></i> Recent Orders</h3>
                <a href="orders.php" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (!empty($recentOrders)): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>
                                    <a href="order-detail.php?id=<?php echo $order['id']; ?>" style="font-weight:600;color:var(--gold-primary);">
                                        <?php echo clean($order['order_number']); ?>
                                    </a>
                                </td>
                                <td><?php echo clean($order['customer_name'] ?? 'Guest'); ?></td>
                                <td style="font-weight:600;"><?php echo formatIndianPrice($order['total']); ?></td>
                                <td>
                                    <span class="badge <?php echo getStatusBadge($order['status']); ?>">
                                        <?php echo ucfirst(clean($order['status'])); ?>
                                    </span>
                                </td>
                                <td style="color:var(--text-muted);font-size:12px;"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state" style="padding:40px 20px;">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>No Orders Yet</h3>
                    <p>Orders will appear here once customers start placing them.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- 5. TOP SELLING PRODUCTS                      -->
        <!-- ============================================ -->
        <?php
        $topProducts = [];
        try {
            $topProducts = getTopSellingProducts(5);
        } catch (Exception $e) {
            // Database not set up yet
        }
        ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-fire" style="color:var(--danger);margin-right:8px;"></i> Top Selling Products</h3>
                <a href="products.php" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (!empty($topProducts)): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $idx => $product): ?>
                            <tr>
                                <td>
                                    <div class="product-cell">
                                        <?php if (!empty($product['main_image'])): ?>
                                        <img src="<?php echo SITE_URL . '/' . clean($product['main_image']); ?>" alt="<?php echo clean($product['name']); ?>" class="product-img">
                                        <?php else: ?>
                                        <div class="product-img" style="display:flex;align-items:center;justify-content:center;background:var(--bg-secondary);">
                                            <i class="fas fa-image" style="color:var(--text-muted);font-size:16px;"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="product-name"><?php echo clean($product['name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight:600;color:var(--success);"><?php echo number_format($product['total_sold']); ?></span>
                                </td>
                                <td style="font-weight:600;"><?php echo formatIndianPrice($product['total_revenue']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state" style="padding:40px 20px;">
                    <i class="fas fa-fire"></i>
                    <h3>No Sales Data</h3>
                    <p>Top selling products will appear once orders are placed.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 7. RECENT ACTIVITY LOG                       -->
    <!-- ============================================ -->
    <?php
    $recentActivities = [];
    try {
        $db = getDB();
        $stmt = $db->query("
            SELECT al.*, a.name as admin_name
            FROM activity_log al
            LEFT JOIN admins a ON al.admin_id = a.id
            ORDER BY al.created_at DESC
            LIMIT 10
        ");
        $recentActivities = $stmt->fetchAll();
    } catch (Exception $e) {
        // Database not set up yet
    }

    // Map actions to icon types for CSS classes
    function getActivityIconClass($action) {
        $createActions = ['create', 'add', 'insert', 'upload'];
        $updateActions = ['update', 'edit', 'modify', 'change', 'toggle'];
        $deleteActions = ['delete', 'remove', 'trash'];
        $loginActions  = ['login', 'logout', 'auth'];

        $actionLower = strtolower($action);

        foreach ($createActions as $a) {
            if (strpos($actionLower, $a) !== false) return 'create';
        }
        foreach ($updateActions as $a) {
            if (strpos($actionLower, $a) !== false) return 'update';
        }
        foreach ($deleteActions as $a) {
            if (strpos($actionLower, $a) !== false) return 'delete';
        }
        foreach ($loginActions as $a) {
            if (strpos($actionLower, $a) !== false) return 'login';
        }

        return 'update'; // default
    }

    function getActivityIcon($action) {
        $icons = [
            'login'    => 'fa-sign-in-alt',
            'logout'   => 'fa-sign-out-alt',
            'create'   => 'fa-plus',
            'add'      => 'fa-plus',
            'update'   => 'fa-pen',
            'edit'     => 'fa-pen',
            'delete'   => 'fa-trash',
            'remove'   => 'fa-trash',
            'toggle'   => 'fa-toggle-on',
        ];
        $actionLower = strtolower($action);
        foreach ($icons as $key => $icon) {
            if (strpos($actionLower, $key) !== false) return $icon;
        }
        return 'fa-circle';
    }

    function describeActivity($row) {
        $action     = $row['action'];
        $entityType = $row['entity_type'] ?? '';
        $adminName  = $row['admin_name'] ?? 'System';
        $details    = $row['details'] ? json_decode($row['details'], true) : [];

        $entityLabel = ucfirst($entityType);
        $actionLabel = ucfirst($action);

        switch ($action) {
            case 'login':
                return "<strong>" . clean($adminName) . "</strong> logged in";
            case 'logout':
                return "<strong>" . clean($adminName) . "</strong> logged out";
            default:
                $text = "<strong>" . clean($adminName) . "</strong> performed <em>" . clean($actionLabel) . "</em>";
                if ($entityType) {
                    $text .= " on <strong>" . clean($entityLabel) . "</strong>";
                }
                if (isset($details['name'])) {
                    $text .= " — " . clean($details['name']);
                }
                return $text;
        }
    }
    ?>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history" style="color:var(--purple);margin-right:8px;"></i> Recent Activity</h3>
            <a href="activity.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body">
            <?php if (!empty($recentActivities)): ?>
                <?php foreach ($recentActivities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon <?php echo getActivityIconClass($activity['action']); ?>">
                        <i class="fas <?php echo getActivityIcon($activity['action']); ?>"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="activity-text"><?php echo describeActivity($activity); ?></div>
                        <div class="activity-time">
                            <i class="fas fa-clock" style="margin-right:4px;"></i>
                            <?php echo timeAgo($activity['created_at']); ?>
                            <?php if (!empty($activity['ip_address'])): ?>
                                <span style="margin-left:8px;color:var(--text-muted);"><i class="fas fa-globe" style="margin-right:2px;"></i><?php echo clean($activity['ip_address']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="padding:40px 20px;">
                    <i class="fas fa-history"></i>
                    <h3>No Activity Yet</h3>
                    <p>Admin activity will be logged here as you use the panel.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.page-content -->
</main><!-- /.main-content -->

</div><!-- /.admin-layout -->

<!-- ============================================ -->
<!-- CHART.JS INITIALIZATION                      -->
<!-- ============================================ -->
<script>
(function() {
    'use strict';

    const chartLabels = <?php echo json_encode($chartLabels); ?>;
    const chartRevenue = <?php echo json_encode($chartRevenue); ?>;
    const chartOrders = <?php echo json_encode($chartOrders); ?>;

    // Chart.js global defaults for dark theme
    Chart.defaults.color = '#9a9ab0';
    Chart.defaults.borderColor = 'rgba(42, 42, 74, 0.6)';
    Chart.defaults.font.family = "'Inter', 'Segoe UI', -apple-system, sans-serif";

    const ctx = document.getElementById('salesChart').getContext('2d');

    // Gradient for revenue area
    const revenueGradient = ctx.createLinearGradient(0, 0, 0, 300);
    revenueGradient.addColorStop(0, 'rgba(212, 168, 83, 0.3)');
    revenueGradient.addColorStop(1, 'rgba(212, 168, 83, 0.01)');

    // Gradient for orders area
    const ordersGradient = ctx.createLinearGradient(0, 0, 0, 300);
    ordersGradient.addColorStop(0, 'rgba(52, 152, 219, 0.2)');
    ordersGradient.addColorStop(1, 'rgba(52, 152, 219, 0.01)');

    window.salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [
                {
                    label: 'Revenue (₹)',
                    data: chartRevenue,
                    borderColor: '#d4a853',
                    backgroundColor: revenueGradient,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#d4a853',
                    pointBorderColor: '#0a0a0f',
                    pointBorderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#d4a853',
                    pointHoverBorderColor: '#fff',
                    yAxisID: 'y',
                },
                {
                    label: 'Orders',
                    data: chartOrders,
                    borderColor: '#3498db',
                    backgroundColor: ordersGradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#3498db',
                    pointBorderColor: '#0a0a0f',
                    pointBorderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#3498db',
                    pointHoverBorderColor: '#fff',
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 16,
                        font: { size: 12, weight: '500' },
                        color: '#9a9ab0',
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(18, 18, 26, 0.95)',
                    titleColor: '#f0f0f5',
                    bodyColor: '#9a9ab0',
                    borderColor: '#2a2a4a',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    titleFont: { size: 13, weight: '600' },
                    bodyFont: { size: 12 },
                    callbacks: {
                        label: function(context) {
                            if (context.dataset.label.includes('Revenue')) {
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
                        color: 'rgba(42, 42, 74, 0.3)',
                        drawBorder: false,
                    },
                    ticks: {
                        font: { size: 11 },
                        maxRotation: 0,
                        maxTicksLimit: 10,
                    }
                },
                y: {
                    position: 'left',
                    grid: {
                        color: 'rgba(42, 42, 74, 0.3)',
                        drawBorder: false,
                    },
                    ticks: {
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
                    grid: { drawOnChartArea: false },
                    ticks: {
                        font: { size: 11 },
                        stepSize: 1,
                    }
                }
            }
        }
    });
})();

/**
 * Update chart when period selector changes (reloads page with param)
 */
function updateChart() {
    const period = document.getElementById('chartPeriod').value;
    const url = new URL(window.location.href);
    url.searchParams.set('period', period);
    window.location.href = url.toString();
}
</script>

<!-- Responsive grid fix for dashboard layout -->
<style>
.dashboard-grid {
    display: grid;
    gap: 20px;
}

@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: 1fr !important;
    }
}

/* Stat card subtle pulse animation for low-stock danger */
.stat-card.danger .stat-value {
    animation: dangerPulse 2s ease-in-out infinite;
}

@keyframes dangerPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Smooth chart container */
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

/* Activity scroll for many items */
.card-body .activity-item:last-child {
    border-bottom: none;
}
</style>

</body>
</html>
