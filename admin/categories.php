<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Categories Management - DesiVastra Admin
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

// ============================================
// HANDLE POST REQUESTS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($csrfToken)) {
        setFlash('error', 'Invalid request. Please try again.');
        redirect('categories.php');
    }

    $action = $_POST['action'];

    // ----------------------------------------
    // CREATE CATEGORY
    // ----------------------------------------
    if ($action === 'create') {
        $name        = sanitize($_POST['name'] ?? '');
        $slug        = sanitize($_POST['slug'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $icon        = sanitize($_POST['icon'] ?? '');
        $parentId    = (int)($_POST['parent_id'] ?? 0) ?: null;
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $status      = isset($_POST['status']) ? 1 : 0;

        if (empty($name)) {
            setFlash('error', 'Category name is required.');
            redirect('categories.php');
        }

        try {
            $db = getDB();

            // Generate slug if empty
            if (empty($slug)) {
                $slug = generateUniqueSlug($name, 'categories');
            } else {
                // Ensure slug uniqueness
                $slug = generateUniqueSlug($slug, 'categories');
            }

            // Handle image upload
            $imagePath = null;
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['image'], 'categories');
                if ($uploadResult['success']) {
                    $imagePath = $uploadResult['path'];
                } else {
                    setFlash('error', 'Image upload failed: ' . $uploadResult['message']);
                    redirect('categories.php');
                }
            }

            $stmt = $db->prepare("INSERT INTO categories (name, slug, description, image, icon, parent_id, sort_order, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$name, $slug, $description, $imagePath, $icon, $parentId, $sortOrder, $status]);

            $newId = $db->lastInsertId();
            logActivity('create_category', 'category', $newId, ['name' => $name]);

            setFlash('success', 'Category "' . $name . '" created successfully.');
        } catch (Exception $e) {
            setFlash('error', 'Failed to create category. ' . $e->getMessage());
        }

        redirect('categories.php');
    }

    // ----------------------------------------
    // UPDATE CATEGORY
    // ----------------------------------------
    if ($action === 'update') {
        $id          = (int)($_POST['category_id'] ?? 0);
        $name        = sanitize($_POST['name'] ?? '');
        $slug        = sanitize($_POST['slug'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $icon        = sanitize($_POST['icon'] ?? '');
        $parentId    = (int)($_POST['parent_id'] ?? 0) ?: null;
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $status      = isset($_POST['status']) ? 1 : 0;
        $removeImage = isset($_POST['remove_image']) ? true : false;

        if ($id <= 0 || empty($name)) {
            setFlash('error', 'Invalid category data.');
            redirect('categories.php');
        }

        try {
            $db = getDB();

            // Get existing category
            $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();

            if (!$existing) {
                setFlash('error', 'Category not found.');
                redirect('categories.php');
            }

            // Prevent setting parent to self
            if ($parentId == $id) {
                $parentId = null;
            }

            // Generate slug if empty
            if (empty($slug)) {
                $slug = generateUniqueSlug($name, 'categories', $id);
            } else {
                $slug = generateUniqueSlug($slug, 'categories', $id);
            }

            // Handle image
            $imagePath = $existing['image'];

            if ($removeImage && !empty($imagePath)) {
                deleteUploadedFile($imagePath);
                $imagePath = null;
            }

            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['image'], 'categories');
                if ($uploadResult['success']) {
                    // Delete old image
                    if (!empty($imagePath)) {
                        deleteUploadedFile($imagePath);
                    }
                    $imagePath = $uploadResult['path'];
                } else {
                    setFlash('error', 'Image upload failed: ' . $uploadResult['message']);
                    redirect('categories.php');
                }
            }

            $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, image = ?, icon = ?, parent_id = ?, sort_order = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $slug, $description, $imagePath, $icon, $parentId, $sortOrder, $status, $id]);

            logActivity('update_category', 'category', $id, ['name' => $name]);

            setFlash('success', 'Category "' . $name . '" updated successfully.');
        } catch (Exception $e) {
            setFlash('error', 'Failed to update category. ' . $e->getMessage());
        }

        redirect('categories.php');
    }

    // ----------------------------------------
    // DELETE CATEGORY
    // ----------------------------------------
    if ($action === 'delete') {
        $categoryId = (int)($_POST['category_id'] ?? 0);

        if ($categoryId <= 0) {
            setFlash('error', 'Invalid category ID.');
            redirect('categories.php');
        }

        try {
            $db = getDB();

            // Get category info
            $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            $category = $stmt->fetch();

            if ($category) {
                // Check if category has products
                $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM products WHERE category_id = ?");
                $stmt->execute([$categoryId]);
                $productCount = (int)$stmt->fetch()['cnt'];

                // Check if category has child categories
                $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM categories WHERE parent_id = ?");
                $stmt->execute([$categoryId]);
                $childCount = (int)$stmt->fetch()['cnt'];

                if ($productCount > 0) {
                    setFlash('error', 'Cannot delete category "' . $category['name'] . '" — it has ' . $productCount . ' associated product(s). Please reassign or delete the products first.');
                    redirect('categories.php');
                }

                if ($childCount > 0) {
                    setFlash('error', 'Cannot delete category "' . $category['name'] . '" — it has ' . $childCount . ' sub-categor(ies). Please reassign or delete them first.');
                    redirect('categories.php');
                }

                // Delete category image
                if (!empty($category['image'])) {
                    deleteUploadedFile($category['image']);
                }

                // Delete the category
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$categoryId]);

                logActivity('delete_category', 'category', $categoryId, ['name' => $category['name']]);

                setFlash('success', 'Category "' . $category['name'] . '" deleted successfully.');
            } else {
                setFlash('error', 'Category not found.');
            }
        } catch (Exception $e) {
            setFlash('error', 'Failed to delete category. ' . $e->getMessage());
        }

        redirect('categories.php');
    }

    // Unknown action
    setFlash('error', 'Unknown action.');
    redirect('categories.php');
}

// ============================================
// FETCH CATEGORIES
// ============================================
$categories = [];
$allCategoriesFlat = [];
$dbError = false;

try {
    $db = getDB();

    // Get all categories with parent name and product count
    $stmt = $db->query("
        SELECT c.*,
               pc.name AS parent_name,
               (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count,
               (SELECT COUNT(*) FROM categories cc WHERE cc.parent_id = c.id) AS child_count
        FROM categories c
        LEFT JOIN categories pc ON c.parent_id = pc.id
        ORDER BY c.sort_order ASC, c.name ASC
    ");
    $categories = $stmt->fetchAll();

    // Get flat list for parent dropdown (only top-level and active)
    $stmt = $db->query("SELECT id, name, parent_id FROM categories WHERE status = 1 ORDER BY sort_order ASC, name ASC");
    $allCategoriesFlat = $stmt->fetchAll();

} catch (Exception $e) {
    $dbError = true;
    error_log("Categories page error: " . $e->getMessage());
}

// ============================================
// GET FLASH MESSAGE
// ============================================
$flash = getFlash();

// ============================================
// HELPER: Build hierarchical options for parent dropdown
// ============================================
function buildCategoryOptions($categories, $parentId = null, $excludeId = null, $depth = 0) {
    $html = '';
    $prefix = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
    if ($depth > 0) {
        $prefix .= '↳ ';
    }
    foreach ($categories as $cat) {
        if (($cat['parent_id'] ?? null) != $parentId) continue;
        if ($cat['id'] == $excludeId) continue;
        $html .= '<option value="' . $cat['id'] . '">' . $prefix . clean($cat['name']) . '</option>';
        $html .= buildCategoryOptions($categories, $cat['id'], $excludeId, $depth + 1);
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .top-header { margin-left: var(--sidebar-width); }
        @media (max-width: 768px) {
            .top-header { margin-left: 0; }
        }

        /* Category Grid */
        .category-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        @media (max-width: 1024px) {
            .category-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .category-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Category Card */
        .category-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            overflow: hidden;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
        }

        .category-card:hover {
            border-color: var(--gold-dark);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .category-card-image {
            width: 100%;
            height: 160px;
            background: var(--bg-input);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .category-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .category-card-image .icon-placeholder {
            font-size: 48px;
            color: var(--text-muted);
        }

        .category-card-image .category-icon-badge {
            position: absolute;
            bottom: 10px;
            left: 10px;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            background: rgba(10, 10, 15, 0.85);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--gold-primary);
        }

        .category-card-status {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .category-card-body {
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .category-card-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .category-card-slug {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 8px;
            font-family: 'Courier New', monospace;
        }

        .category-card-desc {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 12px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .category-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }

        .category-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: var(--text-muted);
            background: var(--bg-input);
            padding: 3px 8px;
            border-radius: 4px;
        }

        .category-meta-item i {
            font-size: 10px;
            color: var(--gold-primary);
        }

        .category-card-actions {
            display: flex;
            gap: 8px;
            padding-top: 14px;
            border-top: 1px solid var(--border-color);
        }

        .category-card-actions .btn {
            flex: 1;
            justify-content: center;
        }

        /* Image preview in modal */
        .category-image-preview {
            width: 100%;
            max-height: 200px;
            object-fit: contain;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            margin-top: 8px;
        }

        /* Search filter for categories */
        .category-search {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .category-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 13px;
        }

        .category-search input {
            width: 100%;
            padding: 9px 14px 9px 36px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 13px;
            transition: var(--transition);
        }

        .category-search input:focus {
            outline: none;
            border-color: var(--gold-dark);
            box-shadow: 0 0 0 3px rgba(212, 168, 83, 0.1);
        }

        .category-search input::placeholder {
            color: var(--text-muted);
        }

        /* Slug input group */
        .slug-input-group {
            display: flex;
            gap: 0;
        }

        .slug-input-group .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .slug-toggle-btn {
            padding: 10px 14px;
            background: var(--bg-card-hover);
            border: 1px solid var(--border-color);
            border-left: none;
            border-top-right-radius: var(--radius-sm);
            border-bottom-right-radius: var(--radius-sm);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 12px;
            transition: var(--transition);
            white-space: nowrap;
        }

        .slug-toggle-btn:hover {
            color: var(--gold-primary);
        }

        .slug-toggle-btn.active {
            color: var(--gold-primary);
            background: rgba(212, 168, 83, 0.1);
        }

        /* Remove image checkbox */
        .remove-image-check {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            font-size: 12px;
            color: var(--danger);
        }

        .remove-image-check input {
            accent-color: var(--danger);
        }

        /* Stats row */
        .categories-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .categories-stat {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .categories-stat i {
            font-size: 14px;
            color: var(--gold-primary);
        }

        .categories-stat strong {
            color: var(--text-primary);
            font-weight: 700;
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
                    <h1><i class="fas fa-tags" style="color: var(--gold-primary); margin-right: 8px;"></i>Categories</h1>
                    <p class="subtitle">
                        <?php if (!$dbError && !empty($categories)): ?>
                            Manage your product categories &mdash; <?php echo count($categories); ?> total
                        <?php else: ?>
                            Organize your products into categories
                        <?php endif; ?>
                    </p>
                </div>
                <button type="button" class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Category
                </button>
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
                    Unable to load categories. The database tables may not exist yet. Please run the setup script first.
                    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!$dbError): ?>

                <!-- Quick Stats -->
                <div class="categories-stats">
                    <div class="categories-stat">
                        <i class="fas fa-layer-group"></i>
                        <span>Total: <strong><?php echo count($categories); ?></strong></span>
                    </div>
                    <?php
                        $activeCount = count(array_filter($categories, fn($c) => !empty($c['status'])));
                        $inactiveCount = count($categories) - $activeCount;
                        $topLevel = count(array_filter($categories, fn($c) => empty($c['parent_id'])));
                        $subCategories = count($categories) - $topLevel;
                    ?>
                    <div class="categories-stat">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i>
                        <span>Active: <strong><?php echo $activeCount; ?></strong></span>
                    </div>
                    <?php if ($inactiveCount > 0): ?>
                    <div class="categories-stat">
                        <i class="fas fa-pause-circle" style="color: var(--text-muted);"></i>
                        <span>Inactive: <strong><?php echo $inactiveCount; ?></strong></span>
                    </div>
                    <?php endif; ?>
                    <div class="categories-stat">
                        <i class="fas fa-folder"></i>
                        <span>Parent: <strong><?php echo $topLevel; ?></strong></span>
                    </div>
                    <div class="categories-stat">
                        <i class="fas fa-folder-open"></i>
                        <span>Sub: <strong><?php echo $subCategories; ?></strong></span>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-body" style="padding: 14px 20px;">
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <div class="category-search">
                                <i class="fas fa-search"></i>
                                <input type="text" id="categorySearchInput" placeholder="Search categories by name..." oninput="filterCategories()">
                            </div>
                            <select id="statusFilterSelect" onchange="filterCategories()" style="padding: 9px 32px 9px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 13px; appearance: none; background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2712%27 height=%2712%27 fill=%27%239a9ab0%27 viewBox=%270 0 16 16%27%3E%3Cpath d=%27M8 11L3 6h10l-5 5z%27/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 10px center; cursor: pointer; min-width: 130px;">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <select id="parentFilterSelect" onchange="filterCategories()" style="padding: 9px 32px 9px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 13px; appearance: none; background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2712%27 height=%2712%27 fill=%27%239a9ab0%27 viewBox=%270 0 16 16%27%3E%3Cpath d=%27M8 11L3 6h10l-5 5z%27/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 10px center; cursor: pointer; min-width: 160px;">
                                <option value="">All Levels</option>
                                <option value="parent">Parent Only</option>
                                <option value="sub">Sub-Categories</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Category Grid -->
                <?php if (!empty($categories)): ?>
                    <div class="category-grid" id="categoryGrid">
                        <?php foreach ($categories as $cat): ?>
                            <?php
                                $hasImage   = !empty($cat['image']);
                                $hasIcon    = !empty($cat['icon']);
                                $imageSrc   = $hasImage ? '../' . $cat['image'] : '';
                                $isActive   = !empty($cat['status']);
                                $prodCount  = (int)$cat['product_count'];
                                $childCount = (int)$cat['child_count'];
                            ?>
                            <div class="category-card"
                                 data-name="<?php echo strtolower(clean($cat['name'])); ?>"
                                 data-status="<?php echo $isActive ? 'active' : 'inactive'; ?>"
                                 data-parent="<?php echo !empty($cat['parent_id']) ? 'sub' : 'parent'; ?>">
                                <!-- Image Area -->
                                <div class="category-card-image">
                                    <?php if ($hasImage): ?>
                                        <img src="<?php echo clean($imageSrc); ?>" alt="<?php echo clean($cat['name']); ?>" loading="lazy">
                                    <?php elseif ($hasIcon): ?>
                                        <i class="<?php echo clean($cat['icon']); ?> icon-placeholder"></i>
                                    <?php else: ?>
                                        <i class="fas fa-folder icon-placeholder"></i>
                                    <?php endif; ?>

                                    <?php if ($hasIcon && $hasImage): ?>
                                        <div class="category-icon-badge">
                                            <i class="<?php echo clean($cat['icon']); ?>"></i>
                                        </div>
                                    <?php endif; ?>

                                    <div class="category-card-status">
                                        <?php if ($isActive): ?>
                                            <span class="badge badge-success"><span class="badge-dot" style="background: var(--success);"></span> Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger"><span class="badge-dot" style="background: var(--danger);"></span> Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Body -->
                                <div class="category-card-body">
                                    <div class="category-card-name">
                                        <?php if ($hasIcon && !$hasImage): ?>
                                            <i class="<?php echo clean($cat['icon']); ?>" style="color: var(--gold-primary); font-size: 14px;"></i>
                                        <?php endif; ?>
                                        <?php echo clean($cat['name']); ?>
                                    </div>
                                    <div class="category-card-slug"><?php echo clean($cat['slug']); ?></div>

                                    <?php if (!empty($cat['description'])): ?>
                                        <div class="category-card-desc"><?php echo clean($cat['description']); ?></div>
                                    <?php else: ?>
                                        <div class="category-card-desc" style="color: var(--text-muted); font-style: italic;">No description</div>
                                    <?php endif; ?>

                                    <div class="category-card-meta">
                                        <?php if (!empty($cat['parent_name'])): ?>
                                            <span class="category-meta-item">
                                                <i class="fas fa-sitemap"></i>
                                                <?php echo clean($cat['parent_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="category-meta-item">
                                            <i class="fas fa-box"></i>
                                            <?php echo $prodCount; ?> product<?php echo $prodCount !== 1 ? 's' : ''; ?>
                                        </span>
                                        <?php if ($childCount > 0): ?>
                                            <span class="category-meta-item">
                                                <i class="fas fa-folder-open"></i>
                                                <?php echo $childCount; ?> sub
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($cat['sort_order'])): ?>
                                            <span class="category-meta-item">
                                                <i class="fas fa-sort-numeric-down"></i>
                                                Order: <?php echo (int)$cat['sort_order']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="category-card-actions">
                                        <button type="button" class="btn btn-sm btn-secondary" onclick='openEditModal(<?php echo json_encode([
                                            "id"          => (int)$cat["id"],
                                            "name"        => $cat["name"],
                                            "slug"        => $cat["slug"],
                                            "description" => $cat["description"] ?? "",
                                            "image"       => $cat["image"] ?? "",
                                            "icon"        => $cat["icon"] ?? "",
                                            "parent_id"   => $cat["parent_id"] ?? null,
                                            "sort_order"  => (int)$cat["sort_order"],
                                            "status"      => (int)$cat["status"],
                                        ], JSON_UNESCAPED_UNICODE); ?>)' data-tooltip="Edit Category">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo (int)$cat['id']; ?>, '<?php echo clean(addslashes($cat['name'])); ?>', <?php echo $prodCount; ?>, <?php echo $childCount; ?>)" data-tooltip="Delete Category">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- No results (hidden by default) -->
                    <div id="noResults" style="display: none;">
                        <div class="card">
                            <div class="card-body">
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <h3>No Categories Match</h3>
                                    <p>Try adjusting your search or filter criteria.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Empty State -->
                    <div class="card">
                        <div class="card-body">
                            <div class="empty-state">
                                <i class="fas fa-tags"></i>
                                <h3>No Categories Yet</h3>
                                <p>Start organizing your products by creating your first category.</p>
                                <button type="button" class="btn btn-primary" style="margin-top: 16px;" onclick="openAddModal()">
                                    <i class="fas fa-plus"></i> Add Your First Category
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </main>

</div><!-- /admin-layout -->

<!-- ========================================
     ADD / EDIT CATEGORY MODAL
     ======================================== -->
<div class="modal-overlay" id="categoryModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-plus-circle" style="color: var(--gold-primary); margin-right: 8px;"></i>Add Category</h3>
            <button class="modal-close" onclick="closeCategoryModal()">&times;</button>
        </div>
        <form method="POST" action="categories.php" id="categoryForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="category_id" id="formCategoryId" value="">

            <div class="modal-body">
                <div class="form-row">
                    <!-- Name -->
                    <div class="form-group">
                        <label class="form-label">Name <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="name" id="formName" class="form-control" placeholder="e.g., Sarees, Kurtas, Lehengas" required oninput="handleNameInput()">
                    </div>

                    <!-- Slug -->
                    <div class="form-group">
                        <label class="form-label">Slug</label>
                        <div class="slug-input-group">
                            <input type="text" name="slug" id="formSlug" class="form-control" placeholder="auto-generated-from-name" style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                            <button type="button" class="slug-toggle-btn" id="slugLockBtn" onclick="toggleSlugLock()" data-tooltip="Lock/Unlock auto-generation">
                                <i class="fas fa-lock"></i> Auto
                            </button>
                        </div>
                        <div class="form-hint">Leave empty to auto-generate from name. Click "Auto" to toggle manual editing.</div>
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="formDescription" class="form-control" rows="3" placeholder="Brief description of this category..."></textarea>
                </div>

                <div class="form-row">
                    <!-- Image Upload -->
                    <div class="form-group">
                        <label class="form-label">Category Image</label>
                        <div class="image-upload" id="imageUploadArea" onclick="document.getElementById('formImage').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload image</p>
                            <small>Recommended: 600x400px, JPG/PNG, max 5MB</small>
                            <input type="file" name="image" id="formImage" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this)">
                        </div>
                        <div id="imagePreviewContainer" style="display: none; margin-top: 8px;">
                            <img id="imagePreview" class="category-image-preview" src="" alt="Preview">
                            <label class="remove-image-check">
                                <input type="checkbox" name="remove_image" id="removeImageCheck" value="1">
                                <i class="fas fa-trash-alt"></i> Remove current image
                            </label>
                        </div>
                    </div>

                    <!-- Icon Class + Sort Order + Parent -->
                    <div style="display: flex; flex-direction: column; gap: 0;">
                        <div class="form-group">
                            <label class="form-label">Icon Class (FontAwesome)</label>
                            <div class="input-group">
                                <span class="input-prefix"><i class="fas fa-icons" style="font-size: 13px;"></i></span>
                                <input type="text" name="icon" id="formIcon" class="form-control" placeholder="e.g., fas fa-saree, fas fa-gem" oninput="previewIcon()">
                            </div>
                            <div class="form-hint">
                                Preview: <span id="iconPreview" style="color: var(--gold-primary);"><i class="fas fa-folder"></i></span>
                                &mdash; <a href="https://fontawesome.com/icons" target="_blank" style="color: var(--gold-primary);">Browse icons</a>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Parent Category</label>
                            <select name="parent_id" id="formParentId" class="form-control">
                                <option value="">None (Top-level)</option>
                                <?php echo buildCategoryOptions($allCategoriesFlat); ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Sort Order</label>
                                <input type="number" name="sort_order" id="formSortOrder" class="form-control" value="0" min="0" placeholder="0">
                                <div class="form-hint">Lower numbers appear first</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <div style="display: flex; align-items: center; gap: 12px; padding-top: 6px;">
                                    <label class="switch">
                                        <input type="checkbox" name="status" id="formStatus" value="1" checked>
                                        <span class="slider"></span>
                                    </label>
                                    <span id="statusLabel" style="font-size: 13px; color: var(--success); font-weight: 600;">Active</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="formSubmitBtn">
                    <i class="fas fa-save"></i> <span id="formSubmitText">Create Category</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================
     DELETE CONFIRMATION MODAL
     ======================================== -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal" style="max-width: 440px;">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger); margin-right: 8px;"></i>Confirm Delete</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 12px;">Are you sure you want to delete this category?</p>
            <div style="background: var(--danger-bg); border: 1px solid rgba(231,76,60,0.2); border-radius: var(--radius-sm); padding: 12px 16px;">
                <p style="font-weight: 600; color: var(--danger); margin-bottom: 4px;" id="deleteCategoryName"></p>
                <p style="font-size: 12px; color: var(--text-muted);" id="deleteCategoryWarning">This action cannot be undone. The category will be permanently removed.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <form method="POST" action="categories.php" id="deleteForm" style="display: inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="category_id" id="deleteCategoryId" value="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Delete Category
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================
// SLUG AUTO-GENERATION
// ============================================
var slugLocked = true; // auto-generate by default

function slugify(text) {
    return text.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function handleNameInput() {
    if (slugLocked) {
        var nameVal = document.getElementById('formName').value;
        document.getElementById('formSlug').value = slugify(nameVal);
    }
}

function toggleSlugLock() {
    slugLocked = !slugLocked;
    var btn = document.getElementById('slugLockBtn');
    if (slugLocked) {
        btn.classList.remove('active');
        btn.innerHTML = '<i class="fas fa-lock"></i> Auto';
        document.getElementById('formSlug').readOnly = false;
        // Re-slug from name
        handleNameInput();
    } else {
        btn.classList.add('active');
        btn.innerHTML = '<i class="fas fa-unlock"></i> Manual';
        document.getElementById('formSlug').readOnly = false;
    }
}

// Initialize slug field as not read-only but auto-populated
document.getElementById('formSlug').readOnly = false;

// ============================================
// STATUS TOGGLE LABEL
// ============================================
document.getElementById('formStatus').addEventListener('change', function() {
    var label = document.getElementById('statusLabel');
    if (this.checked) {
        label.textContent = 'Active';
        label.style.color = 'var(--success)';
    } else {
        label.textContent = 'Inactive';
        label.style.color = 'var(--text-muted)';
    }
});

// ============================================
// ICON PREVIEW
// ============================================
function previewIcon() {
    var iconClass = document.getElementById('formIcon').value.trim();
    var previewEl = document.getElementById('iconPreview');
    if (iconClass) {
        previewEl.innerHTML = '<i class="' + iconClass.replace(/"/g, '') + '"></i>';
    } else {
        previewEl.innerHTML = '<i class="fas fa-folder"></i>';
    }
}

// ============================================
// IMAGE PREVIEW
// ============================================
function previewImage(input) {
    var container = document.getElementById('imagePreviewContainer');
    var preview = document.getElementById('imagePreview');
    var removeCheck = document.getElementById('removeImageCheck');

    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            container.style.display = 'block';
            removeCheck.checked = false;
            removeCheck.parentElement.style.display = 'none'; // Hide remove for new upload
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        container.style.display = 'none';
    }
}

function showExistingImage(imagePath) {
    var container = document.getElementById('imagePreviewContainer');
    var preview = document.getElementById('imagePreview');
    var removeCheck = document.getElementById('removeImageCheck');

    if (imagePath) {
        preview.src = '../' + imagePath;
        container.style.display = 'block';
        removeCheck.checked = false;
        removeCheck.parentElement.style.display = 'flex'; // Show remove option
    } else {
        container.style.display = 'none';
    }
}

// ============================================
// ADD MODAL
// ============================================
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle" style="color: var(--gold-primary); margin-right: 8px;"></i>Add Category';
    document.getElementById('formAction').value = 'create';
    document.getElementById('formCategoryId').value = '';
    document.getElementById('formSubmitText').textContent = 'Create Category';

    // Reset form
    document.getElementById('categoryForm').reset();
    document.getElementById('formSlug').value = '';
    document.getElementById('formSortOrder').value = '0';
    document.getElementById('formStatus').checked = true;
    document.getElementById('statusLabel').textContent = 'Active';
    document.getElementById('statusLabel').style.color = 'var(--success)';
    document.getElementById('imagePreviewContainer').style.display = 'none';
    document.getElementById('removeImageCheck').checked = false;
    document.getElementById('removeImageCheck').parentElement.style.display = 'none';
    document.getElementById('iconPreview').innerHTML = '<i class="fas fa-folder"></i>';

    // Reset slug lock
    slugLocked = true;
    document.getElementById('slugLockBtn').classList.remove('active');
    document.getElementById('slugLockBtn').innerHTML = '<i class="fas fa-lock"></i> Auto';

    // Reset parent dropdown (remove any dynamic exclude)
    var parentSelect = document.getElementById('formParentId');
    for (var i = 0; i < parentSelect.options.length; i++) {
        parentSelect.options[i].disabled = false;
    }

    document.getElementById('categoryModal').classList.add('show');
}

// ============================================
// EDIT MODAL
// ============================================
function openEditModal(cat) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit" style="color: var(--gold-primary); margin-right: 8px;"></i>Edit Category';
    document.getElementById('formAction').value = 'update';
    document.getElementById('formCategoryId').value = cat.id;
    document.getElementById('formSubmitText').textContent = 'Update Category';

    // Populate fields
    document.getElementById('formName').value = cat.name;
    document.getElementById('formSlug').value = cat.slug;
    document.getElementById('formDescription').value = cat.description || '';
    document.getElementById('formIcon').value = cat.icon || '';
    document.getElementById('formSortOrder').value = cat.sort_order || 0;
    document.getElementById('formStatus').checked = cat.status == 1;
    document.getElementById('formParentId').value = cat.parent_id || '';

    // Status label
    var label = document.getElementById('statusLabel');
    if (cat.status == 1) {
        label.textContent = 'Active';
        label.style.color = 'var(--success)';
    } else {
        label.textContent = 'Inactive';
        label.style.color = 'var(--text-muted)';
    }

    // Icon preview
    previewIcon();

    // Existing image
    showExistingImage(cat.image || '');

    // Unlock slug for editing
    slugLocked = false;
    document.getElementById('slugLockBtn').classList.add('active');
    document.getElementById('slugLockBtn').innerHTML = '<i class="fas fa-unlock"></i> Manual';

    // Disable self in parent dropdown
    var parentSelect = document.getElementById('formParentId');
    for (var i = 0; i < parentSelect.options.length; i++) {
        if (parentSelect.options[i].value == cat.id) {
            parentSelect.options[i].disabled = true;
        } else {
            parentSelect.options[i].disabled = false;
        }
    }

    document.getElementById('categoryModal').classList.add('show');
}

// ============================================
// CLOSE CATEGORY MODAL
// ============================================
function closeCategoryModal() {
    document.getElementById('categoryModal').classList.remove('show');
}

// ============================================
// DELETE MODAL
// ============================================
function confirmDelete(categoryId, categoryName, productCount, childCount) {
    document.getElementById('deleteCategoryId').value = categoryId;
    document.getElementById('deleteCategoryName').textContent = categoryName;

    var warning = document.getElementById('deleteCategoryWarning');
    if (productCount > 0 || childCount > 0) {
        var parts = [];
        if (productCount > 0) parts.push(productCount + ' product(s)');
        if (childCount > 0) parts.push(childCount + ' sub-categor(ies)');
        warning.textContent = 'This category has ' + parts.join(' and ') + ' associated. Please reassign them before deleting.';
        // Disable delete button
        document.getElementById('deleteForm').querySelector('button[type="submit"]').disabled = true;
        document.getElementById('deleteForm').querySelector('button[type="submit"]').style.opacity = '0.5';
    } else {
        warning.textContent = 'This action cannot be undone. The category will be permanently removed.';
        document.getElementById('deleteForm').querySelector('button[type="submit"]').disabled = false;
        document.getElementById('deleteForm').querySelector('button[type="submit"]').style.opacity = '1';
    }

    document.getElementById('deleteModal').classList.add('show');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.getElementById('deleteCategoryId').value = '';
}

// ============================================
// CLOSE MODALS ON OVERLAY CLICK / ESCAPE
// ============================================
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCategoryModal();
        closeDeleteModal();
    }
});

// ============================================
// FILTER / SEARCH CATEGORIES
// ============================================
function filterCategories() {
    var searchTerm = document.getElementById('categorySearchInput').value.toLowerCase().trim();
    var statusFilter = document.getElementById('statusFilterSelect').value;
    var parentFilter = document.getElementById('parentFilterSelect').value;

    var cards = document.querySelectorAll('.category-card');
    var visibleCount = 0;

    cards.forEach(function(card) {
        var name = card.getAttribute('data-name');
        var status = card.getAttribute('data-status');
        var parent = card.getAttribute('data-parent');

        var matchesSearch = !searchTerm || name.indexOf(searchTerm) !== -1;
        var matchesStatus = !statusFilter || status === statusFilter;
        var matchesParent = !parentFilter || parent === parentFilter;

        if (matchesSearch && matchesStatus && matchesParent) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Show/hide no results
    var noResults = document.getElementById('noResults');
    if (noResults) {
        noResults.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}
</script>

</body>
</html>
