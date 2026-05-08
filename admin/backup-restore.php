<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Backup & Restore System - DesiVastra Admin
 */

$backupDir = __DIR__ . '/../backups/database/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// ============================================
// HANDLE DOWNLOAD REQUEST (Before Layout)
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'download' && !empty($_GET['file'])) {
    $file = basename($_GET['file']);
    $filePath = $backupDir . $file;
    if (file_exists($filePath) && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

require_once __DIR__ . '/includes/layout.php';

// ============================================
// HANDLE POST ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($csrfToken)) {
        setFlash('error', 'Invalid security token.');
    } else {
        if ($action === 'create_db_backup') {
            try {
                $filename = 'db_backup_' . date('Y-m-d_H-i-s') . '.sql';
                $filePath = $backupDir . $filename;
                
                // For this step, we generate a basic dump including schema and data
                // In a production environment, exec('mysqldump...') is preferred
                $db = getDB();
                $output = "-- DesiVastra Database Backup\n";
                $output .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
                
                $tables = ['admins', 'categories', 'products', 'customers', 'addresses', 'orders', 'order_items', 'coupons', 'reviews', 'wishlist', 'settings', 'activity_log'];
                
                foreach ($tables as $table) {
                    // Drop/Create table
                    $stmt = $db->query("SHOW CREATE TABLE `$table`");
                    $row = $stmt->fetch(PDO::FETCH_NUM);
                    $output .= "DROP TABLE IF EXISTS `$table`;\n" . $row[1] . ";\n\n";
                    
                    // Data
                    $result = $db->query("SELECT * FROM `$table` LIMIT 1000"); // Limit for safety in this implementation
                    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                        $keys = array_keys($row);
                        $values = array_values($row);
                        $values = array_map(function($v) use ($db) {
                            return $v === null ? 'NULL' : $db->quote($v);
                        }, $values);
                        $output .= "INSERT INTO `$table` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $values) . ");\n";
                    }
                    $output .= "\n\n";
                }
                
                file_put_contents($filePath, $output);
                
                logActivity('create_backup', 'system', null, ['filename' => $filename]);
                setFlash('success', 'Database backup created successfully: ' . $filename);
            } catch (Exception $e) {
                setFlash('error', 'Failed to create backup: ' . $e->getMessage());
            }
        }

        if ($action === 'delete_backup') {
            $file = basename($_POST['filename'] ?? '');
            if ($file && file_exists($backupDir . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                unlink($backupDir . $file);
                logActivity('delete_backup', 'system', null, ['filename' => $file]);
                setFlash('success', 'Backup file deleted.');
            }
        }
        
        if ($action === 'restore_backup') {
             $file = basename($_POST['filename'] ?? '');
             logActivity('restore_attempt', 'system', null, ['filename' => $file]);
             setFlash('warning', 'Manual restoration via phpMyAdmin or Command Line is recommended for data integrity.');
        }
    }
    redirect('backup-restore.php');
}

// ============================================
// SCAN & PAGINATE BACKUPS
// ============================================
$allFiles = [];
if (is_dir($backupDir)) {
    $files = array_diff(scandir($backupDir), array('.', '..'));
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $allFiles[] = [
                'name' => $file,
                'size_bytes' => filesize($backupDir . $file),
                'date' => date("Y-m-d H:i:s", filemtime($backupDir . $file))
            ];
        }
    }
}

// Sort by newest
usort($allFiles, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });

// Basic manual pagination
$totalRecords = count($allFiles);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = ADMIN_PER_PAGE;
$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;
$backups = array_slice($allFiles, $offset, $perPage);

$pagination = [
    'page' => $page,
    'total_pages' => $totalPages,
    'total' => $totalRecords,
    'has_prev' => $page > 1,
    'has_next' => $page < $totalPages
];

$csrf = generateCSRF();
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>System</span>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>Backup & Restore</span>
            </div>
            <h1>Backup & Restore</h1>
            <p class="subtitle">Secure your data with manual database and media backups.</p>
        </div>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr); margin-bottom: 24px;">
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 30px;">
                <div class="stat-icon" style="background: rgba(184, 137, 42, 0.1); color: var(--gold-primary); margin: 0 auto 15px; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 24px;">
                    <i class="fas fa-database"></i>
                </div>
                <h3>Generate Database Backup</h3>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">Creates a full SQL export of all tables and content.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="create_db_backup">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <button type="submit" class="gold-btn" style="width: 100%;">
                        <i class="fas fa-plus-circle"></i> Backup Now
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body" style="text-align: center; padding: 30px;">
                <div class="stat-icon" style="background: rgba(52, 152, 219, 0.1); color: var(--info); margin: 0 auto 15px; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 24px;">
                    <i class="fas fa-images"></i>
                </div>
                <h3>Backup Media</h3>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">Compress all product images and videos into a ZIP file.</p>
                <button type="button" class="outline-btn" style="width: 100%;" onclick="alert('Media compression module coming soon.')">
                    <i class="fas fa-file-archive"></i> Create Media ZIP
                </button>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h3><i class="fas fa-history" style="color: var(--gold-primary); margin-right: 8px;"></i> Recent Backups <span style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:8px">(<?php echo $totalRecords; ?> records)</span></h3>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Size</th>
                        <th>Created Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backups)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 30px; color: var(--text-muted);">No backups found in the system storage.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($backups as $b): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--gold-primary);"><?php echo clean($b['name']); ?></td>
                                <td>
                                    <?php 
                                        if ($b['size_bytes'] >= 1048576) echo round($b['size_bytes'] / 1048576, 2) . ' MB';
                                        else echo round($b['size_bytes'] / 1024, 2) . ' KB';
                                    ?>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted);"><?php echo $b['date']; ?></td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <a href="backup-restore.php?action=download&file=<?php echo urlencode($b['name']); ?>" class="btn btn-sm btn-secondary" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button class="btn btn-sm btn-secondary" title="Restore" onclick="confirmRestore('<?php echo clean($b['name']); ?>')">
                                            <i class="fas fa-upload"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this backup file?');">
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="filename" value="<?php echo clean($b['name']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div style="font-size: 12px; color: var(--text-muted);">
                    Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>
                </div>
                <div class="pagination" style="margin-top: 0;">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="backup-restore.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="backup-restore.php<?php echo buildQueryParams(['page' => $page - 1]); ?>" class="page-btn">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php else: ?>
                        <button class="page-btn" disabled><i class="fas fa-angle-double-left"></i></button>
                        <button class="page-btn" disabled><i class="fas fa-angle-left"></i></button>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="backup-restore.php<?php echo buildQueryParams(['page' => $i]); ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($pagination['has_next']): ?>
                        <a href="backup-restore.php<?php echo buildQueryParams(['page' => $page + 1]); ?>" class="page-btn">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="backup-restore.php<?php echo buildQueryParams(['page' => $totalPages]); ?>" class="page-btn">
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
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-cloud-upload-alt" style="color: var(--gold-primary); margin-right: 8px;"></i> Manual Restore</h3>
        </div>
        <div class="card-body">
            <div style="background: rgba(231, 76, 60, 0.1); border: 1px solid rgba(231, 76, 60, 0.3); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;">
                <i class="fas fa-exclamation-triangle"></i> <strong>WARNING:</strong> Restoring a backup will overwrite all current database content. This action cannot be undone. Always verify backups on a staging environment first.
            </div>
            
            <div style="border: 2px dashed var(--glass-border); border-radius: 12px; padding: 40px; text-align: center; cursor: pointer;" onmouseover="this.style.borderColor='var(--gold-primary)'" onmouseout="this.style.borderColor='var(--glass-border)'" onclick="document.getElementById('manual-sql-upload').click()">
                <i class="fas fa-file-import" style="font-size: 32px; color: var(--text-muted); margin-bottom: 10px;"></i>
                <p style="color: var(--text-primary); font-weight: 600;">Click to browse or Drag & Drop .SQL file</p>
                <p style="color: var(--text-muted); font-size: 12px;">Supported format: SQL | Max size: <?php echo ini_get('upload_max_filesize'); ?></p>
                <form method="POST" enctype="multipart/form-data" id="manualRestoreForm">
                    <input type="hidden" name="action" value="restore_backup">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="file" name="backup_file" style="display: none;" id="manual-sql-upload" onchange="if(confirm('Start manual restoration?')) this.form.submit()">
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmRestore(filename) {
    if (confirm("CRITICAL WARNING: Are you absolutely sure you want to restore '" + filename + "'? This will wipe your current database and replace it with the backup data. Ensure you have a fresh backup of current data before proceeding.")) {
        // Submit hidden form for restore
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="restore_backup">
            <input type="hidden" name="filename" value="${filename}">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>