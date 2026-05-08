<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Payment Settings Page - DesiVastra Admin
 */

$csrf = generateCSRF();

// ============================================
// HANDLE SETTINGS UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please refresh the page.');
    } else {
        // Define settings to update
        $settingKeys = [
            'upi_id', 'upi_name', 'bank_account_name', 'bank_account_number', 'bank_ifsc', 
            'payment_instructions', 'enable_payment_screenshot',
            'razorpay_key', 'razorpay_secret', 'razorpay_mode',
            'cashfree_app_id', 'cashfree_secret_key',
            'cod_enabled', 'cod_extra_charge'
        ];

        try {
            foreach ($settingKeys as $key) {
                $value = $_POST[$key] ?? '';
                // Handle booleans/toggles
                if (in_array($key, ['enable_payment_screenshot', 'cod_enabled'])) {
                    $value = isset($_POST[$key]) ? '1' : '0';
                }
                updateSetting($key, $value);
            }
            
            logActivity('update_settings', 'payment', null, ['details' => 'Payment Gateway Settings Updated']);
            setFlash('success', 'Payment settings updated successfully.');
            redirect('payment-settings.php');
        } catch (Exception $e) {
            setFlash('error', 'Failed to save settings: ' . $e->getMessage());
        }
    }
}

$pageTitle = "Payment Settings - DesiVastra Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 992px) { .grid-2 { grid-template-columns: 1fr; } }
        
        .form-section-title { 
            font-size: 14px; 
            font-weight: 700; 
            color: var(--gold-primary); 
            margin-bottom: 16px; 
            display: flex; 
            align-items: center; 
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .checkbox-group { display: flex; align-items: center; gap: 12px; margin-top: 10px; }
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 22px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--bg-input); transition: .3s; border-radius: 22px; border: 1px solid var(--border-color); }
        .toggle-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 2px; background-color: var(--text-muted); transition: .3s; border-radius: 50%; }
        input:checked + .toggle-slider { background-color: rgba(184, 137, 42, 0.2); border-color: var(--gold-primary); }
        input:checked + .toggle-slider:before { transform: translateX(22px); background-color: var(--gold-primary); }
        .checkbox-label { font-size: 13px; font-weight: 600; color: var(--text-secondary); }
        
        .form-hint { color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block; line-height: 1.4; }
        
        .sticky-actions {
            position: sticky;
            bottom: -24px;
            margin: 30px -24px -24px;
            padding: 16px 24px;
            background: rgba(15, 15, 23, 0.8);
            backdrop-filter: blur(10px);
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            z-index: 10;
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
            <span>Settings</span>
            <span class="separator"><i class="fas fa-chevron-right"></i></span>
            <span>Payment Gateways</span>
        </div>

        <div class="page-header">
            <div>
                <h1><i class="fas fa-credit-card" style="color: var(--gold-primary); margin-right: 12px;"></i>Payment Settings</h1>
                <p class="subtitle">Manage online gateways, manual transfers, and COD options.</p>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo clean($flash['message']); ?>
                <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

            <div class="grid-2">
                <!-- Manual Payments -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-university" style="color: var(--gold-primary); margin-right: 8px;"></i> Manual Payments (UPI/Bank)</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">UPI ID</label>
                            <input type="text" name="upi_id" class="form-control" value="<?php echo clean(getSetting('upi_id')); ?>" placeholder="e.g. store@upi">
                        </div>
                        <div class="form-group">
                            <label class="form-label">UPI Display Name</label>
                            <input type="text" name="upi_name" class="form-control" value="<?php echo clean(getSetting('upi_name')); ?>" placeholder="e.g. DesiVastra Fashion">
                        </div>
                        <div class="form-divider" style="margin: 20px 0; height: 1px; background: var(--border-color);"></div>
                        <div class="form-group">
                            <label class="form-label">Bank Account Holder Name</label>
                            <input type="text" name="bank_account_name" class="form-control" value="<?php echo clean(getSetting('bank_account_name')); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bank Account Number</label>
                            <input type="text" name="bank_account_number" class="form-control" value="<?php echo clean(getSetting('bank_account_number')); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">IFSC Code</label>
                            <input type="text" name="bank_ifsc" class="form-control" value="<?php echo clean(getSetting('bank_ifsc')); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Checkout Instructions</label>
                            <textarea name="payment_instructions" class="form-control" rows="3"><?php echo clean(getSetting('payment_instructions')); ?></textarea>
                            <small class="form-hint">Displayed to customers when they select Manual Payment at checkout.</small>
                        </div>
                        <div class="form-group checkbox-group">
                            <label class="toggle-switch">
                                <input type="checkbox" name="enable_payment_screenshot" <?php echo getSetting('enable_payment_screenshot') == '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="checkbox-label">Require Payment Screenshot Upload</span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <!-- COD Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-hand-holding-dollar" style="color: var(--gold-primary); margin-right: 8px;"></i> Cash on Delivery (COD)</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group checkbox-group">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="cod_enabled" <?php echo getSetting('cod_enabled') == '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="checkbox-label">Enable Cash on Delivery</span>
                            </div>
                            <div class="form-group" style="margin-top: 20px;">
                                <label class="form-label">COD Extra Charge (₹)</label>
                                <input type="number" name="cod_extra_charge" class="form-control" value="<?php echo clean(getSetting('cod_extra_charge')); ?>" step="0.01">
                                <small class="form-hint">Additional flat fee applied to COD orders to cover collection risks.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Cashfree -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-bolt" style="color: var(--gold-primary); margin-right: 8px;"></i> Cashfree Integration</h3>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="alert('Testing connectivity...')">Test API</button>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Cashfree App ID</label>
                                <input type="text" name="cashfree_app_id" class="form-control" value="<?php echo clean(getSetting('cashfree_app_id')); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Cashfree Secret Key</label>
                                <input type="password" name="cashfree_secret_key" class="form-control" value="<?php echo clean(getSetting('cashfree_secret_key')); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Webhook Endpoint</label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="text" class="form-control" value="<?php echo SITE_URL; ?>/api/payments/webhook.php" readonly style="background: var(--bg-dark); cursor: text;">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="navigator.clipboard.writeText('<?php echo SITE_URL; ?>/api/payments/webhook.php'); alert('Copied!')"><i class="fas fa-copy"></i></button>
                                </div>
                                <small class="form-hint">Configure this URL in your Cashfree Merchant Dashboard.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid-2" style="margin-top: 24px;">
                <!-- Razorpay -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fab fa-cc-amazon-pay" style="color: var(--gold-primary); margin-right: 8px;"></i> Razorpay Integration</h3>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="alert('Testing connectivity...')">Test API</button>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Razorpay Key ID</label>
                            <input type="text" name="razorpay_key" class="form-control" value="<?php echo clean(getSetting('razorpay_key')); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Razorpay Key Secret</label>
                            <input type="password" name="razorpay_secret" class="form-control" value="<?php echo clean(getSetting('razorpay_secret')); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Transaction Mode</label>
                            <select name="razorpay_mode" class="form-control">
                                <option value="test" <?php echo getSetting('razorpay_mode') === 'test' ? 'selected' : ''; ?>>Test / Sandbox</option>
                                <option value="live" <?php echo getSetting('razorpay_mode') === 'live' ? 'selected' : ''; ?>>Live / Production</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sticky-actions">
                <button type="submit" name="save_settings" class="btn btn-primary btn-lg" style="padding: 12px 32px; font-weight: 700; box-shadow: 0 4px 15px rgba(184, 137, 42, 0.3);">
                    <i class="fas fa-save" style="margin-right: 10px;"></i> Save All Settings
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Handle flash message closing
    document.querySelectorAll('.flash-close').forEach(btn => {
        btn.addEventListener('click', () => btn.parentElement.remove());
    });
</script>

</body>
</html>