<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Review & Trust Manager - DesiVastra Admin
 */

$db = getDB();

// ============================================
// HANDLE ACTIONS (AJAX)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxRequest()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }

    $reviewId = (int)($_POST['review_id'] ?? 0);

    if ($action === 'approve_review') {
        $stmt = $db->prepare("UPDATE reviews SET is_approved = 1 WHERE id = ?");
        $stmt->execute([$reviewId]);
        logActivity('approve_review', 'review', $reviewId);
        echo json_encode(['success' => true, 'message' => 'Review approved.']);
        exit;
    }

    if ($action === 'reject_review') {
        $stmt = $db->prepare("UPDATE reviews SET is_approved = 0 WHERE id = ?");
        $stmt->execute([$reviewId]);
        logActivity('reject_review', 'review', $reviewId);
        echo json_encode(['success' => true, 'message' => 'Review unapproved.']);
        exit;
    }

    if ($action === 'toggle_verified') {
        $status = (int)($_POST['status'] ?? 0);
        $stmt = $db->prepare("UPDATE reviews SET is_verified_buyer = ? WHERE id = ?");
        $stmt->execute([$status, $reviewId]);
        logActivity('toggle_verified_buyer', 'review', $reviewId);
        echo json_encode(['success' => true, 'message' => 'Verified status updated.']);
        exit;
    }

    if ($action === 'save_reply') {
        $reply = sanitize($_POST['reply'] ?? '');
        $stmt = $db->prepare("UPDATE reviews SET admin_reply = ? WHERE id = ?");
        $stmt->execute([$reply, $reviewId]);
        logActivity('reply_to_review', 'review', $reviewId);
        echo json_encode(['success' => true, 'message' => 'Reply saved.']);
        exit;
    }
}

// ============================================
// DATA FETCHING
// ============================================
$page = max(1, (int)($_GET['page'] ?? 1));
$statusFilter = $_GET['status'] ?? 'all';

$where = "1=1";
$params = [];

if ($statusFilter === 'pending') {
    $where .= " AND is_approved = 0";
} elseif ($statusFilter === 'approved') {
    $where .= " AND is_approved = 1";
}

$query = "SELECT r.*, p.name as product_name, p.sku as product_sku 
          FROM reviews r 
          LEFT JOIN products p ON r.product_id = p.id 
          WHERE {$where}";

$pagination = paginate($query . " ORDER BY r.created_at DESC", $params, $page, ADMIN_PER_PAGE);
$reviews = $pagination['data'];

// Get counts for labels
$stmt = $db->query("SELECT COUNT(*) FROM reviews");
$totalCount = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM reviews WHERE is_approved = 0");
$pendingCount = $stmt->fetchColumn();

$csrf = generateCSRF();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review & Trust Manager - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/layout.php'; ?>

    <div class="page-content">
        <div class="breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i></a>
            <span class="separator"><i class="fas fa-chevron-right"></i></span>
            <span>Reviews</span>
        </div>

        <div class="page-header">
            <div>
                <h1><i class="fas fa-star" style="color:var(--gold-primary);margin-right:8px"></i>Review & Trust Manager</h1>
                <p class="subtitle">Moderate customer feedback and manage social proof.</p>
            </div>
            <div class="header-actions">
                <a href="trust-badges.php" class="btn btn-secondary">
                    <i class="fas fa-shield-alt"></i> Trust Badges
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <i class="fas fa-info-circle"></i>
                <?php echo clean($flash['message']); ?>
                <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 20px;">
            <div class="stat-card info">
                <div class="stat-label">Pending Approval</div>
                <div class="stat-value"><?php echo $pendingCount; ?></div>
            </div>
            <div class="stat-card gold">
                <div class="stat-label">Total Reviews</div>
                <div class="stat-value"><?php echo $totalCount; ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Customer Reviews (<?php echo $pagination['total']; ?> records)</h3>
                <div class="filter-group">
                    <select onchange="location.href='?status=' + this.value" class="form-control" style="width: auto;">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Reviews</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    </select>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Customer</th>
                            <th>Rating</th>
                            <th>Review Text</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reviews)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    No reviews found matching the criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($reviews as $rev): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; font-size: 13px;"><?php echo clean($rev['product_name']); ?></div>
                                <code class="gold" style="font-size: 11px;"><?php echo clean($rev['product_sku']); ?></code>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo clean($rev['customer_name']); ?></div>
                                <label class="switch-sm" title="Verified Buyer" style="display: flex; align-items: center; gap: 8px; font-size: 11px; margin-top: 4px; cursor: pointer;">
                                    <input type="checkbox" <?php echo $rev['is_verified_buyer'] ? 'checked' : ''; ?> 
                                           onclick="updateToggle(<?php echo $rev['id']; ?>, 'toggle_verified', this.checked)">
                                    <span class="badge <?php echo $rev['is_verified_buyer'] ? 'badge-success' : 'badge-secondary'; ?>" style="font-size: 9px;">Verified Buyer</span>
                                </label>
                            </td>
                            <td>
                                <div class="stars">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="<?php echo $i <= $rev['rating'] ? 'fas' : 'far'; ?> fa-star" style="color: var(--gold-primary); font-size: 12px;"></i>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td>
                                <div style="max-width: 350px; font-size: 13px; color: var(--text-primary);">
                                    <?php echo clean($rev['review']); ?>
                                </div>
                                <?php if($rev['admin_reply']): ?>
                                    <div style="font-size: 11px; background: rgba(184, 137, 42, 0.05); border-left: 2px solid var(--gold-primary); padding: 8px; margin-top: 8px; color: var(--gold-light);">
                                        <i class="fas fa-reply fa-rotate-180" style="margin-right: 6px;"></i> <strong>Reply:</strong> <?php echo clean($rev['admin_reply']); ?>
                                    </div>
                                <?php endif; ?>
                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                                    <i class="fas fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($rev['created_at'])); ?>
                                </div>
                            </td>
                            <td>
                                <?php if($rev['is_approved']): ?>
                                    <span class="badge badge-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-btns" style="justify-content: flex-end;">
                                    <button class="btn-icon" onclick="openReplyModal(<?php echo $rev['id']; ?>, '<?php echo addslashes($rev['admin_reply']); ?>')" title="Reply">
                                        <i class="fas fa-comment-dots"></i>
                                    </button>
                                    <?php if(!$rev['is_approved']): ?>
                                        <button class="btn-icon success" onclick="performAction(<?php echo $rev['id']; ?>, 'approve_review')" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-icon danger" onclick="performAction(<?php echo $rev['id']; ?>, 'reject_review')" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                            <a href="review-mgmt.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn" title="First Page">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="review-mgmt.php<?php echo buildQueryParams(['page' => $pagination['page'] - 1]); ?>" class="page-btn" title="Previous">
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
                            <a href="review-mgmt.php<?php echo buildQueryParams(['page' => $i]); ?>" class="page-btn <?php echo $i === $pagination['page'] ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pagination['has_next']): ?>
                            <a href="review-mgmt.php<?php echo buildQueryParams(['page' => $pagination['page'] + 1]); ?>" class="page-btn" title="Next">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="review-mgmt.php<?php echo buildQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn" title="Last Page">
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

<!-- Reply Modal -->
<div id="replyModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:10000; align-items:center; justify-content:center;">
    <div class="modal" style="max-width: 500px; width: 90%; background: var(--bg-card-solid); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
        <div class="modal-header" style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin:0;">Admin Reply</h3>
            <button class="modal-close" onclick="closeModal()" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <input type="hidden" id="replyReviewId">
            <label class="form-label">Response to Customer</label>
            <textarea id="adminReplyText" rows="5" class="form-control" style="width:100%; background:var(--bg-input); border:1px solid var(--border-color); color:var(--text-primary); padding:10px; border-radius:4px;" placeholder="Type your reply here..."></textarea>
        </div>
        <div class="modal-footer" style="padding: 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
            <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveReply()">Save Response</button>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo $csrf; ?>';

function performAction(id, action) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('review_id', id);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('review-mgmt.php', {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) location.reload();
        else alert(data.message);
    })
    .catch(err => alert('An error occurred. Please try again.'));
}

function updateToggle(id, action, status) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('review_id', id);
    formData.append('status', status ? 1 : 0);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('review-mgmt.php', {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(res => res.json())
    .then(data => {
        if(!data.success) {
            alert(data.message);
            location.reload();
        }
    })
    .catch(err => alert('An error occurred. Please try again.'));
}

function openReplyModal(id, existingReply) {
    document.getElementById('replyReviewId').value = id;
    document.getElementById('adminReplyText').value = existingReply;
    document.getElementById('replyModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('replyModal').style.display = 'none';
}

function saveReply() {
    const id = document.getElementById('replyReviewId').value;
    const reply = document.getElementById('adminReplyText').value;

    const formData = new FormData();
    formData.append('action', 'save_reply');
    formData.append('review_id', id);
    formData.append('reply', reply);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('review-mgmt.php', {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) location.reload();
        else alert(data.message);
    })
    .catch(err => alert('An error occurred. Please try again.'));
}

window.onclick = function(event) {
    if (event.target == document.getElementById('replyModal')) closeModal();
}
</script>

</body>
</html>