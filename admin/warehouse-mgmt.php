<?php
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

$db = getDB();
$flash = getFlash();

// Handle warehouse actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        redirect('warehouse-mgmt.php');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $stmt = $db->prepare("INSERT INTO warehouses (name, location, contact_person, phone, status, created_at) VALUES (?,?,?,?,1,NOW())");
        $stmt->execute([
            sanitize($_POST['name'] ?? ''),
            sanitize($_POST['location'] ?? ''),
            sanitize($_POST['contact_person'] ?? ''),
            sanitize($_POST['phone'] ?? ''),
        ]);
        setFlash('success', 'Warehouse added.');
    } elseif ($action === 'toggle') {
        $id  = (int)$_POST['id'];
        $cur = (int)$_POST['current_status'];
        $db->prepare("UPDATE warehouses SET status = ? WHERE id = ?")->execute([$cur ? 0 : 1, $id]);
        setFlash('success', 'Warehouse status updated.');
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM warehouses WHERE id = ?")->execute([(int)$_POST['id']]);
        setFlash('success', 'Warehouse deleted.');
    }
    redirect('warehouse-mgmt.php');
}

$warehouses = $db->query("SELECT * FROM warehouses ORDER BY created_at DESC")->fetchAll();
$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Management - DesiVastra Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
                    <span>Warehouse Management</span>
                </div>
                <h1><i class="fas fa-warehouse" style="color:var(--gold-primary);margin-right:8px;"></i>Warehouse Management</h1>
                <p class="subtitle">Manage storage locations and inventory distribution.</p>
            </div>
            <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
                <i class="fas fa-plus"></i> Add Warehouse
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
                <h3><i class="fas fa-warehouse" style="color:var(--gold-primary);margin-right:8px;"></i>All Warehouses (<?php echo count($warehouses); ?>)</h3>
            </div>
            <?php if (empty($warehouses)): ?>
                <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
                    <i class="fas fa-warehouse" style="font-size:48px;opacity:0.3;display:block;margin-bottom:16px;"></i>
                    <p>No warehouses yet. Add your first warehouse.</p>
                </div>
            <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Location</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warehouses as $wh): ?>
                        <tr>
                            <td><?php echo $wh['id']; ?></td>
                            <td><strong><?php echo clean($wh['name']); ?></strong></td>
                            <td><?php echo clean($wh['location']); ?></td>
                            <td><?php echo clean($wh['contact_person']); ?></td>
                            <td><?php echo clean($wh['phone']); ?></td>
                            <td>
                                <span class="badge <?php echo $wh['status'] ? 'badge-success' : 'badge-secondary'; ?>">
                                    <?php echo $wh['status'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo $wh['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $wh['status']; ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary" title="Toggle Status">
                                        <i class="fas fa-toggle-<?php echo $wh['status'] ? 'on' : 'off'; ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this warehouse?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $wh['id']; ?>">
                                    <button type="submit" class="btn btn-sm" style="background:var(--danger-bg);color:var(--danger);border:1px solid rgba(231,76,60,0.2);">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Warehouse Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header">
            <h3><i class="fas fa-plus" style="color:var(--gold-primary);margin-right:8px;"></i>Add Warehouse</h3>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-body" style="display:flex;flex-direction:column;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Warehouse Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g., Mumbai Hub" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Location / Address</label>
                    <input type="text" name="location" class="form-control" placeholder="Full address">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="Manager name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="+91 98765 43210">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Warehouse</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>