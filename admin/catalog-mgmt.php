<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/core/app.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Catalog & Book Management - DesiVastra Admin
 */


$db = getDB();

// ============================================
// HANDLE ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($csrfToken)) {
        setFlash('error', 'Invalid security token.');
    } else {
        if ($action === 'save_catalog') {
            $id = (int)($_POST['catalog_id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $code = sanitize($_POST['code'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $status = (int)($_POST['status'] ?? 1);
            $sort_order = (int)($_POST['sort_order'] ?? 0);

            try {
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE catalogs SET name = ?, code = ?, description = ?, status = ?, sort_order = ? WHERE id = ?");
                    $stmt->execute([$name, $code, $description, $status, $sort_order, $id]);
                    logActivity('update_catalog', 'catalog', $id, ['name' => $name]);
                    setFlash('success', 'Catalog updated successfully.');
                } else {
                    $stmt = $db->prepare("INSERT INTO catalogs (name, code, description, status, sort_order) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $code, $description, $status, $sort_order]);
                    $newId = $db->lastInsertId();
                    logActivity('create_catalog', 'catalog', $newId, ['name' => $name]);
                    setFlash('success', 'New catalog created successfully.');
                }
            } catch (Exception $e) {
                setFlash('error', 'Database error: ' . $e->getMessage());
            }
        }

        if ($action === 'delete_catalog') {
            $id = (int)($_POST['catalog_id'] ?? 0);
            try {
                $stmt = $db->prepare("DELETE FROM catalogs WHERE id = ?");
                $stmt->execute([$id]);
                logActivity('delete_catalog', 'catalog', $id);
                setFlash('success', 'Catalog deleted successfully.');
            } catch (Exception $e) {
                setFlash('error', 'Failed to delete catalog.');
            }
        }
    }
    redirect('catalog-mgmt.php');
}

// ============================================
// DATA FETCHING
// ============================================
$page = (int)($_GET['page'] ?? 1);
$search = sanitize($_GET['search'] ?? '');

$query = "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.catalog_id = c.id) as product_count FROM catalogs c";
$params = [];

if ($search) {
    $query .= " WHERE c.name LIKE ? OR c.code LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY c.sort_order ASC, c.id DESC";

$pagination = paginate($query, $params, $page, ADMIN_PER_PAGE);
$catalogs = $pagination['data'];
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
    <?php require_once __DIR__ . '/../includes/layout.php'; ?>
<div class="page-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>Catalogs</span>
            </div>
            <h1>Catalog & Book Management</h1>
            <p class="subtitle">Organize and manage your product catalogs and collections.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-secondary" onclick="openUploadModal()">
                <i class="fas fa-upload"></i> Bulk Upload Media
            </button>
            <button class="btn btn-primary" onclick="openCatalogModal()">
                <i class="fas fa-plus"></i> Add New Catalog
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php $flash = getFlash(); if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo clean($flash['message']); ?>
            <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Catalog List -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-book" style="color:var(--gold-primary);margin-right:8px;"></i> Website Catalogs (<?php echo $pagination['total']; ?> total)</h3>
            <div class="header-tools">
                <form method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Search catalogs..." value="<?php echo clean($search); ?>" class="form-control">
                    <button type="submit" class="btn btn-icon"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Cover</th>
                        <th>Catalog Details</th>
                        <th>Code</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($catalogs)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);">
                                No catalogs found. Click "Add New Catalog" to begin.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($catalogs as $catalog): ?>
                            <tr>
                                <td>
                                    <?php if ($catalog['cover_image']): ?>
                                        <img src="../<?php echo clean($catalog['cover_image']); ?>" alt="" class="img-thumb" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">
                                    <?php else: ?>
                                        <div class="img-placeholder" style="width:50px;height:50px;background:var(--bg-input);display:flex;align-items:center;justify-content:center;border-radius:4px;">
                                            <i class="fas fa-image" style="color:var(--text-muted)"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:600;color:var(--text-primary);"><?php echo clean($catalog['name']); ?></div>
                                    <div style="font-size:11px;color:var(--text-muted);"><?php echo clean(substr($catalog['description'], 0, 50)); ?><?php echo strlen($catalog['description']) > 50 ? '...' : ''; ?></div>
                                </td>
                                <td><code style="color:var(--gold-primary);"><?php echo clean($catalog['code']); ?></code></td>
                                <td>
                                    <span class="badge badge-info"><?php echo $catalog['product_count']; ?> Products</span>
                                </td>
                                <td>
                                    <?php if ($catalog['status']): ?>
                                        <span class="badge badge-success"><span class="badge-dot"></span> Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><span class="badge-dot"></span> Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div class="btn-group" style="display: flex; gap: 6px; justify-content: flex-end;">
                                        <button class="btn btn-icon" title="Generate PDF" onclick="generatePDF(<?php echo $catalog['id']; ?>)">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                        <a href="products.php?catalog=<?php echo $catalog['id']; ?>" class="btn btn-icon" title="Manage Products">
                                            <i class="fas fa-boxes"></i>
                                        </a>
                                        <button class="btn btn-icon" title="Edit" onclick="editCatalog(<?php echo htmlspecialchars(json_encode($catalog)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-icon danger" title="Delete" onclick="confirmDelete(<?php echo $catalog['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
                        Showing page <?php echo $pagination['page']; ?> of <?php echo $pagination['total_pages']; ?> &mdash; <?php echo number_format($pagination['total']); ?> total catalogs
                    </div>
                    <div class="pagination" style="margin-top: 0;">
                        <?php if ($pagination['has_prev']): ?>
                            <a href="catalog-mgmt.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn" title="First Page"><i class="fas fa-angle-double-left"></i></a>
                            <a href="catalog-mgmt.php<?php echo buildQueryParams(['page' => $pagination['page'] - 1]); ?>" class="page-btn" title="Previous"><i class="fas fa-angle-left"></i></a>
                        <?php else: ?>
                            <button class="page-btn" disabled><i class="fas fa-angle-double-left"></i></button>
                            <button class="page-btn" disabled><i class="fas fa-angle-left"></i></button>
                        <?php endif; ?>

                        <?php
                            $startPage = max(1, $pagination['page'] - 2);
                            $endPage = min($pagination['total_pages'], $pagination['page'] + 2);
                            for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="catalog-mgmt.php<?php echo buildQueryParams(['page' => $i]); ?>" class="page-btn <?php echo $i === $pagination['page'] ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($pagination['has_next']): ?>
                            <a href="catalog-mgmt.php<?php echo buildQueryParams(['page' => $pagination['page'] + 1]); ?>" class="page-btn" title="Next"><i class="fas fa-angle-right"></i></a>
                            <a href="catalog-mgmt.php<?php echo buildQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn" title="Last Page"><i class="fas fa-angle-double-right"></i></a>
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

<!-- Add/Edit Modal -->
<div id="catalogModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Catalog</h3>
            <button class="close-btn" onclick="closeModal('catalogModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="save_catalog">
            <input type="hidden" name="catalog_id" id="cat_id" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Catalog Name</label>
                    <input type="text" name="name" id="cat_name" required class="form-control" placeholder="e.g. Obsidian Collection 2024">
                </div>
                <div class="form-group">
                    <label>Catalog Code</label>
                    <input type="text" name="code" id="cat_code" required class="form-control" placeholder="e.g. OBS-2024">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="cat_description" class="form-control" rows="3" placeholder="Brief description of the catalog..."></textarea>
                </div>
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="cat_sort_order" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="cat_status" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('catalogModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Catalog</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="close-btn" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="delete_catalog">
            <input type="hidden" name="catalog_id" id="delete_cat_id" value="">
            
            <div class="modal-body">
                <p>Are you sure you want to delete this catalog? Products associated with it will remain but won't belong to a catalog.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCatalogModal() {
    document.getElementById('modalTitle').innerText = 'Add New Catalog';
    document.getElementById('cat_id').value = '';
    document.getElementById('cat_name').value = '';
    document.getElementById('cat_code').value = '';
    document.getElementById('cat_description').value = '';
    document.getElementById('cat_sort_order').value = '0';
    document.getElementById('cat_status').value = '1';
    document.getElementById('catalogModal').classList.add('show');
}

function editCatalog(catalog) {
    document.getElementById('modalTitle').innerText = 'Edit Catalog';
    document.getElementById('cat_id').value = catalog.id;
    document.getElementById('cat_name').value = catalog.name;
    document.getElementById('cat_code').value = catalog.code;
    document.getElementById('cat_description').value = catalog.description || '';
    document.getElementById('cat_sort_order').value = catalog.sort_order || '0';
    document.getElementById('cat_status').value = catalog.status;
    document.getElementById('catalogModal').classList.add('show');
}

function confirmDelete(id) {
    document.getElementById('delete_cat_id').value = id;
    document.getElementById('deleteModal').classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function generatePDF(id) {
    alert('Generating PDF catalog for ID: ' + id + '... (Feature being implemented in Step 28)');
}

function openUploadModal() {
    alert('Bulk Upload Media tool... (Feature being implemented in Step 28)');
}
</script>
</div>
</body>
</html>