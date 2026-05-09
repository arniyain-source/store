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
    <style>
        .dashboard-grid { display: grid; gap: 24px; margin-bottom: 24px; }
        .grid-stats { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .grid-main { grid-template-columns: 2fr 1fr; }
        .grid-secondary { grid-template-columns: 1.2fr 0.8fr; }
        
        @media (max-width: 1024px) {
            .grid-main, .grid-secondary { grid-template-columns: 1fr; }
        }

        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); padding: 20px; border-radius: var(--radius-md); display: flex; align-items: center; gap: 16px; transition: var(--transition); }
        .stat-card:hover { border-color: var(--gold-primary); transform: translateY(-3px); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .stat-info .stat-value { font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.2; }
        .stat-info .stat-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

        .stat-card.gold .stat-icon { background: rgba(212, 168, 83, 0.1); color: var(--gold-primary); }
        .stat-card.success .stat-icon { background: rgba(46, 204, 113, 0.1); color: var(--success); }
        .stat-card.info .stat-icon { background: rgba(52, 152, 219, 0.1); color: var(--info); }
        .stat-card.purple .stat-icon { background: rgba(155, 89, 182, 0.1); color: var(--purple); }
        .stat-card.warning .stat-icon { background: rgba(241, 196, 15, 0.1); color: var(--warning); }
        .stat-card.danger .stat-icon { background: rgba(231, 76, 60, 0.1); color: var(--danger); }

        .quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .qa-btn { background: var(--bg-input); border: 1px solid var(--border-color); padding: 16px; border-radius: var(--radius-sm); display: flex; flex-direction: column; align-items: center; gap: 8px; transition: var(--transition); text-align: center; }
        .qa-btn i { font-size: 18px; color: var(--gold-primary); }
        .qa-btn span { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
        .qa-btn:hover { border-color: var(--gold-primary); background: rgba(212, 168, 83, 0.05); }

        .activity-feed { max-height: 400px; overflow-y: auto; padding-right: 8px; }
        .activity-item { padding: 12px 0; border-bottom: 1px solid var(--border-color); display: flex; gap: 12px; }
        .activity-item:last-child { border-bottom: none; }
        .activity-point { width: 8px; height: 8px; border-radius: 50%; background: var(--gold-primary); margin-top: 5px; flex-shrink: 0; }
        .activity-content .act-text { font-size: 13px; color: var(--text-primary); margin-bottom: 2px; }
        .activity-content .act-time { font-size: 11px; color: var(--text-muted); }
    </style>
</head>
<body>
<div class="admin-layout">

<?php require_once __DIR__ . '/includes/layout.php'; ?>

<div class="page-content">

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

    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>Dashboard</span>
            </div>
            <h1>Dashboard Overview</h1>
            <p class="subtitle">Real-time insights and management for your luxury store.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="reports.php" class="btn btn-secondary">
                <i class="fas fa-chart-line"></i> Analytics
            </a>
            <a href="product-form.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Product
            </a>
        </div>
    </div>

    <?php
    $stats = [
        'total_revenue'   => 0,
        'today_revenue'   => 0,
        'total_orders'    => 0,
        'pending_orders'  => 0,
        'low_stock'       => 0,
        'total_customers' => 0,
    ];

    try {
        $stats = getDashboardStats();
    } catch (Exception $e) {}
    ?>

    <!-- Stat Cards -->
    <div class="dashboard-grid grid-stats">
        <div class="stat-card gold">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo formatIndianPrice($stats['total_revenue'] ?? 0); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo formatIndianPrice($stats['today_revenue'] ?? 0); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo number_format($stats['total_orders'] ?? 0); ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo number_format($stats['pending_orders'] ?? 0); ?></div>
                <div class="stat-label">Pending Orders</div>
            </div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo number_format($stats['low_stock'] ?? 0); ?></div>
                <div class="stat-label">Low Stock</div>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo number_format($stats['total_customers'] ?? 0); ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid grid-main">
        <!-- Sales Chart -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line" style="color:var(--gold-primary);margin-right:8px;"></i> Sales Overview (30 Days)</h3>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 300px; position: relative;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt" style="color:var(--gold-primary);margin-right:8px;"></i> Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="product-form.php" class="qa-btn">
                        <i class="fas fa-box"></i>
                        <span>Add Product</span>
                    </a>
                    <a href="coupons.php" class="qa-btn">
                        <i class="fas fa-tag"></i>
                        <span>Add Coupon</span>
                    </a>
                    <a href="reports.php" class="qa-btn">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>View Reports</span>
                    </a>
                    <a href="settings.php" class="qa-btn">
                        <i class="fas fa-search-dollar"></i>
                        <span>Manage SEO</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid grid-secondary">
        <!-- Recent Orders -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history" style="color:var(--gold-primary);margin-right:8px;"></i> Recent Orders</h3>
                <a href="orders.php" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="card-body" style="padding:0;">
                <?php
                $recentOrders = [];
                try { $recentOrders = getRecentOrders(8); } catch (Exception $e) {}
                ?>
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
                                <td style="font-weight:700;color:var(--gold-primary);"><?php echo clean($order['order_number']); ?></td>
                                <td><?php echo clean($order['customer_name'] ?? 'Guest'); ?></td>
                                <td style="font-weight:600;"><?php echo formatIndianPrice($order['total']); ?></td>
                                <td><span class="badge <?php echo getStatusBadge($order['status']); ?>"><?php echo ucfirst(clean($order['status'])); ?></span></td>
                                <td style="font-size:12px;color:var(--text-muted);"><?php echo date('d M, h:i A', strtotime($order['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentOrders)): ?>
                            <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted);">No recent orders found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Products & Activity -->
        <div style="display:flex; flex-direction:column; gap:24px;">
            <!-- Top Selling -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-fire" style="color:var(--danger);margin-right:8px;"></i> Top Products</h3>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php
                    $topProducts = [];
                    try { $topProducts = getTopSellingProducts(5); } catch (Exception $e) {}
                    ?>
                    <table class="data-table">
                        <tbody>
                            <?php foreach ($topProducts as $p): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <img src="<?php echo SITE_URL . '/' . clean($p['main_image']); ?>" style="width:32px;height:32px;border-radius:4px;object-fit:cover;">
                                        <div style="font-size:13px;font-weight:600;color:var(--text-primary);max-width:140px;" class="truncate"><?php echo clean($p['name']); ?></div>
                                    </div>
                                </td>
                                <td style="text-align:right;"><span style="font-weight:700;color:var(--success);"><?php echo $p['total_sold']; ?></span> sold</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-stream" style="color:var(--purple);margin-right:8px;"></i> Recent Activity</h3>
                </div>
                <div class="card-body">
                    <div class="activity-feed">
                        <?php
                        $recentActivities = [];
                        try {
                            $db = getDB();
                            $stmt = $db->query("SELECT al.*, a.name as admin_name FROM activity_log al LEFT JOIN admins a ON al.admin_id = a.id ORDER BY al.created_at DESC LIMIT 10");
                            $recentActivities = $stmt->fetchAll();
                        } catch (Exception $e) {}

                        foreach ($recentActivities as $act):
                            $details = !empty($act['details']) ? json_decode($act['details'], true) : [];
                        ?>
                        <div class="activity-item">
                            <div class="activity-point"></div>
                            <div class="activity-content">
                                <div class="act-text">
                                    <strong><?php echo clean($act['admin_name'] ?? 'System'); ?></strong> 
                                    <?php echo clean($act['action']); ?> 
                                    <?php echo !empty($act['entity_type']) ? clean($act['entity_type']) : ''; ?>
                                </div>
                                <div class="act-time"><?php echo timeAgo($act['created_at']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
</main>
</div>

<script>
(function() {
    'use strict';
    const chartData = <?php 
        try {
            $data = getSalesChartData(30);
            $labels = []; $revenue = [];
            $dataMap = []; foreach ($data as $r) $dataMap[$r['date']] = $r;
            for ($i = 29; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $labels[] = date('d M', strtotime($date));
                $revenue[] = isset($dataMap[$date]) ? (float)$dataMap[$date]['revenue'] : 0;
            }
            echo json_encode(['labels' => $labels, 'revenue' => $revenue]);
        } catch (Exception $e) { echo json_encode(['labels' => [], 'revenue' => []]); }
    ?>;

    Chart.defaults.color = '#9a9ab0';
    Chart.defaults.borderColor = 'rgba(42, 42, 74, 0.4)';
    Chart.defaults.font.family = "'Inter', sans-serif";

    const ctx = document.getElementById('salesChart').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(212, 168, 83, 0.2)');
    gradient.addColorStop(1, 'rgba(212, 168, 83, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Revenue (₹)',
                data: chartData.revenue,
                borderColor: '#d4a853',
                backgroundColor: gradient,
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: '#d4a853',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                x: { grid: { display: false } }
            }
        }
    });
})();

function updateChart() {
    const period = document.getElementById('chartPeriod').value;
    window.location.href = 'index.php?period=' + period;
}
</script>

</body>
</html>