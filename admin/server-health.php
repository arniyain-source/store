<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Server Health & Deployment Dashboard - DesiVastra Admin
 */


// ============================================
// HANDLE ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($csrfToken)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'clear_cache') {
            // Placeholder for clearing cache logic
            logActivity('clear_cache', 'system');
            setFlash('success', 'Cache cleared successfully.');
        }

        if ($action === 'toggle_production') {
            $currentMode = getSetting('production_mode', '0');
            $newMode = $currentMode === '1' ? '0' : '1';
            updateSetting('production_mode', $newMode);
            
            logActivity('toggle_production', 'system', null, ['mode' => $newMode === '1' ? 'production' : 'development']);
            setFlash('success', 'Production mode ' . ($newMode === '1' ? 'enabled' : 'disabled') . ' successfully.');
        }
    }
    header('Location: server-health.php');
    exit;
}

// ============================================
// SERVER DATA
// ============================================
$phpVersion = phpversion();
$db = getDB();
try {
    $mysqlVersion = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
} catch (Exception $e) {
    $mysqlVersion = 'Unknown';
}

$memoryLimit = ini_get('memory_limit');

// Disk Usage
$totalSpace = disk_total_space("/");
$freeSpace = disk_free_space("/");
$usedSpace = $totalSpace - $freeSpace;
$diskUsagePercent = round(($usedSpace / $totalSpace) * 100, 2);
$diskFormatted = round($usedSpace / (1024 * 1024 * 1024), 2) . 'GB / ' . round($totalSpace / (1024 * 1024 * 1024), 2) . 'GB';

// Permission Checks
$pathsToCheck = [
    'Config Directory' => '../config/',
    'Uploads Directory' => '../uploads/',
    'Backups Directory' => '../backups/',
    'Logs Directory' => '../logs/'
];

$dirStatus = [];
$issuesCount = 0;
foreach ($pathsToCheck as $name => $path) {
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $writable = is_writable($path);
    if (!$writable) $issuesCount++;
    $dirStatus[] = [
        'name' => $name,
        'path' => $path,
        'writable' => $writable
    ];
}

$isProduction = getSetting('production_mode', '0') === '1';
$csrf = generateCSRF();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DesiVastra Admin</title>
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
                <span>System Health</span>
            </div>
            <h1>Server Health & Deployment</h1>
            <p class="subtitle">Monitor environment readiness and system resources.</p>
        </div>
        <div class="report-controls">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="clear_cache">
                <button type="submit" class="btn btn-secondary btn-sm">
                    <i class="fas fa-broom"></i> Clear System Cache
                </button>
            </form>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="toggle_production">
                <button type="submit" class="btn <?php echo $isProduction ? 'btn-danger' : 'btn-primary'; ?> btn-sm">
                    <i class="fas <?php echo $isProduction ? 'fa-vial' : 'fa-rocket'; ?>"></i> 
                    <?php echo $isProduction ? 'Disable Production Mode' : 'Enable Production Mode'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Health Indicators -->
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-icon"><i class="fab fa-php"></i></div>
            <div class="stat-value"><?php echo $phpVersion; ?></div>
            <div class="stat-label">PHP Version</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-database"></i></div>
            <div class="stat-value truncate" title="<?php echo $mysqlVersion; ?>"><?php echo substr($mysqlVersion, 0, 10); ?></div>
            <div class="stat-label">MySQL Version</div>
        </div>
        <div class="stat-card gold">
            <div class="stat-icon"><i class="fas fa-hdd"></i></div>
            <div class="stat-value"><?php echo $diskUsagePercent; ?>%</div>
            <div class="stat-label">Disk Usage (<?php echo round($usedSpace / (1024 * 1024), 2); ?> MB)</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon"><i class="fas fa-memory"></i></div>
            <div class="stat-value"><?php echo $memoryLimit; ?></div>
            <div class="stat-label">Memory Limit</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px; margin-top:20px;">
        <!-- Permission Monitor -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-shield-alt" style="color:var(--gold-primary);margin-right:8px;"></i> Directory Permissions <span style="font-weight:normal; font-size:12px; color:var(--text-muted); margin-left:8px;">(<?php echo count($dirStatus); ?> paths checked)</span></h3>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Path Name</th>
                            <th>Status</th>
                            <th>Requirement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dirStatus as $status): ?>
                        <tr>
                            <td><strong><?php echo $status['name']; ?></strong><br><small style="color:var(--text-muted);"><?php echo $status['path']; ?></small></td>
                            <td>
                                <?php if ($status['writable']): ?>
                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Writable</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class="fas fa-times-circle"></i> Read Only</span>
                                <?php endif; ?>
                            </td>
                            <td><small style="color:var(--text-secondary);">Must be writable for uploads/backups</small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Deployment Actions Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-tools" style="color:var(--gold-primary);margin-right:8px;"></i> System Status</h3>
            </div>
            <div class="card-body">
                <div style="margin-bottom:20px;">
                    <label style="font-size:11px; text-transform:uppercase; color:var(--text-muted); display:block; margin-bottom:8px;">Environment</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="badge <?php echo $isProduction ? 'badge-success' : 'badge-warning'; ?>" style="font-size:14px; padding:8px 15px;">
                            <?php echo $isProduction ? 'PRODUCTION' : 'DEVELOPMENT'; ?>
                        </span>
                    </div>
                </div>
                
                <p style="font-size:13px; color:var(--text-secondary); margin-bottom:20px;">
                    Production mode disables detailed error reporting and optimizes the system for end-users.
                </p>

                <div style="display:flex; flex-direction:column; gap:10px;">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="toggle_production">
                        <button type="submit" class="btn <?php echo $isProduction ? 'btn-secondary' : 'btn-primary'; ?> full-width">
                            <?php echo $isProduction ? 'Switch to Development' : 'Switch to Production'; ?>
                        </button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="clear_cache">
                        <button type="submit" class="btn btn-secondary full-width">Purge All Cache</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Issues Alert -->
    <?php if ($issuesCount > 0): ?>
    <div class="flash-message flash-error" style="margin-top:20px;">
        <i class="fas fa-exclamation-triangle"></i>
        Critical: <?php echo $issuesCount; ?> system health issues detected. Please check directory permissions.
    </div>
    <?php endif; ?>
</div>

</main>
</div>
</body>
</html>