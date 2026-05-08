<?php
/**
 * Global Notification & Alert Settings - DesiVastra Admin
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

// ============================================
// HANDLE SETTINGS UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notification_settings'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $settingsToUpdate = [
            'notify_whatsapp_enabled', 'notify_firebase_enabled', 'notify_email_enabled',
            'alert_customer_order_placed', 'alert_customer_order_shipped', 'alert_customer_payment_received', 'alert_customer_refund_completed',
            'alert_admin_new_order', 'alert_admin_low_stock', 'alert_admin_failed_api', 'alert_admin_security_alert',
            'whatsapp_phone_id', 'whatsapp_business_id', 'whatsapp_access_token',
            'firebase_server_key', 'firebase_messaging_id'
        ];

        foreach ($settingsToUpdate as $key) {
            if (isset($_POST[$key])) {
                updateSetting($key, sanitize($_POST[$key]));
            } else {
                // If it's a checkbox that wasn't checked, set to '0'
                if (strpos($key, 'notify_') === 0 || strpos($key, 'alert_') === 0) {
                    updateSetting($key, '0');
                }
            }
        }

        logActivity('update_notification_settings', 'settings', null, ['updated_by' => $_SESSION['admin_name']]);
        setFlash('success', 'Notification settings updated successfully.');
    }
    redirect('notification-settings.php');
}

$csrf = generateCSRF();
$pageTitle = "Notification & Alert Settings";
require_once __DIR__ . '/includes/layout.php';
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>System</span>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>Notifications</span>
            </div>
            <h1><i class="fas fa-bell" style="color:var(--gold-primary);margin-right:10px;"></i>Notification & Alert Settings</h1>
            <p class="subtitle">Manage automated outreach and critical system alerts for staff and customers.</p>
        </div>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px;">
            
            <!-- API Channels Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-plug"></i> API Channels Configuration</h3>
                </div>
                <div class="card-body">
                    <h4 style="font-size:12px; color:var(--gold-primary); margin-bottom:15px; text-transform:uppercase;">WhatsApp Cloud API</h4>
                    <div class="form-group">
                        <label>Phone Number ID</label>
                        <input type="text" name="whatsapp_phone_id" value="<?php echo clean(getSetting('whatsapp_phone_id')); ?>" placeholder="e.g. 109283746556473">
                    </div>
                    <div class="form-group">
                        <label>WhatsApp Business ID</label>
                        <input type="text" name="whatsapp_business_id" value="<?php echo clean(getSetting('whatsapp_business_id')); ?>" placeholder="e.g. 987654321012345">
                    </div>
                    <div class="form-group">
                        <label>Permanent Access Token</label>
                        <input type="password" name="whatsapp_access_token" value="<?php echo clean(getSetting('whatsapp_access_token')); ?>" placeholder="EAAGZB...">
                    </div>

                    <div class="detail-divider" style="margin:20px 0;"></div>

                    <h4 style="font-size:12px; color:var(--gold-primary); margin-bottom:15px; text-transform:uppercase;">Firebase Cloud Messaging</h4>
                    <div class="form-group">
                        <label>Server Key (Legacy)</label>
                        <input type="password" name="firebase_server_key" value="<?php echo clean(getSetting('firebase_server_key')); ?>" placeholder="AAAA...">
                    </div>
                    <div class="form-group">
                        <label>Sender ID / Messaging ID</label>
                        <input type="text" name="firebase_messaging_id" value="<?php echo clean(getSetting('firebase_messaging_id')); ?>" placeholder="e.g. 1234567890">
                    </div>
                </div>
            </div>

            <!-- Global Toggles -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-toggle-on"></i> Enable/Disable Channels</h3>
                </div>
                <div class="card-body">
                    <div class="channel-toggle">
                        <div>
                            <label>WhatsApp Status</label>
                            <p>Send automated WhatsApp updates to customers.</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notify_whatsapp_enabled" value="1" <?php echo getSetting('notify_whatsapp_enabled') == '1' ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>

                    <div class="channel-toggle">
                        <div>
                            <label>Push Notification Status</label>
                            <p>Send Firebase alerts to mobile and web browsers.</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notify_firebase_enabled" value="1" <?php echo getSetting('notify_firebase_enabled') == '1' ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>

                    <div class="channel-toggle">
                        <div>
                            <label>Email Status</label>
                            <p>Send standard transactional emails via SMTP.</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notify_email_enabled" value="1" <?php echo getSetting('notify_email_enabled') == '1' ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>

                    <div style="margin-top:30px;">
                        <button type="button" class="btn btn-secondary btn-sm full-width" onclick="location.href='notification-logs.php'">
                            <i class="fas fa-history"></i> View Delivery Logs
                        </button>
                    </div>
                </div>
            </div>

            <!-- Customer Alerts Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-tag"></i> Customer Automations</h3>
                </div>
                <div class="card-body">
                    <div class="alert-item">
                        <label class="checkbox-container">
                            <input type="checkbox" name="alert_customer_order_placed" value="1" <?php echo getSetting('alert_customer_order_placed') == '1' ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Order Placed Confirmation
                        </label>
                        <a href="notifications/templates.php?key=order_placed" class="edit-link">Template <i class="fas fa-external-link-alt"></i></a>
                    </div>
                    
                    <div class="alert-item">
                        <label class="checkbox-container">
                            <input type="checkbox" name="alert_customer_order_shipped" value="1" <?php echo getSetting('alert_customer_order_shipped') == '1' ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Order Shipped (with Tracking)
                        </label>
                        <a href="notifications/templates.php?key=order_shipped" class="edit-link">Template <i class="fas fa-external-link-alt"></i></a>
                    </div>

                    <div class="alert-item">
                        <label class="checkbox-container">
                            <input type="checkbox" name="alert_customer_payment_received" value="1" <?php echo getSetting('alert_customer_payment_received') == '1' ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Payment Verification Successful
                        </label>
                        <a href="notifications/templates.php?key=payment_received" class="edit-link">Template <i class="fas fa-external-link-alt"></i></a>
                    </div>

                    <div class="alert-item">
                        <label class="checkbox-container">
                            <input type="checkbox" name="alert_customer_refund_completed" value="1" <?php echo getSetting('alert_customer_refund_completed') == '1' ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Refund Processed Successfully
                        </label>
                        <a href="notifications/templates.php?key=refund_completed" class="edit-link">Template <i class="fas fa-external-link-alt"></i></a>
                    </div>
                </div>
            </div>

            <!-- Admin Alerts Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-shield"></i> Admin & Staff Alerts</h3>
                </div>
                <div class="card-body">
                    <div class="alert-item">
                        <label class="checkbox-container">
                            <input type="checkbox" name="alert_admin_new_order" value="1" <?php echo getSetting('alert_admin_new_order') == '1' ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            New Order Received (Instant Alert)
                        </label>
                    </div>

                    <div class="alert-item">
                        <label class="checkbox-container">
                            <input type="checkbox" name="alert_admin_low_stock" value="1" <?php echo getSetting('alert_admin_low_stock') == '1' ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            Inventory Low Stock Warning
                        </label>
                    </div>

                    <div class="alert-item">
                        <label class="checkbox-container">
                            <input type="checkbox" name="alert_admin_failed_api" value="1" <?php echo getSetting('alert_admin_failed_api') == '1' ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            API Connectivity Failed
                        </label>
                    </div>

                    <div class="alert-item">
                        <label class="checkbox-container">
                            <input type="checkbox" name="alert_admin_security_alert" value="1" <?php echo getSetting('alert_admin_security_alert') == '1' ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            System Security Alert
                        </label>
                    </div>

                    <div class="alert-item">
                        <label class="checkbox-container">
                            <input type="checkbox" name="alert_admin_new_ticket" value="1" <?php echo getSetting('alert_admin_new_ticket') == '1' ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            New Support Ticket Created
                        </label>
                    </div>
                </div>
            </div>

        </div>

        <div class="form-actions" style="margin-top:30px; padding:20px; background:var(--bg-card-solid); border:1px solid var(--border-color); border-radius:var(--radius-md); text-align:right;">
            <button type="submit" name="save_notification_settings" class="btn btn-primary" style="padding:12px 30px;">
                <i class="fas fa-save"></i> Save Notification Settings
            </button>
        </div>
    </form>
</div>

<style>
    .channel-toggle {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .channel-toggle:last-child { border-bottom: none; }
    .channel-toggle label { margin-bottom: 0; font-weight: 600; }
    .channel-toggle p { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

    .alert-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .alert-item:last-child { border-bottom: none; }

    .edit-link {
        font-size: 11px;
        color: var(--gold-primary);
        text-decoration: none;
        font-weight: 600;
        white-space: nowrap;
    }
    .edit-link:hover { text-decoration: underline; }

    /* Switch Component */
    .switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 22px;
    }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: var(--bg-input);
        transition: .4s;
        border: 1px solid var(--border-color);
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 14px;
        width: 14px;
        left: 3px;
        bottom: 3px;
        background-color: var(--text-muted);
        transition: .4s;
    }
    input:checked + .slider { background-color: var(--gold-primary); border-color: var(--gold-dark); }
    input:checked + .slider:before { transform: translateX(18px); background-color: #000; }
    .slider.round { border-radius: 34px; }
    .slider.round:before { border-radius: 50%; }

    /* Custom Checkbox Styles */
    .checkbox-container {
        display: block;
        position: relative;
        padding-left: 35px;
        cursor: pointer;
        font-size: 13px;
        color: var(--text-primary);
        user-select: none;
    }
    .checkbox-container input {
        position: absolute;
        opacity: 0;
        cursor: pointer;
        height: 0; width: 0;
    }
    .checkmark {
        position: absolute;
        top: 0;
        left: 0;
        height: 20px;
        width: 20px;
        background-color: var(--bg-input);
        border: 1px solid var(--border-color);
        border-radius: 4px;
        transition: all 0.2s;
    }
    .checkbox-container:hover input ~ .checkmark {
        border-color: var(--gold-primary);
    }
    .checkbox-container input:checked ~ .checkmark {
        background-color: var(--gold-primary);
        border-color: var(--gold-primary);
    }
    .checkmark:after {
        content: "";
        position: absolute;
        display: none;
    }
    .checkbox-container input:checked ~ .checkmark:after {
        display: block;
    }
    .checkbox-container .checkmark:after {
        left: 7px;
        top: 3px;
        width: 4px;
        height: 9px;
        border: solid #000;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
    }
</style>

<?php require_once __DIR__ . '/includes/layout.php'; ?>