<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Customer Support & Ticketing - DesiVastra Admin
 */

$db = getDB();

// Ensure support_tickets table exists (SQLite auto-create)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS support_tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_no VARCHAR(20) NOT NULL UNIQUE,
        customer_id INTEGER DEFAULT NULL,
        customer_name VARCHAR(100) NOT NULL DEFAULT '',
        mobile VARCHAR(20) DEFAULT NULL,
        email VARCHAR(150) DEFAULT NULL,
        subject VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT DEFAULT NULL,
        priority VARCHAR(20) DEFAULT 'medium',
        status VARCHAR(30) DEFAULT 'open',
        assigned_staff_id INTEGER DEFAULT NULL,
        resolution TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}


// ============================================
// HANDLE ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRF($csrfToken)) {
        setFlash('error', 'Invalid security token.');
    } else {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        
        if ($action === 'update_status') {
            $newStatus = sanitize($_POST['status'] ?? '');
            $stmt = $db->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$newStatus, $ticketId])) {
                logActivity('update_ticket_status', 'support_ticket', $ticketId, ['status' => $newStatus]);
                setFlash('success', 'Ticket status updated to ' . ucfirst($newStatus));
            }
        }
        
        if ($action === 'assign_staff') {
            $staffId = (int)($_POST['staff_id'] ?? 0);
            $stmt = $db->prepare("UPDATE support_tickets SET assigned_staff_id = ?, status = 'in_progress', updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$staffId, $ticketId])) {
                logActivity('assign_ticket', 'support_ticket', $ticketId, ['staff_id' => $staffId]);
                setFlash('success', 'Ticket assigned successfully.');
            }
        }

        if ($action === 'delete_ticket') {
            $stmt = $db->prepare("DELETE FROM support_tickets WHERE id = ?");
            if ($stmt->execute([$ticketId])) {
                logActivity('delete_ticket', 'support_ticket', $ticketId);
                setFlash('success', 'Ticket deleted successfully.');
            }
        }
    }
    redirect('support-tickets.php' . buildQueryParams([]));
}

// ============================================
// FILTERS & PAGINATION
// ============================================
$statusFilter = sanitize($_GET['status'] ?? '');
$priorityFilter = sanitize($_GET['priority'] ?? '');
$searchQuery = sanitize($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ["1=1"];
$params = [];

if ($statusFilter) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}
if ($priorityFilter) {
    $where[] = "priority = ?";
    $params[] = $priorityFilter;
}
if ($searchQuery) {
    $where[] = "(ticket_no LIKE ? OR customer_name LIKE ? OR subject LIKE ?)";
    $s = "%$searchQuery%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}

$whereClause = implode(' AND ', $where);
$query = "SELECT * FROM support_tickets WHERE $whereClause ORDER BY created_at DESC";

$pagination = paginate($query, $params, $page, ADMIN_PER_PAGE);
$tickets = $pagination['data'];

// Get Stats for Header
$totalOpen = $db->query("SELECT COUNT(*) FROM support_tickets WHERE status != 'closed' AND status != 'resolved'")->fetchColumn();

// Get staff for assignment
$staffMembers = $db->query("SELECT id, name FROM admins WHERE status = 1")->fetchAll();

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
    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>Customer Support</span>
            </div>
            <h1>Support Tickets</h1>
            <p class="subtitle">Manage and resolve customer inquiries</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card card" style="margin-bottom: 20px;">
        <div class="card-body">
            <form method="GET" class="filter-bar">
                <div class="search-wrap" style="flex: 1; min-width: 200px; position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" name="search" placeholder="Search ticket #, name..." value="<?php echo clean($searchQuery); ?>" class="form-control" style="padding-left: 35px;">
                </div>
                <select name="status" class="form-control" style="width: auto;">
                    <option value="">All Statuses</option>
                    <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
                <select name="priority" class="form-control" style="width: auto;">
                    <option value="">All Priorities</option>
                    <option value="low" <?php echo $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                    <option value="medium" <?php echo $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="urgent" <?php echo $priorityFilter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                <a href="support-tickets.php" class="btn btn-secondary btn-sm"><i class="fas fa-sync"></i> Reset</a>
            </form>
        </div>
    </div>

    <!-- Tickets Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-headset" style="color: var(--gold-primary); margin-right: 8px;"></i> Support Tickets (<?php echo $totalOpen; ?> open)</h3>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Customer</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr><td colspan="7" style="text-align:center; padding: 40px; color: var(--text-muted);">No tickets found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--gold-primary);"><?php echo clean($t['ticket_no']); ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo clean($t['customer_name']); ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo clean($t['mobile']); ?></div>
                                </td>
                                <td style="max-width: 200px;" class="truncate" title="<?php echo clean($t['subject']); ?>">
                                    <?php echo clean($t['subject']); ?>
                                </td>
                                <td>
                                    <?php
                                    $pClass = match($t['priority']) {
                                        'low' => 'badge-success',
                                        'medium' => 'badge-primary',
                                        'high' => 'badge-warning',
                                        'urgent' => 'badge-danger',
                                        default => 'badge-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $pClass; ?>"><?php echo ucfirst($t['priority']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $sClass = match($t['status']) {
                                        'open' => 'badge-info',
                                        'in_progress' => 'badge-warning',
                                        'resolved' => 'badge-success',
                                        'closed' => 'badge-secondary',
                                        default => 'badge-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $sClass; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $t['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 12px;"><?php echo date('M j, Y', strtotime($t['created_at'])); ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo timeAgo($t['created_at']); ?></div>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 5px; justify-content: flex-end;">
                                        <a href="ticket-detail.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-secondary" title="View Detail"><i class="fas fa-eye"></i></a>
                                        
                                        <div class="dropdown-wrap" style="position:relative;">
                                            <button class="btn btn-sm btn-primary" onclick="this.nextElementSibling.classList.toggle('show')" title="Assign Staff"><i class="fas fa-user-plus"></i></button>
                                            <div class="dropdown-menu" style="right:0; top:100%; min-width:150px;">
                                                <?php foreach ($staffMembers as $staff): ?>
                                                    <form method="POST" style="display:block;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                        <input type="hidden" name="action" value="assign_staff">
                                                        <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                                        <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                                        <button type="submit" class="dropdown-item"><?php echo clean($staff['name']); ?></button>
                                                    </form>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this ticket?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="action" value="delete_ticket">
                                            <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete Ticket"><i class="fas fa-trash-alt"></i></button>
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
                        Showing page <?php echo $page; ?> of <?php echo $pagination['total_pages']; ?> &mdash; <?php echo number_format($pagination['total']); ?> records
                    </div>
                    <div class="pagination" style="margin-top: 0;">
                        <?php if ($pagination['has_prev']): ?>
                            <a href="support-tickets.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn" data-tooltip="First Page">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="support-tickets.php<?php echo buildQueryParams(['page' => $page - 1]); ?>" class="page-btn" data-tooltip="Previous">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php else: ?>
                            <button class="page-btn" disabled><i class="fas fa-angle-double-left"></i></button>
                            <button class="page-btn" disabled><i class="fas fa-angle-left"></i></button>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($pagination['total_pages'], $page + 2);
                        for ($p = $startPage; $p <= $endPage; $p++):
                        ?>
                            <a href="support-tickets.php<?php echo buildQueryParams(['page' => $p]); ?>" class="page-btn <?php echo $p === $page ? 'active' : ''; ?>">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pagination['has_next']): ?>
                            <a href="support-tickets.php<?php echo buildQueryParams(['page' => $page + 1]); ?>" class="page-btn" data-tooltip="Next">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="support-tickets.php<?php echo buildQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn" data-tooltip="Last Page">
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

<script>
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown-wrap')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
    }
});
</script>

</body>
</html>