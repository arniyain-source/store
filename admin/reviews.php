<?php

require_once __DIR__ . '/../includes/core/app.php';
requireAdminLogin();

$db = getDB();
$pageTitle = 'Manage Reviews';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $reviewId = (int)$_POST['review_id'];
    if (verifyCSRF($_POST['csrf_token'])) {
        if ($_POST['action'] === 'approve') {
            $stmt = $db->prepare("UPDATE reviews SET is_approved = 1 WHERE id = ?");
            $stmt->execute([$reviewId]);
            setFlash('success', 'Review approved.');
        } elseif ($_POST['action'] === 'unapprove') {
            $stmt = $db->prepare("UPDATE reviews SET is_approved = 0 WHERE id = ?");
            $stmt->execute([$reviewId]);
            setFlash('success', 'Review unapproved.');
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$reviewId]);
            setFlash('success', 'Review deleted.');
        }
    }
    // Redirect to the same page and filter
    $page = $_POST['page'] ?? 1;
    $filter = $_POST['filter'] ?? 'all';
    redirect("reviews.php?page={$page}&filter={$filter}");
}

// Pagination and Filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$filter = $_GET['filter'] ?? 'all';
$limit = 15; // Reviews per page

$whereClause = '';
$params = [];
if ($filter === 'pending') {
    $whereClause = 'WHERE r.is_approved = ?';
    $params[] = 0;
} elseif ($filter === 'approved') {
    $whereClause = 'WHERE r.is_approved = ?';
    $params[] = 1;
}

// Get total count for pagination
$countStmt = $db->prepare("SELECT COUNT(*) FROM reviews r $whereClause");
$countStmt->execute($params);
$total_reviews = $countStmt->fetchColumn();

// Fetch reviews for the current page
$offset = ($page - 1) * $limit;
$query = "SELECT r.*, p.name as product_name 
          FROM reviews r 
          JOIN products p ON r.product_id = p.id 
          $whereClause 
          ORDER BY r.created_at DESC 
          LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$csrf = generateCSRF();

require_once __DIR__ . '/../includes/layout.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <h1 class="m-0">Manage Product Reviews</h1>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="btn-group">
                        <a href="?filter=all" class="btn btn-sm btn-<?php echo $filter === 'all' ? 'primary' : 'outline-primary'; ?>">All</a>
                        <a href="?filter=pending" class="btn btn-sm btn-<?php echo $filter === 'pending' ? 'primary' : 'outline-primary'; ?>">Pending</a>
                        <a href="?filter=approved" class="btn btn-sm btn-<?php echo $filter === 'approved' ? 'primary' : 'outline-primary'; ?>">Approved</a>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Rating</th>
                                <th style="width: 30%;">Review</th>
                                <th>Status</th>
                                <th>Submitted On</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reviews)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No reviews found for this filter.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td><a href="../product.php?id=<?php echo $review['product_id']; ?>" target="_blank"><?php echo clean(substr($review['product_name'], 0, 30)); ?>...</a></td>
                                    <td><?php echo clean($review['customer_name']); ?></td>
                                    <td><span class="text-warning"><?php echo str_repeat('★', $review['rating']); ?></span><span class="text-muted"><?php echo str_repeat('☆', 5 - $review['rating']); ?></span></td>
                                    <td class="review-text-cell"><?php echo clean($review['review']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $review['is_approved'] ? 'success' : 'warning'; ?>">
                                            <?php echo $review['is_approved'] ? 'Approved' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M, Y H:i', strtotime($review['created_at'])); ?></td>
                                    <td class="text-right">
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                            <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="page" value="<?php echo $page; ?>">
                                            <input type="hidden" name="filter" value="<?php echo $filter; ?>">

                                            <?php if ($review['is_approved']): ?>
                                                <button type="submit" name="action" value="unapprove" class="btn btn-xs btn-outline-secondary">Unapprove</button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="approve" class="btn btn-xs btn-success">Approve</button>
                                            <?php endif; ?>
                                            <button type="submit" name="action" value="delete" class="btn btn-xs btn-danger ml-1">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer clearfix">
                <?php renderPaginationControls('reviews.php', $page, $total_reviews, $limit, ['filter' => $filter]); ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
