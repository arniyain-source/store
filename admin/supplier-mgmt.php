<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Supplier & Vendor Management - DesiVastra Admin
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
        if ($action === 'save_supplier') {
            $id = (int)($_POST['supplier_id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $company = sanitize($_POST['company_name'] ?? '');
            $mobile = sanitize($_POST['mobile'] ?? '');
            $whatsapp = sanitize($_POST['whatsapp'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $gst = sanitize($_POST['gst_number'] ?? '');
            $address = sanitize($_POST['address'] ?? '');
            $city = sanitize($_POST['city'] ?? '');
            $state = sanitize($_POST['state'] ?? '');
            $pincode = sanitize($_POST['pincode'] ?? '');
            $terms = sanitize($_POST['payment_terms'] ?? '');
            $status = (int)($_POST['status'] ?? 1);

            try {
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE suppliers SET name = ?, company_name = ?, mobile = ?, whatsapp = ?, email = ?, gst_number = ?, address = ?, city = ?, state = ?, pincode = ?, payment_terms = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $company, $mobile, $whatsapp, $email, $gst, $address, $city, $state, $pincode, $terms, $status, $id]);
                    setFlash('success', 'Supplier "' . $company . '" updated successfully.');
                } else {
                    $stmt = $db->prepare("INSERT INTO suppliers (name, company_name, mobile, whatsapp, email, gst_number, address, city, state, pincode, payment_terms, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $company, $mobile, $whatsapp, $email, $gst, $address, $city, $state, $pincode, $terms, $status]);
                    $id = $db->lastInsertId();
                    setFlash('success', 'New supplier "' . $company . '" added.');
                }
                logActivity($id > 0 ? 'edit_supplier' : 'add_supplier', 'supplier', $id, ['company' => $company]);
            } catch (Exception $e) {
                setFlash('error', 'Database error: ' . $e->getMessage());
            }
        }

        if ($action === 'delete_supplier') {
            $id = (int)($_POST['supplier_id'] ?? 0);
            try {
                $stmt = $db->prepare("SELECT company_name FROM suppliers WHERE id = ?");
                $stmt->execute([$id]);
                $sName = $stmt->fetchColumn();
                
                $stmt = $db->prepare("DELETE FROM suppliers WHERE id = ?");
                $stmt->execute([$id]);
                logActivity('delete_supplier', 'supplier', $id, ['company' => $sName]);
                setFlash('success', 'Supplier deleted successfully.');
            } catch (Exception $e) {
                setFlash('error', 'Failed to delete supplier. It may be linked to purchase orders.');
            }
        }

        if ($action === 'toggle_status') {
            $id = (int)($_POST['supplier_id'] ?? 0);
            $status = (int)($_POST['status'] ?? 1);
            $stmt = $db->prepare("UPDATE suppliers SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            logActivity('toggle_supplier_status', 'supplier', $id, ['new_status' => $status]);
            setFlash('success', 'Supplier status updated.');
        }
    }
    redirect('supplier-mgmt.php' . buildQueryParams());
}

// ============================================
// DATA FETCHING
// ============================================
$search = sanitize($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$whereClauses = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(company_name LIKE ? OR name LIKE ? OR mobile LIKE ? OR email LIKE ?)";
    $st = "%$search%";
    $params = [$st, $st, $st, $st];
}

$whereSQL = implode(' AND ', $whereClauses);
$query = "SELECT * FROM suppliers WHERE {$whereSQL} ORDER BY created_at DESC";

$pagination = paginate($query, $params, $page, ADMIN_PER_PAGE);
$suppliers = $pagination['data'];

?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-truck-field" style="color: var(--gold-primary); margin-right: 8px;"></i>Supplier & Vendor Management</h1>
            <p class="subtitle">Manage manufacturers and product procurement sources</p>
        </div>
        <button class="btn btn-primary" onclick="openSupplierModal()">
            <i class="fas fa-plus"></i> Add New Supplier
        </button>
    </div>

    <!-- Flash Message -->
    <?php $flash = getFlash(); if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo clean($flash['message']); ?>
            <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Search & Filter -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-body" style="padding: 16px 20px;">
            <form method="GET" class="filter-bar" style="margin-bottom: 0;">
                <div style="position: relative; flex: 1;">
                    <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px;"></i>
                    <input type="text" name="search" class="search-input" placeholder="Search by company, name, email or phone..." value="<?php echo clean($search); ?>" style="width: 100%;">
                </div>
                <button type="submit" class="btn btn-secondary btn-sm">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if ($search): ?>
                    <a href="supplier-mgmt.php" class="btn btn-sm" style="background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(231,76,60,0.2);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Supplier Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list" style="margin-right: 8px; color: var(--gold-primary);"></i>Suppliers (<?php echo $pagination['total']; ?> total)</h3>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Contact Person</th>
                        <th>WhatsApp / Mobile</th>
                        <th>City</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:48px;">
                            <div style="color: var(--text-muted);">
                                <i class="fas fa-truck-ramp-box" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                                No suppliers found.
                            </div>
                        </td></tr>
                    <?php else: foreach ($suppliers as $s): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: var(--text-primary);"><?php echo clean($s['company_name']); ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?php echo clean($s['email']); ?></div>
                            </td>
                            <td><?php echo clean($s['name']); ?></td>
                            <td>
                                <?php if($s['whatsapp']): ?>
                                    <a href="https://wa.me/<?php echo clean($s['whatsapp']); ?>" target="_blank" style="color: #25D366; font-size: 13px; font-weight: 500;">
                                        <i class="fab fa-whatsapp"></i> <?php echo clean($s['whatsapp']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 13px; color: var(--text-secondary);"><?php echo clean($s['mobile'] ?: '—'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo clean($s['city'] ?: '—'); ?></td>
                            <td>
                                <span class="badge <?php echo $s['status'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <span class="badge-dot" style="background: var(--<?php echo $s['status'] ? 'success' : 'danger'; ?>);"></span>
                                    <?php echo $s['status'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                    <button class="btn btn-sm btn-secondary" onclick="editSupplier(<?php echo htmlspecialchars(json_encode($s)); ?>)" data-tooltip="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $s['id']; ?>, '<?php echo clean(addslashes($s['company_name'])); ?>')" data-tooltip="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div style="font-size: 12px; color: var(--text-muted);">
                        Showing page <?php echo $pagination['page']; ?> of <?php echo $pagination['total_pages']; ?>
                    </div>
                    <div class="pagination" style="margin-top: 0;">
                        <?php if ($pagination['has_prev']): ?>
                            <a href="supplier-mgmt.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn"><i class="fas fa-angle-double-left"></i></a>
                            <a href="supplier-mgmt.php<?php echo buildQueryParams(['page' => $pagination['page'] - 1]); ?>" class="page-btn"><i class="fas fa-angle-left"></i></a>
                        <?php else: ?>
                            <button class="page-btn" disabled><i class="fas fa-angle-double-left"></i></button>
                            <button class="page-btn" disabled><i class="fas fa-angle-left"></i></button>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                            <a href="supplier-mgmt.php<?php echo buildQueryParams(['page' => $i]); ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($pagination['has_next']): ?>
                            <a href="supplier-mgmt.php<?php echo buildQueryParams(['page' => $pagination['page'] + 1]); ?>" class="page-btn"><i class="fas fa-angle-right"></i></a>
                            <a href="supplier-mgmt.php<?php echo buildQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn"><i class="fas fa-angle-double-right"></i></a>
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

<!-- Add/Edit Supplier Modal -->
<div class="modal-overlay" id="supplierModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Supplier</h3>
            <button class="modal-close" onclick="closeSupplierModal()">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="save_supplier">
                <input type="hidden" name="supplier_id" id="f_supplier_id" value="">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
                    <div class="form-section">
                        <h4 style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--gold-primary); margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">Basic Information</h4>
                        <div class="form-group">
                            <label class="form-label">Company Name *</label>
                            <input type="text" name="company_name" id="f_company_name" required placeholder="Business Name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Person *</label>
                            <input type="text" name="name" id="f_name" required placeholder="Full Name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" id="f_status">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">GSTIN</label>
                            <input type="text" name="gst_number" id="f_gst" placeholder="GST Number">
                        </div>
                    </div>

                    <div class="form-section">
                        <h4 style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--gold-primary); margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">Contact & Address</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="form-group">
                                <label class="form-label">Mobile</label>
                                <input type="text" name="mobile" id="f_mobile" placeholder="Phone">
                            </div>
                            <div class="form-group">
                                <label class="form-label">WhatsApp *</label>
                                <input type="text" name="whatsapp" id="f_whatsapp" required placeholder="WhatsApp">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="f_email" placeholder="Email Address">
                        </div>
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" id="f_city" placeholder="Operating City">
                        </div>
                    </div>
                </div>

                <div class="form-section" style="margin-top: 10px;">
                    <h4 style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--gold-primary); margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">Full Address & Terms</h4>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Street Address</label>
                            <textarea name="address" id="f_address" style="min-height: 80px;" placeholder="Full workshop/office address"></textarea>
                        </div>
                        <div>
                            <div class="form-group">
                                <label class="form-label">State</label>
                                <input type="text" name="state" id="f_state" placeholder="State">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Pincode</label>
                                <input type="text" name="pincode" id="f_pincode" placeholder="Pincode">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Terms & Notes</label>
                        <textarea name="payment_terms" id="f_terms" placeholder="e.g. Net 30, 5% Discount on Cash, etc."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSupplierModal()">Cancel</button>
                <button type="submit" name="save_supplier" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Supplier
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 30px 20px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: var(--danger); margin-bottom: 15px;"></i>
            <p>Are you sure you want to delete supplier <strong id="deleteSupplierName" style="color: var(--text-primary);"></strong>?</p>
            <p style="font-size: 12px; color: var(--text-muted); margin-top: 10px;">This action cannot be undone if there are no related records.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="delete_supplier">
                <input type="hidden" name="supplier_id" id="deleteSupplierId" value="">
                <button type="submit" class="btn btn-danger">Delete Now</button>
            </form>
        </div>
    </div>
</div>

<script>
function openSupplierModal() {
    document.getElementById('modalTitle').innerText = 'Add New Supplier';
    document.getElementById('f_supplier_id').value = '';
    document.getElementById('f_name').value = '';
    document.getElementById('f_company_name').value = '';
    document.getElementById('f_mobile').value = '';
    document.getElementById('f_whatsapp').value = '';
    document.getElementById('f_email').value = '';
    document.getElementById('f_gst').value = '';
    document.getElementById('f_address').value = '';
    document.getElementById('f_city').value = '';
    document.getElementById('f_state').value = '';
    document.getElementById('f_pincode').value = '';
    document.getElementById('f_terms').value = '';
    document.getElementById('f_status').value = '1';
    document.getElementById('supplierModal').classList.add('show');
}

function editSupplier(data) {
    document.getElementById('modalTitle').innerText = 'Edit Supplier';
    document.getElementById('f_supplier_id').value = data.id;
    document.getElementById('f_name').value = data.name;
    document.getElementById('f_company_name').value = data.company_name;
    document.getElementById('f_mobile').value = data.mobile;
    document.getElementById('f_whatsapp').value = data.whatsapp;
    document.getElementById('f_email').value = data.email || '';
    document.getElementById('f_gst').value = data.gst_number || '';
    document.getElementById('f_address').value = data.address || '';
    document.getElementById('f_city').value = data.city || '';
    document.getElementById('f_state').value = data.state || '';
    document.getElementById('f_pincode').value = data.pincode || '';
    document.getElementById('f_terms').value = data.payment_terms || '';
    document.getElementById('f_status').value = data.status;
    document.getElementById('supplierModal').classList.add('show');
}

function closeSupplierModal() {
    document.getElementById('supplierModal').classList.remove('show');
}

function confirmDelete(id, name) {
    document.getElementById('deleteSupplierId').value = id;
    document.getElementById('deleteSupplierName').innerText = name;
    document.getElementById('deleteModal').classList.add('show');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
}
</script>

</body>
</html>