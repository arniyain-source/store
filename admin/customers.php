<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Customers Management Page - DesiVastra Admin
 */


// ============================================
// HANDLE AJAX REQUESTS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
        exit;
    }

    if ($_POST['action'] === 'update_status') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $newStatus  = sanitize($_POST['status'] ?? '');

        if (!$customerId || !in_array($newStatus, ['active', 'inactive'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            exit;
        }

        try {
            $db = getDB();

            // Fetch current customer
            $stmt = $db->prepare("SELECT id, name, is_active FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch();

            if (!$customer) {
                echo json_encode(['success' => false, 'message' => 'Customer not found.']);
                exit;
            }

            $isActive = $newStatus === 'active' ? 1 : 0;
            $stmt = $db->prepare("UPDATE customers SET is_active = ? WHERE id = ?");
            $stmt->execute([$isActive, $customerId]);

            logActivity('update_status', 'customer', $customerId, [
                'customer_name' => $customer['name'],
                'old_status'    => $customer['is_active'] ? 'active' : 'inactive',
                'new_status'    => $newStatus
            ]);

            $badgeClass = $newStatus === 'active' ? 'badge-success' : 'badge-danger';
            $badgeHtml  = '<span class="badge ' . $badgeClass . '"><span class="badge-dot" style="background:var(--' . ($newStatus === 'active' ? 'success' : 'danger') . ')"></span> ' . ucfirst($newStatus) . '</span>';

            echo json_encode([
                'success' => true,
                'message' => 'Customer "' . $customer['name'] . '" status updated to ' . ucfirst($newStatus),
                'badge'   => $badgeHtml,
                'status'  => $newStatus
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($_POST['action'] === 'get_customer_detail') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        if (!$customerId) {
            echo json_encode(['success' => false, 'message' => 'Invalid customer ID.']);
            exit;
        }

        try {
            $db = getDB();

            // Customer profile
            $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch();

            if (!$customer) {
                echo json_encode(['success' => false, 'message' => 'Customer not found.']);
                exit;
            }

            // Addresses
            $stmt = $db->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC");
            $stmt->execute([$customerId]);
            $addresses = $stmt->fetchAll();

            // Recent orders
            $stmt = $db->prepare("
                SELECT o.id, o.order_number, o.total, o.status, o.payment_status, o.created_at,
                       COALESCE(oi.item_count, 0) as items_count
                FROM orders o
                LEFT JOIN (
                    SELECT order_id, COUNT(*) as item_count FROM order_items GROUP BY order_id
                ) oi ON oi.order_id = o.id
                WHERE o.customer_id = ?
                ORDER BY o.created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$customerId]);
            $recentOrders = $stmt->fetchAll();

            // Total spent
            $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total_spent FROM orders WHERE customer_id = ? AND payment_status = 'paid'");
            $stmt->execute([$customerId]);
            $totalSpent = (float)$stmt->fetch()['total_spent'];

            // Total orders
            $stmt = $db->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE customer_id = ?");
            $stmt->execute([$customerId]);
            $totalOrders = (int)$stmt->fetch()['total_orders'];

            // Build addresses HTML
            $addressesHtml = '';
            if (empty($addresses)) {
                $addressesHtml = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px"><i class="fas fa-map-marker-alt" style="font-size:24px;margin-bottom:8px;display:block"></i>No addresses on file</div>';
            } else {
                foreach ($addresses as $addr) {
                    $defaultBadge = !empty($addr['is_default']) ? '<span class="badge badge-primary" style="margin-left:8px">Default</span>' : '';
                    $addressesHtml .= '<div class="address-block" style="margin-bottom:10px">';
                    $addressesHtml .= '<div class="name" style="margin-bottom:4px">' . clean($addr['name'] ?? $customer['name']) . $defaultBadge . '</div>';
                    $addrLine = clean($addr['address_line1'] ?? '');
                    if (!empty($addr['address_line2'])) $addrLine .= ', ' . clean($addr['address_line2']);
                    if (!empty($addr['city'])) $addrLine .= '<br>' . clean($addr['city']);
                    if (!empty($addr['state'])) $addrLine .= ', ' . clean($addr['state']);
                    if (!empty($addr['pincode'])) $addrLine .= ' - ' . clean($addr['pincode']);
                    if (!empty($addr['phone'])) $addrLine .= '<br><i class="fas fa-phone" style="font-size:10px;margin-right:4px"></i>' . clean($addr['phone']);
                    $addressesHtml .= $addrLine;
                    $addressesHtml .= '</div>';
                }
            }

            // Build recent orders HTML
            $ordersHtml = '';
            if (empty($recentOrders)) {
                $ordersHtml = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px"><i class="fas fa-shopping-bag" style="font-size:24px;margin-bottom:8px;display:block"></i>No orders yet</div>';
            } else {
                $ordersHtml .= '<table class="detail-items-table"><thead><tr>';
                $ordersHtml .= '<th>Order #</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th>';
                $ordersHtml .= '</tr></thead><tbody>';
                foreach ($recentOrders as $ro) {
                    $ordersHtml .= '<tr>';
                    $ordersHtml .= '<td><span class="order-number">' . clean($ro['order_number']) . '</span></td>';
                    $ordersHtml .= '<td>' . (int)$ro['items_count'] . '</td>';
                    $ordersHtml .= '<td style="font-weight:600">' . formatIndianPrice($ro['total']) . '</td>';
                    $ordersHtml .= '<td><span class="badge ' . getStatusBadge($ro['status']) . '">' . ucfirst($ro['status']) . '</span></td>';
                    $ordersHtml .= '<td style="font-size:12px;color:var(--text-muted)">' . date('M j, Y', strtotime($ro['created_at'])) . '</td>';
                    $ordersHtml .= '</tr>';
                }
                $ordersHtml .= '</tbody></table>';
            }

            // Avatar
            $avatarHtml = '';
            if (!empty($customer['avatar']) && file_exists(dirname(__DIR__) . '/' . $customer['avatar'])) {
                $avatarHtml = '<img src="' . SITE_URL . '/' . $customer['avatar'] . '" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--gold-primary)">';
            } else {
                $initials = strtoupper(substr($customer['name'], 0, 1));
                $avatarHtml = '<div style="width:80px;height:80px;border-radius:50%;background:var(--gold-gradient);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:#0a0a0f;border:3px solid var(--gold-primary)">' . $initials . '</div>';
            }

            // User type badge
            $typeBadges = [
                'wholesale' => 'badge-warning',
                'retailer'  => 'badge-info',
                'reseller'  => 'badge-purple',
                'customer'  => 'badge-primary'
            ];
            $typeBadge = $typeBadges[$customer['user_type']] ?? 'badge-secondary';

            // Status badge
            $statusBadge = $customer['is_active']
                ? '<span class="badge badge-success"><span class="badge-dot" style="background:var(--success)"></span> Active</span>'
                : '<span class="badge badge-danger"><span class="badge-dot" style="background:var(--danger)"></span> Inactive</span>';

            // Verified badge
            $verifiedBadge = !empty($customer['is_verified'])
                ? '<span class="badge badge-success"><i class="fas fa-check-circle" style="font-size:10px"></i> Verified</span>'
                : '<span class="badge badge-warning"><i class="fas fa-clock" style="font-size:10px"></i> Unverified</span>';

            // Build full modal body HTML
            $html = '';

            // Profile header
            $html .= '<div style="display:flex;gap:20px;align-items:center;margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid var(--border-color)">';
            $html .= $avatarHtml;
            $html .= '<div style="flex:1">';
            $html .= '<h3 style="font-size:18px;font-weight:700;margin-bottom:4px">' . clean($customer['name']) . '</h3>';
            if (!empty($customer['business_name'])) {
                $html .= '<div style="font-size:13px;color:var(--text-secondary);margin-bottom:6px"><i class="fas fa-building" style="margin-right:6px;color:var(--gold-primary);font-size:11px"></i>' . clean($customer['business_name']) . '</div>';
            }
            $html .= '<div style="display:flex;gap:8px;flex-wrap:wrap">';
            $html .= '<span class="badge ' . $typeBadge . '">' . ucfirst($customer['user_type'] ?? 'customer') . '</span>';
            $html .= $statusBadge . ' ' . $verifiedBadge;
            $html .= '</div></div></div>';

            // Stats row
            $html .= '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px">';
            $html .= '<div style="background:var(--bg-input);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:14px;text-align:center">';
            $html .= '<div style="font-size:20px;font-weight:800;color:var(--gold-primary)">' . $totalOrders . '</div>';
            $html .= '<div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-top:2px">Total Orders</div></div>';
            $html .= '<div style="background:var(--bg-input);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:14px;text-align:center">';
            $html .= '<div style="font-size:20px;font-weight:800;color:var(--success)">' . formatIndianPrice($totalSpent) . '</div>';
            $html .= '<div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-top:2px">Total Spent</div></div>';
            $html .= '<div style="background:var(--bg-input);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:14px;text-align:center">';
            $html .= '<div style="font-size:20px;font-weight:800;color:var(--info)">' . ($totalOrders > 0 ? formatIndianPrice(round($totalSpent / max($totalOrders, 1))) : '₹0') . '</div>';
            $html .= '<div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-top:2px">Avg. Order</div></div>';
            $html .= '</div>';

            // Profile details
            $html .= '<div class="detail-section-title"><i class="fas fa-user"></i> Profile Information</div>';
            $html .= '<div class="detail-grid" style="margin-bottom:20px">';
            $html .= '<div class="detail-item"><div class="detail-label">Email</div><div class="detail-value"><i class="fas fa-envelope" style="font-size:11px;margin-right:4px;color:var(--gold-primary)"></i>' . clean($customer['email']) . '</div></div>';
            $html .= '<div class="detail-item"><div class="detail-label">Phone</div><div class="detail-value"><i class="fas fa-phone" style="font-size:11px;margin-right:4px;color:var(--gold-primary)"></i>' . clean($customer['phone'] ?? 'N/A') . '</div></div>';
            $html .= '<div class="detail-item"><div class="detail-label">City</div><div class="detail-value"><i class="fas fa-city" style="font-size:11px;margin-right:4px;color:var(--gold-primary)"></i>' . clean($customer['city'] ?? 'N/A') . '</div></div>';
            $html .= '<div class="detail-item"><div class="detail-label">State</div><div class="detail-value"><i class="fas fa-map" style="font-size:11px;margin-right:4px;color:var(--gold-primary)"></i>' . clean($customer['state'] ?? 'N/A') . '</div></div>';
            $html .= '<div class="detail-item"><div class="detail-label">Last Login</div><div class="detail-value"><i class="fas fa-clock" style="font-size:11px;margin-right:4px;color:var(--gold-primary)"></i>' . ($customer['last_login'] ? date('M j, Y g:i A', strtotime($customer['last_login'])) : 'Never') . '</div></div>';
            $html .= '<div class="detail-item"><div class="detail-label">Joined</div><div class="detail-value"><i class="fas fa-calendar" style="font-size:11px;margin-right:4px;color:var(--gold-primary)"></i>' . date('M j, Y', strtotime($customer['created_at'])) . '</div></div>';
            $html .= '</div>';

            // Addresses
            $html .= '<div class="detail-divider"></div>';
            $html .= '<div class="detail-section-title"><i class="fas fa-map-marker-alt"></i> Addresses</div>';
            $html .= $addressesHtml;

            // Recent orders
            $html .= '<div class="detail-divider"></div>';
            $html .= '<div class="detail-section-title"><i class="fas fa-shopping-bag"></i> Recent Orders</div>';
            $html .= $ordersHtml;

            echo json_encode(['success' => true, 'html' => $html]);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ============================================
// HANDLE CSV EXPORT
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $csrfCheck = $_GET['token'] ?? '';
    if (!verifyCSRF($csrfCheck)) {
        setFlash('error', 'Invalid export token.');
        redirect('customers.php');
    }

    try {
        $db = getDB();

        $searchQuery   = sanitize($_GET['search'] ?? '');
        $userTypeFilter = sanitize($_GET['user_type'] ?? '');
        $statusFilter   = sanitize($_GET['status'] ?? '');

        $whereClauses = ["1=1"];
        $params = [];

        if ($searchQuery) {
            $whereClauses[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
            $sp = "%{$searchQuery}%";
            $params[] = $sp; $params[] = $sp; $params[] = $sp;
        }
        if ($userTypeFilter && in_array($userTypeFilter, ['wholesale', 'retailer', 'reseller', 'customer'])) {
            $whereClauses[] = "c.user_type = ?";
            $params[] = $userTypeFilter;
        }
        if ($statusFilter === 'active') {
            $whereClauses[] = "c.is_active = 1";
        } elseif ($statusFilter === 'inactive') {
            $whereClauses[] = "c.is_active = 0";
        } elseif ($statusFilter === 'verified') {
            $whereClauses[] = "c.is_verified = 1";
        }

        $whereSQL = implode(' AND ', $whereClauses);

        $stmt = $db->prepare("
            SELECT c.*, 
                   COALESCE(o_cnt.order_count, 0) as orders_count,
                   COALESCE(o_spent.total_spent, 0) as total_spent
            FROM customers c
            LEFT JOIN (SELECT customer_id, COUNT(*) as order_count FROM orders GROUP BY customer_id) o_cnt ON o_cnt.customer_id = c.id
            LEFT JOIN (SELECT customer_id, COALESCE(SUM(total), 0) as total_spent FROM orders WHERE payment_status = 'paid' GROUP BY customer_id) o_spent ON o_spent.customer_id = c.id
            WHERE {$whereSQL}
            ORDER BY c.created_at DESC
        ");
        $stmt->execute($params);
        $customers = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers_export_' . date('Y-m-d_His') . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

        fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'User Type', 'Business Name', 'City', 'State', 'Orders', 'Total Spent', 'Verified', 'Status', 'Last Login', 'Joined']);

        foreach ($customers as $c) {
            fputcsv($output, [
                $c['id'],
                $c['name'],
                $c['email'],
                $c['phone'] ?? '',
                ucfirst($c['user_type'] ?? 'customer'),
                $c['business_name'] ?? '',
                $c['city'] ?? '',
                $c['state'] ?? '',
                $c['orders_count'],
                $c['total_spent'],
                $c['is_verified'] ? 'Yes' : 'No',
                $c['is_active'] ? 'Active' : 'Inactive',
                $c['last_login'] ?? 'Never',
                $c['created_at']
            ]);
        }

        fclose($output);
        logActivity('export', 'customer', null, ['count' => count($customers)]);
        exit;

    } catch (Exception $e) {
        setFlash('error', 'Export failed: ' . $e->getMessage());
        redirect('customers.php');
    }
}

// ============================================
// GET FILTER PARAMETERS
// ============================================
$searchQuery    = sanitize($_GET['search'] ?? '');
$userTypeFilter = sanitize($_GET['user_type'] ?? '');
$statusFilter   = sanitize($_GET['status'] ?? '');
$currentPage    = max(1, (int)($_GET['page'] ?? 1));

// Validate filters
$validUserTypes = ['wholesale', 'retailer', 'reseller', 'customer'];
if ($userTypeFilter && !in_array($userTypeFilter, $validUserTypes)) {
    $userTypeFilter = '';
}

$validStatuses = ['active', 'inactive', 'verified'];
if ($statusFilter && !in_array($statusFilter, $validStatuses)) {
    $statusFilter = '';
}

// ============================================
// BUILD QUERY
// ============================================
$baseQuery = "
    SELECT c.id, c.name, c.email, c.phone, c.user_type, c.city, c.state,
           c.business_name, c.avatar, c.is_verified, c.is_active, c.last_login, c.created_at,
           COALESCE(o_cnt.order_count, 0) as orders_count,
           COALESCE(o_spent.total_spent, 0) as total_spent
    FROM customers c
    LEFT JOIN (
        SELECT customer_id, COUNT(*) as order_count 
        FROM orders 
        GROUP BY customer_id
    ) o_cnt ON o_cnt.customer_id = c.id
    LEFT JOIN (
        SELECT customer_id, COALESCE(SUM(total), 0) as total_spent 
        FROM orders 
        WHERE payment_status = 'paid' 
        GROUP BY customer_id
    ) o_spent ON o_spent.customer_id = c.id
";

$whereClauses = [];
$params = [];

if ($searchQuery) {
    $whereClauses[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($userTypeFilter) {
    $whereClauses[] = "c.user_type = ?";
    $params[] = $userTypeFilter;
}

if ($statusFilter === 'active') {
    $whereClauses[] = "c.is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $whereClauses[] = "c.is_active = 0";
} elseif ($statusFilter === 'verified') {
    $whereClauses[] = "c.is_verified = 1";
}

if (!empty($whereClauses)) {
    $baseQuery .= " WHERE " . implode(" AND ", $whereClauses);
}

$baseQuery .= " ORDER BY c.created_at DESC";

// Get paginated results
$pagination = paginate($baseQuery, $params, $currentPage, ADMIN_PER_PAGE);
$customers = $pagination['data'];

// ============================================
// GET SUMMARY COUNTS
// ============================================
$summaryCounts = ['total' => 0, 'active' => 0, 'inactive' => 0, 'verified' => 0];
try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as cnt, SUM(is_active = 1) as active_cnt, SUM(is_active = 0) as inactive_cnt, SUM(is_verified = 1) as verified_cnt FROM customers");
    $row = $stmt->fetch();
    $summaryCounts['total']    = (int)$row['cnt'];
    $summaryCounts['active']   = (int)$row['active_cnt'];
    $summaryCounts['inactive'] = (int)$row['inactive_cnt'];
    $summaryCounts['verified'] = (int)$row['verified_cnt'];
} catch (Exception $e) {}

// Flash message & CSRF
$flash = getFlash();
$csrf  = generateCSRF();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        /* Customers-specific styles */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .summary-card-item {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
            cursor: default;
        }

        .summary-card-item:hover {
            border-color: var(--gold-dark);
        }

        .summary-card-item .sc-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .summary-card-item .sc-icon.gold   { background: rgba(212,168,83,0.15); color: var(--gold-primary); }
        .summary-card-item .sc-icon.green   { background: var(--success-bg); color: var(--success); }
        .summary-card-item .sc-icon.red     { background: var(--danger-bg); color: var(--danger); }
        .summary-card-item .sc-icon.blue    { background: var(--info-bg); color: var(--info); }

        .summary-card-item .sc-value {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .summary-card-item .sc-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .filter-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-row .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .filter-row .filter-group label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .filter-row .filter-group input,
        .filter-row .filter-group select {
            padding: 8px 12px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 13px;
            font-family: inherit;
            transition: var(--transition);
        }

        .filter-row .filter-group input:focus,
        .filter-row .filter-group select:focus {
            outline: none;
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(212, 168, 83, 0.1);
        }

        .filter-row .filter-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%239a9ab0' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 30px;
            cursor: pointer;
        }

        .filter-row .search-wrap {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .filter-row .search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 13px;
        }

        .filter-row .search-wrap input {
            padding-left: 36px;
            width: 100%;
        }

        /* Customer avatar */
        .customer-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            flex-shrink: 0;
        }

        .customer-avatar-placeholder {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--gold-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            color: #0a0a0f;
            flex-shrink: 0;
        }

        .customer-info-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .customer-info-cell .info {
            min-width: 0;
        }

        .customer-info-cell .info .name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .customer-info-cell .info .email {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .customer-info-cell .info .business {
            font-size: 10px;
            color: var(--gold-primary);
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .phone-cell {
            font-size: 13px;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .location-cell {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .location-cell .city {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 13px;
        }

        .location-cell .state {
            font-size: 11px;
            color: var(--text-muted);
        }

        .orders-count-cell {
            text-align: center;
        }

        .orders-count-cell .count {
            font-weight: 700;
            font-size: 15px;
            color: var(--text-primary);
        }

        .orders-count-cell .spent {
            font-size: 11px;
            color: var(--success);
            font-weight: 600;
            margin-top: 1px;
        }

        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .type-wholesale { background: var(--warning-bg); color: var(--warning); }
        .type-retailer  { background: var(--info-bg); color: var(--info); }
        .type-reseller   { background: var(--purple-bg); color: var(--purple); }
        .type-customer   { background: rgba(212,168,83,0.15); color: var(--gold-primary); }

        .verified-icon {
            font-size: 10px;
            color: var(--success);
            margin-left: 2px;
        }

        .date-cell {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .date-cell .time-ago {
            font-size: 11px;
            color: var(--text-muted);
            display: block;
            margin-top: 1px;
        }

        .action-btns {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .action-btns .btn-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            font-size: 12px;
        }

        .action-btns .btn-icon:hover {
            border-color: var(--gold-dark);
            color: var(--gold-primary);
            background: rgba(212, 168, 83, 0.08);
        }

        .action-btns .btn-icon.view:hover {
            border-color: var(--info);
            color: var(--info);
            background: var(--info-bg);
        }

        .action-btns .btn-icon.toggle-active:hover {
            border-color: var(--success);
            color: var(--success);
            background: var(--success-bg);
        }

        .action-btns .btn-icon.toggle-inactive:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: var(--danger-bg);
        }

        /* Status dropdown in table */
        .status-dropdown-wrap {
            position: relative;
            display: inline-block;
        }

        .status-trigger {
            cursor: pointer;
            transition: var(--transition);
        }

        .status-trigger:hover {
            filter: brightness(1.2);
        }

        .status-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            margin-top: 4px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-lg);
            z-index: 100;
            min-width: 140px;
            display: none;
            overflow: hidden;
        }

        .status-dropdown.show {
            display: block;
            animation: fadeIn 0.15s ease;
        }

        .status-dropdown .status-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .status-dropdown .status-option:hover {
            background: rgba(212, 168, 83, 0.06);
            color: var(--text-primary);
        }

        .status-dropdown .status-option.current {
            background: rgba(212, 168, 83, 0.1);
            color: var(--gold-primary);
        }

        .status-dropdown .status-option .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .dot-active   { background: var(--success); }
        .dot-inactive  { background: var(--danger); }

        .no-results-row td {
            text-align: center;
            padding: 48px 16px !important;
        }

        .no-results-row .no-results-icon {
            font-size: 40px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .no-results-row h3 {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .no-results-row p {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* Detail modal styles (shared from orders) */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .detail-item .detail-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .detail-item .detail-value {
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .detail-divider {
            height: 1px;
            background: var(--border-color);
            margin: 16px 0;
        }

        .detail-section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-section-title i {
            color: var(--gold-primary);
            font-size: 14px;
        }

        .address-block {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 14px;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.7;
        }

        .address-block .name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .detail-items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .detail-items-table th {
            padding: 8px 12px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
            text-align: left;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid var(--border-color);
        }

        .detail-items-table td {
            padding: 10px 12px;
            font-size: 13px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .order-number {
            font-weight: 700;
            color: var(--gold-primary);
            font-size: 13px;
        }

        /* Loading spinner for modal */
        .modal-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            gap: 16px;
        }

        .modal-loading .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border-color);
            border-top-color: var(--gold-primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .modal-loading p {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* Toast notification */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 24px;
            z-index: 3000;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .toast {
            padding: 12px 20px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInRight 0.3s ease;
            box-shadow: var(--shadow-lg);
            min-width: 280px;
            max-width: 400px;
        }

        .toast-success { background: #1a3a2a; color: var(--success); border: 1px solid rgba(46,204,113,0.3); }
        .toast-error   { background: #3a1a1a; color: var(--danger); border: 1px solid rgba(231,76,60,0.3); }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0); opacity: 1; }
        }

        @media (max-width: 1024px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }

            .summary-card-item {
                padding: 10px 12px;
            }

            .summary-card-item .sc-value {
                font-size: 16px;
            }

            .summary-card-item .sc-icon {
                width: 34px;
                height: 34px;
                font-size: 14px;
            }

            .filter-row {
                flex-direction: column;
            }

            .filter-row .search-wrap {
                min-width: 100%;
            }

            .filter-row .filter-group {
                width: 100%;
            }

            .filter-row .filter-group input,
            .filter-row .filter-group select {
                width: 100%;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .summary-cards {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/layout.php'; ?>

    
        <div class="page-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator">/</span>
                <span>Customers</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-users" style="color:var(--gold-primary);margin-right:8px"></i>Customers</h1>
                    <p class="subtitle">Manage your customer base and accounts</p>
                </div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-secondary btn-sm" onclick="exportCustomers()" data-tooltip="Export to CSV">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>

            <!-- Flash Message -->
            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                    <?php echo clean($flash['message']); ?>
                    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card-item">
                    <div class="sc-icon gold"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="sc-value"><?php echo number_format($summaryCounts['total']); ?></div>
                        <div class="sc-label">Total Customers</div>
                    </div>
                </div>
                <div class="summary-card-item">
                    <div class="sc-icon green"><i class="fas fa-user-check"></i></div>
                    <div>
                        <div class="sc-value"><?php echo number_format($summaryCounts['active']); ?></div>
                        <div class="sc-label">Active</div>
                    </div>
                </div>
                <div class="summary-card-item">
                    <div class="sc-icon red"><i class="fas fa-user-slash"></i></div>
                    <div>
                        <div class="sc-value"><?php echo number_format($summaryCounts['inactive']); ?></div>
                        <div class="sc-label">Inactive</div>
                    </div>
                </div>
                <div class="summary-card-item">
                    <div class="sc-icon blue"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <div class="sc-value"><?php echo number_format($summaryCounts['verified']); ?></div>
                        <div class="sc-label">Verified</div>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-card">
                <form method="GET" action="customers.php" id="filterForm">
                    <div class="filter-row">
                        <div class="filter-group search-wrap">
                            <label>Search</label>
                            <div style="position:relative">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Name, email, or phone..." value="<?php echo clean($searchQuery); ?>">
                            </div>
                        </div>

                        <div class="filter-group">
                            <label>User Type</label>
                            <select name="user_type">
                                <option value="">All Types</option>
                                <option value="wholesale" <?php echo $userTypeFilter === 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                                <option value="retailer" <?php echo $userTypeFilter === 'retailer' ? 'selected' : ''; ?>>Retailer</option>
                                <option value="reseller" <?php echo $userTypeFilter === 'reseller' ? 'selected' : ''; ?>>Reseller</option>
                                <option value="customer" <?php echo $userTypeFilter === 'customer' ? 'selected' : ''; ?>>Customer</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                            </select>
                        </div>

                        <div class="filter-group" style="flex-direction:row;align-items:flex-end;gap:6px">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="customers.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Active Filters Summary -->
            <?php if ($searchQuery || $userTypeFilter || $statusFilter): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
                    <span style="font-size:12px;color:var(--text-muted)"><i class="fas fa-filter" style="margin-right:4px"></i>Filters:</span>
                    <?php if ($searchQuery): ?>
                        <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;font-size:12px;color:var(--text-secondary)">
                            <i class="fas fa-search" style="font-size:10px;color:var(--info)"></i>
                            "<?php echo clean($searchQuery); ?>"
                            <a href="customers.php?<?php echo http_build_query(array_filter(['user_type' => $userTypeFilter, 'status' => $statusFilter])); ?>" style="color:var(--text-muted);font-size:14px">&times;</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($userTypeFilter): ?>
                        <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;font-size:12px;color:var(--text-secondary)">
                            <i class="fas fa-tag" style="font-size:10px;color:var(--purple)"></i>
                            <?php echo ucfirst($userTypeFilter); ?>
                            <a href="customers.php?<?php echo http_build_query(array_filter(['search' => $searchQuery, 'status' => $statusFilter])); ?>" style="color:var(--text-muted);font-size:14px">&times;</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($statusFilter): ?>
                        <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;font-size:12px;color:var(--text-secondary)">
                            <i class="fas fa-circle" style="font-size:8px;color:<?php echo $statusFilter === 'active' ? 'var(--success)' : ($statusFilter === 'inactive' ? 'var(--danger)' : 'var(--info)'); ?>"></i>
                            <?php echo ucfirst($statusFilter); ?>
                            <a href="customers.php?<?php echo http_build_query(array_filter(['search' => $searchQuery, 'user_type' => $userTypeFilter])); ?>" style="color:var(--text-muted);font-size:14px">&times;</a>
                        </span>
                    <?php endif; ?>
                    <span style="font-size:12px;color:var(--text-muted)">
                        — <?php echo $pagination['total']; ?> result<?php echo $pagination['total'] !== 1 ? 's' : ''; ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Customers Table -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-list" style="color:var(--gold-primary);margin-right:8px"></i>
                        <?php echo $userTypeFilter ? ucfirst($userTypeFilter) . ' Customers' : 'All Customers'; ?>
                        <span style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:8px">(<?php echo $pagination['total']; ?>)</span>
                    </h3>
                </div>

                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Orders</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr class="no-results-row">
                                    <td colspan="8">
                                        <div class="no-results-icon"><i class="fas fa-users"></i></div>
                                        <h3>No customers found</h3>
                                        <p>
                                            <?php if ($searchQuery || $userTypeFilter || $statusFilter): ?>
                                                Try adjusting your filters or search terms.
                                            <?php else: ?>
                                                Customers will appear here once they register on your store.
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer): ?>
                                    <?php
                                        $initials = strtoupper(substr($customer['name'], 0, 1));
                                        $avatarPath = !empty($customer['avatar']) ? '../' . $customer['avatar'] : '';
                                        $hasAvatar = $avatarPath && file_exists(dirname(__DIR__) . '/' . $customer['avatar']);
                                    ?>
                                    <tr id="customer-row-<?php echo $customer['id']; ?>">
                                        <!-- Avatar + Name/Email -->
                                        <td>
                                            <div class="customer-info-cell">
                                                <?php if ($hasAvatar): ?>
                                                    <img src="<?php echo clean($avatarPath); ?>" alt="<?php echo clean($customer['name']); ?>" class="customer-avatar" loading="lazy">
                                                <?php else: ?>
                                                    <div class="customer-avatar-placeholder"><?php echo $initials; ?></div>
                                                <?php endif; ?>
                                                <div class="info">
                                                    <div class="name">
                                                        <?php echo clean($customer['name']); ?>
                                                        <?php if (!empty($customer['is_verified'])): ?>
                                                            <i class="fas fa-check-circle verified-icon" title="Verified"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="email"><?php echo clean($customer['email']); ?></div>
                                                    <?php if (!empty($customer['business_name'])): ?>
                                                        <div class="business"><i class="fas fa-building" style="font-size:9px;margin-right:3px"></i><?php echo clean($customer['business_name']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Phone -->
                                        <td>
                                            <span class="phone-cell"><?php echo clean($customer['phone'] ?? '—'); ?></span>
                                        </td>

                                        <!-- User Type Badge -->
                                        <td>
                                            <span class="type-badge type-<?php echo $customer['user_type'] ?? 'customer'; ?>">
                                                <?php
                                                    $typeIcons = [
                                                        'wholesale' => 'fa-warehouse',
                                                        'retailer'  => 'fa-store',
                                                        'reseller'   => 'fa-exchange-alt',
                                                        'customer'   => 'fa-user'
                                                    ];
                                                    $icon = $typeIcons[$customer['user_type']] ?? 'fa-user';
                                                ?>
                                                <i class="fas <?php echo $icon; ?>" style="font-size:9px"></i>
                                                <?php echo ucfirst($customer['user_type'] ?? 'customer'); ?>
                                            </span>
                                        </td>

                                        <!-- City/State -->
                                        <td>
                                            <div class="location-cell">
                                                <?php if (!empty($customer['city'])): ?>
                                                    <div class="city"><?php echo clean($customer['city']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($customer['state'])): ?>
                                                    <div class="state"><?php echo clean($customer['state']); ?></div>
                                                <?php endif; ?>
                                                <?php if (empty($customer['city']) && empty($customer['state'])): ?>
                                                    <span style="color:var(--text-muted)">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Orders Count + Total Spent -->
                                        <td>
                                            <div class="orders-count-cell">
                                                <div class="count"><?php echo (int)$customer['orders_count']; ?></div>
                                                <?php if ($customer['total_spent'] > 0): ?>
                                                    <div class="spent"><?php echo formatIndianPrice($customer['total_spent']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Status -->
                                        <td>
                                            <div class="status-dropdown-wrap">
                                                <?php if ($customer['is_active']): ?>
                                                    <span class="badge badge-success status-trigger"
                                                          onclick="toggleStatusDropdown(event, <?php echo $customer['id']; ?>)"
                                                          id="status-badge-<?php echo $customer['id']; ?>">
                                                        <span class="badge-dot" style="background:var(--success)"></span> Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger status-trigger"
                                                          onclick="toggleStatusDropdown(event, <?php echo $customer['id']; ?>)"
                                                          id="status-badge-<?php echo $customer['id']; ?>">
                                                        <span class="badge-dot" style="background:var(--danger)"></span> Inactive
                                                    </span>
                                                <?php endif; ?>
                                                <div class="status-dropdown" id="status-dropdown-<?php echo $customer['id']; ?>">
                                                    <div class="status-option <?php echo $customer['is_active'] ? 'current' : ''; ?>"
                                                         onclick="updateCustomerStatus(<?php echo $customer['id']; ?>, 'active', '<?php echo clean(addslashes($customer['name'])); ?>')">
                                                        <span class="dot dot-active"></span>
                                                        Active
                                                        <?php if ($customer['is_active']): ?>
                                                            <i class="fas fa-check" style="margin-left:auto;font-size:10px;color:var(--gold-primary)"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="status-option <?php echo !$customer['is_active'] ? 'current' : ''; ?>"
                                                         onclick="updateCustomerStatus(<?php echo $customer['id']; ?>, 'inactive', '<?php echo clean(addslashes($customer['name'])); ?>')">
                                                        <span class="dot dot-inactive"></span>
                                                        Inactive
                                                        <?php if (!$customer['is_active']): ?>
                                                            <i class="fas fa-check" style="margin-left:auto;font-size:10px;color:var(--gold-primary)"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Joined Date -->
                                        <td>
                                            <div class="date-cell">
                                                <?php echo date('M j, Y', strtotime($customer['created_at'])); ?>
                                                <span class="time-ago"><?php echo timeAgo($customer['created_at']); ?></span>
                                            </div>
                                        </td>

                                        <!-- Actions -->
                                        <td>
                                            <div class="action-btns">
                                                <button class="btn-icon view" onclick="viewCustomer(<?php echo $customer['id']; ?>)" data-tooltip="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($customer['is_active']): ?>
                                                    <button class="btn-icon toggle-inactive" onclick="updateCustomerStatus(<?php echo $customer['id']; ?>, 'inactive', '<?php echo clean(addslashes($customer['name'])); ?>')" data-tooltip="Deactivate">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn-icon toggle-active" onclick="updateCustomerStatus(<?php echo $customer['id']; ?>, 'active', '<?php echo clean(addslashes($customer['name'])); ?>')" data-tooltip="Activate">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="card-footer">
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                            <div style="font-size:12px;color:var(--text-muted)">
                                Showing <?php echo (($pagination['page'] - 1) * $pagination['per_page']) + 1; ?>–<?php echo min($pagination['page'] * $pagination['per_page'], $pagination['total']); ?>
                                of <?php echo $pagination['total']; ?> customers
                            </div>
                            <div class="pagination" style="margin-top:0">
                                <?php if ($pagination['has_prev']): ?>
                                    <a href="customers.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="customers.php<?php echo buildQueryParams(['page' => $pagination['page'] - 1]); ?>" class="page-btn">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="page-btn" disabled><i class="fas fa-angle-double-left"></i></button>
                                    <button class="page-btn" disabled><i class="fas fa-angle-left"></i></button>
                                <?php endif; ?>

                                <?php
                                    $startPage = max(1, $pagination['page'] - 2);
                                    $endPage = min($pagination['total_pages'], $pagination['page'] + 2);

                                    if ($startPage > 1): ?>
                                        <a href="customers.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn">1</a>
                                        <?php if ($startPage > 2): ?>
                                            <span class="page-btn" style="border:none;background:none;color:var(--text-muted)">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                        <a href="customers.php<?php echo buildQueryParams(['page' => $p]); ?>"
                                           class="page-btn <?php echo $p === $pagination['page'] ? 'active' : ''; ?>">
                                            <?php echo $p; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($endPage < $pagination['total_pages']): ?>
                                        <?php if ($endPage < $pagination['total_pages'] - 1): ?>
                                            <span class="page-btn" style="border:none;background:none;color:var(--text-muted)">...</span>
                                        <?php endif; ?>
                                        <a href="customers.php<?php echo buildQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn">
                                            <?php echo $pagination['total_pages']; ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($pagination['has_next']): ?>
                                        <a href="customers.php<?php echo buildQueryParams(['page' => $pagination['page'] + 1]); ?>" class="page-btn">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                        <a href="customers.php<?php echo buildQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn">
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
    </main>
</div>

<!-- Customer Detail Modal -->
<div class="modal-overlay" id="customerDetailModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-user" style="color:var(--gold-primary);margin-right:8px"></i>Customer Details</h3>
            <button class="modal-close" onclick="closeDetailModal()">&times;</button>
        </div>
        <div class="modal-body" id="customerDetailBody">
            <div class="modal-loading">
                <div class="spinner"></div>
                <p>Loading customer details...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDetailModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ============================================
// CSRF TOKEN
// ============================================
const CSRF_TOKEN = '<?php echo $csrf; ?>';

// ============================================
// TOAST NOTIFICATIONS
// ============================================
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;

    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    toast.innerHTML = '<i class="fas fa-' + icon + '"></i> ' + message;

    container.appendChild(toast);

    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(function() { toast.remove(); }, 300);
    }, 4000);
}

// ============================================
// STATUS DROPDOWN
// ============================================
function toggleStatusDropdown(event, customerId) {
    event.stopPropagation();

    // Close all other dropdowns
    document.querySelectorAll('.status-dropdown.show').forEach(function(dd) {
        if (dd.id !== 'status-dropdown-' + customerId) {
            dd.classList.remove('show');
        }
    });

    var dropdown = document.getElementById('status-dropdown-' + customerId);
    dropdown.classList.toggle('show');
}

// Close all dropdowns on outside click
document.addEventListener('click', function() {
    document.querySelectorAll('.status-dropdown.show').forEach(function(dd) {
        dd.classList.remove('show');
    });
});

// ============================================
// UPDATE CUSTOMER STATUS
// ============================================
function updateCustomerStatus(customerId, newStatus, customerName) {
    // Close dropdown
    document.querySelectorAll('.status-dropdown.show').forEach(function(dd) {
        dd.classList.remove('show');
    });

    var formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('customer_id', customerId);
    formData.append('status', newStatus);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('customers.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showToast(data.message, 'success');

            // Update badge in table
            var badgeEl = document.getElementById('status-badge-' + customerId);
            if (badgeEl && data.badge) {
                badgeEl.outerHTML = '<span class="badge ' + (newStatus === 'active' ? 'badge-success' : 'badge-danger') + ' status-trigger" onclick="toggleStatusDropdown(event, ' + customerId + ')" id="status-badge-' + customerId + '">' + data.badge.replace(/<span class="badge[^"]*">/, '').replace(/<\/span>/, '') + '</span>';
            }

            // Update action buttons
            var row = document.getElementById('customer-row-' + customerId);
            if (row) {
                var actionCell = row.querySelector('.action-btns');
                if (actionCell) {
                    var toggleBtn = actionCell.querySelector('.toggle-active, .toggle-inactive');
                    if (toggleBtn) {
                        if (newStatus === 'active') {
                            toggleBtn.className = 'btn-icon toggle-inactive';
                            toggleBtn.setAttribute('data-tooltip', 'Deactivate');
                            toggleBtn.innerHTML = '<i class="fas fa-ban"></i>';
                            toggleBtn.setAttribute('onclick', "updateCustomerStatus(" + customerId + ", 'inactive', '" + customerName.replace(/'/g, "\\'") + "')");
                        } else {
                            toggleBtn.className = 'btn-icon toggle-active';
                            toggleBtn.setAttribute('data-tooltip', 'Activate');
                            toggleBtn.innerHTML = '<i class="fas fa-check"></i>';
                            toggleBtn.setAttribute('onclick', "updateCustomerStatus(" + customerId + ", 'active', '" + customerName.replace(/'/g, "\\'") + "')");
                        }
                    }
                }
            }
        } else {
            showToast(data.message || 'Failed to update status.', 'error');
        }
    })
    .catch(function(err) {
        showToast('Network error. Please try again.', 'error');
    });
}

// ============================================
// VIEW CUSTOMER DETAIL MODAL
// ============================================
function viewCustomer(customerId) {
    var modal = document.getElementById('customerDetailModal');
    var body = document.getElementById('customerDetailBody');

    // Show modal with loading state
    body.innerHTML = '<div class="modal-loading"><div class="spinner"></div><p>Loading customer details...</p></div>';
    modal.classList.add('show');

    var formData = new FormData();
    formData.append('action', 'get_customer_detail');
    formData.append('customer_id', customerId);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('customers.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            body.innerHTML = data.html;
        } else {
            body.innerHTML = '<div style="text-align:center;padding:40px"><i class="fas fa-exclamation-triangle" style="font-size:32px;color:var(--danger);margin-bottom:12px;display:block"></i><p style="color:var(--danger)">' + (data.message || 'Failed to load customer details.') + '</p></div>';
        }
    })
    .catch(function(err) {
        body.innerHTML = '<div style="text-align:center;padding:40px"><i class="fas fa-exclamation-triangle" style="font-size:32px;color:var(--danger);margin-bottom:12px;display:block"></i><p style="color:var(--danger)">Network error. Please try again.</p></div>';
    });
}

function closeDetailModal() {
    document.getElementById('customerDetailModal').classList.remove('show');
}

// Close modal on overlay click
document.getElementById('customerDetailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDetailModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetailModal();
        document.querySelectorAll('.status-dropdown.show').forEach(function(dd) {
            dd.classList.remove('show');
        });
    }
});

// ============================================
// EXPORT CUSTOMERS CSV
// ============================================
function exportCustomers() {
    var params = new URLSearchParams();
    params.set('action', 'export');
    params.set('token', CSRF_TOKEN);

    <?php if ($searchQuery): ?>
        params.set('search', '<?php echo clean(addslashes($searchQuery)); ?>');
    <?php endif; ?>
    <?php if ($userTypeFilter): ?>
        params.set('user_type', '<?php echo clean($userTypeFilter); ?>');
    <?php endif; ?>
    <?php if ($statusFilter): ?>
        params.set('status', '<?php echo clean($statusFilter); ?>');
    <?php endif; ?>

    window.location.href = 'customers.php?' + params.toString();
}
</script>

</body>
</html>