<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * CMS Page Builder - DesiVastra Admin
 */
require_once __DIR__ . '/admin/includes/layout.php';

$db = getDB();
$csrf = generateCSRF();

// ============================================
// HANDLE ACTIONS (CREATE/UPDATE/DELETE)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Security verification failed.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_page') {
            $id = (int)($_POST['page_id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $slug = !empty($_POST['slug']) ? sanitize($_POST['slug']) : generateSlug($title);
            $content = $_POST['content'] ?? ''; // Keep HTML for CMS
            $status = sanitize($_POST['status'] ?? 'draft');
            $metaTitle = sanitize($_POST['meta_title'] ?? '');
            $metaDescription = sanitize($_POST['meta_description'] ?? '');

            try {
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE pages SET title = ?, slug = ?, content = ?, meta_title = ?, meta_description = ?, status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$title, $slug, $content, $metaTitle, $metaDescription, $status, $id]);
                    setFlash('success', 'Page updated successfully.');
                } else {
                    $stmt = $db->prepare("INSERT INTO pages (title, slug, content, meta_title, meta_description, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $slug, $content, $metaTitle, $metaDescription, $status]);
                    $id = $db->lastInsertId();
                    setFlash('success', 'Page created successfully.');
                }
                logActivity($id > 0 ? 'update_page' : 'create_page', 'cms', $id, ['title' => $title]);
            } catch (Exception $e) {
                setFlash('error', 'Error saving page: ' . $e->getMessage());
            }
        }

        if ($action === 'delete_page') {
            $id = (int)($_POST['page_id'] ?? 0);
            try {
                $stmt = $db->prepare("DELETE FROM pages WHERE id = ?");
                $stmt->execute([$id]);
                setFlash('success', 'Page deleted successfully.');
                logActivity('delete_page', 'cms', $id);
            } catch (Exception $e) {
                setFlash('error', 'Error deleting page.');
            }
        }
    }
    redirect('cms-pages.php');
}

// ============================================
// FETCH DATA
// ============================================
$page = max(1, (int)($_GET['page'] ?? 1));
$query = "SELECT * FROM pages ORDER BY updated_at DESC";
$pagination = paginate($query, [], $page, ADMIN_PER_PAGE);
$pages = $pagination['data'];
$pageCount = $pagination['total'];
$flash = getFlash();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Page Builder - DesiVastra Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="admin-layout">
    <div class="page-content">
        
        <div class="page-header">
            <div>
                <h1><i class="fas fa-file-alt" style="color: var(--gold-primary); margin-right: 8px;"></i>Page Builder</h1>
                <p class="subtitle">Manage and build custom website pages</p>
            </div>
            <button class="btn btn-primary" onclick="openPageModal()">
                <i class="fas fa-plus"></i> Add New Page
            </button>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo clean($flash['message']); ?>
                <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Website Pages (<?php echo $pageCount; ?> total)</h3>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Page Title</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pages)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    No pages found. Start by creating one.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pages as $p): ?>
                                <tr>
                                    <td><strong><?php echo clean($p['title']); ?></strong></td>
                                    <td><code>/<?php echo clean($p['slug']); ?></code></td>
                                    <td>
                                        <span class="badge <?php echo $p['status'] === 'published' ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo ucfirst($p['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y, H:i', strtotime($p['updated_at'])); ?></td>
                                    <td style="text-align: right;">
                                        <div class="action-btns">
                                            <a href="../page.php?slug=<?php echo clean($p['slug']); ?>" target="_blank" class="btn btn-sm btn-secondary" title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-secondary" onclick='editPage(<?php echo json_encode($p); ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this page permanently?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                <input type="hidden" name="action" value="delete_page">
                                                <input type="hidden" name="page_id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                        <div style="font-size: 12px; color: var(--text-muted);">
                            Showing page <?php echo $pagination['page']; ?> of <?php echo $pagination['total_pages']; ?>
                        </div>
                        <div class="pagination" style="margin-top: 0;">
                            <?php if ($pagination['has_prev']): ?>
                                <a href="cms-pages.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="cms-pages.php<?php echo buildQueryParams(['page' => $pagination['page'] - 1]); ?>" class="page-btn">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php else: ?>
                                <button class="page-btn" disabled><i class="fas fa-angle-double-left"></i></button>
                                <button class="page-btn" disabled><i class="fas fa-angle-left"></i></button>
                            <?php endif; ?>

                            <?php for ($i = max(1, $pagination['page'] - 2); $i <= min($pagination['total_pages'], $pagination['page'] + 2); $i++): ?>
                                <a href="cms-pages.php<?php echo buildQueryParams(['page' => $i]); ?>" class="page-btn <?php echo $i === $pagination['page'] ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($pagination['has_next']): ?>
                                <a href="cms-pages.php<?php echo buildQueryParams(['page' => $pagination['page'] + 1]); ?>" class="page-btn">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="cms-pages.php<?php echo buildQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php else: ?>
                                <button class="page-btn" disabled><i class="fas fa-angle-right"></i></button>
                                <button class="page-btn" disabled><i class="fas fa-angle-double-right"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Page Editor Modal -->
<div id="pageModal" class="modal-overlay">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="modalTitle">Create New Page</h3>
            <button class="modal-close" onclick="closePageModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="save_page">
            <input type="hidden" name="page_id" id="field_id" value="0">
            
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                    <!-- Content Side -->
                    <div>
                        <div class="form-group">
                            <label class="form-label">Page Title</label>
                            <input type="text" name="title" id="field_title" class="form-control" required placeholder="e.g., About Us">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Slug</label>
                            <input type="text" name="slug" id="field_slug" class="form-control" placeholder="about-us">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Page Content (HTML supported)</label>
                            <textarea name="content" id="field_content" class="form-control" style="height: 350px; font-family: inherit;"></textarea>
                        </div>
                    </div>
                    
                    <!-- Settings Side -->
                    <div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" id="field_status" class="form-control">
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                        <div class="detail-divider" style="margin: 20px 0; border-top: 1px solid var(--border-color);"></div>
                        <h4 style="font-size: 12px; text-transform: uppercase; color: var(--gold-primary); margin-bottom: 15px;">SEO Optimization</h4>
                        <div class="form-group">
                            <label class="form-label">Meta Title</label>
                            <input type="text" name="meta_title" id="field_meta_title" class="form-control" placeholder="Max 60 chars">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Meta Description</label>
                            <textarea name="meta_description" id="field_meta_desc" class="form-control" style="height: 120px;" placeholder="Max 160 chars"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePageModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Page</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPageModal() {
    document.getElementById('modalTitle').innerText = 'Create New Page';
    document.getElementById('field_id').value = '0';
    document.getElementById('field_title').value = '';
    document.getElementById('field_slug').value = '';
    document.getElementById('field_content').value = '';
    document.getElementById('field_meta_title').value = '';
    document.getElementById('field_meta_desc').value = '';
    document.getElementById('field_status').value = 'draft';
    document.getElementById('pageModal').classList.add('show');
}

function closePageModal() {
    document.getElementById('pageModal').classList.remove('show');
}

function editPage(page) {
    document.getElementById('modalTitle').innerText = 'Edit Page: ' + page.title;
    document.getElementById('field_id').value = page.id;
    document.getElementById('field_title').value = page.title;
    document.getElementById('field_slug').value = page.slug;
    document.getElementById('field_content').value = page.content || '';
    document.getElementById('field_status').value = page.status;
    document.getElementById('field_meta_title').value = page.meta_title || '';
    document.getElementById('field_meta_desc').value = page.meta_description || '';
    document.getElementById('pageModal').classList.add('show');
}
</script>

</body>
</html>