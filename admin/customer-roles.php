<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * User Role & Pricing Visibility Manager - DesiVastra Admin
 */
require_once __DIR__ . '/includes/layout.php';

$db = getDB();
$csrf = generateCSRF();

// ============================================
// HANDLE POST ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        // Handle MOQ & Margin Updates
        if (isset($_POST['action']) && $_POST['action'] === 'update_moq') {
            try {
                $stmt = $db->prepare("UPDATE moq_rules SET min_qty = ?, min_amount = ? WHERE user_type = ?");
                $stmt->execute([(int)$_POST['wholesale_qty'], (float)$_POST['wholesale_amount'], 'wholesale']);
                $stmt->execute([(int)$_POST['retailer_qty'], (float)$_POST['retailer_amount'], 'retailer']);
                
                updateSetting('reseller_default_margin', (float)$_POST['reseller_margin']);
                
                logActivity('update_moq_rules', 'system', 0, ['details' => 'Updated MOQ and Reseller margin settings']);
                setFlash('success', 'Rule settings updated successfully.');
            } catch (Exception $e) {
                setFlash('error', 'Failed to update MOQ rules: ' . $e->getMessage());
            }
        }
        
        // Handle Role Permissions Updates
        if (isset($_POST['action']) && $_POST['action'] === 'update_permissions') {
            $roles = ['customer', 'reseller', 'retailer', 'wholesale'];
            $permissions_list = ['view_regular_price', 'view_reseller_price', 'view_wholesale_price', 'show_margin_calculator', 'enable_url_sharing', 'bulk_order_only'];
            
            foreach ($roles as $role) {
                $role_perms = [];
                foreach ($permissions_list as $perm) {
                    $role_perms[$perm] = isset($_POST["perm_{$role}_{$perm}"]) ? '1' : '0';
                }
                updateSetting("role_permissions_{$role}", json_encode($role_perms));
            }
            
            logActivity('update_role_permissions', 'system', 0, ['details' => 'Updated global role permissions']);
            setFlash('success', 'Role permissions updated successfully.');
        }

        // Handle Visibility Toggles
        if (isset($_POST['action']) && $_POST['action'] === 'update_visibility') {
            $settings = [
                'show_wholesale_guest' => $_POST['show_wholesale_guest'] ?? '0',
                'show_reseller_customer' => $_POST['show_reseller_customer'] ?? '0',
                'show_retailer_guest' => $_POST['show_retailer_guest'] ?? '0'
            ];
            foreach ($settings as $key => $val) {
                updateSetting($key, $val);
            }
            setFlash('success', 'Pricing visibility updated.');
        }
    }
}

// ============================================
// FETCH DATA
// ============================================
$moqRules = [];
$stmt = $db->query("SELECT * FROM moq_rules");
while($row = $stmt->fetch()) {
    $moqRules[$row['user_type']] = $row;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$search = sanitize($_GET['search'] ?? '');
$where = "1=1";
$params = [];
if($search) {
    $where .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$customers = paginate("SELECT id, name, email, user_type FROM customers WHERE $where ORDER BY created_at DESC", $params, $page, 15);

// Helper to get permission state
function getRolePerm($role, $perm) {
    $json = getSetting("role_permissions_{$role}");
    if (!$json) return false;
    $perms = json_decode($json, true);
    return isset($perms[$perm]) && $perms[$perm] == '1';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Roles & Pricing - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="admin-layout">
    <div class="page-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-user-shield gold" style="margin-right: 8px;"></i>Role Management</h1>
                <p class="subtitle">Role Management (4 roles total)</p>
            </div>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <i class="fas fa-info-circle"></i> <?php echo clean($flash['message']); ?>
                <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Role Permissions Grid -->
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="update_permissions">
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                <?php 
                $roles_meta = [
                    'customer' => ['title' => 'Standard Customer', 'icon' => 'fa-user'],
                    'reseller' => ['title' => 'Reseller', 'icon' => 'fa-share-nodes'],
                    'retailer' => ['title' => 'Retailer', 'icon' => 'fa-store'],
                    'wholesale' => ['title' => 'Wholesaler', 'icon' => 'fa-boxes-packing']
                ];
                foreach ($roles_meta as $key => $meta): 
                ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas <?php echo $meta['icon']; ?> gold"></i> <?php echo $meta['title']; ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="permission-list">
                            <label class="checkbox-container">Regular Price Visibility
                                <input type="checkbox" name="perm_<?php echo $key; ?>_view_regular_price" value="1" <?php echo getRolePerm($key, 'view_regular_price') ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                            </label>
                            <label class="checkbox-container">Reseller Price Visibility
                                <input type="checkbox" name="perm_<?php echo $key; ?>_view_reseller_price" value="1" <?php echo getRolePerm($key, 'view_reseller_price') ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                            </label>
                            <label class="checkbox-container">Wholesale Price Visibility
                                <input type="checkbox" name="perm_<?php echo $key; ?>_view_wholesale_price" value="1" <?php echo getRolePerm($key, 'view_wholesale_price') ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                            </label>
                            <label class="checkbox-container">Margin Calculator
                                <input type="checkbox" name="perm_<?php echo $key; ?>_show_margin_calculator" value="1" <?php echo getRolePerm($key, 'show_margin_calculator') ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                            </label>
                            <label class="checkbox-container">URL Sharing
                                <input type="checkbox" name="perm_<?php echo $key; ?>_enable_url_sharing" value="1" <?php echo getRolePerm($key, 'enable_url_sharing') ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                            </label>
                            <label class="checkbox-container">Bulk Order Only (MOQ)
                                <input type="checkbox" name="perm_<?php echo $key; ?>_bulk_order_only" value="1" <?php echo getRolePerm($key, 'bulk_order_only') ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                            </label>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-bottom: 24px;">
                <button type="submit" class="gold-btn"><i class="fas fa-save"></i> Save All Permissions</button>
            </div>
        </form>

        <div class="stats-grid" style="grid-template-columns: 1fr 1fr;">
            <!-- MOQ Configurator -->
            <div class="card">
                <div class="card-header"><h3>MOQ & Margin Rules</h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="update_moq">
                        <table class="data-table">
                            <thead>
                                <tr><th>Role</th><th>Min Qty</th><th>Min Amount (₹)</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Wholesale</td>
                                    <td><input type="number" name="wholesale_qty" value="<?php echo $moqRules['wholesale']['min_qty'] ?? 1; ?>" class="form-control"></td>
                                    <td><input type="number" name="wholesale_amount" value="<?php echo $moqRules['wholesale']['min_amount'] ?? 0; ?>" class="form-control"></td>
                                </tr>
                                <tr>
                                    <td>Retailer</td>
                                    <td><input type="number" name="retailer_qty" value="<?php echo $moqRules['retailer']['min_qty'] ?? 1; ?>" class="form-control"></td>
                                    <td><input type="number" name="retailer_amount" value="<?php echo $moqRules['retailer']['min_amount'] ?? 0; ?>" class="form-control"></td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="form-group" style="margin-top: 15px;">
                            <label>Global Default Reseller Margin (%)</label>
                            <input type="number" name="reseller_margin" value="<?php echo getSetting('reseller_default_margin', 10); ?>" class="form-control">
                        </div>
                        <button type="submit" class="gold-btn" style="margin-top:10px;"><i class="fas fa-refresh"></i> Update Rules</button>
                    </form>
                </div>
            </div>

            <!-- Pricing Visibility -->
            <div class="card">
                <div class="card-header"><h3>Pricing Visibility</h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="update_visibility">
                        <div class="form-group">
                            <label class="checkbox-container">Show Wholesale Price to Guest
                                <input type="checkbox" name="show_wholesale_guest" value="1" <?php echo getSetting('show_wholesale_guest') == '1' ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-container">Show Reseller Price to Regular Customer
                                <input type="checkbox" name="show_reseller_customer" value="1" <?php echo getSetting('show_reseller_customer') == '1' ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-container">Show Retailer Price to Guest
                                <input type="checkbox" name="show_retailer_guest" value="1" <?php echo getSetting('show_retailer_guest') == '1' ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <button type="submit" class="gold-btn"><i class="fas fa-check"></i> Save Visibility</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Customer Role List -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h3>Customer List (Role Assignment)</h3>
                <form method="GET" class="search-box">
                    <div style="position:relative">
                        <i class="fas fa-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                        <input type="text" name="search" placeholder="Search customer..." value="<?php echo clean($search); ?>" style="padding-left:35px;">
                    </div>
                </form>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Current Role</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers['data'])): ?>
                            <tr><td colspan="4" style="text-align:center;">No customers found.</td></tr>
                        <?php else: ?>
                            <?php foreach($customers['data'] as $c): ?>
                            <tr>
                                <td><strong><?php echo clean($c['name']); ?></strong></td>
                                <td><?php echo clean($c['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo match($c['user_type']){'wholesale'=>'warning','retailer'=>'info','reseller'=>'purple',default=>'primary'}; ?>">
                                        <?php echo ucfirst($c['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <select onchange="updateRole(<?php echo $c['id']; ?>, this.value)" class="form-control" style="width:auto; display:inline-block;">
                                        <option value="customer" <?php echo $c['user_type'] == 'customer' ? 'selected' : ''; ?>>Customer</option>
                                        <option value="reseller" <?php echo $c['user_type'] == 'reseller' ? 'selected' : ''; ?>>Reseller</option>
                                        <option value="retailer" <?php echo $c['user_type'] == 'retailer' ? 'selected' : ''; ?>>Retailer</option>
                                        <option value="wholesale" <?php echo $c['user_type'] == 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="text-muted" style="font-size: 12px;">Showing page <?php echo $page; ?> of <?php echo $customers['total_pages']; ?></div>
                    <div class="pagination" style="margin: 0;">
                        <?php if($customers['has_prev']): ?>
                            <a href="?page=1&search=<?php echo $search; ?>" class="page-btn"><i class="fas fa-angle-double-left"></i></a>
                            <a href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>" class="page-btn"><i class="fas fa-angle-left"></i></a>
                        <?php endif; ?>
                        
                        <span class="page-btn active"><?php echo $page; ?></span>
                        
                        <?php if($customers['has_next']): ?>
                            <a href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>" class="page-btn"><i class="fas fa-angle-right"></i></a>
                            <a href="?page=<?php echo $customers['total_pages']; ?>&search=<?php echo $search; ?>" class="page-btn"><i class="fas fa-angle-double-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateRole(id, role) {
    if(!confirm('Are you sure you want to change this user\'s role?')) return;
    
    const formData = new FormData();
    formData.append('customer_id', id);
    formData.append('role', role);
    formData.append('csrf_token', '<?php echo $csrf; ?>');

    fetch('api/customers/update-role.php', {
        method: 'POST',
        body: formData
    }).then(res => res.json()).then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    }).catch(err => {
        alert('Request failed. Please try again.');
    });
}
</script>

<style>
/* Checkbox container styling */
.checkbox-container {
    display: block;
    position: relative;
    padding-left: 30px;
    margin-bottom: 12px;
    cursor: pointer;
    font-size: 13px;
    user-select: none;
    color: var(--text-secondary);
}
.checkbox-container input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}
.checkmark {
    position: absolute;
    top: 0;
    left: 0;
    height: 18px;
    width: 18px;
    background-color: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: 4px;
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
    left: 6px;
    top: 2px;
    width: 4px;
    height: 9px;
    border: solid #000;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
.permission-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.badge-purple {
    background: rgba(155, 89, 182, 0.15);
    color: #9b59b6;
}
</style>
</body>
</html>