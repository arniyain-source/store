<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Multi-Language & Translation Manager - DesiVastra Admin
 */


$db = getDB();

// ============================================
// HANDLE POST ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($csrfToken)) {
        setFlash('error', 'Invalid security token.');
    } else {
        if ($action === 'save_language') {
            $langId = (int)($_POST['lang_id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $code = sanitize($_POST['code'] ?? '');
            $direction = sanitize($_POST['direction'] ?? 'ltr');
            $status = isset($_POST['status']) ? 1 : 0;
            $isDefault = isset($_POST['is_default']) ? 1 : 0;

            if ($name && $code) {
                try {
                    $db->beginTransaction();
                    
                    if ($isDefault) {
                        $db->exec("UPDATE languages SET is_default = 0");
                    } else {
                        // Check if we are trying to unset the only default language
                        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM languages WHERE is_default = 1 AND id != ?");
                        $stmt->execute([$langId]);
                        if ($stmt->fetch()['cnt'] == 0) {
                            $isDefault = 1; // Force default if no other exists
                        }
                    }
                    
                    if ($langId > 0) {
                        $stmt = $db->prepare("UPDATE languages SET name = ?, code = ?, direction = ?, is_default = ?, status = ? WHERE id = ?");
                        $stmt->execute([$name, $code, $direction, $isDefault, $status, $langId]);
                        logActivity('edit_language', 'language', $langId, ['code' => $code]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO languages (name, code, direction, is_default, status) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $code, $direction, $isDefault, $status]);
                        $langId = $db->lastInsertId();
                        logActivity('add_language', 'language', $langId, ['code' => $code]);
                    }
                    
                    $db->commit();
                    setFlash('success', "Language '$name' saved successfully.");
                } catch (Exception $e) {
                    $db->rollBack();
                    setFlash('error', 'Failed to save language: ' . $e->getMessage());
                }
            }
        }
        
        if ($action === 'delete_language') {
            $langId = (int)($_POST['lang_id'] ?? 0);
            if ($langId > 0) {
                try {
                    // Check if it's the default language
                    $stmt = $db->prepare("SELECT is_default, name FROM languages WHERE id = ?");
                    $stmt->execute([$langId]);
                    $lang = $stmt->fetch();
                    
                    if ($lang && $lang['is_default']) {
                        setFlash('error', 'Cannot delete the default language.');
                    } elseif ($lang) {
                        $stmt = $db->prepare("DELETE FROM languages WHERE id = ?");
                        $stmt->execute([$langId]);
                        logActivity('delete_language', 'language', $langId, ['name' => $lang['name']]);
                        setFlash('success', "Language deleted.");
                    }
                } catch (Exception $e) {
                    setFlash('error', 'Failed to delete language.');
                }
            }
        }

        if ($action === 'save_settings') {
            updateSetting('auto_detect_language', isset($_POST['auto_detect']) ? '1' : '0');
            setFlash('success', 'Regional settings updated.');
        }
    }
    redirect('language-settings.php');
}

// ============================================
// DATA FETCHING
// ============================================
$page = max(1, (int)($_GET['page'] ?? 1));
$query = "SELECT * FROM languages ORDER BY is_default DESC, name ASC";
$pagination = paginate($query, [], $page, ADMIN_PER_PAGE);
$languages = $pagination['data'];

$flash = getFlash();
$csrf = generateCSRF();
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
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>Settings</span>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>Multi-Language Manager</span>
            </div>
            <h1>Multi-Language Manager</h1>
            <p class="subtitle">Manage regional languages and localized content.</p>
        </div>
        <button class="btn btn-primary" onclick="openLangModal()">
            <i class="fas fa-plus"></i> Add Language
        </button>
    </div>

    <?php if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo clean($flash['message']); ?>
            <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <div class="left-col">
            <!-- Language List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-language"></i> Available Languages (<?php echo $pagination['total']; ?> total)</h3>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Language Name</th>
                                <th>Code</th>
                                <th>Direction</th>
                                <th>Status</th>
                                <th>Default</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($languages)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:20px;">No languages configured.</td></tr>
                            <?php else: ?>
                                <?php foreach ($languages as $lang): ?>
                                <tr>
                                    <td><strong><?php echo clean($lang['name']); ?></strong></td>
                                    <td><code><?php echo clean($lang['code']); ?></code></td>
                                    <td><span class="badge badge-info"><?php echo strtoupper($lang['direction'] ?? 'LTR'); ?></span></td>
                                    <td>
                                        <?php if ($lang['status']): ?>
                                            <span class="badge badge-success"><span class="badge-dot"></span> Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($lang['is_default']): ?>
                                            <span class="badge badge-primary">Default</span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display:flex; gap:6px; justify-content:flex-end;">
                                            <button class="btn btn-sm btn-secondary" onclick='editLang(<?php echo json_encode($lang); ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if (!$lang['is_default']): ?>
                                                <form method="POST" onsubmit="return confirm('Delete this language?')" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_language">
                                                    <input type="hidden" name="lang_id" value="<?php echo $lang['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="card-footer">
                        <div class="pagination">
                            <?php if ($pagination['has_prev']): ?>
                                <a href="language-settings.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn"><i class="fas fa-angle-double-left"></i></a>
                                <a href="language-settings.php<?php echo buildQueryParams(['page' => $pagination['page'] - 1]); ?>" class="page-btn"><i class="fas fa-angle-left"></i></a>
                            <?php else: ?>
                                <button class="page-btn" disabled><i class="fas fa-angle-double-left"></i></button>
                                <button class="page-btn" disabled><i class="fas fa-angle-left"></i></button>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                <a href="language-settings.php<?php echo buildQueryParams(['page' => $i]); ?>" class="page-btn <?php echo $i === $pagination['page'] ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>

                            <?php if ($pagination['has_next']): ?>
                                <a href="language-settings.php<?php echo buildQueryParams(['page' => $pagination['page'] + 1]); ?>" class="page-btn"><i class="fas fa-angle-right"></i></a>
                                <a href="language-settings.php<?php echo buildQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn"><i class="fas fa-angle-double-right"></i></a>
                            <?php else: ?>
                                <button class="page-btn" disabled><i class="fas fa-angle-right"></i></button>
                                <button class="page-btn" disabled><i class="fas fa-angle-double-right"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Translation Quick Search -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3><i class="fas fa-search"></i> Content Translation</h3>
                </div>
                <div class="card-body">
                    <div class="search-box" style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <div style="position: relative; flex: 1;">
                            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                            <input type="text" class="form-control" placeholder="Search product or category to translate..." style="padding-left: 35px;">
                        </div>
                        <button class="btn btn-secondary">Search</button>
                    </div>
                    <div class="empty-state" style="padding: 20px;">
                        <i class="fas fa-globe-asia" style="font-size: 32px; color: var(--border-color); margin-bottom: 10px;"></i>
                        <p>Search for an item above to manage its regional translations.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="right-col">
            <!-- Regional Settings -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-cog"></i> Regional Settings</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_settings">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Auto-Detect Language</label>
                            <div class="toggle-switch">
                                <input type="checkbox" name="auto_detect" id="auto_detect" <?php echo getSetting('auto_detect_language') === '1' ? 'checked' : ''; ?>>
                                <label for="auto_detect"></label>
                                <span style="font-size: 12px; margin-left: 10px; color: var(--text-secondary);">Based on browser/IP</span>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary full-width">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tool Card -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3><i class="fas fa-tools"></i> AI Tools</h3>
                </div>
                <div class="card-body">
                    <button class="btn btn-secondary full-width" style="margin-bottom: 10px;">
                        <i class="fas fa-sync"></i> Scan Missing Translations
                    </button>
                    <button class="btn btn-secondary full-width">
                        <i class="fas fa-magic"></i> Auto-Translate Empty Fields
                    </button>
                    <p style="font-size: 11px; color: var(--text-muted); margin-top: 10px; text-align: center;">
                        Uses Gemini AI to suggest regional content.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="langModal">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h3 id="modalTitle">Add Language</h3>
            <button class="modal-close" onclick="closeLangModal()">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="save_language">
                <input type="hidden" name="lang_id" id="langId" value="0">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                
                <div class="form-group">
                    <label class="form-label">Language Name</label>
                    <input type="text" name="name" id="langName" class="form-control" placeholder="e.g. Hindi" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Language Code (ISO)</label>
                    <input type="text" name="code" id="langCode" class="form-control" placeholder="e.g. hi" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Text Direction</label>
                    <select name="direction" id="langDirection" class="form-control">
                        <option value="ltr">LTR (Left to Right)</option>
                        <option value="rtl">RTL (Right to Left)</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                        <input type="checkbox" name="status" id="langStatus" checked> Active
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                        <input type="checkbox" name="is_default" id="langDefault"> Default
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeLangModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Language</button>
            </div>
        </form>
    </div>
</div>

<script>
function openLangModal() {
    document.getElementById('modalTitle').innerText = 'Add Language';
    document.getElementById('langId').value = '0';
    document.getElementById('langName').value = '';
    document.getElementById('langCode').value = '';
    document.getElementById('langDirection').value = 'ltr';
    document.getElementById('langStatus').checked = true;
    document.getElementById('langDefault').checked = false;
    document.getElementById('langModal').classList.add('show');
}

function closeLangModal() {
    document.getElementById('langModal').classList.remove('show');
}

function editLang(data) {
    document.getElementById('modalTitle').innerText = 'Edit Language';
    document.getElementById('langId').value = data.id;
    document.getElementById('langName').value = data.name;
    document.getElementById('langCode').value = data.code;
    document.getElementById('langDirection').value = data.direction || 'ltr';
    document.getElementById('langStatus').checked = data.status == 1;
    document.getElementById('langDefault').checked = data.is_default == 1;
    document.getElementById('langModal').classList.add('show');
}
</script>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>
</div>
</div>
</body>
</html>