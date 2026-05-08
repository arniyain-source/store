<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Staff & Workflow Management - DesiVastra Admin
 */
require_once __DIR__ . '/includes/layout.php';

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
        if ($action === 'save_staff') {
            $id = (int)($_POST['staff_id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $role = sanitize($_POST['role'] ?? 'admin');
            $status = (int)($_POST['status'] ?? 1);
            $password = $_POST['password'] ?? '';

            try {
                if ($id > 0) {
                    if (!empty($password)) {
                        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $db->prepare("UPDATE admins SET name = ?, email = ?, password = ?, role = ?, status = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $hashedPassword, $role, $status, $id]);
                    } else {
                        $stmt = $db->prepare("UPDATE admins SET name = ?, email = ?, role = ?, status = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $status, $id]);
                    }
                    setFlash('success', 'Staff member updated successfully.');
                } else {
                    $hashedPassword = password_hash($password ?: 'Staff@123', PASSWORD_BCRYPT);
                    $stmt = $db->prepare("INSERT INTO admins (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hashedPassword, $role, $status]);
                    setFlash('success', 'New staff member added successfully.');
                }
                logActivity($id > 0 ? 'update_staff' : 'create_staff', 'staff', $id ?: $db->lastInsertId(), ['name' => $name]);
            } catch (Exception $e) {
                setFlash('error', 'Database error: ' . $e->getMessage());
            }
        } elseif ($action === 'delete_staff') {
            $id = (int)($_POST['staff_id'] ?? 0);
            if ($id === (int)$_SESSION['admin_id']) {
                setFlash('error', 'You cannot delete your own account.');
            } else {
                try {
                    $stmt = $db->prepare("DELETE FROM admins WHERE id = ?");
                    $stmt->execute([$id]);
                    setFlash('success', 'Staff member removed.');
                    logActivity('delete_staff', 'staff', $id);
                } catch (Exception $e) {
                    setFlash('error', 'Error removing staff.');
                }
            }
        }
    }
    redirect('staff-mgmt.php' . buildQueryParams([]));
}

// ============================================
// DATA FETCHING
// ============================================
$search = sanitize($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$where = "1=1";
$params = [];
if ($search) {
    $where .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query = "SELECT * FROM admins WHERE $where ORDER BY created_at DESC";
$pagination = paginate($query, $params, $page, ADMIN_PER_PAGE);
$staffList = $pagination['data'];

$activeCount = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM admins WHERE status = 1");
    $activeCount = (int)$stmt->fetch()['cnt'];
} catch (Exception $e) {}

$csrf = generateCSRF();
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user-shield" style="color:var(--gold-primary);margin-right:8px"></i>Staff Management</h1>
            <p class="subtitle">Manage team roles, permissions, and internal workflows</p>
        </div>
        <button class="btn btn-primary" onclick="openStaffModal()">
            <i class="fas fa-user-plus"></i> Add New Staff
        </button>
    </div>

    <?php $flash = getFlash(); if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <?php echo clean($flash['message']); ?>
            <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Staff Members <span style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:8px">(<?php echo $pagination['total']; ?> total)</span></h3>
            <form method="GET" class="search-box" style="width:250px">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search team..." value="<?php echo clean($search); ?>">
            </form>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffList)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted)">No staff members found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staffList as $staff): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;color:var(--text-primary)"><?php echo clean($staff['name']); ?></div>
                                    <div style="font-size:11px;color:var(--text-muted)">ID: #<?php echo $staff['id']; ?></div>
                                </td>
                                <td><?php echo clean($staff['email']); ?></td>
                                <td>
                                    <?php
                                    $roleClass = match($staff['role']) {
                                        'super_admin' => 'badge-danger',
                                        'editor'      => 'badge-info',
                                        default       => 'badge-primary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $roleClass; ?>"><?php echo ucfirst(str_replace('_', ' ', $staff['role'])); ?></span>
                                </td>
                                <td>
                                    <?php if ($staff['status']): ?>
                                        <span class="badge badge-success"><span class="badge-dot" style="background:var(--success)"></span> Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:flex;gap:6px;justify-content:flex-end">
                                        <button class="btn btn-sm btn-secondary" onclick='editStaff(<?php echo json_encode($staff); ?>)' title="Edit Profile">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Remove this staff member?')" style="display:inline">
                                            <input type="hidden" name="action" value="delete_staff">
                                            <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" <?php echo $staff['id'] == $_SESSION['admin_id'] ? 'disabled' : ''; ?>>
                                                <i class="fas fa-trash-alt"></i>
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
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                    <div style="font-size:12px;color:var(--text-muted)">
                        Showing page <?php echo $pagination['page']; ?> of <?php echo $pagination['total_pages']; ?>
                    </div>
                    <div class="pagination" style="margin-top:0">
                        <?php if ($pagination['has_prev']): ?>
                            <a href="staff-mgmt.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn" data-tooltip="First Page">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="staff-mgmt.php<?php echo buildQueryParams(['page' => $pagination['page'] - 1]); ?>" class="page-btn" data-tooltip="Previous">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php else: ?>
                            <button class="page-btn" disabled><i class="fas fa-angle-double-left"></i></button>
                            <button class="page-btn" disabled><i class="fas fa-angle-left"></i></button>
                        <?php endif; ?>

                        <?php
                            $startPage = max(1, $pagination['page'] - 2);
                            $endPage = min($pagination['total_pages'], $pagination['page'] + 2);
                            for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="staff-mgmt.php<?php echo buildQueryParams(['page' => $i]); ?>" class="page-btn <?php echo $i === $pagination['page'] ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pagination['has_next']): ?>
                            <a href="staff-mgmt.php<?php echo buildQueryParams(['page' => $pagination['page'] + 1]); ?>" class="page-btn" data-tooltip="Next">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="staff-mgmt.php<?php echo buildQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn" data-tooltip="Last Page">
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

<!-- Add/Edit Staff Modal -->
<div class="modal-overlay" id="staffModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Staff</h3>
            <button class="modal-close" onclick="closeStaffModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_staff">
            <input type="hidden" name="staff_id" id="staff_id" value="0">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" id="staff_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="staff_email" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" id="staff_role" class="form-control">
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                            <option value="editor">Editor / Product Staff</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="staff_status" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" id="staff_password" class="form-control" placeholder="Leave empty to keep current (min 6 chars)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeStaffModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Staff Member</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStaffModal() {
    document.getElementById('modalTitle').innerText = 'Add New Staff';
    document.getElementById('staff_id').value = '0';
    document.getElementById('staff_name').value = '';
    document.getElementById('staff_email').value = '';
    document.getElementById('staff_role').value = 'admin';
    document.getElementById('staff_status').value = '1';
    document.getElementById('staff_password').placeholder = "Initial password (default: Staff@123)";
    document.getElementById('staffModal').classList.add('show');
}

function editStaff(data) {
    document.getElementById('modalTitle').innerText = 'Edit Staff Member';
    document.getElementById('staff_id').value = data.id;
    document.getElementById('staff_name').value = data.name;
    document.getElementById('staff_email').value = data.email;
    document.getElementById('staff_role').value = data.role;
    document.getElementById('staff_status').value = data.status;
    document.getElementById('staff_password').placeholder = "Leave empty to keep current";
    document.getElementById('staffModal').classList.add('show');
}

function closeStaffModal() {
    document.getElementById('staffModal').classList.remove('show');
}
</script>