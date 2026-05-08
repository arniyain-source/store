<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Return & Refund Management - DesiVastra Admin
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
        if ($action === 'update_return_status') {
            $returnId = (int)($_POST['return_id'] ?? 0);
            $newStatus = sanitize($_POST['status'] ?? '');

            if ($returnId > 0 && !empty($newStatus)) {
                try {
                    $stmt = $db->prepare("UPDATE order_returns SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$newStatus, $returnId]);

                    logActivity('update_return_status', 'order_return', $returnId, ['status' => $newStatus]);
                    setFlash('success', 'Return status updated successfully.');
                } catch (Exception $e) {
                    setFlash('error', 'Failed to update status.');
                }
            }
        } elseif ($action === 'process_refund') {
            $returnId = (int)($_POST['return_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $method = sanitize($_POST['method'] ?? '');

            if ($returnId > 0 && $amount > 0) {
                try {
                    $stmt = $db->prepare("UPDATE order_returns SET refund_status = 'completed', refund_amount = ?, status = 'completed', admin_note = ?, updated_at = NOW() WHERE id = ?");
                    $note = "Refund of ₹{$amount} processed via {$method}.";
                    $stmt->execute([$amount, $note, $returnId]);

                    logActivity('process_refund', 'order_return', $returnId, ['amount' => $amount, 'method' => $method]);
                    setFlash('success', 'Refund processed and return closed.');
                } catch (Exception $e) {
                    setFlash('error', 'Failed to process refund.');
                }
            }
        }
    }
    redirect('return-mgmt.php' . buildQueryParams());
}

// ============================================
// GET FILTER PARAMETERS
// ============================================
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

// ============================================
// BUILD QUERY
// ============================================
$whereConditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(r.return_no LIKE ? OR o.order_number LIKE ? OR c.name LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($statusFilter)) {
    $whereConditions[] = "r.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $whereConditions);

$query = "SELECT r.*, o.order_number, c.name as customer_name, c.phone as customer_phone
          FROM order_returns r
          JOIN orders o ON r.order_id = o.id
          JOIN customers c ON r.customer_id = c.id
          WHERE {$whereClause}
          ORDER BY r.created_at DESC";

// ============================================
// FETCH DATA
// ============================================
$returns = [];
$pagination = null;

try {
    $pagination = paginate($query, $params, $page, ADMIN_PER_PAGE);
    $returns = $pagination['data'];
} catch (Exception $e) {
    error_log("Return mgmt error: " . $e->getMessage());
}

$csrf = generateCSRF();
$flash = getFlash();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return & Refund Management - DesiVastra Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="admin-layout">
    <div class="page-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <div class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a>
                    <span class="separator"><i class="fas fa-chevron-right"></i></span>
                    <span>Return Management</span>
                </div>
                <h1><i class="fas fa-undo-alt" style="color: var(--gold-primary); margin-right: 8px;"></i>Return & Refund Management</h1>
                <p class="subtitle">Process customer return requests, RTOs, and issued refunds.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="rto-tracker.php" class="btn btn-secondary">
                    <i class="fas fa-truck-loading"></i> RTO Tracker
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo clean($flash['message']); ?>
                <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-body" style="padding: 16px 20px;">
                <form method="GET" action="return-mgmt.php" class="filter-bar" style="margin-bottom: 0;">
                    <div style="position: relative; flex: 1; min-width: 200px;">
                        <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px;"></i>
                        <input type="text" name="search" class="search-input" placeholder="Search by Return #, Order #, Customer..." value="<?php echo clean($search); ?>" style="width: 100%;">
                    </div>

                    <select name="status" style="min-width: 150px;">
                        <option value="">All Statuses</option>
                        <option value="requested" <?php echo $statusFilter === 'requested' ? 'selected' : ''; ?>>Requested</option>
                        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="picked" <?php echo $statusFilter === 'picked' ? 'selected' : ''; ?>>Picked</option>
                        <option value="received" <?php echo $statusFilter === 'received' ? 'selected' : ''; ?>>Received</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>

                    <button type="submit" class="btn btn-secondary btn-sm">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <?php if ($search || $statusFilter): ?>
                        <a href="return-mgmt.php" class="btn btn-sm" style="background: var(--bg-input); color: var(--text-muted);">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Returns Table -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list" style="margin-right: 8px; color: var(--gold-primary);"></i>Return Requests (<?php echo $pagination ? $pagination['total'] : 0; ?> total)</h3>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Return #</th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($returns)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px;">No return requests found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($returns as $row): ?>
                                <tr>
                                    <td class="bold"><?php echo clean($row['return_no']); ?></td>
                                    <td><a href="order-detail.php?id=<?php echo $row['order_id']; ?>" class="gold">#<?php echo clean($row['order_number']); ?></a></td>
                                    <td>
                                        <div class="customer-name"><?php echo clean($row['customer_name']); ?></div>
                                        <div class="customer-phone" style="font-size: 11px; color: var(--text-muted);"><?php echo clean($row['customer_phone']); ?></div>
                                    </td>
                                    <td style="max-width: 200px;"><div class="truncate" title="<?php echo clean($row['reason']); ?>"><?php echo clean($row['reason']); ?></div></td>
                                    <td>
                                        <?php
                                            $badgeClass = match($row['status']) {
                                                'requested' => 'badge-info',
                                                'approved'  => 'badge-warning',
                                                'rejected'  => 'badge-danger',
                                                'completed' => 'badge-success',
                                                default     => 'badge-secondary'
                                            };
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                            <button class="btn btn-sm btn-secondary" onclick="openStatusModal(<?php echo $row['id']; ?>, '<?php echo $row['status']; ?>')" title="Update Status">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($row['status'] !== 'completed' && $row['status'] !== 'rejected'): ?>
                                                <button class="btn btn-sm btn-primary" onclick="openRefundModal(<?php echo $row['id']; ?>, '<?php echo clean($row['return_no']); ?>')" title="Process Refund">
                                                    <i class="fas fa-hand-holding-usd"></i>
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
            <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                <div class="card-footer">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                        <div style="font-size: 12px; color: var(--text-muted);">
                            Showing page <?php echo $pagination['page']; ?> of <?php echo $pagination['total_pages']; ?> &mdash; <?php echo number_format($pagination['total']); ?> total records
                        </div>
                        <div class="pagination" style="margin-top: 0;">
                            <?php if ($pagination['has_prev']): ?>
                                <a href="return-mgmt.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn" title="First Page">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="return-mgmt.php<?php echo buildQueryParams(['page' => $pagination['page'] - 1]); ?>" class="page-btn" title="Previous">
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
                                <a href="return-mgmt.php<?php echo buildQueryParams(['page' => $i]); ?>" class="page-btn <?php echo $i === $pagination['page'] ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($pagination['has_next']): ?>
                                <a href="return-mgmt.php<?php echo buildQueryParams(['page' => $pagination['page'] + 1]); ?>" class="page-btn" title="Next">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="return-mgmt.php<?php echo buildQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn" title="Last Page">
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

<!-- Status Modal -->
<div class="modal-overlay" id="statusModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Update Return Status</h3>
            <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_return_status">
                <input type="hidden" name="return_id" id="status_return_id">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                
                <div class="form-group">
                    <label class="form-label">New Status</label>
                    <select name="status" id="status_select" class="form-control" required>
                        <option value="requested">Requested</option>
                        <option value="approved">Approved</option>
                        <option value="picked">Picked</option>
                        <option value="received">Received</option>
                        <option value="qc_failed">QC Failed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<!-- Refund Modal -->
<div class="modal-overlay" id="refundModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Process Refund</h3>
            <button class="modal-close" onclick="closeModal('refundModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="process_refund">
                <input type="hidden" name="return_id" id="refund_return_id">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                
                <div style="margin-bottom: 15px; color: var(--gold-light); font-weight: 600;" id="refund_info"></div>

                <div class="form-group">
                    <label class="form-label">Refund Amount (₹)</label>
                    <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Refund Method</label>
                    <select name="method" class="form-control">
                        <option value="original">Original Payment Mode</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="wallet">Wallet / Store Credit</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('refundModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Refund</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStatusModal(id, currentStatus) {
    document.getElementById('status_return_id').value = id;
    document.getElementById('status_select').value = currentStatus;
    document.getElementById('statusModal').classList.add('show');
}

function openRefundModal(id, returnNo) {
    document.getElementById('refund_return_id').value = id;
    document.getElementById('refund_info').textContent = 'Processing refund for Return #' + returnNo;
    document.getElementById('refundModal').classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}
</script>

</body>
</html>