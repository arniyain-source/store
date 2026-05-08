<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Module & Plugin Manager - DesiVastra Admin
 */

$db = getDB();

// 1. AJAX Action Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    header('Content-Type: application/json');
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
        exit;
    }

    $moduleKey = sanitize($_POST['module_key'] ?? '');
    $stmt = $db->prepare("SELECT is_enabled, name FROM modules WHERE module_key = ?");
    $stmt->execute([$moduleKey]);
    $module = $stmt->fetch();

    if ($module) {
        $newStatus = $module['is_enabled'] ? 0 : 1;
        $update = $db->prepare("UPDATE modules SET is_enabled = ? WHERE module_key = ?");
        $update->execute([$newStatus, $moduleKey]);
        
        logActivity(($newStatus ? 'enabled' : 'disabled') . '_module', 'module', null, ['module' => $module['name']]);
        
        echo json_encode(['success' => true, 'new_status' => $newStatus]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Module not found.']);
    }
    exit;
}

// 2. Finish Database Setup & Seeding
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `modules` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `module_key` VARCHAR(50) NOT NULL UNIQUE,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `version` VARCHAR(20) DEFAULT '1.0.0',
        `author` VARCHAR(100),
        `is_enabled` TINYINT(1) DEFAULT 0,
        `is_core` TINYINT(1) DEFAULT 0,
        `settings_url` VARCHAR(255) DEFAULT NULL,
        `last_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $stmt = $db->query("SELECT COUNT(*) as cnt FROM modules");
    if ($stmt->fetch()['cnt'] == 0) {
        $coreModules = [
            ['product_mgmt', 'Product Management', 'Core product catalog and inventory', '1.0.0', 'DesiVastra', 1, 1, 'products.php'],
            ['order_mgmt', 'Order Management', 'Processing, invoices, and shipping labels', '1.0.0', 'DesiVastra', 1, 1, 'orders.php'],
            ['payment_gateway', 'Payment Gateways', 'Razorpay, Cashfree, and COD integration', '1.0.0', 'DesiVastra', 1, 1, 'payment-settings.php'],
            ['shipping_logistics', 'Shipping & Logistics', 'Shiprocket and manual courier tracking', '1.0.0', 'DesiVastra', 1, 1, 'shipping-settings.php'],
            ['seo_pro', 'Advanced SEO', 'Sitemaps, Schema, and meta management', '1.0.0', 'DesiVastra', 1, 1, 'seo-settings.php'],
            ['ai_tools', 'AI Product Tools', 'AI product generation and visual search', '1.0.0', 'DesiVastra', 0, 1, 'ai-product-create.php'],
            ['ai_photo_search', 'AI Photo Search', 'Visual search using image matching', '1.0.0', 'DesiVastra', 0, 1, 'seo-settings.php']
        ];
        $insert = $db->prepare("INSERT INTO modules (module_key, name, description, version, author, is_enabled, is_core, settings_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($coreModules as $m) $insert->execute($m);
    }
} catch (Exception $e) {}

// Fetch all modules
$stmt = $db->query("SELECT * FROM modules ORDER BY is_core DESC, name ASC");
$modules = $stmt->fetchAll();

$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plugin / Module Manager - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .plugin-upload-zone { border: 2px dashed var(--glass-border); border-radius: var(--radius-md); padding: 30px; text-align: center; background: rgba(255,255,255,0.02); transition: var(--transition); margin-bottom: 30px; }
        .plugin-upload-zone:hover { border-color: var(--gold-primary); background: rgba(184,137,42,0.05); }
        .module-status { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; }
        .status-active { background: var(--success); box-shadow: 0 0 8px var(--success); }
        .status-inactive { background: #6b6b85; }
        .core-badge { font-size: 10px; background: rgba(184,137,42,0.15); color: var(--gold-primary); padding: 2px 8px; border-radius: 10px; border: 1px solid rgba(184,137,42,0.3); text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/layout.php'; ?>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-plug" style="color:var(--gold-primary);margin-right:8px"></i>Plugin / Module Manager</h1>
                <p class="subtitle">Extend your store functionality with plugins and modules</p>
            </div>
            <div class="report-controls">
                <button class="btn btn-primary btn-sm" onclick="document.getElementById('plugin-file').click()">
                    <i class="fas fa-upload"></i> Upload Plugin (.ZIP)
                </button>
                <input type="file" id="plugin-file" accept=".zip" style="display:none">
            </div>
        </div>

        <div class="plugin-upload-zone">
            <i class="fas fa-cloud-upload-alt fa-3x" style="color:var(--text-muted);margin-bottom:15px"></i>
            <h3>Install New Functionality</h3>
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:15px">Drag and drop your plugin ZIP file here or click the upload button above.</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Installed Modules (<?php echo count($modules); ?>)</h3>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Description</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modules as $m): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:12px">
                                    <div style="width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;color:var(--gold-primary)">
                                        <i class="fas fa-cube"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:6px">
                                            <?php echo clean($m['name']); ?>
                                            <?php if ($m['is_core']): ?>
                                                <span class="core-badge">Core</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size:11px;color:var(--text-muted)">By <?php echo clean($m['author']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:13px;color:var(--text-secondary)"><?php echo clean($m['description']); ?></td>
                            <td><code style="font-size:12px"><?php echo clean($m['version']); ?></code></td>
                            <td>
                                <div class="module-status" id="status-display-<?php echo $m['module_key']; ?>">
                                    <span class="status-dot <?php echo $m['is_enabled'] ? 'status-active' : 'status-inactive'; ?>"></span>
                                    <?php echo $m['is_enabled'] ? 'Active' : 'Inactive'; ?>
                                </div>
                            </td>
                            <td style="text-align:right">
                                <div class="action-btns" style="justify-content:flex-end">
                                    <button class="btn-icon <?php echo $m['is_enabled'] ? 'toggle-inactive' : 'toggle-active'; ?>" 
                                            onclick="toggleModule('<?php echo $m['module_key']; ?>')"
                                            id="btn-toggle-<?php echo $m['module_key']; ?>"
                                            title="<?php echo $m['is_enabled'] ? 'Disable' : 'Enable'; ?>">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                    <a href="<?php echo clean($m['settings_url'] ?? 'settings.php'); ?>" class="btn-icon" title="Settings">
                                        <i class="fas fa-cog"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <div style="font-size:12px;color:var(--text-muted)">
                    Showing <?php echo count($modules); ?> modules in total. All core modules are isolated and safe.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo $csrf; ?>';

function toggleModule(key) {
    const btn = document.getElementById('btn-toggle-' + key);
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('module_key', key);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('module-manager.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
        if (data.success) {
            const statusDisplay = document.getElementById('status-display-' + key);
            if (data.new_status == 1) {
                statusDisplay.innerHTML = '<span class="status-dot status-active"></span>Active';
                btn.className = 'btn-icon toggle-inactive';
                btn.title = 'Disable';
            } else {
                statusDisplay.innerHTML = '<span class="status-dot status-inactive"></span>Inactive';
                btn.className = 'btn-icon toggle-active';
                btn.title = 'Enable';
            }
        } else {
            alert(data.message || 'Failed to toggle module status.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
        alert('Network error. Please try again.');
    });
}
</script>
</body>
</html>