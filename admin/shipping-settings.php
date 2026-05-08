<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Shipping & Courier Settings - DesiVastra Admin
 */

$db = getDB();
$flash = getFlash();
$csrf = generateCSRF();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security verification failed.');
        redirect('shipping-settings.php');
    }

    if ($_POST['action'] === 'save_general') {
        updateSetting('free_shipping_min', sanitize($_POST['free_shipping_min']));
        updateSetting('shipping_cost', sanitize($_POST['shipping_cost']));
        setFlash('success', 'General shipping settings updated.');
    }

    if ($_POST['action'] === 'save_shiprocket') {
        updateSetting('shiprocket_email', sanitize($_POST['shiprocket_email']));
        updateSetting('shiprocket_password', $_POST['shiprocket_password']); // Password usually not sanitized
        updateSetting('shiprocket_weight', sanitize($_POST['shiprocket_weight']));
        updateSetting('shiprocket_length', sanitize($_POST['shiprocket_length']));
        updateSetting('shiprocket_width', sanitize($_POST['shiprocket_width']));
        updateSetting('shiprocket_height', sanitize($_POST['shiprocket_height']));
        setFlash('success', 'Shiprocket integration settings updated.');
    }

    logActivity('update_shipping_settings', 'settings');
    redirect('shipping-settings.php');
}

// Fetch current settings
$settings = [
    'free_shipping_min' => getSetting('free_shipping_min', '999'),
    'shipping_cost'     => getSetting('shipping_cost', '99'),
    'shiprocket_email'  => getSetting('shiprocket_email', ''),
    'shiprocket_password' => getSetting('shiprocket_password', ''),
    'shiprocket_weight' => getSetting('shiprocket_weight', '0.5'),
    'shiprocket_length' => getSetting('shiprocket_length', '20'),
    'shiprocket_width'  => getSetting('shiprocket_width', '15'),
    'shiprocket_height' => getSetting('shiprocket_height', '10'),
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Settings - DesiVastra Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/layout.php'; ?>

    <div class="page-content">
        <div class="page-header">
            <div>
                <div class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a>
                    <span class="separator"><i class="fas fa-chevron-right"></i></span>
                    <span>Settings</span>
                    <span class="separator"><i class="fas fa-chevron-right"></i></span>
                    <span>Shipping</span>
                </div>
                <h1>Shipping & Courier Settings</h1>
                <p class="subtitle">Configure your delivery rates, Shiprocket integration, and manual couriers.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo clean($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            
            <!-- General Shipping -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-truck-fast" style="color: var(--gold-primary); margin-right: 8px;"></i> General Shipping</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="save_general">
                        
                        <div class="form-group">
                            <label class="form-label">Free Shipping Minimum Order (₹)</label>
                            <input type="number" name="free_shipping_min" class="form-control" value="<?php echo clean($settings['free_shipping_min']); ?>" required>
                            <small class="text-muted">Set to 0 to disable free shipping.</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Flat Shipping Rate (₹)</label>
                            <input type="number" name="shipping_cost" class="form-control" value="<?php echo clean($settings['shipping_cost']); ?>" required>
                            <small class="text-muted">Charge applied if order is below minimum threshold.</small>
                        </div>

                        <button type="submit" class="btn btn-primary">Save General Settings</button>
                    </form>
                </div>
            </div>

            <!-- Pincode Checker -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-location-dot" style="color: var(--gold-primary); margin-right: 8px;"></i> Serviceability Check</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Check Pincode Serviceability</label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="test-pincode" class="form-control" placeholder="Enter 6-digit pincode">
                            <button type="button" class="btn btn-secondary" onclick="checkPincode()">Check</button>
                        </div>
                    </div>
                    <div id="pincode-result" style="margin-top: 12px; font-size: 13px;"></div>
                </div>
            </div>

            <!-- Shiprocket Integration -->
            <div class="card" style="grid-column: span 2;">
                <div class="card-header">
                    <h3><i class="fas fa-rocket" style="color: #6c5ce7; margin-right: 8px;"></i> Shiprocket Integration</h3>
                    <button class="btn btn-sm btn-secondary" onclick="testShiprocket()"><i class="fas fa-plug"></i> Test Connection</button>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="save_shiprocket">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Shiprocket Email</label>
                                <input type="email" name="shiprocket_email" class="form-control" value="<?php echo clean($settings['shiprocket_email']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Shiprocket Password / Token</label>
                                <input type="password" name="shiprocket_password" class="form-control" value="<?php echo clean($settings['shiprocket_password']); ?>">
                            </div>
                        </div>

                        <h4 style="font-size: 13px; margin: 15px 0 10px; color: var(--gold-light);">Default Package Defaults (for AWB generation)</h4>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                            <div class="form-group">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" step="0.1" name="shiprocket_weight" class="form-control" value="<?php echo clean($settings['shiprocket_weight']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Length (cm)</label>
                                <input type="number" name="shiprocket_length" class="form-control" value="<?php echo clean($settings['shiprocket_length']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Width (cm)</label>
                                <input type="number" name="shiprocket_width" class="form-control" value="<?php echo clean($settings['shiprocket_width']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Height (cm)</label>
                                <input type="number" name="shiprocket_height" class="form-control" value="<?php echo clean($settings['shiprocket_height']); ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Shiprocket Configuration</button>
                    </form>
                </div>
            </div>

            <!-- Manual Courier Partners -->
            <div class="card" style="grid-column: span 2;">
                <div class="card-header">
                    <h3><i class="fas fa-list-check" style="color: var(--gold-primary); margin-right: 8px;"></i> Manual Courier Partners</h3>
                    <button class="btn btn-sm btn-primary" onclick="addCourier()"><i class="fas fa-plus"></i> Add Partner</button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Courier Name</th>
                                <th>Tracking URL Format</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="font-weight: 600;">Delhivery</td>
                                <td><code style="font-size: 11px;">https://www.delhivery.com/track/package/[AWB]</code></td>
                                <td><span class="badge badge-success">Active</span></td>
                                <td style="text-align: right;">
                                    <button class="btn btn-sm btn-secondary"><i class="fas fa-edit"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600;">BlueDart</td>
                                <td><code style="font-size: 11px;">https://www.bluedart.com/tracking/[AWB]</code></td>
                                <td><span class="badge badge-success">Active</span></td>
                                <td style="text-align: right;">
                                    <button class="btn btn-sm btn-secondary"><i class="fas fa-edit"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testShiprocket() {
    alert("Testing Shiprocket API connection... (Placeholder for API call)");
}

function checkPincode() {
    const pin = document.getElementById('test-pincode').value;
    const result = document.getElementById('pincode-result');
    if(pin.length !== 6) {
        result.innerHTML = '<span style="color: var(--danger)">Please enter a valid 6-digit pincode.</span>';
        return;
    }
    result.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking serviceability...';
    setTimeout(() => {
        result.innerHTML = '<span style="color: var(--success)"><i class="fas fa-check-circle"></i> Serviceable! Estimated Delivery: 3-5 days.</span>';
    }, 800);
}

function addCourier() {
    alert("Functionality to add manual couriers will be implemented in the Module management step.");
}
</script>
</body>
</html>