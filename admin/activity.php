<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Activity Log Page - DesiVastra Admin
 * Displays all admin activity logs with filtering and pagination
 */

// Handle POST actions before layout output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/../includes/functions.php';
    requireAdminLogin();

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please try again.');
        header('Location: activity.php');
        exit;
    }

    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'clear_all') {
        // Only super_admin and admin can clear logs
        if (!in_array($_SESSION['admin_role'] ?? '', ['super_admin', 'admin'])) {
            setFlash('error', 'You do not have permission to clear activity logs.');
            header('Location: activity.php');
            exit;
        }

        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM activity_log");
            $stmt->execute();
            $count = (int)$stmt->fetch()['cnt'];

            $db->exec("TRUNCATE TABLE activity_log");

            logActivity('delete', 'activity_log', null, ['action' => 'clear_all', 'records_cleared' => $count]);
            setFlash('success', "Activity log cleared successfully. {$count} record(s) removed.");
        } catch (Exception $e) {
            setFlash('error', 'Failed to clear activity log: ' . $e->getMessage());
        }

        header('Location: activity.php');
        exit;
    }

    setFlash('error', 'Unknown action.');
    header('Location: activity.php');
    exit;
}

// Normal page load


// Get filter parameters
$searchQuery    = sanitize($_GET['search'] ?? '');
$actionFilter   = sanitize($_GET['action_type'] ?? '');
$entityFilter   = sanitize($_GET['entity_type'] ?? '');
$dateFrom       = sanitize($_GET['date_from'] ?? '');
$dateTo         = sanitize($_GET['date_to'] ?? '');
$currentPage    = max(1, (int)($_GET['page'] ?? 1));

// Validate action filter
$validActions = ['login', 'logout', 'create', 'update', 'delete'];
if ($actionFilter && !in_array($actionFilter, $validActions)) {
    $actionFilter = '';
}

// Get distinct entity types for dropdown
$entityTypes = [];
try {
    $db = getDB();
    $stmt = $db->query("SELECT DISTINCT entity_type FROM activity_log WHERE entity_type IS NOT NULL ORDER BY entity_type ASC");
    while ($row = $stmt->fetch()) {
        $entityTypes[] = $row['entity_type'];
    }
} catch (Exception $e) {
    // Table may be empty
}

// Validate entity filter against known types
if ($entityFilter && !in_array($entityFilter, $entityTypes)) {
    $entityFilter = '';
}

// Build query
$baseQuery = "
    SELECT al.id, al.admin_id, al.action, al.entity_type, al.entity_id,
           al.details, al.ip_address, al.user_agent, al.created_at,
           COALESCE(a.name, 'System') as admin_name,
           COALESCE(a.role, 'system') as admin_role
    FROM activity_log al
    LEFT JOIN admins a ON al.admin_id = a.id
";

$whereClauses = [];
$params = [];

if ($searchQuery) {
    $whereClauses[] = "(a.name LIKE ? OR al.action LIKE ? OR al.entity_type LIKE ? OR al.details LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($actionFilter) {
    $whereClauses[] = "al.action LIKE ?";
    $params[] = $actionFilter . '%';
}

if ($entityFilter) {
    $whereClauses[] = "al.entity_type = ?";
    $params[] = $entityFilter;
}

if ($dateFrom) {
    $whereClauses[] = "DATE(al.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereClauses[] = "DATE(al.created_at) <= ?";
    $params[] = $dateTo;
}

if (!empty($whereClauses)) {
    $baseQuery .= " WHERE " . implode(" AND ", $whereClauses);
}

$baseQuery .= " ORDER BY al.created_at DESC";

// Paginate results
$pagination = paginate($baseQuery, $params, $currentPage, ADMIN_PER_PAGE);
$activities = $pagination['data'];

// Flash message
$flash = getFlash();
$csrf = generateCSRF();

/**
 * Get icon class based on action type
 */
function getActivityIcon($action) {
    if (strpos($action, 'login') !== false)  return ['class' => 'login',  'icon' => 'fa-sign-in-alt'];
    if (strpos($action, 'logout') !== false) return ['class' => 'login',  'icon' => 'fa-sign-out-alt'];
    if (strpos($action, 'create') !== false) return ['class' => 'create', 'icon' => 'fa-plus'];
    if (strpos($action, 'update') !== false) return ['class' => 'update', 'icon' => 'fa-pen'];
    if (strpos($action, 'delete') !== false) return ['class' => 'delete', 'icon' => 'fa-trash'];
    return ['class' => 'update', 'icon' => 'fa-circle'];
}

/**
 * Format action label for display
 */
function formatActionLabel($action) {
    $labels = [
        'login'          => 'Logged In',
        'logout'         => 'Logged Out',
        'create'         => 'Created',
        'update'         => 'Updated',
        'update_status'  => 'Updated Status',
        'delete'         => 'Deleted',
    ];
    return $labels[$action] ?? ucwords(str_replace('_', ' ', $action));
}

/**
 * Format entity type label
 */
function formatEntityLabel($entityType) {
    if (!$entityType) return '';
    $labels = [
        'admin'        => 'Admin',
        'product'      => 'Product',
        'category'     => 'Category',
        'order'        => 'Order',
        'coupon'       => 'Coupon',
        'customer'     => 'Customer',
        'settings'     => 'Settings',
        'review'       => 'Review',
        'activity_log' => 'Activity Log',
    ];
    return $labels[$entityType] ?? ucwords(str_replace('_', ' ', $entityType));
}

/**
 * Build description from activity record
 */
function buildActivityDescription($activity) {
    $action  = $activity['action'];
    $entity  = $activity['entity_type'];
    $details = $activity['details'] ? json_decode($activity['details'], true) : [];
    $admin   = $activity['admin_name'];

    $desc = '<strong>' . clean($admin) . '</strong> ';

    $desc .= formatActionLabel($action);

    if ($entity) {
        $desc .= ' ' . formatEntityLabel($entity);
    }

    // Add contextual detail from JSON
    if (!empty($details)) {
        $contextParts = [];

        if (isset($details['order_number'])) {
            $contextParts[] = '#' . clean($details['order_number']);
        }
        if (isset($details['name'])) {
            $contextParts[] = '"' . clean($details['name']) . '"';
        }
        if (isset($details['old_status']) && isset($details['new_status'])) {
            $contextParts[] = clean(ucfirst($details['old_status'])) . ' → ' . clean(ucfirst($details['new_status']));
        }
        if (isset($details['group'])) {
            $contextParts[] = '(' . clean(ucfirst($details['group'])) . ')';
        }
        if (isset($details['action']) && $details['action'] === 'clear_all') {
            $contextParts[] = '(' . ($details['records_cleared'] ?? 0) . ' records cleared)';
        }

        if (!empty($contextParts)) {
            $desc .= ' ' . implode(' ', $contextParts);
        }
    }

    if ($activity['entity_id'] && $entity !== 'activity_log') {
        $desc .= ' <span style="color:var(--text-muted);font-size:11px;">[ID: ' . $activity['entity_id'] . ']</span>';
    }

    return $desc;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        /* Activity Log page-specific styles */
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

        /* Activity list styles */
        .activity-list {
            padding: 4px 20px;
        }

        .activity-item {
            display: flex;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-color);
            align-items: flex-start;
            transition: var(--transition);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item:hover {
            background: rgba(212, 168, 83, 0.02);
            margin: 0 -20px;
            padding-left: 20px;
            padding-right: 20px;
        }

        .activity-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .activity-icon.create { background: var(--success-bg); color: var(--success); }
        .activity-icon.update { background: var(--info-bg); color: var(--info); }
        .activity-icon.delete { background: var(--danger-bg); color: var(--danger); }
        .activity-icon.login  { background: var(--purple-bg); color: var(--purple); }

        .activity-body {
            flex: 1;
            min-width: 0;
        }

        .activity-text {
            font-size: 13px;
            color: var(--text-primary);
            line-height: 1.5;
        }

        .activity-text strong {
            color: var(--gold-primary);
            font-weight: 600;
        }

        .activity-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 6px;
            flex-wrap: wrap;
        }

        .activity-time {
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .activity-time i {
            font-size: 10px;
        }

        .activity-ip {
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .activity-ip i {
            font-size: 10px;
        }

        .activity-entity-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .entity-admin        { background: var(--purple-bg); color: var(--purple); }
        .entity-product      { background: var(--success-bg); color: var(--success); }
        .entity-category     { background: var(--info-bg); color: var(--info); }
        .entity-order        { background: var(--gold-primary-bg, rgba(212,168,83,0.15)); color: var(--gold-primary); }
        .entity-coupon       { background: var(--warning-bg); color: var(--warning); }
        .entity-customer     { background: var(--info-bg); color: var(--info); }
        .entity-settings     { background: var(--danger-bg); color: var(--danger); }
        .entity-review       { background: var(--success-bg); color: var(--success); }
        .entity-activity_log { background: rgba(100,100,120,0.2); color: var(--text-secondary); }
        .entity-default      { background: rgba(100,100,120,0.2); color: var(--text-secondary); }

        /* Summary chips */
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

        /* Clear all confirm modal styling */
        .confirm-modal-content {
            text-align: center;
            padding: 20px 0;
        }

        .confirm-modal-content .confirm-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--danger-bg);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: var(--danger);
            margin-bottom: 16px;
        }

        .confirm-modal-content h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .confirm-modal-content p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .confirm-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        /* Empty state for activity */
        .empty-activity {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-activity i {
            font-size: 48px;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .empty-activity h3 {
            font-size: 18px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .empty-activity p {
            font-size: 13px;
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
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
            .activity-meta {
                gap: 10px;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/layout.php'; ?>

    

    
        <div class="page-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>Activity Log</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-history" style="color:var(--gold-primary);margin-right:8px"></i>Activity Log</h1>
                    <p class="subtitle">Track all admin actions and system events</p>
                </div>
                <div style="display:flex;gap:8px">
                    <?php if (in_array($admin['role'] ?? '', ['super_admin', 'admin'])): ?>
                        <button class="btn btn-danger btn-sm" onclick="showClearConfirm()">
                            <i class="fas fa-trash-alt"></i> Clear All
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Flash Message -->
            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : ($flash['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle')); ?>"></i>
                    <?php echo clean($flash['message']); ?>
                    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Filter Bar -->
            <div class="filter-card">
                <form method="GET" action="activity.php" id="filterForm">
                    <div class="filter-row">
                        <div class="filter-group search-wrap">
                            <label>Search</label>
                            <div style="position:relative">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Search by admin, action, or details..." value="<?php echo clean($searchQuery); ?>">
                            </div>
                        </div>

                        <div class="filter-group">
                            <label>Action Type</label>
                            <select name="action_type">
                                <option value="">All Actions</option>
                                <option value="login" <?php echo $actionFilter === 'login' ? 'selected' : ''; ?>>Login</option>
                                <option value="logout" <?php echo $actionFilter === 'logout' ? 'selected' : ''; ?>>Logout</option>
                                <option value="create" <?php echo $actionFilter === 'create' ? 'selected' : ''; ?>>Create</option>
                                <option value="update" <?php echo $actionFilter === 'update' ? 'selected' : ''; ?>>Update</option>
                                <option value="delete" <?php echo $actionFilter === 'delete' ? 'selected' : ''; ?>>Delete</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Entity Type</label>
                            <select name="entity_type">
                                <option value="">All Entities</option>
                                <?php foreach ($entityTypes as $et): ?>
                                    <option value="<?php echo clean($et); ?>" <?php echo $entityFilter === $et ? 'selected' : ''; ?>>
                                        <?php echo formatEntityLabel($et); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Date From</label>
                            <input type="date" name="date_from" value="<?php echo clean($dateFrom); ?>">
                        </div>

                        <div class="filter-group">
                            <label>Date To</label>
                            <input type="date" name="date_to" value="<?php echo clean($dateTo); ?>">
                        </div>

                        <div class="filter-group" style="flex-direction:row;align-items:flex-end;gap:6px">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="activity.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Active Filters Summary -->
            <?php if ($searchQuery || $actionFilter || $entityFilter || $dateFrom || $dateTo): ?>
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
                    <?php if ($actionFilter): ?>
                        <div class="summary-chip">
                            <i class="fas fa-bolt" style="color:var(--warning)"></i>
                            Action: <strong><?php echo formatActionLabel($actionFilter); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($entityFilter): ?>
                        <div class="summary-chip">
                            <i class="fas fa-cube" style="color:var(--purple)"></i>
                            Entity: <strong><?php echo formatEntityLabel($entityFilter); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($dateFrom || $dateTo): ?>
                        <div class="summary-chip">
                            <i class="fas fa-calendar" style="color:var(--success)"></i>
                            <?php echo $dateFrom ? clean($dateFrom) : '...'; ?> &mdash; <?php echo $dateTo ? clean($dateTo) : '...'; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Activity Log List -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-stream" style="color:var(--gold-primary);margin-right:8px"></i>
                        Recent Activity
                        <span style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:8px">
                            (<?php echo $pagination['total']; ?> record<?php echo $pagination['total'] !== 1 ? 's' : ''; ?>)
                        </span>
                    </h3>
                </div>

                <?php if (empty($activities)): ?>
                    <div class="empty-activity">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No activity records found</h3>
                        <p>
                            <?php if ($searchQuery || $actionFilter || $entityFilter || $dateFrom || $dateTo): ?>
                                Try adjusting your filters or search terms.
                            <?php else: ?>
                                Activity will appear here as admins perform actions in the panel.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($activities as $activity):
                            $iconInfo = getActivityIcon($activity['action']);
                            $entityBadgeClass = 'entity-' . ($activity['entity_type'] ?? 'default');
                        ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $iconInfo['class']; ?>">
                                    <i class="fas <?php echo $iconInfo['icon']; ?>"></i>
                                </div>
                                <div class="activity-body">
                                    <div class="activity-text">
                                        <?php echo buildActivityDescription($activity); ?>
                                    </div>
                                    <div class="activity-meta">
                                        <?php if ($activity['entity_type']): ?>
                                            <span class="activity-entity-badge <?php echo $entityBadgeClass; ?>">
                                                <?php echo formatEntityLabel($activity['entity_type']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="activity-time">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                            &middot; <?php echo timeAgo($activity['created_at']); ?>
                                        </span>
                                        <?php if ($activity['ip_address']): ?>
                                            <span class="activity-ip">
                                                <i class="fas fa-globe"></i>
                                                <?php echo clean($activity['ip_address']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="card-footer">
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                            <div style="font-size:12px;color:var(--text-muted)">
                                Showing <?php echo (($pagination['page'] - 1) * $pagination['per_page']) + 1; ?>&ndash;<?php echo min($pagination['page'] * $pagination['per_page'], $pagination['total']); ?>
                                of <?php echo $pagination['total']; ?> records
                            </div>
                            <div class="pagination" style="margin-top:0">
                                <?php
                                    $queryParams = [];
                                    if ($searchQuery)  $queryParams['search'] = $searchQuery;
                                    if ($actionFilter) $queryParams['action_type'] = $actionFilter;
                                    if ($entityFilter) $queryParams['entity_type'] = $entityFilter;
                                    if ($dateFrom)     $queryParams['date_from'] = $dateFrom;
                                    if ($dateTo)       $queryParams['date_to'] = $dateTo;
                                    $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                                ?>

                                <?php if ($pagination['has_prev']): ?>
                                    <a href="activity.php?page=<?php echo $pagination['page'] - 1; ?><?php echo $queryString; ?>" class="page-btn">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="page-btn" disabled><i class="fas fa-chevron-left"></i></button>
                                <?php endif; ?>

                                <?php
                                    $startPage = max(1, $pagination['page'] - 2);
                                    $endPage = min($pagination['total_pages'], $pagination['page'] + 2);

                                    if ($startPage > 1): ?>
                                        <a href="activity.php?page=1<?php echo $queryString; ?>" class="page-btn">1</a>
                                        <?php if ($startPage > 2): ?>
                                            <span class="page-btn" style="border:none;background:none;color:var(--text-muted)">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                        <a href="activity.php?page=<?php echo $p; ?><?php echo $queryString; ?>"
                                           class="page-btn <?php echo $p === $pagination['page'] ? 'active' : ''; ?>">
                                            <?php echo $p; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($endPage < $pagination['total_pages']): ?>
                                        <?php if ($endPage < $pagination['total_pages'] - 1): ?>
                                            <span class="page-btn" style="border:none;background:none;color:var(--text-muted)">...</span>
                                        <?php endif; ?>
                                        <a href="activity.php?page=<?php echo $pagination['total_pages']; ?><?php echo $queryString; ?>" class="page-btn">
                                            <?php echo $pagination['total_pages']; ?>
                                        </a>
                                    <?php endif; ?>

                                <?php if ($pagination['has_next']): ?>
                                    <a href="activity.php?page=<?php echo $pagination['page'] + 1; ?><?php echo $queryString; ?>" class="page-btn">
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

<!-- Clear All Confirmation Modal -->
<div class="modal-overlay" id="clearConfirmModal">
    <div class="modal" style="max-width:440px;">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color:var(--danger);margin-right:8px"></i> Clear Activity Log</h3>
            <button class="modal-close" onclick="hideClearConfirm()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="confirm-modal-content">
                <div class="confirm-icon">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <h3>Are you sure?</h3>
                <p>This will permanently delete <strong>all</strong> activity log records. This action cannot be undone.</p>
                <div class="confirm-modal-actions">
                    <button class="btn btn-secondary" onclick="hideClearConfirm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <form method="POST" action="activity.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> Yes, Clear All
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showClearConfirm() {
    document.getElementById('clearConfirmModal').classList.add('show');
}

function hideClearConfirm() {
    document.getElementById('clearConfirmModal').classList.remove('show');
}

// Close modal on overlay click
document.getElementById('clearConfirmModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideClearConfirm();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideClearConfirm();
    }
});
</script>

</body>
</html>
