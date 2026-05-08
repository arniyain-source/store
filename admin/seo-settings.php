<?php
/**
 * Advanced SEO Settings - DesiVastra Admin
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$pageTitle = "SEO Settings - DesiVastra Admin";
$csrf = generateCSRF();

// ============================================
// HANDLE SETTINGS UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_seo'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $seoSettings = [
            'default_meta_title'          => $_POST['default_meta_title'] ?? '',
            'default_meta_description'    => $_POST['default_meta_description'] ?? '',
            'default_meta_keywords'       => $_POST['default_meta_keywords'] ?? '',
            'og_image_url'                => $_POST['og_image_url'] ?? '',
            'fb_page_url'                 => $_POST['fb_page_url'] ?? '',
            'twitter_handle'              => $_POST['twitter_handle'] ?? '',
            'google_analytics_id'         => $_POST['google_analytics_id'] ?? '',
            'google_search_console_tag'   => $_POST['google_search_console_tag'] ?? '',
            'auto_generate_sitemap'       => isset($_POST['auto_generate_sitemap']) ? '1' : '0',
            'robots_txt_content'          => $_POST['robots_txt_content'] ?? ''
        ];

        foreach ($seoSettings as $key => $value) {
            updateSetting($key, $value);
        }

        // Update actual robots.txt file in root
        try {
            file_put_contents(__DIR__ . '/../robots.txt', $_POST['robots_txt_content'] ?? '');
        } catch (Exception $e) {
            error_log("Failed to write robots.txt: " . $e->getMessage());
        }

        logActivity('update_seo_settings', 'settings', null, ['action' => 'updated global seo']);
        setFlash('success', 'SEO settings updated successfully.');
    }
    redirect('seo-settings.php');
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .seo-audit-container {
            background: rgba(0,0,0,0.2);
            border-radius: 8px;
            padding: 15px;
            min-height: 100px;
        }
        .audit-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid var(--glass-border);
        }
        .audit-item:last-child { border-bottom: none; }
        .audit-warning { color: var(--danger); }
        .audit-ok { color: var(--success); }
        .char-count { font-size: 10px; color: var(--text-secondary); text-align: right; margin-top: 2px; }
        .char-count.limit-reached { color: var(--danger); }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/layout.php'; ?>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-search-dollar" style="color: var(--gold-primary); margin-right: 8px;"></i>Advanced SEO</h1>
                <p class="subtitle">Optimize your store for search engines and social media</p>
            </div>
            <button type="submit" form="seoForm" name="save_seo" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo clean($flash['message']); ?>
                <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <form id="seoForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="save_seo" value="1">

            <div class="grid-2">
                <div class="flex-column gap-20">
                    <!-- Global Meta Tags -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-tags"></i> Global Meta Tags</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Default Meta Title</label>
                                <input type="text" name="default_meta_title" id="meta_title" class="form-control" maxlength="60" value="<?php echo clean(getSetting('default_meta_title')); ?>" placeholder="e.g., DesiVastra - Luxury Fashion & Accessories">
                                <div id="meta_title_count" class="char-count">0 / 60</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Default Meta Description</label>
                                <textarea name="default_meta_description" id="meta_desc" class="form-control" rows="3" maxlength="160" placeholder="Describe your store in 160 characters..."><?php echo clean(getSetting('default_meta_description')); ?></textarea>
                                <div id="meta_desc_count" class="char-count">0 / 160</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Meta Keywords</label>
                                <input type="text" name="default_meta_keywords" class="form-control" value="<?php echo clean(getSetting('default_meta_keywords')); ?>" placeholder="sarees, watches, luxury fashion">
                            </div>
                        </div>
                    </div>

                    <!-- Social & Open Graph -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-share-alt"></i> Social & Open Graph</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Default OG Image URL</label>
                                <input type="text" name="og_image_url" class="form-control" value="<?php echo clean(getSetting('og_image_url')); ?>" placeholder="https://example.com/logo.jpg">
                            </div>
                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">Facebook Page URL</label>
                                    <input type="text" name="fb_page_url" class="form-control" value="<?php echo clean(getSetting('fb_page_url')); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Twitter Handle</label>
                                    <input type="text" name="twitter_handle" class="form-control" value="<?php echo clean(getSetting('twitter_handle')); ?>" placeholder="@desivastra">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-column gap-20">
                    <!-- Google Integrations -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fab fa-google"></i> Google Integrations</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Google Analytics ID (G-XXXXXXX)</label>
                                <input type="text" name="google_analytics_id" class="form-control" value="<?php echo clean(getSetting('google_analytics_id')); ?>" placeholder="G-XXXXXXXXXX">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Search Console Meta Tag</label>
                                <input type="text" name="google_search_console_tag" class="form-control" value="<?php echo clean(getSetting('google_search_console_tag')); ?>" placeholder='<meta name="google-site-verification" content="...">'>
                            </div>
                        </div>
                    </div>

                    <!-- Sitemap & Search Bots -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-sitemap"></i> Sitemap & Search Bots</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                                <input type="checkbox" name="auto_generate_sitemap" id="autoSitemap" <?php echo getSetting('auto_generate_sitemap') === '1' ? 'checked' : ''; ?>>
                                <label for="autoSitemap" class="form-label" style="margin: 0;">Auto-Generate Sitemap</label>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Robots.txt Content</label>
                                <textarea name="robots_txt_content" class="form-control" rows="5" style="font-family: monospace; font-size: 12px;"><?php echo clean(getSetting('robots_txt_content') ?: "User-agent: *\nAllow: /"); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SEO Audit -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-stethoscope"></i> SEO Audit</h3>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="runAudit()"><i class="fas fa-sync"></i> Refresh</button>
                        </div>
                        <div class="card-body">
                            <div id="seo-audit-results" class="seo-audit-container">
                                <p style="text-align: center; color: var(--text-secondary);">Loading SEO Audit...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Character counters
    const titleInput = document.getElementById('meta_title');
    const descInput = document.getElementById('meta_desc');
    const titleCount = document.getElementById('meta_title_count');
    const descCount = document.getElementById('meta_desc_count');

    function updateCount(input, counter, max) {
        const len = input.value.length;
        counter.textContent = `${len} / ${max}`;
        if (len > max) {
            counter.classList.add('limit-reached');
        } else {
            counter.classList.remove('limit-reached');
        }
    }

    titleInput.addEventListener('input', () => updateCount(titleInput, titleCount, 60));
    descInput.addEventListener('input', () => updateCount(descInput, descCount, 160));

    // Initial counts
    updateCount(titleInput, titleCount, 60);
    updateCount(descInput, descCount, 160);

    // Initial audit run
    runAudit();
});

async function runAudit() {
    const container = document.getElementById('seo-audit-results');
    container.innerHTML = '<p style="text-align: center;"><i class="fas fa-spinner fa-spin"></i> Running audit...</p>';
    
    try {
        const response = await fetch('ajax/seo-audit.php');
        const data = await response.json();
        
        if (data.success) {
            let html = '';
            if (data.issues && data.issues.length > 0) {
                data.issues.forEach(item => {
                    html += `<div class="audit-item">
                        <i class="fas ${item.status === 'warning' ? 'fa-exclamation-triangle audit-warning' : 'fa-check-circle audit-ok'}"></i>
                        <div>
                            <div style="font-size: 13px; font-weight: 600;">${item.title}</div>
                            <div style="font-size: 11px; color: var(--text-secondary);">${item.message}</div>
                        </div>
                    </div>`;
                });
            }
            container.innerHTML = html || '<div class="audit-item audit-ok"><i class="fas fa-check-circle"></i> No critical SEO issues found!</div>';
        } else {
            container.innerHTML = `<p class="audit-warning">Failed to load audit data: ${data.message || 'Unknown error'}</p>`;
        }
    } catch (e) {
        container.innerHTML = `<p class="audit-warning">Network error during audit.</p>`;
    }
}
</script>
</body>
</html>