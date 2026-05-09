<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api-logger.php';
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
            'google_analytics_id', 'google_maps_api_key', 'google_oauth_client_id',
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

$fbStatus       = getApiStatus(['fb_pixel_id', 'fb_access_token']);
$waStatus       = getApiStatus(['whatsapp_phone_id', 'whatsapp_access_token']);
$firebaseStatus = getApiStatus(['firebase_api_key', 'firebase_project_id']);
$geminiStatus   = getApiStatus(['gemini_api_key']);
$googleStatus   = getApiStatus(['google_analytics_id', 'google_maps_api_key']);
$shippingStatus = getApiStatus(['shiprocket_email', 'shiprocket_token']);

$totalApis = 6;
$connectedApis = count(array_filter([$fbStatus, $waStatus, $firebaseStatus, $geminiStatus, $googleStatus, $shippingStatus], function($s) { return $s['connected']; }));
$percentConnected = $totalApis > 0 ? ($connectedApis / $totalApis) * 100 : 0;


// ============================================
// FETCH LOGS
// ============================================
$logApi = new ApiLogger();
$failedLogs = $logApi->getLogs(5, 'failed');
$allLogs = $logApi->getLogs(10, 'all');


// ============================================
// PAGE META
// ============================================
$pageTitle = 'API & Service Integrations';
require_once 'includes/layout.php';

?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">API Integrations</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">API & Service Integrations</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $flash['message']; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Connection Status -->
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">Connection Status</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentConnected; ?>%;" aria-valuenow="<?php echo $percentConnected; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo round($percentConnected); ?>%</div>
                        </div>
                        <p class="mt-2 text-muted"><?php echo "$connectedApis of $totalApis essential services connected."; ?></p>
                    </div>
                    <div class="col-md-4 d-flex justify-content-end align-items-center">
                        <button class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-check-circle"></i> Run Health Check</button>
                        <a href="#api-settings" class="btn btn-sm btn-primary"><i class="fas fa-cogs"></i> Manage APIs</a>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="api-integrations.php" id="api-settings">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <div class="row">
                <!-- Left column -->
                <div class="col-lg-6">
                    <!-- Facebook & WhatsApp -->
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fab fa-facebook-square"></i> Meta / Facebook</h3>
                            <div class="card-tools">
                                <span class="badge <?php echo $fbStatus['class']; ?>"><?php echo $fbStatus['status']; ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="fb_pixel_id">Facebook Pixel ID</label>
                                <input type="text" class="form-control" id="fb_pixel_id" name="fb_pixel_id" value="<?php echo clean(getSetting('fb_pixel_id')); ?>" placeholder="e.g., 1234567890123456">
                            </div>
                            <div class="form-group">
                                <label for="fb_access_token">Conversion API Access Token</label>
                                <input type="password" class="form-control" id="fb_access_token" name="fb_access_token" value="<?php echo clean(getSetting('fb_access_token')); ?>" placeholder="e.g., EAA... (kept secure)">
                            </div>
                        </div>
                    </div>
                    
                    <div class="card card-outline card-success">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fab fa-whatsapp"></i> WhatsApp Business</h3>
                             <div class="card-tools">
                                <span class="badge <?php echo $waStatus['class']; ?>"><?php echo $waStatus['status']; ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="whatsapp_phone_id">WhatsApp Phone Number ID</label>
                                <input type="text" class="form-control" id="whatsapp_phone_id" name="whatsapp_phone_id" value="<?php echo clean(getSetting('whatsapp_phone_id')); ?>" placeholder="e.g., 109876543210987">
                            </div>
                            <div class="form-group">
                                <label for="whatsapp_access_token">Permanent Access Token</label>
                                <input type="password" class="form-control" id="whatsapp_access_token" name="whatsapp_access_token" value="<?php echo clean(getSetting('whatsapp_access_token')); ?>" placeholder="e.g., EAA... (kept secure)">
                            </div>
                        </div>
                    </div>

                    <!-- AI & Machine Learning -->
                    <div class="card card-outline card-purple">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-robot"></i> AI & Machine Learning</h3>
                            <div class="card-tools">
                                <span class="badge <?php echo $geminiStatus['class']; ?>"><?php echo $geminiStatus['status']; ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="gemini_api_key">Google AI (Gemini) API Key</label>
                                <input type="password" class="form-control" id="gemini_api_key" name="gemini_api_key" value="<?php echo clean(getSetting('gemini_api_key')); ?>" placeholder="For AI-powered features (e.g., product descriptions)">
                            </div>
                            <p class="text-sm text-muted">Used for: AI Product Description Generator, Smart Replies, etc.</p>
                        </div>
                    </div>

                </div>
                <!-- Right column -->
                <div class="col-lg-6">
                    <!-- Google Services -->
                    <div class="card card-outline card-danger">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fab fa-google"></i> Google Services</h3>
                            <div class="card-tools">
                                <span class="badge <?php echo $googleStatus['class']; ?>"><?php echo $googleStatus['status']; ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="google_analytics_id">Google Analytics ID</label>
                                <input type="text" class="form-control" id="google_analytics_id" name="google_analytics_id" value="<?php echo clean(getSetting('google_analytics_id')); ?>" placeholder="e.g., G-XXXXXXXXXX">
                            </div>
                            <div class="form-group">
                                <label for="google_maps_api_key">Google Maps API Key</label>
                                <input type="password" class="form-control" id="google_maps_api_key" name="google_maps_api_key" value="<?php echo clean(getSetting('google_maps_api_key')); ?>" placeholder="For address validation, maps, etc.">
                            </div>
                             <div class="form-group">
                                <label for="google_oauth_client_id">Google OAuth Client ID</label>
                                <input type="password" class="form-control" id="google_oauth_client_id" name="google_oauth_client_id" value="<?php echo clean(getSetting('google_oauth_client_id')); ?>" placeholder="For 'Sign in with Google' feature.">
                            </div>
                        </div>
                    </div>
                    
                    <div class="card card-outline card-warning">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-truck"></i> Logistics & Shipping</h3>
                            <div class="card-tools">
                                <span class="badge <?php echo $shippingStatus['class']; ?>"><?php echo $shippingStatus['status']; ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="shiprocket_email">ShipRocket Registered Email</label>
                                <input type="email" class="form-control" id="shiprocket_email" name="shiprocket_email" value="<?php echo clean(getSetting('shiprocket_email')); ?>" placeholder="e.g., user@example.com">
                            </div>
                            <div class="form-group">
                                <label for="shiprocket_token">ShipRocket API Token</label>
                                <input type="password" class="form-control" id="shiprocket_token" name="shiprocket_token" value="<?php echo clean(getSetting('shiprocket_token')); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Firebase -->
                    <div class="card card-outline card-warning">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fire"></i> Firebase</h3>
                             <div class="card-tools">
                                <span class="badge <?php echo $firebaseStatus['class']; ?>"><?php echo $firebaseStatus['status']; ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="firebase_api_key">Firebase Web API Key</p></label>
                                <input type="password" class="form-control" id="firebase_api_key" name="firebase_api_key" value="<?php echo clean(getSetting('firebase_api_key')); ?>" placeholder="For push notifications, remote config etc.">
                            </div>
                            <div class="form-group">
                                <label for="firebase_project_id">Firebase Project ID</label>
                                <input type="text" class="form-control" id="firebase_project_id" name="firebase_project_id" value="<?php echo clean(getSetting('firebase_project_id')); ?>" placeholder="e.g., your-project-12345">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-3 text-center">
                <button type="submit" name="save_api_settings" class="btn btn-lg btn-primary"><i class="fas fa-save"></i> Save All API Settings</button>
            </div>
        </form>

        <!-- API Logs Section -->
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-secondary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history"></i> Recent API Transaction Logs</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th style="width:40%">Endpoint</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Duration</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allLogs)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center p-4 text-muted">
                                            <i class="fas fa-info-circle" style="font-size:24px; margin-bottom:8px; display:block;"></i>
                                            No recent API transaction logs found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($allLogs as $log): 
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
                                            <td><?php echo round($log['duration'] * 1000); ?> ms</td>
                                            <td><?php echo timeAgo($log['created_at']); ?></td>
                                            <td>
                                                <button class="btn btn-xs btn-outline-info" onclick="viewLog(<?php echo htmlspecialchars(json_encode($log), ENT_QUOTES, 'UTF-8'); ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
    </div><!-- /.container-fluid -->
</section>

<!-- Log Viewer Modal -->
<div class="modal fade" id="logViewerModal" tabindex="-1" role="dialog" aria-labelledby="logViewerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logViewerModalLabel">API Log Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre id="log-details-content" style="white-space: pre-wrap; word-wrap: break-word;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
function viewLog(logData) {
    // Pretty print the JSON details
    const formatted = JSON.stringify(logData, null, 2);
    document.getElementById('log-details-content').textContent = formatted;
    $('#logViewerModal').modal('show');
}
</script>

<?php require_once 'includes/footer.php'; ?>
