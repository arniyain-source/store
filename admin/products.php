<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Products Management - DesiVastra Admin
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

// ============================================
// HANDLE DELETE REQUEST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($csrfToken)) {
        setFlash('error', 'Invalid request. Please try again.');
    } else {
        $productId = (int)($_POST['product_id'] ?? 0);
        if ($productId > 0) {
            try {
                $db = getDB();

                // Get product info for logging and image cleanup
                $stmt = $db->prepare("SELECT name, main_image FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();

                if ($product) {
                    // Delete the product
                    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                    $stmt->execute([$productId]);

                    // Delete product image if exists
                    if (!empty($product['main_image'])) {
                        deleteUploadedFile($product['main_image']);
                    }

                    // Log activity
                    logActivity('delete_product', 'product', $productId, ['name' => $product['name']]);

                    setFlash('success', 'Product "' . $product['name'] . '" deleted successfully.');
                } else {
                    setFlash('error', 'Product not found.');
                }
            } catch (Exception $e) {
                setFlash('error', 'Failed to delete product. ' . $e->getMessage());
            }
        } else {
            setFlash('error', 'Invalid product ID.');
        }
    }

    // Redirect to remove POST data, preserving filter state
    $queryParams = [];
    if (isset($_GET['search'])) $queryParams['search'] = $_GET['search'];
    if (isset($_GET['category'])) $queryParams['category'] = $_GET['category'];
    if (isset($_GET['status'])) $queryParams['status'] = $_GET['status'];
    if (isset($_GET['sort'])) $queryParams['sort'] = $_GET['sort'];
    if (isset($_GET['page'])) $queryParams['page'] = $_GET['page'];

    $redirectUrl = 'products.php';
    if (!empty($queryParams)) {
        $redirectUrl .= '?' . http_build_query($queryParams);
    }
    redirect($redirectUrl);
}

// ============================================
// GET FILTER PARAMETERS
// ============================================
$search = sanitize($_GET['search'] ?? '');
$categoryFilter = (int)($_GET['category'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));

// Validate status filter
if (!in_array($statusFilter, ['', 'active', 'inactive'])) {
    $statusFilter = '';
}

// Validate sort
$allowedSorts = ['newest', 'oldest', 'name_asc', 'name_desc', 'price_low', 'price_high', 'stock_low', 'stock_high'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'newest';
}

// ============================================
// BUILD QUERY
// ============================================
$whereConditions = ["1=1"];
$params = [];

// Search filter
if (!empty($search)) {
    $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Category filter
if ($categoryFilter > 0) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $categoryFilter;
}

// Status filter
if ($statusFilter === 'active') {
    $whereConditions[] = "p.is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $whereConditions[] = "p.is_active = 0";
}

$whereClause = implode(' AND ', $whereConditions);

// Sort order
$sortQuery = match ($sort) {
    'oldest'     => 'p.created_at ASC',
    'name_asc'   => 'p.name ASC',
    'name_desc'  => 'p.name DESC',
    'price_low'  => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'stock_low'  => 'p.stock ASC',
    'stock_high' => 'p.stock DESC',
    default      => 'p.created_at DESC',
};

// Base query with category join
$query = "SELECT p.*, c.name as category_name
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE {$whereClause}
          ORDER BY {$sortQuery}";

// ============================================
// FETCH DATA
// ============================================
$products = [];
$pagination = null;
$categories = [];
$dbError = false;

try {
    $db = getDB();

    // Get paginated products
    $pagination = paginate($query, $params, $page, ADMIN_PER_PAGE);
    $products = $pagination['data'];

    // Get all active categories for filter dropdown
    $stmt = $db->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY sort_order ASC, name ASC");
    $categories = $stmt->fetchAll();

} catch (Exception $e) {
    $dbError = true;
    error_log("Products page error: " . $e->getMessage());
}

// ============================================
// GET FLASH MESSAGE
// ============================================
$flash = getFlash();

// ============================================
// HELPER: Build query string preserving filters
// ============================================
function buildQueryParams($overrides = []) {
    $params = [];
    if (!empty($_GET['search']))  $params['search']   = $_GET['search'];
    if (!empty($_GET['category'])) $params['category'] = $_GET['category'];
    if (!empty($_GET['status']))  $params['status']   = $_GET['status'];
    if (!empty($_GET['sort']))    $params['sort']     = $_GET['sort'];
    $params = array_merge($params, $overrides);
    return !empty($params) ? '?' . http_build_query($params) : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        /* Ensure header aligns with main content beside fixed sidebar */
        .top-header { margin-left: var(--sidebar-width); }
        @media (max-width: 768px) {
            .top-header { margin-left: 0; }
        }
    </style>
</head>
<body>
<div class="admin-layout">

    <?php require_once __DIR__ . '/includes/layout.php'; ?>

    
        <div class="page-content">

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-box-open" style="color: var(--gold-primary); margin-right: 8px;"></i>Products</h1>
                    <p class="subtitle">
                        <?php if (!$dbError && $pagination): ?>
                            Showing <?php echo count($products); ?> of <?php echo number_format($pagination['total']); ?> products
                        <?php else: ?>
                            Manage your product catalog
                        <?php endif; ?>
                    </p>
                </div>
                <a href="product-form.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Product
                </a>
            </div>

            <!-- Flash Message -->
            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <i class="fas fa-<?php echo match($flash['type']) {
                        'success' => 'check-circle',
                        'error'   => 'exclamation-circle',
                        'warning' => 'exclamation-triangle',
                        default   => 'info-circle'
                    }; ?>"></i>
                    <?php echo clean($flash['message']); ?>
                    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Database Error -->
            <?php if ($dbError): ?>
                <div class="flash-message flash-error">
                    <i class="fas fa-exclamation-circle"></i>
                    Unable to load products. The database tables may not exist yet. Please run the setup script first.
                    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Filter Bar -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="padding: 16px 20px;">
                    <form method="GET" action="products.php" class="filter-bar" style="margin-bottom: 0;">
                        <!-- Search Input -->
                        <div style="position: relative; flex: 1; min-width: 200px;">
                            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px;"></i>
                            <input type="text" name="search" class="search-input" placeholder="Search products by name, SKU..." value="<?php echo clean($search); ?>" style="width: 100%;">
                        </div>

                        <!-- Category Dropdown -->
                        <select name="category" style="min-width: 150px;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo clean($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Status Dropdown -->
                        <select name="status" style="min-width: 130px;">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>

                        <!-- Sort Dropdown -->
                        <select name="sort" style="min-width: 160px;">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="stock_low" <?php echo $sort === 'stock_low' ? 'selected' : ''; ?>>Stock: Low to High</option>
                            <option value="stock_high" <?php echo $sort === 'stock_high' ? 'selected' : ''; ?>>Stock: High to Low</option>
                        </select>

                        <!-- Apply Button -->
                        <button type="submit" class="btn btn-secondary btn-sm">
                            <i class="fas fa-filter"></i> Filter
                        </button>

                        <?php if (!empty($search) || $categoryFilter > 0 || !empty($statusFilter) || $sort !== 'newest'): ?>
                            <a href="products.php" class="btn btn-sm" style="background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(231,76,60,0.2);">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Products Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list" style="margin-right: 8px; color: var(--gold-primary);"></i>Product List</h3>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <!-- Bulk Actions (Future) -->
                        <select id="bulkAction" class="form-control" style="width: auto; padding: 6px 30px 6px 10px; font-size: 12px;" disabled>
                            <option value="">Bulk Actions</option>
                            <option value="delete">Delete Selected</option>
                            <option value="activate">Set Active</option>
                            <option value="deactivate">Set Inactive</option>
                        </select>
                        <button class="btn btn-sm btn-secondary" disabled title="Apply bulk action (coming soon)">
                            <i class="fas fa-check"></i> Apply
                        </button>
                    </div>
                </div>

                <?php if (!$dbError && !empty($products)): ?>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" style="accent-color: var(--gold-primary);">
                                    </th>
                                    <th>Image</th>
                                    <th>Name / SKU</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <?php
                                        $lowStock = $product['stock'] <= ($product['low_stock_threshold'] ?? 5);
                                        $imagePath = !empty($product['main_image']) ? '../' . $product['main_image'] : '';
                                        $hasOldPrice = !empty($product['old_price']) && $product['old_price'] > $product['price'];
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="product-checkbox" name="selected[]" value="<?php echo $product['id']; ?>" style="accent-color: var(--gold-primary);">
                                        </td>
                                        <td>
                                            <?php if ($imagePath): ?>
                                                <img src="<?php echo clean($imagePath); ?>" alt="<?php echo clean($product['name']); ?>" class="product-img" loading="lazy">
                                            <?php else: ?>
                                                <div class="product-img" style="display: flex; align-items: center; justify-content: center; background: var(--bg-input);">
                                                    <i class="fas fa-image" style="color: var(--text-muted); font-size: 16px;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="product-name" style="margin-bottom: 2px;">
                                                <?php if (!empty($product['is_featured'])): ?>
                                                    <i class="fas fa-star" style="color: var(--warning); font-size: 10px; margin-right: 4px;" title="Featured"></i>
                                                <?php endif; ?>
                                                <?php if (!empty($product['is_new_arrival'])): ?>
                                                    <i class="fas fa-bolt" style="color: var(--purple); font-size: 10px; margin-right: 4px;" title="New Arrival"></i>
                                                <?php endif; ?>
                                                <?php echo clean($product['name']); ?>
                                            </div>
                                            <div class="product-sku">
                                                <?php echo !empty($product['sku']) ? 'SKU: ' . clean($product['sku']) : 'No SKU'; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($product['category_name'])): ?>
                                                <span class="badge badge-primary"><?php echo clean($product['category_name']); ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 12px;">Uncategorized</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-primary);">
                                                <?php echo formatIndianPrice($product['price']); ?>
                                            </div>
                                            <?php if ($hasOldPrice): ?>
                                                <div style="font-size: 11px; color: var(--text-muted); text-decoration: line-through;">
                                                    <?php echo formatIndianPrice($product['old_price']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($product['stock'] == 0): ?>
                                                <span style="color: var(--danger); font-weight: 600;">
                                                    <i class="fas fa-times-circle" style="font-size: 11px;"></i> Out of Stock
                                                </span>
                                            <?php elseif ($lowStock): ?>
                                                <span style="color: var(--warning); font-weight: 600;">
                                                    <i class="fas fa-exclamation-triangle" style="font-size: 11px;"></i>
                                                    <?php echo (int)$product['stock']; ?>
                                                </span>
                                                <div style="font-size: 10px; color: var(--text-muted);">Low Stock</div>
                                            <?php else: ?>
                                                <span style="color: var(--success); font-weight: 600;">
                                                    <?php echo (int)$product['stock']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($product['is_active'])): ?>
                                                <span class="badge badge-success"><span class="badge-dot" style="background: var(--success);"></span> Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger"><span class="badge-dot" style="background: var(--danger);"></span> Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                                <a href="product-form.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-secondary" data-tooltip="Edit Product">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo (int)$product['id']; ?>, '<?php echo clean(addslashes($product['name'])); ?>')" data-tooltip="Delete Product">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pagination && $pagination['total_pages'] > 1): ?>
                        <div class="card-footer">
                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                                <div style="font-size: 12px; color: var(--text-muted);">
                                    Showing page <?php echo $pagination['page']; ?> of <?php echo $pagination['total_pages']; ?>
                                    &mdash; <?php echo number_format($pagination['total']); ?> total products
                                </div>
                                <div class="pagination" style="margin-top: 0;">
                                    <?php if ($pagination['has_prev']): ?>
                                        <a href="products.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn" data-tooltip="First Page">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                        <a href="products.php<?php echo buildQueryParams(['page' => $pagination['page'] - 1]); ?>" class="page-btn" data-tooltip="Previous">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="page-btn" disabled><i class="fas fa-angle-double-left"></i></button>
                                        <button class="page-btn" disabled><i class="fas fa-angle-left"></i></button>
                                    <?php endif; ?>

                                    <?php
                                        $startPage = max(1, $pagination['page'] - 2);
                                        $endPage = min($pagination['total_pages'], $pagination['page'] + 2);

                                        if ($startPage > 1) {
                                            echo '<span style="color: var(--text-muted); padding: 0 4px;">&hellip;</span>';
                                        }

                                        for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <a href="products.php<?php echo buildQueryParams(['page' => $i]); ?>" class="page-btn <?php echo $i === $pagination['page'] ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php
                                        if ($endPage < $pagination['total_pages']) {
                                            echo '<span style="color: var(--text-muted); padding: 0 4px;">&hellip;</span>';
                                        }
                                    ?>

                                    <?php if ($pagination['has_next']): ?>
                                        <a href="products.php<?php echo buildQueryParams(['page' => $pagination['page'] + 1]); ?>" class="page-btn" data-tooltip="Next">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                        <a href="products.php<?php echo buildQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn" data-tooltip="Last Page">
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

                <?php elseif (!$dbError): ?>
                    <!-- Empty State -->
                    <div class="card-body">
                        <div class="empty-state">
                            <?php if (!empty($search) || $categoryFilter > 0 || !empty($statusFilter)): ?>
                                <i class="fas fa-search"></i>
                                <h3>No Products Found</h3>
                                <p>No products match your current filters. Try adjusting your search criteria.</p>
                                <a href="products.php" class="btn btn-secondary" style="margin-top: 16px;">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                            <?php else: ?>
                                <i class="fas fa-box-open"></i>
                                <h3>No Products Yet</h3>
                                <p>Start building your catalog by adding your first product.</p>
                                <a href="product-form.php" class="btn btn-primary" style="margin-top: 16px;">
                                    <i class="fas fa-plus"></i> Add Your First Product
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

</div><!-- /admin-layout -->

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal" style="max-width: 440px;">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger); margin-right: 8px;"></i>Confirm Delete</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 12px;">Are you sure you want to delete this product?</p>
            <div style="background: var(--danger-bg); border: 1px solid rgba(231,76,60,0.2); border-radius: var(--radius-sm); padding: 12px 16px;">
                <p style="font-weight: 600; color: var(--danger); margin-bottom: 4px;" id="deleteProductName"></p>
                <p style="font-size: 12px; color: var(--text-muted);">This action cannot be undone. The product will be permanently removed from your catalog.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <form method="POST" action="products.php<?php echo buildQueryParams(); ?>" id="deleteForm" style="display: inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" id="deleteProductId" value="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Delete Product
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================
// DELETE MODAL
// ============================================
function confirmDelete(productId, productName) {
    document.getElementById('deleteProductId').value = productId;
    document.getElementById('deleteProductName').textContent = productName;
    document.getElementById('deleteModal').classList.add('show');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.getElementById('deleteProductId').value = '';
}

// Close modal on overlay click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});

// ============================================
// SELECT ALL CHECKBOX
// ============================================
function toggleSelectAll(source) {
    var checkboxes = document.querySelectorAll('.product-checkbox');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}

// ============================================
// AUTO-SUBMIT FILTERS ON DROPDOWN CHANGE
// ============================================
var filterSelects = document.querySelectorAll('.filter-bar select');
for (var i = 0; i < filterSelects.length; i++) {
    filterSelects[i].addEventListener('change', function() {
        this.closest('form').submit();
    });
}
</script>

</body>
</html>
