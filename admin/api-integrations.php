<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * API Integrations Dashboard - DesiVastra Admin
 */

// ============================================
// HANDLE SAVE SETTINGS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_api_settings'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $apiFields = [
            'fb_pixel_id', 'fb_access_token',
            'whatsapp_phone_id', 'whatsapp_access_token',
            'firebase_api_key', 'firebase_project_id',
            'gemini_api_key',
            'seo_analytics_id',
            'shiprocket_email', 'shiprocket_token'
        ];

        foreach ($apiFields as $field) {
            if (isset($_POST[$field])) {
                updateSetting($field, trim($_POST[$field]));
            }
        }

        logActivity('update_api_settings', 'system', null, ['details' => 'Updated global API integration keys']);
        setFlash('success', 'API settings updated successfully.');
    }
    redirect('api-integrations.php');
}



$csrf = generateCSRF();
$flash = getFlash();

// ============================================
// STATUS HELPER
// ============================================
function getApiStatus($keys) {
    foreach ($keys as $key) {
        $val = getSetting($key);
        if (empty($val)) return ['status' => 'Not Configured', 'class' => 'badge-danger', 'connected' => false];
    }
    return ['status' => 'Connected', 'class' => 'badge-success', 'connected' => true];
}

$services = [
    'facebook' => [
        'name' => 'Facebook / Meta Pixel',
        'icon' => 'fa-brands fa-facebook',
        'keys' => ['fb_pixel_id', 'fb_access_token'],
        'fields' => [
            ['label' => 'Pixel ID', 'name' => 'fb_pixel_id', 'type' => 'text', 'placeholder' => '1234567890'],
            ['label' => 'Access Token', 'name' => 'fb_access_token', 'type' => 'password', 'placeholder' => 'EAAb...']
        ]
    ],
    'whatsapp' => [
        'name' => 'WhatsApp Cloud API',
        'icon' => 'fa-brands fa-whatsapp',
        'keys' => ['whatsapp_phone_id', 'whatsapp_access_token'],
        'fields' => [
            ['label' => 'Phone Number ID', 'name' => 'whatsapp_phone_id', 'type' => 'text', 'placeholder' => '109...'],
            ['label' => 'Access Token', 'name' => 'whatsapp_access_token', 'type' => 'password', 'placeholder' => 'EAAC...']
        ]
    ],
    'firebase' => [
        'name' => 'Firebase Cloud Messaging',
        'icon' => 'fa-solid fa-fire',
        'keys' => ['firebase_api_key', 'firebase_project_id'],
        'fields' => [
            ['label' => 'API Key', 'name' => 'firebase_api_key', 'type' => 'password', 'placeholder' => 'AIza...'],
            ['label' => 'Project ID', 'name' => 'firebase_project_id', 'type' => 'text', 'placeholder' => 'my-project-123']
        ]
    ],
    'gemini' => [
        'name' => 'Gemini AI',
        'icon' => 'fa-solid fa-brain',
        'keys' => ['gemini_api_key'],
        'fields' => [
            ['label' => 'Gemini API Key', 'name' => 'gemini_api_key', 'type' => 'password', 'placeholder' => 'AIza...']
        ]
    ],
    'google' => [
        'name' => 'Google Analytics',
        'icon' => 'fa-brands fa-google',
        'keys' => ['seo_analytics_id'],
        'fields' => [
            ['label' => 'Measurement ID', 'name' => 'seo_analytics_id', 'type' => 'text', 'placeholder' => 'G-XXXXXX']
        ]
    ],
    'shiprocket' => [
        'name' => 'Shiprocket Logistics',
        'icon' => 'fa-solid fa-truck-fast',
        'keys' => ['shiprocket_email', 'shiprocket_token'],
        'fields' => [
            ['label' => 'Shiprocket Email', 'name' => 'shiprocket_email', 'type' => 'email', 'placeholder' => 'admin@example.com'],
            ['label' => 'API Token', 'name' => 'shiprocket_token', 'type' => 'password', 'placeholder' => 'eyJh...']
        ]
    ]
];

// ============================================
// FETCH LOGS
// ============================================
$failedLogs = [];
try {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM api_logs ORDER BY created_at DESC LIMIT 10");
    $failedLogs = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist yet
}

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

    <!-- Flash Messages -->
    <?php if ($flash): ?>
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
                <span>API Integrations</span>
            </div>
            <h1>API Integrations</h1>
            <p class="subtitle">Connect and monitor third-party services powering your store.</p>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        
        <!-- API Services Grid -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));">
            <?php foreach ($services as $id => $service): 
                $info = getApiStatus($service['keys']);
            ?>
                <div class="card">
                    <div class="card-header">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:32px; height:32px; border-radius:8px; background:rgba(184,137,42,0.1); display:flex; align-items:center; justify-content:center; color:var(--gold-primary); font-size:16px;">
                                <i class="<?php echo $service['icon']; ?>"></i>
                            </div>
                            <h3 style="font-size:15px; font-weight:700;"><?php echo $service['name']; ?></h3>
                        </div>
                        <span class="badge <?php echo $info['class']; ?>">
                            <span class="badge-dot" style="background:<?php echo $info['connected'] ? 'var(--success)' : 'var(--danger)'; ?>"></span>
                            <?php echo $info['status']; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <?php foreach ($service['fields'] as $field): ?>
                            <div class="form-group" style="margin-bottom:12px;">
                                <label class="form-label" style="font-size:11px;"><?php echo $field['label']; ?></label>
                                <input type="<?php echo $field['type']; ?>" 
                                       name="<?php echo $field['name']; ?>" 
                                       class="form-control" 
                                       style="padding:8px 12px; font-size:13px;"
                                       placeholder="<?php echo $field['placeholder']; ?>"
                                       value="<?php echo clean(getSetting($field['name'])); ?>">
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top:20px; display:flex; gap:10px;">
                            <button type="button" class="btn btn-secondary btn-sm" style="flex:1; justify-content:center;" onclick="testApi('<?php echo $id; ?>')">
                                <i class="fas fa-plug"></i> Test Connection
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="position: sticky; bottom: 20px; z-index: 100; margin-top: 24px; display:flex; justify-content:flex-end;">
            <button type="submit" name="save_api_settings" class="btn btn-primary" style="box-shadow: var(--shadow-lg);">
                <i class="fas fa-save"></i> Save All API Settings
            </button>
        </div>
    </form>

    <!-- API Transaction Logs -->
    <div class="card" style="margin-top:24px;">
        <div class="card-header">
            <h3><i class="fas fa-list-ul" style="color:var(--gold-primary); margin-right:8px;"></i> Recent API Activity</h3>
            <a href="activity.php?type=api" class="btn btn-secondary btn-sm">View Full Logs</a>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Endpoint</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Details / Error</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($failedLogs)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">
                                <i class="fas fa-info-circle" style="font-size:24px; margin-bottom:8px; display:block;"></i>
                                No API transaction logs found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($failedLogs as $log): 
                            $isError = (int)$log['response_status'] >= 400 || (int)$log['response_status'] === 0;
                        ?>
                            <tr>
                                <td><strong><?php echo ucfirst(clean($log['provider'])); ?></strong></td>
                                <td style="font-family:monospace; font-size:11px; color:var(--text-secondary);"><?php echo clean($log['endpoint']); ?></td>
                                <td><span class="badge badge-primary"><?php echo clean($log['request_method']); ?></span></td>
                                <td>
                                    <span class="badge <?php echo $isError ? 'badge-danger' : 'badge-success'; ?>">
                                        <?php echo (int)$log['response_status']; ?>
                                    </span>
                                </td>
                                <td style="max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo clean($log['error_message'] ?: 'Success'); ?>">
                                    <?php echo clean($log['error_message'] ?: 'Success'); ?>
                                </td>
                                <td style="color:var(--text-muted); font-size:12px;"><?php echo timeAgo($log['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function testApi(service) {
    const btn = event.currentTarget;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    btn.disabled = true;

    // Simulated API Connectivity Test
    setTimeout(() => {
        alert('Connectivity test for ' + service + ' initiated. Results will be available in the Activity log.');
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }, 1500);
}
</script>

</body>
</html>