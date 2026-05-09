<?php
require_once __DIR__ . '/../includes/core/app.php';
requireAdminLogin();

$pageTitle = "Backup & Restore";
require __DIR__ . '/includes/header.php';

// Configuration
require __DIR__ . '/../config/db.php'; // for DB_HOST, DB_NAME, etc.
$backupDir = __DIR__ . '/../backups';

// Ensure backup directory exists
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // --- Create Backup --- 
        if ($_POST['action'] === 'backup') {
            $backupFile = $backupDir . '/backup-' . date('Y-m-d_H-i-s') . '.sql';
            $command = sprintf(
                'mysqldump -h %s -u %s -p%s %s > %s',
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASS),
                escapeshellarg(DB_NAME),
                escapeshellarg($backupFile)
            );
            
            exec($command, $output, $return_var);

            if ($return_var === 0) {
                $message = "Database backup created successfully!";
            } else {
                $error = "Error creating backup. Check server logs.";
            }
        }

        // --- Restore Backup ---
        if ($_POST['action'] === 'restore' && isset($_POST['backup_file'])) {
            $backupFile = $backupDir . '/' . basename($_POST['backup_file']); // Sanitize

            if (file_exists($backupFile)) {
                $command = sprintf(
                    'mysql -h %s -u %s -p%s %s < %s',
                    escapeshellarg(DB_HOST),
                    escapeshellarg(DB_USER),
                    escapeshellarg(DB_PASS),
                    escapeshellarg(DB_NAME),
                    escapeshellarg($backupFile)
                );

                exec($command, $output, $return_var);

                if ($return_var === 0) {
                    $message = "Database restored successfully!";
                } else {
                    $error = "Error restoring database. Check server logs.";
                }
            } else {
                $error = "Backup file not found.";
            }
        }
    }
}

// Get existing backups
$backupFiles = array_diff(scandir($backupDir, SCANDIR_SORT_DESCENDING), ['.', '..', '.gitkeep']);

?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-database"></i> Backup & Restore</h1>
        <p class="lead">Manage your database backups to ensure data safety.</p>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <!-- Create Backup -->
    <div class="card">
        <div class="card-header">
            <h4>Create New Backup</h4>
        </div>
        <div class="card-body">
            <p>Click the button below to create a full backup of your database.</p>
            <form action="backup-restore.php" method="POST">
                <input type="hidden" name="action" value="backup">
                <button type="submit" class="btn btn-primary">Create Backup Now</button>
            </form>
        </div>
    </div>

    <!-- Restore From Backup -->
    <div class="card mt-4">
        <div class="card-header">
            <h4>Restore from Backup</h4>
        </div>
        <div class="card-body">
            <form action="backup-restore.php" method="POST">
                 <input type="hidden" name="action" value="restore">
                 <div class="form-group">
                     <label for="backup_file">Select a backup file to restore:</label>
                     <select name="backup_file" id="backup_file" class="form-control">
                         <?php foreach ($backupFiles as $file): ?>
                             <option value="<?php echo htmlspecialchars($file); ?>"><?php echo htmlspecialchars($file); ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>
                 <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to restore the database? This will overwrite existing data.');">Restore Database</button>
            </form>
        </div>
    </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>