<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Final Testing & QA Checklist - DesiVastra Admin
 */
require_once __DIR__ . '/includes/layout.php';

$csrf = generateCSRF();
$reportDir = __DIR__ . '/qa';
$reportFile = $reportDir . '/report.json';

if (!is_dir($reportDir)) {
    mkdir($reportDir, 0755, true);
}

// 1. Grouped Checklist Data
$checklist = [
    'Frontend & Mobile' => [
        'ui_ux_gold'       => 'Luxury Gold Theme Consistency',
        'sticky_search'    => 'Sticky Mobile Search Bar',
        'bottom_nav'       => 'Mobile 5-Button Navigation',
        'responsive_check' => 'Responsive Layout (320px to 4K)'
    ],
    'Product & AI' => [
        'product_crud'     => 'Advanced Product Create/Edit',
        'sku_gen'          => 'SKU Auto-Generator Logic',
        'ai_extraction'    => 'AI Attribute Extraction (Step 5)',
        'photo_search'     => 'AI Photo Search Accuracy'
    ],
    'Order & Logistics' => [
        'checkout_flow'    => 'Mobile-First Checkout Flow',
        'pdf_invoice'      => 'Meesho-style PDF Invoice',
        'shipping_label'   => 'Logistic Shipping Label PDF',
        'order_tracking'   => 'Public Tracking Page Logic'
    ],
    'Payment & Revenue' => [
        'gateway_config'   => 'Razorpay / Cashfree API Connectivity',
        'webhook_secure'   => 'Payment Webhook Signature Verification',
        'manual_verify'    => 'Manual Payment Screenshot Verification',
        'refund_mgmt'      => 'Refund Processing & Logs'
    ],
    'System & Security' => [
        'seo_audit'        => 'Global SEO & Sitemap generation',
        'api_health'       => 'API Integration Health Checks',
        'backup_restore'   => 'Database Backup & Restore Cycle',
        'permissions'      => 'Role-Based Access Control (RBAC)',
        'speed_perf'       => 'Frontend Speed & Lazy Loading'
    ]
];

// 2. Handle POST Logic (Save Report)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_report'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $results = $_POST['qa_results'] ?? [];
        // Basic sanitization: only allow specific status values
        $sanitized = [];
        foreach ($results as $key => $val) {
            if (in_array($val, ['pass', 'fail', 'pending'])) {
                $sanitized[$key] = $val;
            }
        }
        
        if (file_put_contents($reportFile, json_encode($sanitized))) {
            logActivity('save_qa_report', 'system', null, ['tests_total' => count($sanitized)]);
            setFlash('success', 'Final QA Report generated and saved successfully.');
        } else {
            setFlash('error', 'Failed to write report file. Check folder permissions.');
        }
    }
    redirect('qa-checklist.php');
}

// 3. Load Existing Results
$qaData = [];
if (file_exists($reportFile)) {
    $qaData = json_decode(file_get_contents($reportFile), true);
}

// 4. Calculate Progress
$totalItems = 0;
$passedItems = 0;
foreach ($checklist as $group => $items) {
    $totalItems += count($items);
    foreach ($items as $key => $label) {
        if (($qaData[$key] ?? '') === 'pass') $passedItems++;
    }
}
$progressPercent = $totalItems > 0 ? round(($passedItems / $totalItems) * 100) : 0;
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>System QA</span>
            </div>
            <h1>Final Testing & QA Checklist</h1>
            <p class="subtitle"><i class="fas fa-shield-alt"></i> Internal pre-deployment tool for Step 16 validation.</p>
        </div>
        <div class="report-meta" style="text-align: right;">
            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 5px;">
                QA Status (<?php echo $totalItems; ?> items total)
            </div>
            <button type="submit" form="qaForm" name="save_report" class="btn btn-primary">
                <i class="fas fa-file-export"></i> Generate Final QA Report
            </button>
        </div>
    </div>

    <!-- Progress Overview -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span style="font-weight: 600; color: var(--gold-light);">Overall Testing Progress</span>
                <span style="font-weight: 800; color: var(--gold-primary); font-size: 18px;"><?php echo $progressPercent; ?>%</span>
            </div>
            <div style="width: 100%; height: 12px; background: var(--bg-input); border-radius: 6px; overflow: hidden; border: 1px solid var(--border-color);">
                <div id="qa-progress-fill" style="width: <?php echo $progressPercent; ?>%; height: 100%; background: var(--gold-gradient); transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);"></div>
            </div>
            <div style="margin-top: 10px; font-size: 12px; color: var(--text-muted);">
                <?php echo $passedItems; ?> of <?php echo $totalItems; ?> system requirements verified.
            </div>
        </div>
    </div>

    <!-- Flash Message -->
    <?php $flash = getFlash(); if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>" style="margin-bottom: 20px;">
            <i class="fas fa-info-circle"></i> <?php echo clean($flash['message']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="qaForm">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
            <?php foreach ($checklist as $groupName => $items): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list-check" style="color: var(--gold-primary); margin-right: 10px;"></i><?php echo $groupName; ?></h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Test Requirement</th>
                                    <th style="width: 180px; text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $key => $label): 
                                    $currentStatus = $qaData[$key] ?? 'pending';
                                ?>
                                    <tr>
                                        <td style="font-size: 13px;"><?php echo $label; ?></td>
                                        <td>
                                            <div class="qa-radio-group">
                                                <label class="qa-radio pass" title="Pass">
                                                    <input type="radio" name="qa_results[<?php echo $key; ?>]" value="pass" <?php echo $currentStatus === 'pass' ? 'checked' : ''; ?> onchange="recalcProgress()">
                                                    <i class="fas fa-check"></i>
                                                </label>
                                                <label class="qa-radio fail" title="Fail">
                                                    <input type="radio" name="qa_results[<?php echo $key; ?>]" value="fail" <?php echo $currentStatus === 'fail' ? 'checked' : ''; ?> onchange="recalcProgress()">
                                                    <i class="fas fa-xmark"></i>
                                                </label>
                                                <label class="qa-radio pending" title="Pending">
                                                    <input type="radio" name="qa_results[<?php echo $key; ?>]" value="pending" <?php echo $currentStatus === 'pending' ? 'checked' : ''; ?> onchange="recalcProgress()">
                                                    <i class="fas fa-clock"></i>
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Sticky Save Bar -->
        <div style="margin-top: 40px; padding: 20px; background: var(--bg-card-solid); border: 1px solid var(--border-color); border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center;">
            <div style="color: var(--text-muted); font-size: 13px;">
                <i class="fas fa-info-circle" style="margin-right: 6px;"></i> Ensure all "Fail" items are resolved before final deployment.
            </div>
            <button type="submit" name="save_report" class="btn btn-primary">
                <i class="fas fa-save"></i> Save & Generate Report
            </button>
        </div>
    </form>
</div>

<style>
    .qa-radio-group {
        display: flex;
        justify-content: center;
        gap: 12px;
    }
    .qa-radio {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        transition: all 0.2s ease;
    }
    .qa-radio input { display: none; }
    
    .qa-radio.pass:hover, .qa-radio.pass input:checked + i { color: var(--success); }
    .qa-radio.fail:hover, .qa-radio.fail input:checked + i { color: var(--danger); }
    .qa-radio.pending:hover, .qa-radio.pending input:checked + i { color: var(--info); }
    
    .qa-radio input:checked {
        border-width: 2px;
    }
    .qa-radio.pass input:checked { border-color: var(--success); background: rgba(46, 204, 113, 0.1); }
    .qa-radio.fail input:checked { border-color: var(--danger); background: rgba(231, 76, 60, 0.1); }
    .qa-radio.pending input:checked { border-color: var(--info); background: rgba(52, 152, 219, 0.1); }

    .data-table td { padding: 12px 15px; }
    .data-table tr:hover { background: rgba(255,255,255,0.02); }
</style>

<script>
function recalcProgress() {
    const total = document.querySelectorAll('.qa-radio-group').length;
    const passed = document.querySelectorAll('input[value="pass"]:checked').length;
    const percent = total > 0 ? Math.round((passed / total) * 100) : 0;
    
    document.getElementById('qa-progress-fill').style.width = percent + '%';
    document.querySelector('span[style*="font-size: 18px"]').innerText = percent + '%';
}
</script>

<?php require_once __DIR__ . '/includes/layout.php'; ?>