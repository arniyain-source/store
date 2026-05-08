<?php
/**
 * Marketing & Coupon Automation - DesiVastra Admin
 */
require_once __DIR__ . '/../includes/functions.php';

// Auth Guard
requireAdminLogin();

$db = getDB();
$csrf = generateCSRF();

// ============================================
// PAGINATION SETUP
// ============================================
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// ============================================
// DATA FETCHING
// ============================================
$coupons = [];
$totalCoupons = 0;
$activeCouponsCount = 0;
$flashSales = [];

try {
    // Count active for header
    $stmtCountActive = $db->query("SELECT COUNT(*) FROM coupons WHERE is_active = 1");
    $activeCouponsCount = $stmtCountActive->fetchColumn();

    // Paginated Coupons
    $stmtTotal = $db->query("SELECT COUNT(*) FROM coupons");
    $totalCoupons = $stmtTotal->fetchColumn();
    $totalPages = ceil($totalCoupons / $perPage);

    $stmt = $db->prepare("SELECT * FROM coupons ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $coupons = $stmt->fetchAll();

    // Fetch Flash Sales (upcoming/active)
    // Table 'flash_sales' structure assumed based on Step 20
    $flashSales = []; 
    try {
        $stmtSales = $db->query("SELECT fs.*, p.name as product_name, p.main_image 
                                FROM flash_sales fs 
                                JOIN products p ON fs.product_id = p.id 
                                WHERE fs.end_time > NOW() AND fs.status = 'active'
                                ORDER BY fs.start_time ASC");
        $flashSales = $stmtSales->fetchAll();
    } catch (Exception $e) {
        // Table might not exist yet if migration pending
    }

} catch (Exception $e) {
    error_log("Marketing Hub Error: " . $e->getMessage());
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing Hub - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="admin-layout">

    <?php require_once __DIR__ . '/includes/layout.php'; ?>

    <div class="page-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <div class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a>
                    <span class="separator"><i class="fas fa-chevron-right"></i></span>
                    <span>Marketing Hub</span>
                </div>
                <h1>Marketing & Automation</h1>
                <p class="subtitle">Manage coupons, flash sales, and site-wide promotions.</p>
            </div>
            <div style="display:flex;gap:10px;">
                <a href="coupons.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Coupon
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <i class="fas fa-info-circle"></i>
                <?php echo clean($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px;">
            
            <div class="left-col">
                <!-- 1. COUPON MANAGEMENT -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h3><i class="fas fa-tags" style="color:var(--gold-primary); margin-right:8px;"></i> Active Coupons (<?php echo $activeCouponsCount; ?> records)</h3>
                        <a href="coupons.php" class="btn btn-sm btn-secondary">Manage All</a>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Value</th>
                                    <th>Min. Order</th>
                                    <th>Usage</th>
                                    <th>Status</th>
                                    <th style="text-align:right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($coupons)): ?>
                                    <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">No coupons found.</td></tr>
                                <?php else: ?>
                                    <?php foreach($coupons as $coupon): ?>
                                    <tr>
                                        <td><strong style="color:var(--gold-primary)"><?php echo clean($coupon['code']); ?></strong></td>
                                        <td><?php echo ucfirst($coupon['type']); ?></td>
                                        <td><?php echo $coupon['type'] == 'percentage' ? $coupon['value'].'%' : formatIndianPrice($coupon['value']); ?></td>
                                        <td><?php echo formatIndianPrice($coupon['min_order_amount']); ?></td>
                                        <td><?php echo $coupon['usage_count']; ?> / <?php echo $coupon['usage_limit'] ?? '∞'; ?></td>
                                        <td>
                                            <span class="badge <?php echo $coupon['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo $coupon['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right">
                                            <a href="coupons.php?action=edit&id=<?php echo $coupon['id']; ?>" class="btn btn-sm btn-icon"><i class="fas fa-edit"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Standardized Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <div class="pagination">
                            <a href="marketing-hub.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="marketing-hub.php<?php echo buildQueryParams(['page' => $page - 1]); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                            
                            <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            
                            <a href="marketing-hub.php<?php echo buildQueryParams(['page' => $page + 1]); ?>" class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="marketing-hub.php<?php echo buildQueryParams(['page' => $totalPages]); ?>" class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 2. FLASH SALES -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt" style="color:var(--gold-primary); margin-right:8px;"></i> Live & Upcoming Flash Sales</h3>
                        <a href="flash-sales.php" class="btn btn-sm btn-primary">New Flash Sale</a>
                    </div>
                    <div class="card-body">
                        <?php if(empty($flashSales)): ?>
                        <div class="empty-state" style="padding:40px; text-align:center;">
                            <i class="fas fa-clock" style="font-size:32px; color:var(--text-muted); margin-bottom:15px; display:block;"></i>
                            <p style="color:var(--text-muted)">No active flash sales. Boost urgency by creating time-limited events.</p>
                        </div>
                        <?php else: ?>
                            <div class="flash-sale-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:15px;">
                                <?php foreach($flashSales as $sale): ?>
                                <div class="sale-mini-card" style="background:var(--bg-input); border-radius:8px; padding:10px; border:1px solid var(--border-color);">
                                    <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                                        <img src="<?php echo SITE_URL.'/'.$sale['main_image']; ?>" style="width:40px; height:40px; object-fit:cover; border-radius:4px;">
                                        <div style="flex:1; min-width:0;">
                                            <div class="truncate" style="font-size:12px; font-weight:600;"><?php echo clean($sale['product_name']); ?></div>
                                            <div style="font-size:11px; color:var(--gold-primary);"><?php echo formatIndianPrice($sale['sale_price']); ?></div>
                                        </div>
                                    </div>
                                    <div class="timer" style="font-family:monospace; font-size:13px; text-align:center; padding:5px; background:#000; border-radius:4px; color:var(--danger);">
                                        Ends: <?php echo date('d M, H:i', strtotime($sale['end_time'])); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="right-col">
                <!-- 3. CHANNELS & AUTOMATION -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h3><i class="fas fa-bullhorn"></i> Automation Channels</h3>
                    </div>
                    <div class="card-body">
                        <div style="display:flex; flex-direction:column; gap:15px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-size:13px; font-weight:600;">WhatsApp Notifications</div>
                                    <small style="color:var(--text-muted)">Order/Payment alerts</small>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" <?php echo getSetting('whatsapp_notify_enabled') ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-size:13px; font-weight:600;">Push Notifications</div>
                                    <small style="color:var(--text-muted)">via Firebase</small>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" <?php echo getSetting('push_notify_enabled') ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <hr style="border:0; border-top:1px solid var(--border-color); margin:5px 0;">
                            <button class="btn btn-primary full-width" onclick="location.href='campaign-create.php'">
                                <i class="fas fa-paper-plane"></i> Launch Broadcast
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 4. PROMOTIONAL BANNERS -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-image"></i> Site Banners</h3>
                    </div>
                    <div class="card-body" style="padding:0">
                        <div class="list-group">
                            <div style="padding:15px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-size:13px; font-weight:600;">Hero Slider</div>
                                    <small style="color:var(--success)">● 3 Active</small>
                                </div>
                                <div style="display:flex; gap:8px;">
                                    <label class="switch" style="transform:scale(0.8)">
                                        <input type="checkbox" checked>
                                        <span class="slider round"></span>
                                    </label>
                                    <a href="banners.php?pos=hero" class="btn btn-sm btn-icon"><i class="fas fa-cog"></i></a>
                                </div>
                            </div>
                            <div style="padding:15px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-size:13px; font-weight:600;">Shop Page Banner</div>
                                    <small style="color:var(--text-muted)">Disabled</small>
                                </div>
                                <div style="display:flex; gap:8px;">
                                    <label class="switch" style="transform:scale(0.8)">
                                        <input type="checkbox">
                                        <span class="slider round"></span>
                                    </label>
                                    <a href="banners.php?pos=shop" class="btn btn-sm btn-icon"><i class="fas fa-cog"></i></a>
                                </div>
                            </div>
                            <div style="padding:15px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-size:13px; font-weight:600;">Category Banners</div>
                                    <small style="color:var(--text-muted)">Manual</small>
                                </div>
                                <a href="banners.php?pos=category" class="btn btn-sm btn-icon"><i class="fas fa-cog"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div><!-- /.page-content -->
</div>

<style>
.full-width { width: 100%; justify-content: center; }
.switch { position: relative; display: inline-block; width: 40px; height: 20px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #333; transition: .4s; border-radius: 20px; }
.slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .slider { background-color: var(--gold-primary); }
input:checked + .slider:before { transform: translateX(20px); }
.disabled { opacity: 0.5; pointer-events: none; }
.page-info { font-size: 12px; color: var(--text-muted); font-weight: 600; padding: 0 10px; }
</style>

</body>
</html>