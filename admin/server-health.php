<?php
require_once __DIR__ . '/../includes/core/app.php';
requireAdminLogin();

$pageTitle = "Server Health";
require __DIR__ . '/includes/header.php';

// Basic Checks
$phpVersion = phpversion();
$phpVersionOk = version_compare($phpVersion, '7.4', '>=');

// DB Connection Check
$dbOk = false;
try {
    getDB();
    $dbOk = true;
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// Check for sensitive files
$sensitiveFiles = [
    __DIR__ . '/../config/db.php'
];
$publiclyAccessibleIssues = [];
// This check is a simulation. A real check would involve making an HTTP request.

// Directory Permissions
$pathsToCheck = [
    '../backups/' => 'Backups Directory',
    '../uploads/' => 'Uploads Directory',
];
$dirStatus = [];
foreach ($pathsToCheck as $path => $name) {
    $writable = is_writable($path);
    $dirStatus[] = [
        'name' => $name,
        'path' => realpath($path) ?: $path,
        'writable' => $writable
    ];
}

?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-heartbeat"></i> Server Health</h1>
        <p class="lead">Monitor the core components of your application infrastructure.</p>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fab fa-php"></i> PHP Environment</h4>
                </div>
                <div class="card-body">
                    <p><strong>Version:</strong> <?php echo $phpVersion; ?> 
                        <?php if ($phpVersionOk): ?>
                            <span class="badge badge-success">OK</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Outdated</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-database"></i> Database</h4>
                </div>
                <div class="card-body">
                     <?php if ($dbOk): ?>
                        <p class="text-success"><i class="fas fa-check-circle"></i> Connection successful.</p>
                    <?php else: ?>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Connection failed:</p>
                        <p class="text-monospace small"><?php echo htmlspecialchars($dbError); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h4><i class="fas fa-folder"></i> Directory Permissions</h4>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Directory</th>
                        <th>Path</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dirStatus as $status): ?>
                        <tr>
                            <td><?php echo $status['name']; ?></td>
                            <td><?php echo $status['path']; ?></td>
                            <td>
                                <?php if ($status['writable']): ?>
                                    <span class="badge badge-success">Writable</span>
                                <?php else: ?>
                                     <span class="badge badge-danger">Not Writable</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>