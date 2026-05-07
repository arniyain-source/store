<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Coupons Management - DesiVastra Admin
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

// ============================================
// HANDLE POST REQUESTS (CREATE / UPDATE / DELETE)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($csrfToken)) {
        setFlash('error', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'];

        // ── DELETE ──
        if ($action === 'delete') {
            $couponId = (int)($_POST['coupon_id'] ?? 0);
            if ($couponId > 0) {
                try {
                    $db = getDB();
                    $stmt = $db->prepare("SELECT code FROM coupons WHERE id = ?");
                    $stmt->execute([$couponId]);
                    $coupon = $stmt->fetch();

                    if ($coupon) {
                        $stmt = $db->prepare("DELETE FROM coupons WHERE id = ?");
                        $stmt->execute([$couponId]);
                        logActivity('delete_coupon', 'coupon', $couponId, ['code' => $coupon['code']]);
                        setFlash('success', 'Coupon "' . strtoupper($coupon['code']) . '" deleted successfully.');
                    } else {
                        setFlash('error', 'Coupon not found.');
                    }
                } catch (Exception $e) {
                    setFlash('error', 'Failed to delete coupon. ' . $e->getMessage());
                }
            } else {
                setFlash('error', 'Invalid coupon ID.');
            }
        }

        // ── CREATE ──
        elseif ($action === 'create') {
            $code              = strtoupper(trim($_POST['code'] ?? ''));
            $type              = $_POST['type'] ?? 'percentage';
            $value             = (float)($_POST['value'] ?? 0);
            $minOrderAmount    = (float)($_POST['min_order_amount'] ?? 0);
            $maxDiscount       = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
            $usageLimit        = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
            $perCustomerLimit  = (int)($_POST['per_customer_limit'] ?? 1);
            $validFrom         = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
            $validTo           = !empty($_POST['valid_to']) ? $_POST['valid_to'] : null;
            $isActive          = isset($_POST['is_active']) ? 1 : 0;

            // Validate
            $errors = [];
            if (empty($code)) $errors[] = 'Coupon code is required.';
            if (!in_array($type, ['percentage', 'fixed'])) $errors[] = 'Invalid coupon type.';
            if ($value <= 0) $errors[] = 'Value must be greater than 0.';
            if ($type === 'percentage' && $value > 100) $errors[] = 'Percentage value cannot exceed 100.';
            if (!empty($validFrom) && !empty($validTo) && strtotime($validTo) <= strtotime($validFrom)) {
                $errors[] = 'Valid To date must be after Valid From date.';
            }

            // Check unique code
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM coupons WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetch()) $errors[] = 'Coupon code already exists.';
            } catch (Exception $e) {
                $errors[] = 'Database error. Please try again.';
            }

            if (empty($errors)) {
                try {
                    $db = getDB();
                    $stmt = $db->prepare("
                        INSERT INTO coupons (code, type, value, min_order_amount, max_discount, usage_limit, per_customer_limit, valid_from, valid_to, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$code, $type, $value, $minOrderAmount, $maxDiscount, $usageLimit, $perCustomerLimit, $validFrom, $validTo, $isActive]);

                    $newId = $db->lastInsertId();
                    logActivity('create_coupon', 'coupon', $newId, ['code' => $code, 'type' => $type, 'value' => $value]);
                    setFlash('success', 'Coupon "' . $code . '" created successfully.');
                } catch (Exception $e) {
                    setFlash('error', 'Failed to create coupon. ' . $e->getMessage());
                }
            } else {
                setFlash('error', implode('<br>', $errors));
            }
        }

        // ── UPDATE ──
        elseif ($action === 'update') {
            $couponId          = (int)($_POST['coupon_id'] ?? 0);
            $code              = strtoupper(trim($_POST['code'] ?? ''));
            $type              = $_POST['type'] ?? 'percentage';
            $value             = (float)($_POST['value'] ?? 0);
            $minOrderAmount    = (float)($_POST['min_order_amount'] ?? 0);
            $maxDiscount       = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
            $usageLimit        = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
            $perCustomerLimit  = (int)($_POST['per_customer_limit'] ?? 1);
            $validFrom         = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
            $validTo           = !empty($_POST['valid_to']) ? $_POST['valid_to'] : null;
            $isActive          = isset($_POST['is_active']) ? 1 : 0;

            // Validate
            $errors = [];
            if ($couponId <= 0) $errors[] = 'Invalid coupon ID.';
            if (empty($code)) $errors[] = 'Coupon code is required.';
            if (!in_array($type, ['percentage', 'fixed'])) $errors[] = 'Invalid coupon type.';
            if ($value <= 0) $errors[] = 'Value must be greater than 0.';
            if ($type === 'percentage' && $value > 100) $errors[] = 'Percentage value cannot exceed 100.';
            if (!empty($validFrom) && !empty($validTo) && strtotime($validTo) <= strtotime($validFrom)) {
                $errors[] = 'Valid To date must be after Valid From date.';
            }

            // Check unique code (exclude self)
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM coupons WHERE code = ? AND id != ?");
                $stmt->execute([$code, $couponId]);
                if ($stmt->fetch()) $errors[] = 'Coupon code already exists.';
            } catch (Exception $e) {
                $errors[] = 'Database error. Please try again.';
            }

            if (empty($errors)) {
                try {
                    $db = getDB();
                    $stmt = $db->prepare("
                        UPDATE coupons SET
                            code = ?, type = ?, value = ?, min_order_amount = ?,
                            max_discount = ?, usage_limit = ?, per_customer_limit = ?,
                            valid_from = ?, valid_to = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$code, $type, $value, $minOrderAmount, $maxDiscount, $usageLimit, $perCustomerLimit, $validFrom, $validTo, $isActive, $couponId]);

                    logActivity('update_coupon', 'coupon', $couponId, ['code' => $code, 'type' => $type, 'value' => $value]);
                    setFlash('success', 'Coupon "' . $code . '" updated successfully.');
                } catch (Exception $e) {
                    setFlash('error', 'Failed to update coupon. ' . $e->getMessage());
                }
            } else {
                setFlash('error', implode('<br>', $errors));
            }
        }
    }

    // Redirect to preserve filter state
    $queryParams = [];
    if (isset($_GET['search'])) $queryParams['search'] = $_GET['search'];
    if (isset($_GET['type']))   $queryParams['type']   = $_GET['type'];
    if (isset($_GET['status'])) $queryParams['status'] = $_GET['status'];
    if (isset($_GET['page']))   $queryParams['page']   = $_GET['page'];

    $redirectUrl = 'coupons.php';
    if (!empty($queryParams)) {
        $redirectUrl .= '?' . http_build_query($queryParams);
    }
    redirect($redirectUrl);
}

// ============================================
// GET FILTER PARAMETERS
// ============================================
$search      = sanitize($_GET['search'] ?? '');
$typeFilter  = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));

// Validate filters
if (!in_array($typeFilter, ['', 'percentage', 'fixed'])) {
    $typeFilter = '';
}
if (!in_array($statusFilter, ['', 'active', 'inactive', 'expired'])) {
    $statusFilter = '';
}

// ============================================
// BUILD QUERY
// ============================================
$whereConditions = ["1=1"];
$params = [];

// Search filter
if (!empty($search)) {
    $whereConditions[] = "code LIKE ?";
    $params[] = "%{$search}%";
}

// Type filter
if (!empty($typeFilter)) {
    $whereConditions[] = "type = ?";
    $params[] = $typeFilter;
}

// Status filter
if ($statusFilter === 'active') {
    $whereConditions[] = "is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $whereConditions[] = "is_active = 0";
} elseif ($statusFilter === 'expired') {
    $whereConditions[] = "valid_to < NOW()";
}

$whereClause = implode(' AND ', $whereConditions);

$query = "SELECT * FROM coupons WHERE {$whereClause} ORDER BY created_at DESC";

// ============================================
// FETCH DATA
// ============================================
$coupons    = [];
$pagination = null;
$stats      = [];
$dbError    = false;

try {
    $db = getDB();

    // Get paginated coupons
    $pagination = paginate($query, $params, $page, ADMIN_PER_PAGE);
    $coupons = $pagination['data'];

    // Quick stats
    $stmt = $db->query("SELECT COUNT(*) as total FROM coupons");
    $stats['total'] = (int)$stmt->fetch()['total'];

    $stmt = $db->query("SELECT COUNT(*) as active FROM coupons WHERE is_active = 1 AND (valid_to IS NULL OR valid_to >= NOW())");
    $stats['active'] = (int)$stmt->fetch()['active'];

    $stmt = $db->query("SELECT COUNT(*) as expired FROM coupons WHERE valid_to IS NOT NULL AND valid_to < NOW()");
    $stats['expired'] = (int)$stmt->fetch()['expired'];

    $stmt = $db->query("SELECT COALESCE(SUM(usage_count), 0) as total_usage FROM coupons");
    $stats['total_usage'] = (int)$stmt->fetch()['total_usage'];

} catch (Exception $e) {
    $dbError = true;
    error_log("Coupons page error: " . $e->getMessage());
}

// ============================================
// GET FLASH MESSAGE
// ============================================
$flash = getFlash();

// ============================================
// HELPER: Build query string preserving filters
// ============================================
function buildCouponQueryParams($overrides = []) {
    $params = [];
    if (!empty($_GET['search'])) $params['search'] = $_GET['search'];
    if (!empty($_GET['type']))   $params['type']   = $_GET['type'];
    if (!empty($_GET['status'])) $params['status'] = $_GET['status'];
    $params = array_merge($params, $overrides);
    return !empty($params) ? '?' . http_build_query($params) : '';
}

/**
 * Generate a random coupon code
 */
function generateCouponCode() {
    $prefixes = ['DESI', 'DV', 'SAVE', 'GRAB', 'BEST', 'MEGA', 'SALE', 'FEST'];
    $prefix = $prefixes[array_rand($prefixes)];
    return $prefix . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupons - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .top-header { margin-left: var(--sidebar-width); }
        @media (max-width: 768px) {
            .top-header { margin-left: 0; }
        }

        /* Coupon code styling */
        .coupon-code {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 13px;
            color: var(--gold-primary);
            background: rgba(212, 168, 83, 0.08);
            padding: 4px 10px;
            border-radius: 4px;
            letter-spacing: 1px;
            display: inline-block;
        }

        /* Type badge variants */
        .badge-percentage {
            background: rgba(155, 89, 182, 0.15);
            color: #9b59b6;
        }
        .badge-fixed {
            background: rgba(52, 152, 219, 0.15);
            color: #3498db;
        }

        /* Usage progress bar */
        .usage-bar-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .usage-bar {
            flex: 1;
            height: 6px;
            background: var(--bg-input);
            border-radius: 3px;
            overflow: hidden;
            min-width: 60px;
        }
        .usage-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease;
        }
        .usage-bar-fill.low  { background: var(--success); }
        .usage-bar-fill.mid  { background: var(--warning); }
        .usage-bar-fill.high { background: var(--danger); }

        /* Date display */
        .date-range {
            font-size: 12px;
            line-height: 1.5;
        }
        .date-range .date-label {
            color: var(--text-muted);
            font-size: 10px;
        }
        .date-range .date-value {
            color: var(--text-secondary);
        }

        /* Radio group for coupon type */
        .radio-group {
            display: flex;
            gap: 12px;
        }
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            flex: 1;
            justify-content: center;
        }
        .radio-option:hover {
            border-color: var(--gold-dark);
        }
        .radio-option.selected {
            border-color: var(--gold-primary);
            background: rgba(212, 168, 83, 0.08);
        }
        .radio-option input[type="radio"] {
            accent-color: var(--gold-primary);
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        .radio-option label {
            font-size: 13px;
            color: var(--text-secondary);
            cursor: pointer;
            font-weight: 500;
        }
        .radio-option.selected label {
            color: var(--gold-primary);
        }

        /* Auto-generate button */
        .input-with-btn {
            display: flex;
            gap: 8px;
        }
        .input-with-btn .form-control {
            flex: 1;
        }
        .input-with-btn .btn {
            white-space: nowrap;
        }

        /* Active toggle in modal */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 8px;
        }
        .toggle-row .toggle-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .toggle-row .toggle-hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Expired row styling */
        .expired-row td {
            opacity: 0.55;
        }
    </style>
</head>
<body>
<div class="admin-layout">

    <?php require_once __DIR__ . '/includes/layout.php'; ?>

    
        <div class="page-content">

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-percent" style="color: var(--gold-primary); margin-right: 8px;"></i>Coupons</h1>
                    <p class="subtitle">
                        <?php if (!$dbError && $pagination): ?>
                            Showing <?php echo count($coupons); ?> of <?php echo number_format($pagination['total']); ?> coupons
                        <?php else: ?>
                            Manage discount coupons
                        <?php endif; ?>
                    </p>
                </div>
                <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> Create Coupon
                </button>
            </div>

            <!-- Flash Message -->
            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <i class="fas fa-<?php echo match($flash['type']) {
                        'success' => 'check-circle',
                        'error'   => 'exclamation-circle',
                        'warning' => 'exclamation-triangle',
                        default   => 'info-circle'
                    }; ?>"></i>
                    <?php echo $flash['message']; ?>
                    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Database Error -->
            <?php if ($dbError): ?>
                <div class="flash-message flash-error">
                    <i class="fas fa-exclamation-circle"></i>
                    Unable to load coupons. The database tables may not exist yet. Please run the setup script first.
                    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Quick Stat Cards -->
            <?php if (!$dbError): ?>
            <div class="stats-grid">
                <div class="stat-card gold">
                    <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total Coupons</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
                    <div class="stat-label">Active Coupons</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-value"><?php echo number_format($stats['expired']); ?></div>
                    <div class="stat-label">Expired</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="stat-value"><?php echo number_format($stats['total_usage']); ?></div>
                    <div class="stat-label">Total Usage</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filter Bar -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="padding: 16px 20px;">
                    <form method="GET" action="coupons.php" class="filter-bar" style="margin-bottom: 0;">
                        <!-- Search Input -->
                        <div style="position: relative; flex: 1; min-width: 200px;">
                            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px;"></i>
                            <input type="text" name="search" class="search-input" placeholder="Search by coupon code..." value="<?php echo clean($search); ?>" style="width: 100%;">
                        </div>

                        <!-- Type Dropdown -->
                        <select name="type" style="min-width: 150px;">
                            <option value="">All Types</option>
                            <option value="percentage" <?php echo $typeFilter === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                            <option value="fixed" <?php echo $typeFilter === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                        </select>

                        <!-- Status Dropdown -->
                        <select name="status" style="min-width: 140px;">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>

                        <!-- Apply Button -->
                        <button type="submit" class="btn btn-secondary btn-sm">
                            <i class="fas fa-filter"></i> Filter
                        </button>

                        <?php if (!empty($search) || !empty($typeFilter) || !empty($statusFilter)): ?>
                            <a href="coupons.php" class="btn btn-sm" style="background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(231,76,60,0.2);">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Coupons Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list" style="margin-right: 8px; color: var(--gold-primary);"></i>All Coupons</h3>
                </div>

                <?php if (!$dbError && !empty($coupons)): ?>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Value</th>
                                    <th>Min Order</th>
                                    <th>Max Discount</th>
                                    <th>Usage</th>
                                    <th>Valid Period</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coupons as $coupon):
                                    $isExpired = !empty($coupon['valid_to']) && strtotime($coupon['valid_to']) < time();
                                    $usagePercent = ($coupon['usage_limit'] && $coupon['usage_limit'] > 0)
                                        ? min(100, round(($coupon['usage_count'] / $coupon['usage_limit']) * 100))
                                        : 0;
                                    $barClass = $usagePercent < 50 ? 'low' : ($usagePercent < 80 ? 'mid' : 'high');
                                ?>
                                <tr class="<?php echo $isExpired ? 'expired-row' : ''; ?>">
                                    <td>
                                        <span class="coupon-code"><?php echo clean($coupon['code']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($coupon['type'] === 'percentage'): ?>
                                            <span class="badge badge-percentage"><i class="fas fa-percent" style="font-size: 9px;"></i> Percentage</span>
                                        <?php else: ?>
                                            <span class="badge badge-fixed"><i class="fas fa-rupee-sign" style="font-size: 9px;"></i> Fixed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-weight: 700; color: var(--text-primary);">
                                            <?php if ($coupon['type'] === 'percentage'): ?>
                                                <?php echo (float)$coupon['value']; ?>%
                                            <?php else: ?>
                                                <?php echo formatIndianPrice($coupon['value']); ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($coupon['min_order_amount'] > 0): ?>
                                            <?php echo formatIndianPrice($coupon['min_order_amount']); ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($coupon['max_discount']) && $coupon['max_discount'] > 0): ?>
                                            <?php echo formatIndianPrice($coupon['max_discount']); ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="usage-bar-wrap">
                                            <span style="font-weight: 600; color: var(--text-primary); white-space: nowrap;">
                                                <?php echo (int)$coupon['usage_count']; ?>
                                            </span>
                                            <span style="color: var(--text-muted);">/</span>
                                            <span style="color: var(--text-secondary); white-space: nowrap;">
                                                <?php echo $coupon['usage_limit'] ? (int)$coupon['usage_limit'] : '&infin;'; ?>
                                            </span>
                                        </div>
                                        <?php if ($coupon['usage_limit'] && $coupon['usage_limit'] > 0): ?>
                                        <div class="usage-bar" style="margin-top: 4px;">
                                            <div class="usage-bar-fill <?php echo $barClass; ?>" style="width: <?php echo $usagePercent; ?>%;"></div>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="date-range">
                                            <?php if (!empty($coupon['valid_from']) || !empty($coupon['valid_to'])): ?>
                                                <?php if (!empty($coupon['valid_from'])): ?>
                                                    <div><span class="date-label">From </span><span class="date-value"><?php echo date('d M Y', strtotime($coupon['valid_from'])); ?></span></div>
                                                <?php endif; ?>
                                                <?php if (!empty($coupon['valid_to'])): ?>
                                                    <div><span class="date-label">To </span><span class="date-value" style="<?php echo $isExpired ? 'color: var(--danger);' : ''; ?>"><?php echo date('d M Y', strtotime($coupon['valid_to'])); ?></span></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 12px;">No Expiry</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isExpired): ?>
                                            <span class="badge badge-danger"><span class="badge-dot" style="background: var(--danger);"></span> Expired</span>
                                        <?php elseif (!empty($coupon['is_active'])): ?>
                                            <span class="badge badge-success"><span class="badge-dot" style="background: var(--success);"></span> Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-dark"><span class="badge-dot" style="background: var(--text-muted);"></span> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                            <button type="button" class="btn btn-sm btn-secondary" onclick='openEditModal(<?php echo json_encode($coupon, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' data-tooltip="Edit Coupon">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo (int)$coupon['id']; ?>, '<?php echo clean(addslashes($coupon['code'])); ?>')" data-tooltip="Delete Coupon">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                        <div class="card-footer">
                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                                <div style="font-size: 12px; color: var(--text-muted);">
                                    Showing page <?php echo $pagination['page']; ?> of <?php echo $pagination['total_pages']; ?>
                                    &mdash; <?php echo number_format($pagination['total']); ?> total coupons
                                </div>
                                <div class="pagination" style="margin-top: 0;">
                                    <?php if ($pagination['has_prev']): ?>
                                        <a href="coupons.php<?php echo buildCouponQueryParams(['page' => 1]); ?>" class="page-btn" data-tooltip="First Page">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                        <a href="coupons.php<?php echo buildCouponQueryParams(['page' => $pagination['page'] - 1]); ?>" class="page-btn" data-tooltip="Previous">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="page-btn" disabled><i class="fas fa-angle-double-left"></i></button>
                                        <button class="page-btn" disabled><i class="fas fa-angle-left"></i></button>
                                    <?php endif; ?>

                                    <?php
                                        $startPage = max(1, $pagination['page'] - 2);
                                        $endPage = min($pagination['total_pages'], $pagination['page'] + 2);

                                        if ($startPage > 1) {
                                            echo '<span style="color: var(--text-muted); padding: 0 4px;">&hellip;</span>';
                                        }

                                        for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <a href="coupons.php<?php echo buildCouponQueryParams(['page' => $i]); ?>" class="page-btn <?php echo $i === $pagination['page'] ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php
                                        if ($endPage < $pagination['total_pages']) {
                                            echo '<span style="color: var(--text-muted); padding: 0 4px;">&hellip;</span>';
                                        }
                                    ?>

                                    <?php if ($pagination['has_next']): ?>
                                        <a href="coupons.php<?php echo buildCouponQueryParams(['page' => $pagination['page'] + 1]); ?>" class="page-btn" data-tooltip="Next">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                        <a href="coupons.php<?php echo buildCouponQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn" data-tooltip="Last Page">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="page-btn" disabled><i class="fas fa-angle-right"></i></button>
                                        <button class="page-btn" disabled><i class="fas fa-angle-double-right"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php elseif (!$dbError): ?>
                    <!-- Empty State -->
                    <div class="card-body">
                        <div class="empty-state">
                            <?php if (!empty($search) || !empty($typeFilter) || !empty($statusFilter)): ?>
                                <i class="fas fa-search"></i>
                                <h3>No Coupons Found</h3>
                                <p>No coupons match your current filters. Try adjusting your search criteria.</p>
                                <a href="coupons.php" class="btn btn-secondary" style="margin-top: 16px;">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                            <?php else: ?>
                                <i class="fas fa-ticket-alt"></i>
                                <h3>No Coupons Yet</h3>
                                <p>Create your first discount coupon to attract more customers.</p>
                                <button type="button" class="btn btn-primary" style="margin-top: 16px;" onclick="openCreateModal()">
                                    <i class="fas fa-plus"></i> Create First Coupon
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

</div><!-- /admin-layout -->

<!-- ============================================
     ADD / EDIT COUPON MODAL
     ============================================ -->
<div class="modal-overlay" id="couponModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="couponModalTitle"><i class="fas fa-plus-circle" style="color: var(--gold-primary); margin-right: 8px;"></i>Create Coupon</h3>
            <button class="modal-close" onclick="closeCouponModal()">&times;</button>
        </div>
        <form method="POST" action="coupons.php<?php echo buildCouponQueryParams(); ?>" id="couponForm">
            <input type="hidden" name="action" id="couponFormAction" value="create">
            <input type="hidden" name="coupon_id" id="couponFormId" value="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

            <div class="modal-body">
                <!-- Coupon Code -->
                <div class="form-group">
                    <label class="form-label">Coupon Code</label>
                    <div class="input-with-btn">
                        <input type="text" name="code" id="couponCode" class="form-control" placeholder="e.g. SUMMER2025" style="text-transform: uppercase; letter-spacing: 1px; font-family: 'Courier New', monospace; font-weight: 700;" maxlength="50" required>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="autoGenerateCode()" data-tooltip="Auto-generate code">
                            <i class="fas fa-magic"></i> Auto
                        </button>
                    </div>
                    <div class="form-hint">Uppercase letters and numbers recommended. Click "Auto" to generate a unique code.</div>
                </div>

                <!-- Coupon Type -->
                <div class="form-group">
                    <label class="form-label">Discount Type</label>
                    <div class="radio-group">
                        <div class="radio-option selected" onclick="selectCouponType('percentage')">
                            <input type="radio" name="type" id="typePercentage" value="percentage" checked>
                            <label for="typePercentage"><i class="fas fa-percent" style="margin-right: 4px;"></i> Percentage</label>
                        </div>
                        <div class="radio-option" onclick="selectCouponType('fixed')">
                            <input type="radio" name="type" id="typeFixed" value="fixed">
                            <label for="typeFixed"><i class="fas fa-rupee-sign" style="margin-right: 4px;"></i> Fixed Amount</label>
                        </div>
                    </div>
                </div>

                <!-- Value & Min Order -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Discount Value <span style="color: var(--danger);">*</span></label>
                        <div class="input-group">
                            <span class="input-prefix" id="valuePrefix">%</span>
                            <input type="number" name="value" id="couponValue" class="form-control" placeholder="0" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-hint" id="valueHint">Enter discount percentage (1-100)</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Order Amount</label>
                        <div class="input-group">
                            <span class="input-prefix"><?php echo CURRENCY_SYMBOL; ?></span>
                            <input type="number" name="min_order_amount" class="form-control" placeholder="0" step="0.01" min="0" value="0">
                        </div>
                        <div class="form-hint">Minimum order value to apply coupon</div>
                    </div>
                </div>

                <!-- Max Discount & Usage Limit -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Max Discount</label>
                        <div class="input-group">
                            <span class="input-prefix"><?php echo CURRENCY_SYMBOL; ?></span>
                            <input type="number" name="max_discount" id="couponMaxDiscount" class="form-control" placeholder="No limit" step="0.01" min="0">
                        </div>
                        <div class="form-hint">Maximum discount amount (for % coupons)</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Usage Limit</label>
                        <input type="number" name="usage_limit" class="form-control" placeholder="Unlimited" min="1" id="couponUsageLimit">
                        <div class="form-hint">Leave empty for unlimited usage</div>
                    </div>
                </div>

                <!-- Per Customer Limit & Dates -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Per Customer Limit</label>
                        <input type="number" name="per_customer_limit" class="form-control" placeholder="1" min="1" value="1">
                        <div class="form-hint">How many times one customer can use this</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <div style="font-size: 11px; color: var(--text-muted); padding-top: 4px;">
                            <i class="fas fa-info-circle" style="margin-right: 4px;"></i>
                            Set valid dates below to control when the coupon is available.
                        </div>
                    </div>
                </div>

                <!-- Valid From / Valid To -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Valid From</label>
                        <input type="datetime-local" name="valid_from" id="couponValidFrom" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valid To</label>
                        <input type="datetime-local" name="valid_to" id="couponValidTo" class="form-control">
                    </div>
                </div>

                <!-- Active Toggle -->
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Active Status</div>
                        <div class="toggle-hint">Inactive coupons cannot be applied by customers</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="is_active" id="couponIsActive" value="1" checked>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCouponModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="couponSubmitBtn">
                    <i class="fas fa-save"></i> <span id="couponSubmitText">Create Coupon</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================
     DELETE CONFIRMATION MODAL
     ============================================ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal" style="max-width: 440px;">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger); margin-right: 8px;"></i>Confirm Delete</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 12px;">Are you sure you want to delete this coupon?</p>
            <div style="background: var(--danger-bg); border: 1px solid rgba(231,76,60,0.2); border-radius: var(--radius-sm); padding: 12px 16px;">
                <p style="font-weight: 600; color: var(--danger); margin-bottom: 4px; font-family: 'Courier New', monospace; letter-spacing: 1px;" id="deleteCouponCode"></p>
                <p style="font-size: 12px; color: var(--text-muted);">This action cannot be undone. The coupon will be permanently removed and cannot be used by customers anymore.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <form method="POST" action="coupons.php<?php echo buildCouponQueryParams(); ?>" id="deleteForm" style="display: inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="coupon_id" id="deleteCouponId" value="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Delete Coupon
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================
// COUPON MODAL (CREATE / EDIT)
// ============================================
function openCreateModal() {
    document.getElementById('couponModalTitle').innerHTML = '<i class="fas fa-plus-circle" style="color: var(--gold-primary); margin-right: 8px;"></i>Create Coupon';
    document.getElementById('couponFormAction').value = 'create';
    document.getElementById('couponFormId').value = '';
    document.getElementById('couponSubmitText').textContent = 'Create Coupon';

    // Reset form
    var form = document.getElementById('couponForm');
    form.reset();
    document.getElementById('couponCode').value = '';
    document.getElementById('couponIsActive').checked = true;
    document.getElementById('typePercentage').checked = true;
    selectCouponType('percentage');

    document.getElementById('couponModal').classList.add('show');
}

function openEditModal(coupon) {
    document.getElementById('couponModalTitle').innerHTML = '<i class="fas fa-edit" style="color: var(--gold-primary); margin-right: 8px;"></i>Edit Coupon';
    document.getElementById('couponFormAction').value = 'update';
    document.getElementById('couponFormId').value = coupon.id;
    document.getElementById('couponSubmitText').textContent = 'Update Coupon';

    // Fill form fields
    document.getElementById('couponCode').value = coupon.code;

    // Type
    if (coupon.type === 'fixed') {
        document.getElementById('typeFixed').checked = true;
        selectCouponType('fixed');
    } else {
        document.getElementById('typePercentage').checked = true;
        selectCouponType('percentage');
    }

    document.getElementById('couponValue').value = parseFloat(coupon.value);
    document.querySelector('input[name="min_order_amount"]').value = parseFloat(coupon.min_order_amount) || 0;
    document.getElementById('couponMaxDiscount').value = coupon.max_discount ? parseFloat(coupon.max_discount) : '';
    document.getElementById('couponUsageLimit').value = coupon.usage_limit || '';
    document.querySelector('input[name="per_customer_limit"]').value = coupon.per_customer_limit || 1;

    // Dates — format for datetime-local input
    if (coupon.valid_from) {
        document.getElementById('couponValidFrom').value = coupon.valid_from.substring(0, 16);
    } else {
        document.getElementById('couponValidFrom').value = '';
    }
    if (coupon.valid_to) {
        document.getElementById('couponValidTo').value = coupon.valid_to.substring(0, 16);
    } else {
        document.getElementById('couponValidTo').value = '';
    }

    document.getElementById('couponIsActive').checked = coupon.is_active == 1;

    document.getElementById('couponModal').classList.add('show');
}

function closeCouponModal() {
    document.getElementById('couponModal').classList.remove('show');
}

// ============================================
// COUPON TYPE SELECTION
// ============================================
function selectCouponType(type) {
    var options = document.querySelectorAll('.radio-option');
    options.forEach(function(opt) {
        opt.classList.remove('selected');
    });

    if (type === 'percentage') {
        document.getElementById('typePercentage').closest('.radio-option').classList.add('selected');
        document.getElementById('typePercentage').checked = true;
        document.getElementById('valuePrefix').textContent = '%';
        document.getElementById('valueHint').textContent = 'Enter discount percentage (1-100)';
        document.getElementById('couponMaxDiscount').closest('.form-group').style.display = '';
    } else {
        document.getElementById('typeFixed').closest('.radio-option').classList.add('selected');
        document.getElementById('typeFixed').checked = true;
        document.getElementById('valuePrefix').textContent = '<?php echo CURRENCY_SYMBOL; ?>';
        document.getElementById('valueHint').textContent = 'Enter fixed discount amount';
        document.getElementById('couponMaxDiscount').closest('.form-group').style.display = 'none';
    }
}

// ============================================
// AUTO-GENERATE COUPON CODE
// ============================================
function autoGenerateCode() {
    var prefixes = ['DESI', 'DV', 'SAVE', 'GRAB', 'BEST', 'MEGA', 'SALE', 'FEST', 'NEW', 'VIP'];
    var prefix = prefixes[Math.floor(Math.random() * prefixes.length)];
    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var code = prefix;
    for (var i = 0; i < 6; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('couponCode').value = code;
}

// ============================================
// DELETE MODAL
// ============================================
function confirmDelete(couponId, couponCode) {
    document.getElementById('deleteCouponId').value = couponId;
    document.getElementById('deleteCouponCode').textContent = couponCode;
    document.getElementById('deleteModal').classList.add('show');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.getElementById('deleteCouponId').value = '';
}

// ============================================
// CLOSE MODALS ON OVERLAY / ESCAPE
// ============================================
document.getElementById('couponModal').addEventListener('click', function(e) {
    if (e.target === this) closeCouponModal();
});

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCouponModal();
        closeDeleteModal();
    }
});

// ============================================
// AUTO-SUBMIT FILTER DROPDOWNS
// ============================================
var filterSelects = document.querySelectorAll('.filter-bar select');
for (var i = 0; i < filterSelects.length; i++) {
    filterSelects[i].addEventListener('change', function() {
        this.closest('form').submit();
    });
}
</script>

</body>
</html>
