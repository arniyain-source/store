<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Reviews Management Page - DesiVastra Admin
 */


// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitize($_POST['action'] ?? '');
    $reviewId = (int)($_POST['review_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($csrfToken)) {
        setFlash('error', 'Invalid security token. Please try again.');
        redirect('reviews.php');
        exit;
    }

    if (!$reviewId) {
        setFlash('error', 'Invalid review ID.');
        redirect('reviews.php');
        exit;
    }

    try {
        $db = getDB();

        // Fetch review for logging
        $stmt = $db->prepare("SELECT r.id, r.title, r.is_approved, r.product_id, p.name as product_name FROM reviews r LEFT JOIN products p ON r.product_id = p.id WHERE r.id = ?");
        $stmt->execute([$reviewId]);
        $review = $stmt->fetch();

        if (!$review) {
            setFlash('error', 'Review not found.');
            redirect('reviews.php');
            exit;
        }

        if ($action === 'approve') {
            $stmt = $db->prepare("UPDATE reviews SET is_approved = 1, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$reviewId]);

            // Update product rating
            updateProductRating($db, $review['product_id'] ?? null);

            logActivity('approve_review', 'review', $reviewId, [
                'title' => $review['title'],
                'product' => $review['product_name']
            ]);
            setFlash('success', 'Review has been approved successfully.');

        } elseif ($action === 'reject') {
            $stmt = $db->prepare("UPDATE reviews SET is_approved = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$reviewId]);

            // Update product rating
            updateProductRating($db, $review['product_id'] ?? null);

            logActivity('reject_review', 'review', $reviewId, [
                'title' => $review['title'],
                'product' => $review['product_name']
            ]);
            setFlash('success', 'Review has been rejected.');

        } elseif ($action === 'delete') {
            $productId = $review['product_id'] ?? null;
            $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$reviewId]);

            // Update product rating after delete
            if ($productId) {
                recalcProductRating($db, $productId);
            }

            logActivity('delete_review', 'review', $reviewId, [
                'title' => $review['title'],
                'product' => $review['product_name']
            ]);
            setFlash('success', 'Review has been deleted permanently.');

        } else {
            setFlash('error', 'Unknown action.');
        }

    } catch (Exception $e) {
        setFlash('error', 'Database error: ' . $e->getMessage());
    }

    // Preserve filters on redirect
    $queryParams = [];
    if (!empty($_POST['filter_search'])) $queryParams['search'] = $_POST['filter_search'];
    if (!empty($_POST['filter_rating'])) $queryParams['rating'] = $_POST['filter_rating'];
    if (isset($_POST['filter_status']) && $_POST['filter_status'] !== '') $queryParams['status'] = $_POST['filter_status'];
    if (!empty($_POST['filter_product'])) $queryParams['product'] = $_POST['filter_product'];
    if (!empty($_POST['filter_page'])) $queryParams['page'] = $_POST['filter_page'];

    $redirectUrl = 'reviews.php';
    if (!empty($queryParams)) {
        $redirectUrl .= '?' . http_build_query($queryParams);
    }
    redirect($redirectUrl);
    exit;
}

// Helper: Recalculate product average rating
function recalcProductRating($db, $productId) {
    $stmt = $db->prepare("SELECT COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as review_count FROM reviews WHERE product_id = ? AND is_approved = 1");
    $stmt->execute([$productId]);
    $result = $stmt->fetch();
    $avgRating = round($result['avg_rating'], 1);
    $reviewCount = (int)$result['review_count'];

    $stmt = $db->prepare("UPDATE products SET rating = ?, reviews_count = ? WHERE id = ?");
    $stmt->execute([$avgRating, $reviewCount, $productId]);
}

function updateProductRating($db, $productId) {
    if ($productId) {
        recalcProductRating($db, $productId);
    }
}

// Handle GET parameters for filtering
$searchQuery = sanitize($_GET['search'] ?? '');
$ratingFilter = (int)($_GET['rating'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$productFilter = (int)($_GET['product'] ?? 0);
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Validate filters
if ($ratingFilter < 0 || $ratingFilter > 5) $ratingFilter = 0;
if ($statusFilter !== '' && $statusFilter !== '0' && $statusFilter !== '1') $statusFilter = '';

// Get stats
$db = getDB();
$totalReviews = 0;
$pendingCount = 0;
$approvedCount = 0;
$avgRating = 0;

try {
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM reviews");
    $totalReviews = (int)$stmt->fetch()['cnt'];

    $stmt = $db->query("SELECT COUNT(*) as cnt FROM reviews WHERE is_approved = 0");
    $pendingCount = (int)$stmt->fetch()['cnt'];

    $stmt = $db->query("SELECT COUNT(*) as cnt FROM reviews WHERE is_approved = 1");
    $approvedCount = (int)$stmt->fetch()['cnt'];

    $stmt = $db->query("SELECT COALESCE(AVG(rating), 0) as avg_r FROM reviews");
    $avgRating = round((float)$stmt->fetch()['avg_r'], 1);
} catch (Exception $e) {
    // Will show 0s
}

// Get products for filter dropdown
$products = [];
try {
    $stmt = $db->query("SELECT p.id, p.name FROM products p INNER JOIN reviews r ON r.product_id = p.id GROUP BY p.id, p.name ORDER BY p.name ASC");
    $products = $stmt->fetchAll();
} catch (Exception $e) {}

// Build query
$baseQuery = "
    SELECT r.id, r.product_id, r.customer_id, r.customer_name, r.rating, r.title, r.review, 
           r.images, r.is_verified, r.is_approved, r.created_at,
           p.name as product_name, p.main_image as product_image
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
";

$whereClauses = [];
$params = [];

if ($searchQuery) {
    $whereClauses[] = "(r.title LIKE ? OR r.review LIKE ? OR r.customer_name LIKE ? OR p.name LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($ratingFilter > 0) {
    $whereClauses[] = "r.rating = ?";
    $params[] = $ratingFilter;
}

if ($statusFilter !== '') {
    $whereClauses[] = "r.is_approved = ?";
    $params[] = (int)$statusFilter;
}

if ($productFilter > 0) {
    $whereClauses[] = "r.product_id = ?";
    $params[] = $productFilter;
}

if (!empty($whereClauses)) {
    $baseQuery .= " WHERE " . implode(" AND ", $whereClauses);
}

$baseQuery .= " ORDER BY r.created_at DESC";

// Get paginated results
$pagination = paginate($baseQuery, $params, $currentPage, ADMIN_PER_PAGE);
$reviews = $pagination['data'];

// Flash message
$flash = getFlash();
$csrf = generateCSRF();

// Store current filters for POST redirects
$filterQueryString = '';
$filterParams = [];
if ($searchQuery) $filterParams['search'] = $searchQuery;
if ($ratingFilter) $filterParams['rating'] = $ratingFilter;
if ($statusFilter !== '') $filterParams['status'] = $statusFilter;
if ($productFilter) $filterParams['product'] = $productFilter;
if ($currentPage > 1) $filterParams['page'] = $currentPage;
if (!empty($filterParams)) {
    $filterQueryString = '&' . http_build_query($filterParams);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        /* Reviews-specific styles */
        .star-display {
            display: inline-flex;
            gap: 2px;
            font-size: 14px;
        }
        .star-display .fa-star { color: var(--border-color); }
        .star-display .fa-star.filled { color: var(--gold-primary); }
        .star-display .fa-star-half-alt { color: var(--gold-primary); }

        .rating-number {
            font-weight: 700;
            font-size: 13px;
            color: var(--gold-primary);
            margin-right: 4px;
        }

        .review-text {
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .review-images {
            display: flex;
            gap: 4px;
        }

        .review-images .thumb {
            width: 36px;
            height: 36px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .review-images .thumb:hover {
            border-color: var(--gold-dark);
            transform: scale(1.1);
        }

        .review-images .more-images {
            width: 36px;
            height: 36px;
            border-radius: 4px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 10px;
            font-weight: 600;
            color: var(--success);
            margin-left: 6px;
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

        .action-btns .btn-icon.approve:hover {
            border-color: var(--success);
            color: var(--success);
            background: var(--success-bg);
        }

        .action-btns .btn-icon.reject:hover {
            border-color: var(--warning);
            color: var(--warning);
            background: var(--warning-bg);
        }

        .action-btns .btn-icon.delete:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: var(--danger-bg);
        }

        /* Approval Status Badge */
        .approval-pending {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: var(--warning-bg);
            color: var(--warning);
            border: 1px solid rgba(241, 196, 15, 0.2);
        }

        .approval-approved {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .approval-rejected {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

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

        /* View Review Modal */
        .modal-review-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }

        .modal-review-header .product-thumb {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
            background: var(--bg-secondary);
        }

        .modal-review-header .product-thumb-placeholder {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-sm);
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 20px;
            flex-shrink: 0;
        }

        .modal-review-header .header-info {
            flex: 1;
        }

        .modal-review-header .header-info .product-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .modal-review-header .header-info .review-title-text {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 8px;
        }

        .modal-review-header .header-info .star-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 6px;
        }

        .review-detail-divider {
            height: 1px;
            background: var(--border-color);
            margin: 20px 0;
        }

        .review-detail-section-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .review-detail-section-title i {
            color: var(--gold-primary);
            font-size: 12px;
        }

        .review-full-text {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.7;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 14px;
        }

        .review-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
        }

        .review-images-grid .review-img {
            aspect-ratio: 1;
            border-radius: var(--radius-sm);
            object-fit: cover;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
        }

        .review-images-grid .review-img:hover {
            border-color: var(--gold-dark);
            transform: scale(1.03);
        }

        .review-customer-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .review-customer-info .detail-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .review-customer-info .detail-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .review-customer-info .detail-value {
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 500;
        }

        /* Quick action buttons row in modal */
        .modal-actions-row {
            display: flex;
            gap: 8px;
            margin-top: 20px;
        }

        /* Rating filter pills */
        .rating-pills {
            display: flex;
            gap: 4px;
        }

        .rating-pill {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .rating-pill:hover {
            border-color: var(--gold-dark);
            color: var(--gold-primary);
        }

        .rating-pill.active {
            background: rgba(212, 168, 83, 0.12);
            border-color: var(--gold-dark);
            color: var(--gold-primary);
        }

        .rating-pill i {
            color: var(--gold-primary);
            font-size: 10px;
        }

        /* Summary bar */
        .summary-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .summary-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 12px;
            color: var(--text-secondary);
        }

        .summary-chip strong {
            color: var(--text-primary);
            font-weight: 700;
        }

        .summary-chip i {
            font-size: 12px;
        }

        /* Lightbox for images */
        .lightbox-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            z-index: 3000;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .lightbox-overlay.show { display: flex; }

        .lightbox-overlay img {
            max-width: 90%;
            max-height: 90vh;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
        }

        /* Delete confirm modal */
        .confirm-modal-body {
            text-align: center;
            padding: 20px 0;
        }

        .confirm-modal-body .confirm-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--danger-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
            color: var(--danger);
        }

        .confirm-modal-body h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .confirm-modal-body p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .confirm-modal-body .confirm-btns {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        @media (max-width: 768px) {
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
            .summary-bar {
                gap: 8px;
            }
            .summary-chip {
                flex: 1;
                min-width: calc(50% - 8px);
            }
            .review-customer-info {
                grid-template-columns: 1fr;
            }
            .review-text {
                max-width: 160px;
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
                <span>Reviews</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-star" style="color:var(--gold-primary);margin-right:8px"></i>Reviews</h1>
                    <p class="subtitle">Manage customer reviews and ratings</p>
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

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card gold">
                    <div class="stat-icon"><i class="fas fa-comments"></i></div>
                    <div class="stat-value"><?php echo $totalReviews; ?></div>
                    <div class="stat-label">Total Reviews</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-value"><?php echo $pendingCount; ?></div>
                    <div class="stat-label">Pending Approval</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?php echo $approvedCount; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon"><i class="fas fa-star-half-alt"></i></div>
                    <div class="stat-value"><?php echo $avgRating; ?></div>
                    <div class="stat-label">Average Rating</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-card">
                <form method="GET" action="reviews.php" id="filterForm">
                    <div class="filter-row">
                        <div class="filter-group search-wrap">
                            <label>Search</label>
                            <div style="position:relative">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Search review text, customer, product..." value="<?php echo clean($searchQuery); ?>">
                            </div>
                        </div>

                        <div class="filter-group">
                            <label>Rating</label>
                            <select name="rating">
                                <option value="0">All Ratings</option>
                                <option value="5" <?php echo $ratingFilter === 5 ? 'selected' : ''; ?>>5 Stars</option>
                                <option value="4" <?php echo $ratingFilter === 4 ? 'selected' : ''; ?>>4 Stars</option>
                                <option value="3" <?php echo $ratingFilter === 3 ? 'selected' : ''; ?>>3 Stars</option>
                                <option value="2" <?php echo $ratingFilter === 2 ? 'selected' : ''; ?>>2 Stars</option>
                                <option value="1" <?php echo $ratingFilter === 1 ? 'selected' : ''; ?>>1 Star</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Approval Status</label>
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>Pending</option>
                                <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>Approved</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Product</label>
                            <select name="product">
                                <option value="0">All Products</option>
                                <?php foreach ($products as $prod): ?>
                                    <option value="<?php echo $prod['id']; ?>" <?php echo $productFilter === (int)$prod['id'] ? 'selected' : ''; ?>>
                                        <?php echo clean($prod['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group" style="flex-direction:row;align-items:flex-end;gap:6px">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="reviews.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Summary Bar (only when filters active) -->
            <?php if ($searchQuery || $ratingFilter || $statusFilter !== '' || $productFilter): ?>
                <div class="summary-bar">
                    <div class="summary-chip">
                        <i class="fas fa-filter" style="color:var(--gold-primary)"></i>
                        Showing <strong><?php echo $pagination['total']; ?></strong> result<?php echo $pagination['total'] !== 1 ? 's' : ''; ?>
                    </div>
                    <?php if ($searchQuery): ?>
                        <div class="summary-chip">
                            <i class="fas fa-search" style="color:var(--info)"></i>
                            Search: <strong>"<?php echo clean($searchQuery); ?>"</strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($ratingFilter): ?>
                        <div class="summary-chip">
                            <i class="fas fa-star" style="color:var(--gold-primary)"></i>
                            Rating: <strong><?php echo $ratingFilter; ?> Star<?php echo $ratingFilter > 1 ? 's' : ''; ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($statusFilter !== ''): ?>
                        <div class="summary-chip">
                            <i class="fas fa-<?php echo $statusFilter === '1' ? 'check-circle' : 'clock'; ?>" style="color:var(--<?php echo $statusFilter === '1' ? 'success' : 'warning'; ?>)"></i>
                            Status: <strong><?php echo $statusFilter === '1' ? 'Approved' : 'Pending'; ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($productFilter): ?>
                        <div class="summary-chip">
                            <i class="fas fa-box-open" style="color:var(--purple)"></i>
                            Product filtered
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Reviews Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list" style="color:var(--gold-primary);margin-right:8px"></i>
                        All Reviews
                        <span style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:8px">(<?php echo $pagination['total']; ?>)</span>
                    </h3>
                    <?php if ($pendingCount > 0): ?>
                        <a href="reviews.php?status=0" class="btn btn-warning btn-sm">
                            <i class="fas fa-clock"></i> <?php echo $pendingCount; ?> Pending
                        </a>
                    <?php endif; ?>
                </div>

                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Rating</th>
                                <th>Review</th>
                                <th>Images</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reviews)): ?>
                                <tr class="no-results-row">
                                    <td colspan="8">
                                        <div class="no-results-icon"><i class="fas fa-star"></i></div>
                                        <h3>No reviews found</h3>
                                        <p>
                                            <?php if ($searchQuery || $ratingFilter || $statusFilter !== '' || $productFilter): ?>
                                                Try adjusting your filters or search terms.
                                            <?php else: ?>
                                                Reviews will appear here once customers start leaving them.
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reviews as $rev): ?>
                                    <?php
                                        $reviewImages = [];
                                        if (!empty($rev['images'])) {
                                            $decoded = json_decode($rev['images'], true);
                                            if (is_array($decoded)) {
                                                $reviewImages = $decoded;
                                            }
                                        }
                                        $isApproved = (int)$rev['is_approved'];
                                    ?>
                                    <tr id="review-row-<?php echo $rev['id']; ?>">
                                        <td>
                                            <div class="product-cell">
                                                <?php if (!empty($rev['product_image'])): ?>
                                                    <img src="<?php echo clean(SITE_URL . '/' . $rev['product_image']); ?>" alt="<?php echo clean($rev['product_name'] ?? ''); ?>" class="product-img">
                                                <?php else: ?>
                                                    <div class="product-img" style="display:flex;align-items:center;justify-content:center;background:var(--bg-input);color:var(--text-muted);font-size:16px">
                                                        <i class="fas fa-box-open"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="product-name"><?php echo clean($rev['product_name'] ?? 'Unknown Product'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div style="font-weight:600;color:var(--text-primary);font-size:13px">
                                                    <?php echo clean($rev['customer_name']); ?>
                                                    <?php if ($rev['is_verified']): ?>
                                                        <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:4px">
                                                <span class="rating-number"><?php echo (int)$rev['rating']; ?></span>
                                                <div class="star-display">
                                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                                        <?php if ($s <= (int)$rev['rating']): ?>
                                                            <i class="fas fa-star filled"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-star"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="review-text" title="<?php echo clean($rev['review'] ?? ''); ?>">
                                                <?php echo clean($rev['title'] ?? $rev['review'] ?? 'No text'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($reviewImages)): ?>
                                                <div class="review-images">
                                                    <?php
                                                        $displayImages = array_slice($reviewImages, 0, 2);
                                                        $remainingCount = count($reviewImages) - 2;
                                                    ?>
                                                    <?php foreach ($displayImages as $img): ?>
                                                        <img src="<?php echo clean(SITE_URL . '/' . $img); ?>" alt="Review image" class="thumb" onclick="openLightbox('<?php echo clean(SITE_URL . '/' . $img); ?>')">
                                                    <?php endforeach; ?>
                                                    <?php if ($remainingCount > 0): ?>
                                                        <div class="more-images" onclick="viewReview(<?php echo $rev['id']; ?>)">+<?php echo $remainingCount; ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="font-size:11px;color:var(--text-muted)">No images</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isApproved === 1): ?>
                                                <span class="approval-approved"><i class="fas fa-check"></i> Approved</span>
                                            <?php else: ?>
                                                <span class="approval-pending"><i class="fas fa-clock"></i> Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size:12px;color:var(--text-secondary)">
                                                <?php echo date('M j, Y', strtotime($rev['created_at'])); ?>
                                                <span style="display:block;font-size:11px;color:var(--text-muted);margin-top:1px"><?php echo timeAgo($rev['created_at']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <button class="btn-icon view" onclick="viewReview(<?php echo $rev['id']; ?>)" data-tooltip="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($isApproved !== 1): ?>
                                                    <button class="btn-icon approve" onclick="quickAction(<?php echo $rev['id']; ?>, 'approve')" data-tooltip="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($isApproved !== 0 || !isset($isApproved)): ?>
                                                    <button class="btn-icon reject" onclick="quickAction(<?php echo $rev['id']; ?>, 'reject')" data-tooltip="Reject">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn-icon delete" onclick="confirmDelete(<?php echo $rev['id']; ?>, '<?php echo clean($rev['title'] ?? $rev['customer_name']); ?>')" data-tooltip="Delete">
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

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="card-footer">
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                            <div style="font-size:12px;color:var(--text-muted)">
                                Showing <?php echo (($pagination['page'] - 1) * $pagination['per_page']) + 1; ?>–<?php echo min($pagination['page'] * $pagination['per_page'], $pagination['total']); ?>
                                of <?php echo $pagination['total']; ?> reviews
                            </div>
                            <div class="pagination" style="margin-top:0">
                                <?php
                                    $queryParams = [];
                                    if ($searchQuery) $queryParams['search'] = $searchQuery;
                                    if ($ratingFilter) $queryParams['rating'] = $ratingFilter;
                                    if ($statusFilter !== '') $queryParams['status'] = $statusFilter;
                                    if ($productFilter) $queryParams['product'] = $productFilter;
                                    $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                                ?>

                                <?php if ($pagination['has_prev']): ?>
                                    <a href="reviews.php?page=<?php echo $pagination['page'] - 1; ?><?php echo $queryString; ?>" class="page-btn">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="page-btn" disabled><i class="fas fa-chevron-left"></i></button>
                                <?php endif; ?>

                                <?php
                                    $startPage = max(1, $pagination['page'] - 2);
                                    $endPage = min($pagination['total_pages'], $pagination['page'] + 2);

                                    if ($startPage > 1): ?>
                                        <a href="reviews.php?page=1<?php echo $queryString; ?>" class="page-btn">1</a>
                                        <?php if ($startPage > 2): ?>
                                            <span class="page-btn" style="border:none;background:none;color:var(--text-muted)">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                        <a href="reviews.php?page=<?php echo $p; ?><?php echo $queryString; ?>"
                                           class="page-btn <?php echo $p === $pagination['page'] ? 'active' : ''; ?>">
                                            <?php echo $p; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($endPage < $pagination['total_pages']): ?>
                                        <?php if ($endPage < $pagination['total_pages'] - 1): ?>
                                            <span class="page-btn" style="border:none;background:none;color:var(--text-muted)">...</span>
                                        <?php endif; ?>
                                        <a href="reviews.php?page=<?php echo $pagination['total_pages']; ?><?php echo $queryString; ?>" class="page-btn">
                                            <?php echo $pagination['total_pages']; ?>
                                        </a>
                                    <?php endif; ?>

                                <?php if ($pagination['has_next']): ?>
                                    <a href="reviews.php?page=<?php echo $pagination['page'] + 1; ?><?php echo $queryString; ?>" class="page-btn">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="page-btn" disabled><i class="fas fa-chevron-right"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- View Review Modal -->
<div class="modal-overlay" id="viewReviewModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-star" style="color:var(--gold-primary);margin-right:8px"></i>Review Details</h3>
            <button class="modal-close" onclick="closeViewModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="viewReviewBody">
            <!-- Content loaded dynamically -->
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal" style="max-width:440px">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="modal-close" onclick="closeDeleteModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="confirm-modal-body">
                <div class="confirm-icon">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <h3>Delete Review?</h3>
                <p id="deleteReviewName">This action cannot be undone. The review will be permanently deleted.</p>
                <div class="confirm-btns">
                    <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button class="btn btn-danger" id="confirmDeleteBtn">Delete Review</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox()">
    <img src="" alt="Full image" id="lightboxImg">
</div>

<!-- Hidden form for quick actions -->
<form method="POST" action="reviews.php" id="actionForm" style="display:none">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
    <input type="hidden" name="action" id="actionFormAction" value="">
    <input type="hidden" name="review_id" id="actionFormReviewId" value="">
    <input type="hidden" name="filter_search" value="<?php echo clean($searchQuery); ?>">
    <input type="hidden" name="filter_rating" value="<?php echo $ratingFilter; ?>">
    <input type="hidden" name="filter_status" value="<?php echo clean($statusFilter); ?>">
    <input type="hidden" name="filter_product" value="<?php echo $productFilter; ?>">
    <input type="hidden" name="filter_page" value="<?php echo $currentPage; ?>">
</form>

<script>
// Review data for modal view (embedded from PHP)
const reviewsData = <?php
    $jsReviews = [];
    foreach ($reviews as $rev) {
        $imgs = [];
        if (!empty($rev['images'])) {
            $decoded = json_decode($rev['images'], true);
            if (is_array($decoded)) $imgs = $decoded;
        }
        $jsReviews[] = [
            'id' => (int)$rev['id'],
            'product_name' => $rev['product_name'] ?? 'Unknown Product',
            'product_image' => $rev['product_image'] ?? '',
            'customer_name' => $rev['customer_name'],
            'customer_id' => (int)($rev['customer_id'] ?? 0),
            'rating' => (int)$rev['rating'],
            'title' => $rev['title'] ?? '',
            'review' => $rev['review'] ?? '',
            'images' => $imgs,
            'is_verified' => (int)$rev['is_verified'],
            'is_approved' => (int)$rev['is_approved'],
            'created_at' => $rev['created_at']
        ];
    }
    echo json_encode($jsReviews, JSON_UNESCAPED_UNICODE);
?>;

const siteUrl = '<?php echo SITE_URL; ?>';
const csrfToken = '<?php echo $csrf; ?>';

/**
 * Generate star HTML
 */
function starHtml(rating, size = 14) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            html += `<i class="fas fa-star filled" style="font-size:${size}px;color:var(--gold-primary)"></i>`;
        } else {
            html += `<i class="fas fa-star" style="font-size:${size}px;color:var(--border-color)"></i>`;
        }
    }
    return html;
}

/**
 * View Review Modal
 */
function viewReview(id) {
    const rev = reviewsData.find(r => r.id === id);
    if (!rev) return;

    const productImg = rev.product_image
        ? `<img src="${siteUrl}/${rev.product_image}" alt="${rev.product_name}" class="product-thumb">`
        : `<div class="product-thumb-placeholder"><i class="fas fa-box-open"></i></div>`;

    const reviewTitle = rev.title ? `<div class="review-title-text">"${rev.title}"</div>` : '';
    const verifiedBadge = rev.is_verified ? `<span class="verified-badge"><i class="fas fa-check-circle"></i> Verified Purchase</span>` : '';

    let imagesHtml = '';
    if (rev.images && rev.images.length > 0) {
        imagesHtml = `
            <div class="review-detail-section-title"><i class="fas fa-images"></i> Review Images</div>
            <div class="review-images-grid">
                ${rev.images.map(img => `<img src="${siteUrl}/${img}" alt="Review image" class="review-img" onclick="openLightbox('${siteUrl}/${img}')">`).join('')}
            </div>
            <div class="review-detail-divider"></div>
        `;
    }

    const statusHtml = rev.is_approved === 1
        ? `<span class="approval-approved"><i class="fas fa-check"></i> Approved</span>`
        : `<span class="approval-pending"><i class="fas fa-clock"></i> Pending Approval</span>`;

    const approveBtn = rev.is_approved !== 1
        ? `<button class="btn btn-success btn-sm" onclick="quickAction(${rev.id}, 'approve')"><i class="fas fa-check"></i> Approve</button>`
        : '';
    const rejectBtn = rev.is_approved !== 0
        ? `<button class="btn btn-warning btn-sm" onclick="quickAction(${rev.id}, 'reject')"><i class="fas fa-ban"></i> Reject</button>`
        : '';
    const deleteBtn = `<button class="btn btn-danger btn-sm" onclick="confirmDelete(${rev.id}, '${rev.title || rev.customer_name}')"><i class="fas fa-trash"></i> Delete</button>`;

    const body = `
        <div class="modal-review-header">
            ${productImg}
            <div class="header-info">
                <div class="product-name">${rev.product_name}</div>
                ${reviewTitle}
                <div class="star-row">
                    <span class="rating-number">${rev.rating}</span>
                    <div class="star-display">${starHtml(rev.rating)}</div>
                    ${verifiedBadge}
                </div>
            </div>
        </div>

        <div class="review-detail-divider"></div>

        <div class="review-detail-section-title"><i class="fas fa-user"></i> Customer Info</div>
        <div class="review-customer-info">
            <div class="detail-item">
                <div class="detail-label">Name</div>
                <div class="detail-value">${rev.customer_name}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Customer ID</div>
                <div class="detail-value">${rev.customer_id > 0 ? '#' + rev.customer_id : 'Guest'}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Submitted</div>
                <div class="detail-value">${new Date(rev.created_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' })}</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Approval</div>
                <div class="detail-value">${statusHtml}</div>
            </div>
        </div>

        <div class="review-detail-divider"></div>

        ${rev.review ? `
            <div class="review-detail-section-title"><i class="fas fa-quote-left"></i> Review Text</div>
            <div class="review-full-text">${rev.review}</div>
            <div class="review-detail-divider"></div>
        ` : ''}

        ${imagesHtml}

        <div class="modal-actions-row">
            ${approveBtn}
            ${rejectBtn}
            ${deleteBtn}
        </div>
    `;

    document.getElementById('viewReviewBody').innerHTML = body;
    document.getElementById('viewReviewModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('viewReviewModal').classList.remove('show');
    document.body.style.overflow = '';
}

/**
 * Quick approve/reject action
 */
function quickAction(id, action) {
    document.getElementById('actionFormAction').value = action;
    document.getElementById('actionFormReviewId').value = id;
    document.getElementById('actionForm').submit();
}

/**
 * Delete confirmation
 */
let deleteReviewId = null;

function confirmDelete(id, name) {
    deleteReviewId = id;
    document.getElementById('deleteReviewName').innerHTML =
        `This action cannot be undone. The review by <strong>${name}</strong> will be permanently deleted.`;
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
    deleteReviewId = null;
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (deleteReviewId) {
        // Close the view modal if open
        closeViewModal();
        quickAction(deleteReviewId, 'delete');
    }
});

/**
 * Lightbox
 */
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('show');
    event.stopPropagation();
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('show');
    document.getElementById('lightboxImg').src = '';
}

/**
 * Close modals on overlay click
 */
document.getElementById('viewReviewModal').addEventListener('click', function(e) {
    if (e.target === this) closeViewModal();
});

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

/**
 * Close modals with Escape key
 */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeViewModal();
        closeDeleteModal();
        closeLightbox();
    }
});
</script>

</body>
</html>
