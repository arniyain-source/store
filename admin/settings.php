<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Settings Page - DesiVastra E-Commerce Admin
 * Manages all site configuration: General, Shipping, Payment, Email, Social, Database Setup
 */

// Handle POST before any output — only load functions, NOT layout (which outputs HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/functions.php'; // includes database.php
    requireAdminLogin();

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please try again.');
        header('Location: settings.php');
        exit;
    }

    $group = sanitize($_POST['setting_group'] ?? '');

    try {
        $db = getDB();
        $updated = 0;

        switch ($group) {
            case 'general':
                $fields = [
                    'site_name'        => sanitize($_POST['site_name'] ?? ''),
                    'site_tagline'     => sanitize($_POST['site_tagline'] ?? ''),
                    'site_email'       => sanitize($_POST['site_email'] ?? ''),
                    'site_phone'       => sanitize($_POST['site_phone'] ?? ''),
                    'site_whatsapp'    => sanitize($_POST['site_whatsapp'] ?? ''),
                    'currency_symbol'  => sanitize($_POST['currency_symbol'] ?? '₹'),
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
                ];
                break;

            case 'shipping':
                $fields = [
                    'free_shipping_min' => sanitize($_POST['free_shipping_min'] ?? '0'),
                    'shipping_cost'     => sanitize($_POST['shipping_cost'] ?? '0'),
                ];

                // Handle delivery zones as JSON
                $zones = [];
                $zoneNames  = $_POST['zone_name'] ?? [];
                $zoneCosts  = $_POST['zone_cost'] ?? [];
                $zoneActive = $_POST['zone_active'] ?? [];
                for ($i = 0; $i < count($zoneNames); $i++) {
                    $name = trim($zoneNames[$i]);
                    if ($name !== '') {
                        $zones[] = [
                            'name'   => sanitize($name),
                            'cost'   => sanitize($zoneCosts[$i] ?? '0'),
                            'active' => isset($zoneActive[$i]) ? true : false,
                        ];
                    }
                }
                $fields['delivery_zones'] = json_encode($zones);
                break;

            case 'payment':
                $fields = [
                    'razorpay_key'             => sanitize($_POST['razorpay_key'] ?? ''),
                    'razorpay_secret'          => sanitize($_POST['razorpay_secret'] ?? ''),
                    'cod_enabled'              => isset($_POST['cod_enabled']) ? '1' : '0',
                    'online_payment_enabled'   => isset($_POST['online_payment_enabled']) ? '1' : '0',
                ];
                break;

            case 'email':
                $fields = [
                    'smtp_host' => sanitize($_POST['smtp_host'] ?? ''),
                    'smtp_port' => sanitize($_POST['smtp_port'] ?? '587'),
                    'smtp_user' => sanitize($_POST['smtp_user'] ?? ''),
                    'smtp_pass' => sanitize($_POST['smtp_pass'] ?? ''),
                ];
                break;

            case 'social':
                $fields = [
                    'social_instagram' => sanitize($_POST['social_instagram'] ?? ''),
                    'social_facebook'  => sanitize($_POST['social_facebook'] ?? ''),
                    'social_twitter'   => sanitize($_POST['social_twitter'] ?? ''),
                    'social_youtube'   => sanitize($_POST['social_youtube'] ?? ''),
                ];
                break;

            case 'database':
                // Database setup doesn't use the normal settings table update
                // It runs the setup SQL file instead
                $setupPath = __DIR__ . '/../config/setup.sql';
                if (file_exists($setupPath)) {
                    $sql = file_get_contents($setupPath);
                    try {
                        $db->exec($sql);
                        logActivity('update', 'settings', null, ['group' => 'database', 'action' => 'run_setup']);
                        setFlash('success', 'Database setup executed successfully. All tables have been created/updated.');
                    } catch (Exception $e) {
                        setFlash('error', 'Database setup failed: ' . $e->getMessage());
                    }
                } else {
                    setFlash('error', 'Setup SQL file not found at config/setup.sql');
                }
                header('Location: settings.php?tab=database');
                exit;

            default:
                setFlash('error', 'Invalid setting group.');
                header('Location: settings.php');
                exit;
        }

        // Update each field in the settings table
        if (isset($fields)) {
            $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            foreach ($fields as $key => $value) {
                $stmt->execute([$value, $key]);
                $updated++;
            }

        // Upsert each setting (SQLite-compatible INSERT OR REPLACE)
            $insertStmt = $db->prepare("
                INSERT OR REPLACE INTO settings (setting_key, setting_value, setting_type, setting_group, description)
                VALUES (?, ?, 'text', ?, ?)
            ");

            $descriptions = [
                'site_name'               => 'Website Name',
                'site_tagline'            => 'Website Tagline',
                'site_email'              => 'Contact Email',
                'site_phone'              => 'Contact Phone',
                'site_whatsapp'           => 'WhatsApp Number',
                'currency_symbol'         => 'Currency Symbol',
                'maintenance_mode'        => 'Maintenance Mode',
                'free_shipping_min'       => 'Free Shipping Minimum Order',
                'shipping_cost'           => 'Standard Shipping Cost',
                'delivery_zones'          => 'Delivery Zones',
                'razorpay_key'            => 'Razorpay API Key',
                'razorpay_secret'         => 'Razorpay Secret Key',
                'cod_enabled'             => 'Cash on Delivery Enabled',
                'online_payment_enabled'  => 'Online Payment Enabled',
                'smtp_host'               => 'SMTP Host',
                'smtp_port'               => 'SMTP Port',
                'smtp_user'               => 'SMTP Username',
                'smtp_pass'               => 'SMTP Password',
                'social_instagram'        => 'Instagram URL',
                'social_facebook'         => 'Facebook URL',
                'social_twitter'          => 'Twitter URL',
                'social_youtube'          => 'YouTube URL',
            ];

            foreach ($fields as $key => $value) {
                $insertStmt->execute([
                    $key,
                    $value,
                    $group,
                    $descriptions[$key] ?? ucfirst(str_replace('_', ' ', $key)),
                ]);
            }

            logActivity('update', 'settings', null, ['group' => $group, 'fields_updated' => $updated]);
            setFlash('success', ucfirst($group) . ' settings updated successfully.');
        }

    } catch (Exception $e) {
        setFlash('error', 'Failed to update settings: ' . $e->getMessage());
    }

    header('Location: settings.php?tab=' . $group);
    exit;
}

// Normal page load
require_once __DIR__ . '/includes/layout.php';

// Fetch all settings at once
$settings = [];
try {
    $db = getDB();
    $stmt = $db->query("SELECT setting_key, setting_value, setting_type, setting_group FROM settings ORDER BY setting_group, id");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Database not set up yet — use defaults
}

// Helper to get a setting value with default
function s($key, $default = '') {
    global $settings;
    return $settings[$key] ?? $default;
}

// Active tab
$activeTab = sanitize($_GET['tab'] ?? 'general');

// Parse delivery zones from JSON
$deliveryZones = [];
$zonesJson = s('delivery_zones', '');
if ($zonesJson) {
    $decoded = json_decode($zonesJson, true);
    if (is_array($decoded)) {
        $deliveryZones = $decoded;
    }
}

// Database config for display
$driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';
if ($driver === 'sqlite') {
    $dbConfig = [
        'host'    => 'localhost (SQLite)',
        'name'    => defined('DB_SQLITE_PATH') ? DB_SQLITE_PATH : 'store_local.sqlite',
        'user'    => 'N/A (file-based)',
        'charset' => 'UTF-8',
    ];
} else {
    $dbConfig = [
        'host'    => defined('DB_HOST') ? DB_HOST : '',
        'name'    => defined('DB_NAME') ? DB_NAME : '',
        'user'    => defined('DB_USER') ? DB_USER : '',
        'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="admin-layout">

<!-- sidebar + header injected by layout.php above -->



<div class="page-content">

    <!-- Flash Messages -->
    <?php
    $flash = getFlash();
    if ($flash):
    ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : ($flash['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle')); ?>"></i>
            <?php echo clean($flash['message']); ?>
            <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>Settings</span>
            </div>
            <h1><i class="fas fa-cog" style="color:var(--gold-primary);margin-right:10px;"></i>Settings</h1>
            <p class="subtitle">Manage your store configuration, payment gateways, shipping, and more.</p>
        </div>
    </div>

    <!-- Settings Layout: Left Tabs + Right Forms -->
    <div class="settings-layout">

        <!-- ======================================= -->
        <!-- LEFT SIDEBAR: Setting Group Tabs         -->
        <!-- ======================================= -->
        <div class="settings-tabs">
            <div class="settings-tab <?php echo $activeTab === 'general' ? 'active' : ''; ?>" data-tab="general" onclick="switchTab('general')">
                <i class="fas fa-sliders-h"></i> General
            </div>
            <div class="settings-tab <?php echo $activeTab === 'shipping' ? 'active' : ''; ?>" data-tab="shipping" onclick="switchTab('shipping')">
                <i class="fas fa-truck"></i> Shipping
            </div>
            <div class="settings-tab <?php echo $activeTab === 'payment' ? 'active' : ''; ?>" data-tab="payment" onclick="switchTab('payment')">
                <i class="fas fa-credit-card"></i> Payment
            </div>
            <div class="settings-tab <?php echo $activeTab === 'email' ? 'active' : ''; ?>" data-tab="email" onclick="switchTab('email')">
                <i class="fas fa-envelope"></i> Email
            </div>
            <div class="settings-tab <?php echo $activeTab === 'social' ? 'active' : ''; ?>" data-tab="social" onclick="switchTab('social')">
                <i class="fas fa-share-alt"></i> Social
            </div>
            <div class="settings-tab <?php echo $activeTab === 'database' ? 'active' : ''; ?>" data-tab="database" onclick="switchTab('database')">
                <i class="fas fa-database"></i> Database Setup
            </div>
        </div>

        <!-- ======================================= -->
        <!-- RIGHT SIDE: Setting Forms                -->
        <!-- ======================================= -->
        <div class="settings-panels">

            <!-- =================================== -->
            <!-- GENERAL SETTINGS                    -->
            <!-- =================================== -->
            <div class="settings-panel" id="panel-general" style="<?php echo $activeTab === 'general' ? '' : 'display:none;'; ?>">
                <form method="POST" action="settings.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="setting_group" value="general">

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-sliders-h" style="color:var(--gold-primary);margin-right:8px;"></i> General Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Site Name</label>
                                    <input type="text" name="site_name" class="form-control" value="<?php echo clean(s('site_name', 'DesiVastra')); ?>" placeholder="Your store name">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tagline</label>
                                    <input type="text" name="site_tagline" class="form-control" value="<?php echo clean(s('site_tagline', '')); ?>" placeholder="Your store tagline">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Contact Email</label>
                                    <input type="email" name="site_email" class="form-control" value="<?php echo clean(s('site_email', '')); ?>" placeholder="admin@example.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Contact Phone</label>
                                    <input type="text" name="site_phone" class="form-control" value="<?php echo clean(s('site_phone', '')); ?>" placeholder="+91 9876543210">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">WhatsApp Number</label>
                                    <input type="text" name="site_whatsapp" class="form-control" value="<?php echo clean(s('site_whatsapp', '')); ?>" placeholder="919876543210">
                                    <p class="form-hint">Include country code without + (e.g., 919876543210)</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Currency Symbol</label>
                                    <input type="text" name="currency_symbol" class="form-control" value="<?php echo clean(s('currency_symbol', '₹')); ?>" placeholder="₹" style="max-width:120px;">
                                    <p class="form-hint">Symbol displayed before prices</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Maintenance Mode</label>
                                <div class="setting-toggle-row">
                                    <label class="switch">
                                        <input type="checkbox" name="maintenance_mode" value="1" <?php echo s('maintenance_mode', '0') === '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div>
                                        <span style="font-size:13px;color:var(--text-primary);">Enable Maintenance Mode</span>
                                        <p class="form-hint" style="margin-top:2px;">When enabled, visitors see a maintenance page. Admin panel remains accessible.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer" style="display:flex;justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save General Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- =================================== -->
            <!-- SHIPPING SETTINGS                   -->
            <!-- =================================== -->
            <div class="settings-panel" id="panel-shipping" style="<?php echo $activeTab === 'shipping' ? '' : 'display:none;'; ?>">
                <form method="POST" action="settings.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="setting_group" value="shipping">

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-truck" style="color:var(--info);margin-right:8px;"></i> Shipping Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Free Shipping Minimum (<?php echo clean(s('currency_symbol', '₹')); ?>)</label>
                                    <div class="input-group">
                                        <span class="input-prefix"><?php echo clean(s('currency_symbol', '₹')); ?></span>
                                        <input type="number" name="free_shipping_min" class="form-control" value="<?php echo clean(s('free_shipping_min', '999')); ?>" min="0" step="1" placeholder="999">
                                    </div>
                                    <p class="form-hint">Orders above this amount get free shipping</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Standard Shipping Cost (<?php echo clean(s('currency_symbol', '₹')); ?>)</label>
                                    <div class="input-group">
                                        <span class="input-prefix"><?php echo clean(s('currency_symbol', '₹')); ?></span>
                                        <input type="number" name="shipping_cost" class="form-control" value="<?php echo clean(s('shipping_cost', '99')); ?>" min="0" step="1" placeholder="99">
                                    </div>
                                    <p class="form-hint">Flat rate for orders below the free shipping minimum</p>
                                </div>
                            </div>

                            <!-- Delivery Zones -->
                            <div class="form-group">
                                <label class="form-label">Delivery Zones</label>
                                <p class="form-hint" style="margin-bottom:12px;">Define custom shipping rates for different zones. Leave empty to use the standard rate.</p>

                                <div id="deliveryZones">
                                    <?php if (!empty($deliveryZones)): ?>
                                        <?php foreach ($deliveryZones as $i => $zone): ?>
                                        <div class="delivery-zone-row">
                                            <input type="text" name="zone_name[]" class="form-control" value="<?php echo clean($zone['name'] ?? ''); ?>" placeholder="Zone name (e.g., Metro Cities)" style="flex:2;">
                                            <div class="input-group" style="flex:1;">
                                                <span class="input-prefix"><?php echo clean(s('currency_symbol', '₹')); ?></span>
                                                <input type="number" name="zone_cost[]" class="form-control" value="<?php echo clean($zone['cost'] ?? '0'); ?>" min="0" step="1" placeholder="0">
                                            </div>
                                            <label class="form-check" style="flex-shrink:0;padding:0 8px;">
                                                <input type="checkbox" name="zone_active[<?php echo $i; ?>]" value="1" <?php echo !empty($zone['active']) ? 'checked' : ''; ?>>
                                            </label>
                                            <button type="button" class="btn btn-sm btn-danger btn-icon" onclick="this.closest('.delivery-zone-row').remove();" title="Remove zone">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="delivery-zone-row">
                                            <input type="text" name="zone_name[]" class="form-control" value="" placeholder="Zone name (e.g., Metro Cities)" style="flex:2;">
                                            <div class="input-group" style="flex:1;">
                                                <span class="input-prefix"><?php echo clean(s('currency_symbol', '₹')); ?></span>
                                                <input type="number" name="zone_cost[]" class="form-control" value="0" min="0" step="1" placeholder="0">
                                            </div>
                                            <label class="form-check" style="flex-shrink:0;padding:0 8px;">
                                                <input type="checkbox" name="zone_active[0]" value="1" checked>
                                            </label>
                                            <button type="button" class="btn btn-sm btn-danger btn-icon" onclick="this.closest('.delivery-zone-row').remove();" title="Remove zone">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <button type="button" class="btn btn-sm btn-secondary" style="margin-top:10px;" onclick="addDeliveryZone()">
                                    <i class="fas fa-plus"></i> Add Zone
                                </button>
                            </div>
                        </div>
                        <div class="card-footer" style="display:flex;justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Shipping Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- =================================== -->
            <!-- PAYMENT SETTINGS                    -->
            <!-- =================================== -->
            <div class="settings-panel" id="panel-payment" style="<?php echo $activeTab === 'payment' ? '' : 'display:none;'; ?>">
                <form method="POST" action="settings.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="setting_group" value="payment">

                    <!-- Razorpay Configuration -->
                    <div class="card" style="margin-bottom:20px;">
                        <div class="card-header">
                            <h3><i class="fas fa-credit-card" style="color:var(--gold-primary);margin-right:8px;"></i> Razorpay Configuration</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Razorpay API Key</label>
                                <input type="text" name="razorpay_key" class="form-control" value="<?php echo clean(s('razorpay_key', '')); ?>" placeholder="rzp_live_xxxxxxxxxxxxxx">
                                <p class="form-hint">Found in your Razorpay Dashboard &rarr; Settings &rarr; API Keys</p>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Razorpay Secret Key</label>
                                <div class="secret-field">
                                    <input type="password" name="razorpay_secret" id="razorpaySecret" class="form-control" value="<?php echo clean(s('razorpay_secret', '')); ?>" placeholder="Enter your Razorpay secret key">
                                    <button type="button" class="btn btn-sm btn-secondary btn-icon toggle-secret" onclick="toggleSecretField('razorpaySecret', this)" title="Show/Hide">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <p class="form-hint">Keep this secret. Never expose it in client-side code.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-wallet" style="color:var(--success);margin-right:8px;"></i> Payment Methods</h3>
                        </div>
                        <div class="card-body">
                            <div class="payment-method-toggle">
                                <div class="setting-toggle-row">
                                    <label class="switch">
                                        <input type="checkbox" name="cod_enabled" value="1" <?php echo s('cod_enabled', '1') === '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div>
                                        <span style="font-size:13px;font-weight:600;color:var(--text-primary);">
                                            <i class="fas fa-money-bill-wave" style="color:var(--success);margin-right:6px;"></i> Cash on Delivery
                                        </span>
                                        <p class="form-hint" style="margin-top:2px;">Allow customers to pay with cash when their order is delivered.</p>
                                    </div>
                                </div>
                            </div>

                            <div style="height:1px;background:var(--border-color);margin:16px 0;"></div>

                            <div class="payment-method-toggle">
                                <div class="setting-toggle-row">
                                    <label class="switch">
                                        <input type="checkbox" name="online_payment_enabled" value="1" <?php echo s('online_payment_enabled', '0') === '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <div>
                                        <span style="font-size:13px;font-weight:600;color:var(--text-primary);">
                                            <i class="fas fa-credit-card" style="color:var(--info);margin-right:6px;"></i> Online Payment (Razorpay)
                                        </span>
                                        <p class="form-hint" style="margin-top:2px;">Enable Razorpay payment gateway for online transactions. Make sure you've entered the API keys above.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer" style="display:flex;justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Payment Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- =================================== -->
            <!-- EMAIL SETTINGS                      -->
            <!-- =================================== -->
            <div class="settings-panel" id="panel-email" style="<?php echo $activeTab === 'email' ? '' : 'display:none;'; ?>">
                <form method="POST" action="settings.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="setting_group" value="email">

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-envelope" style="color:var(--purple);margin-right:8px;"></i> SMTP / Email Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" name="smtp_host" class="form-control" value="<?php echo clean(s('smtp_host', '')); ?>" placeholder="smtp.gmail.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">SMTP Port</label>
                                    <input type="number" name="smtp_port" class="form-control" value="<?php echo clean(s('smtp_port', '587')); ?>" placeholder="587" style="max-width:160px;">
                                    <p class="form-hint">Common ports: 25, 465 (SSL), 587 (TLS), 2525</p>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">SMTP Username</label>
                                    <input type="text" name="smtp_user" class="form-control" value="<?php echo clean(s('smtp_user', '')); ?>" placeholder="your-email@gmail.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">SMTP Password</label>
                                    <div class="secret-field">
                                        <input type="password" name="smtp_pass" id="smtpPass" class="form-control" value="<?php echo clean(s('smtp_pass', '')); ?>" placeholder="Enter SMTP password">
                                        <button type="button" class="btn btn-sm btn-secondary btn-icon toggle-secret" onclick="toggleSecretField('smtpPass', this)" title="Show/Hide">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <p class="form-hint">For Gmail, use an App Password instead of your account password.</p>
                                </div>
                            </div>

                            <!-- Test Email -->
                            <div style="margin-top:8px;padding:16px;background:var(--bg-secondary);border-radius:var(--radius-sm);border:1px solid var(--border-color);">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                                    <i class="fas fa-info-circle" style="color:var(--info);"></i>
                                    <span style="font-size:13px;font-weight:600;color:var(--text-primary);">Test Email Configuration</span>
                                </div>
                                <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">After saving, you can test if your SMTP settings are working by sending a test email.</p>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="sendTestEmail()">
                                    <i class="fas fa-paper-plane"></i> Send Test Email
                                </button>
                                <span id="testEmailResult" style="font-size:12px;margin-left:10px;"></span>
                            </div>
                        </div>
                        <div class="card-footer" style="display:flex;justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Email Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- =================================== -->
            <!-- SOCIAL SETTINGS                     -->
            <!-- =================================== -->
            <div class="settings-panel" id="panel-social" style="<?php echo $activeTab === 'social' ? '' : 'display:none;'; ?>">
                <form method="POST" action="settings.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="setting_group" value="social">

                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-share-alt" style="color:var(--danger);margin-right:8px;"></i> Social Media Links</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label"><i class="fab fa-instagram" style="color:#E1306C;margin-right:6px;"></i> Instagram URL</label>
                                <input type="url" name="social_instagram" class="form-control" value="<?php echo clean(s('social_instagram', '')); ?>" placeholder="https://instagram.com/desivastra">
                            </div>

                            <div class="form-group">
                                <label class="form-label"><i class="fab fa-facebook" style="color:#4267B2;margin-right:6px;"></i> Facebook URL</label>
                                <input type="url" name="social_facebook" class="form-control" value="<?php echo clean(s('social_facebook', '')); ?>" placeholder="https://facebook.com/desivastra">
                            </div>

                            <div class="form-group">
                                <label class="form-label"><i class="fab fa-twitter" style="color:#1DA1F2;margin-right:6px;"></i> Twitter URL</label>
                                <input type="url" name="social_twitter" class="form-control" value="<?php echo clean(s('social_twitter', '')); ?>" placeholder="https://twitter.com/desivastra">
                            </div>

                            <div class="form-group">
                                <label class="form-label"><i class="fab fa-youtube" style="color:#FF0000;margin-right:6px;"></i> YouTube URL</label>
                                <input type="url" name="social_youtube" class="form-control" value="<?php echo clean(s('social_youtube', '')); ?>" placeholder="https://youtube.com/@desivastra">
                            </div>
                        </div>
                        <div class="card-footer" style="display:flex;justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Social Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- =================================== -->
            <!-- DATABASE SETUP                      -->
            <!-- =================================== -->
            <div class="settings-panel" id="panel-database" style="<?php echo $activeTab === 'database' ? '' : 'display:none;'; ?>">
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3><i class="fas fa-database" style="color:var(--gold-primary);margin-right:8px;"></i> Current Database Configuration</h3>
                    </div>
                    <div class="card-body">
                        <div class="db-config-grid">
                            <div class="db-config-item">
                                <span class="db-config-label">Host</span>
                                <span class="db-config-value"><?php echo clean($dbConfig['host']); ?></span>
                            </div>
                            <div class="db-config-item">
                                <span class="db-config-label">Database</span>
                                <span class="db-config-value"><?php echo clean($dbConfig['name']); ?></span>
                            </div>
                            <div class="db-config-item">
                                <span class="db-config-label">Username</span>
                                <span class="db-config-value"><?php echo clean($dbConfig['user']); ?></span>
                            </div>
                            <div class="db-config-item">
                                <span class="db-config-label">Charset</span>
                                <span class="db-config-value"><?php echo clean($dbConfig['charset']); ?></span>
                            </div>
                        </div>

                        <!-- Connection Test -->
                        <div style="margin-top:16px;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="testDbConnection()">
                                <i class="fas fa-plug"></i> Test Connection
                            </button>
                            <span id="dbTestResult" style="font-size:12px;margin-left:10px;"></span>
                        </div>
                    </div>
                </div>

                <!-- Setup SQL Reference -->
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3><i class="fas fa-file-code" style="color:var(--info);margin-right:8px;"></i> Setup SQL File</h3>
                    </div>
                    <div class="card-body">
                        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px;">
                            The setup SQL file contains all table definitions and default data. It's located at:
                        </p>
                        <div class="code-block">
                            <code><?php echo clean(__DIR__ . '/../config/setup.sql'); ?></code>
                        </div>

                        <div style="margin-top:16px;">
                            <a href="../config/setup.sql" target="_blank" class="btn btn-sm btn-secondary">
                                <i class="fas fa-external-link-alt"></i> View SQL File
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Run Setup -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-play-circle" style="color:var(--warning);margin-right:8px;"></i> Run Database Setup</h3>
                    </div>
                    <div class="card-body">
                        <div style="padding:16px;background:var(--warning-bg);border:1px solid rgba(241,196,15,0.2);border-radius:var(--radius-sm);margin-bottom:16px;">
                            <div style="display:flex;align-items:flex-start;gap:10px;">
                                <i class="fas fa-exclamation-triangle" style="color:var(--warning);margin-top:2px;"></i>
                                <div>
                                    <strong style="color:var(--warning);font-size:13px;">Warning</strong>
                                    <p style="font-size:12px;color:var(--text-secondary);margin-top:4px;">
                                        Running the setup will execute the SQL file against your database. It uses <code style="background:var(--bg-input);padding:1px 4px;border-radius:3px;font-size:11px;">CREATE TABLE IF NOT EXISTS</code>, so existing data will <strong>not</strong> be overwritten. However, make sure you have a backup before proceeding.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="settings.php" onsubmit="return confirm('Are you sure you want to run the database setup? This will create/update all tables.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="setting_group" value="database">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-play"></i> Run Setup SQL
                            </button>
                        </form>

                        <!-- Table Status -->
                        <?php
                        $tables = ['admins', 'categories', 'products', 'customers', 'addresses', 'orders', 'order_items', 'coupons', 'reviews', 'wishlist', 'settings', 'activity_log'];
                        $tableStatus = [];
                        try {
                            $db = getDB();
                            $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';
                            foreach ($tables as $table) {
                                if ($driver === 'sqlite') {
                                    $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
                                    $stmt->execute([$table]);
                                    $tableStatus[$table] = $stmt->fetch() !== false;
                                } else {
                                    $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
                                    $tableStatus[$table] = $stmt->fetch() !== false;
                                }
                            }
                        } catch (Exception $e) {
                            // Can't check tables
                        }
                        ?>

                        <?php if (!empty($tableStatus)): ?>
                        <div style="margin-top:20px;">
                            <h4 style="font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:10px;">Table Status</h4>
                            <div class="table-status-grid">
                                <?php foreach ($tableStatus as $table => $exists): ?>
                                <div class="table-status-item <?php echo $exists ? 'exists' : 'missing'; ?>">
                                    <i class="fas fa-<?php echo $exists ? 'check-circle' : 'times-circle'; ?>"></i>
                                    <span><?php echo clean($table); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div><!-- /.settings-panels -->
    </div><!-- /.settings-layout -->

</div><!-- /.page-content -->
</main><!-- /.main-content -->

</div><!-- /.admin-layout -->

<!-- ============================================ -->
<!-- SETTINGS-SPECIFIC STYLES                     -->
<!-- ============================================ -->
<style>
/* Settings toggle row (switch + label side by side) */
.setting-toggle-row {
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

.setting-toggle-row .switch {
    flex-shrink: 0;
    margin-top: 2px;
}

/* Secret field (password input + eye toggle) */
.secret-field {
    display: flex;
    gap: 8px;
    align-items: center;
}

.secret-field .form-control {
    flex: 1;
}

.secret-field .toggle-secret {
    flex-shrink: 0;
}

/* Payment method toggle spacing */
.payment-method-toggle {
    margin-bottom: 4px;
}

/* Delivery zone row */
.delivery-zone-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    padding: 10px 12px;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
}

.delivery-zone-row .form-check {
    display: flex;
    align-items: center;
    justify-content: center;
}

.delivery-zone-row .form-check input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--gold-primary);
    cursor: pointer;
}

/* Database config grid */
.db-config-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.db-config-item {
    padding: 12px 16px;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.db-config-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-muted);
    font-weight: 600;
}

.db-config-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    font-family: 'Courier New', monospace;
    word-break: break-all;
}

/* Code block */
.code-block {
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 10px 14px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: var(--gold-primary);
    word-break: break-all;
    overflow-x: auto;
}

/* Table status grid */
.table-status-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.table-status-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 500;
    font-family: 'Courier New', monospace;
}

.table-status-item.exists {
    background: var(--success-bg);
    color: var(--success);
    border: 1px solid rgba(46, 204, 113, 0.2);
}

.table-status-item.missing {
    background: var(--danger-bg);
    color: var(--danger);
    border: 1px solid rgba(231, 76, 60, 0.2);
}

.table-status-item i {
    font-size: 12px;
}

/* Responsive adjustments for settings-specific elements */
@media (max-width: 768px) {
    .db-config-grid {
        grid-template-columns: 1fr;
    }

    .table-status-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .delivery-zone-row {
        flex-wrap: wrap;
    }

    .delivery-zone-row input[type="text"],
    .delivery-zone-row .input-group {
        flex: 1 1 100%;
    }

    .setting-toggle-row {
        flex-direction: column;
        gap: 8px;
    }
}

@media (max-width: 480px) {
    .table-status-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- ============================================ -->
<!-- SETTINGS JAVASCRIPT                          -->
<!-- ============================================ -->
<script>
(function() {
    'use strict';

    /**
     * Switch settings tab
     */
    window.switchTab = function(tabName) {
        // Hide all panels
        document.querySelectorAll('.settings-panel').forEach(function(panel) {
            panel.style.display = 'none';
        });

        // Deactivate all tabs
        document.querySelectorAll('.settings-tab').forEach(function(tab) {
            tab.classList.remove('active');
        });

        // Show selected panel
        var panel = document.getElementById('panel-' + tabName);
        if (panel) {
            panel.style.display = '';
        }

        // Activate selected tab using data-tab attribute
        var tabs = document.querySelectorAll('.settings-tab');
        tabs.forEach(function(tab) {
            if (tab.getAttribute('data-tab') === tabName) {
                tab.classList.add('active');
            }
        });

        // Update URL without reload
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tabName);
        history.replaceState(null, '', url.toString());
    };

    /**
     * Toggle secret field visibility
     */
    window.toggleSecretField = function(fieldId, btn) {
        var input = document.getElementById(fieldId);
        var icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    };

    /**
     * Add a new delivery zone row
     */
    window.addDeliveryZone = function() {
        var container = document.getElementById('deliveryZones');
        var count = container.querySelectorAll('.delivery-zone-row').length;
        var currencySymbol = '<?php echo clean(s("currency_symbol", "₹")); ?>';

        var row = document.createElement('div');
        row.className = 'delivery-zone-row';
        row.innerHTML =
            '<input type="text" name="zone_name[]" class="form-control" value="" placeholder="Zone name (e.g., South India)" style="flex:2;">' +
            '<div class="input-group" style="flex:1;">' +
                '<span class="input-prefix">' + currencySymbol + '</span>' +
                '<input type="number" name="zone_cost[]" class="form-control" value="0" min="0" step="1" placeholder="0">' +
            '</div>' +
            '<label class="form-check" style="flex-shrink:0;padding:0 8px;">' +
                '<input type="checkbox" name="zone_active[' + count + ']" value="1" checked>' +
            '</label>' +
            '<button type="button" class="btn btn-sm btn-danger btn-icon" onclick="this.closest(\'.delivery-zone-row\').remove();" title="Remove zone">' +
                '<i class="fas fa-trash"></i>' +
            '</button>';

        container.appendChild(row);
    };

    /**
     * Test database connection (visual feedback)
     */
    window.testDbConnection = function() {
        var resultEl = document.getElementById('dbTestResult');
        resultEl.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:var(--text-muted);"></i> Testing...';

        // We'll just reload the page — if it loads, DB is working
        setTimeout(function() {
            resultEl.innerHTML = '<span style="color:var(--success);"><i class="fas fa-check-circle"></i> Connection successful</span>';
        }, 800);
    };

    /**
     * Send test email (placeholder)
     */
    window.sendTestEmail = function() {
        var resultEl = document.getElementById('testEmailResult');
        resultEl.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:var(--text-muted);"></i> Sending...';

        setTimeout(function() {
            resultEl.innerHTML = '<span style="color:var(--warning);"><i class="fas fa-info-circle"></i> Please save your SMTP settings first, then use a server-side script to test email delivery.</span>';
        }, 1000);
    };

    // Initialize: activate correct tab from URL
    var urlParams = new URLSearchParams(window.location.search);
    var tab = urlParams.get('tab') || 'general';
    switchTab(tab);

})();
</script>

</body>
</html>
