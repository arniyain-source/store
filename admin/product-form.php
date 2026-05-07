<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Product Add/Edit Form - DesiVastra Admin
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

// ============================================
// INITIALIZE VARIABLES
// ============================================
$editId = (int)($_GET['id'] ?? 0);
$isEdit = $editId > 0;
$product = null;
$errors = [];

// Default values for new product
$defaults = [
    'name' => '', 'slug' => '', 'sku' => '', 'category_id' => '',
    'short_description' => '', 'description' => '', 'price' => '',
    'old_price' => '', 'cost_price' => '', 'stock' => '0',
    'low_stock_threshold' => '5', 'main_image' => '',
    'images' => [], 'sizes' => [], 'colors' => [], 'finishes' => [],
    'features' => [], 'tags' => [], 'is_active' => 1,
    'is_featured' => 0, 'is_new_arrival' => 0,
    'is_top_selling' => 0, 'is_boutique_only' => 0,
    'delivery_min_days' => '3', 'delivery_max_days' => '7',
    'meta_title' => '', 'meta_description' => ''
];

// ============================================
// LOAD EXISTING PRODUCT DATA
// ============================================
if ($isEdit) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$editId]);
        $product = $stmt->fetch();

        if (!$product) {
            setFlash('error', 'Product not found.');
            redirect('products.php');
        }

        // Decode JSON fields
        $product['images']    = json_decode($product['images'] ?? '[]', true) ?: [];
        $product['sizes']     = json_decode($product['sizes'] ?? '[]', true) ?: [];
        $product['colors']    = json_decode($product['colors'] ?? '[]', true) ?: [];
        $product['finishes']  = json_decode($product['finishes'] ?? '[]', true) ?: [];
        $product['features']  = json_decode($product['features'] ?? '[]', true) ?: [];
        $product['tags']      = json_decode($product['tags'] ?? '[]', true) ?: [];

        // Merge with defaults so all keys exist
        $defaults = array_merge($defaults, $product);
    } catch (Exception $e) {
        setFlash('error', 'Failed to load product: ' . $e->getMessage());
        redirect('products.php');
    }
}

// Assign form data (on POST error, re-use submitted data)
$formData = $defaults;

// ============================================
// HANDLE FORM SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($csrfToken)) {
        setFlash('error', 'Invalid request. Please try again.');
        redirect($isEdit ? 'product-form.php?id=' . $editId : 'product-form.php');
    }

    // Collect and sanitize basic fields
    $name               = trim($_POST['name'] ?? '');
    $sku                = trim($_POST['sku'] ?? '');
    $categoryId         = (int)($_POST['category_id'] ?? 0) ?: null;
    $shortDescription   = trim($_POST['short_description'] ?? '');
    $description        = trim($_POST['description'] ?? '');
    $price              = trim($_POST['price'] ?? '');
    $oldPrice           = trim($_POST['old_price'] ?? '') ?: null;
    $costPrice          = trim($_POST['cost_price'] ?? '') ?: null;
    $stock              = (int)($_POST['stock'] ?? 0);
    $lowStockThreshold  = (int)($_POST['low_stock_threshold'] ?? 5);
    $deliveryMinDays    = (int)($_POST['delivery_min_days'] ?? 3);
    $deliveryMaxDays    = (int)($_POST['delivery_max_days'] ?? 7);
    $metaTitle          = trim($_POST['meta_title'] ?? '');
    $metaDescription    = trim($_POST['meta_description'] ?? '');
    $isActive           = isset($_POST['is_active']) ? 1 : 0;
    $isFeatured         = isset($_POST['is_featured']) ? 1 : 0;
    $isNewArrival       = isset($_POST['is_new_arrival']) ? 1 : 0;
    $isTopSelling       = isset($_POST['is_top_selling']) ? 1 : 0;
    $isBoutiqueOnly     = isset($_POST['is_boutique_only']) ? 1 : 0;

    // JSON fields from hidden inputs
    $sizesRaw     = $_POST['sizes_json'] ?? '[]';
    $colorsRaw    = $_POST['colors_json'] ?? '[]';
    $finishesRaw  = $_POST['finishes_json'] ?? '[]';
    $tagsRaw      = $_POST['tags_json'] ?? '[]';

    $sizes    = json_decode($sizesRaw, true) ?: [];
    $colors   = json_decode($colorsRaw, true) ?: [];
    $finishes = json_decode($finishesRaw, true) ?: [];
    $tags     = json_decode($tagsRaw, true) ?: [];

    // Keep existing images (not removed)
    $existingImages = json_decode($_POST['existing_images'] ?? '[]', true) ?: [];

    // ============================================
    // VALIDATION
    // ============================================
    if (empty($name)) {
        $errors[] = 'Product name is required.';
    }
    if ($price === '' || !is_numeric($price) || (float)$price < 0) {
        $errors[] = 'A valid price is required.';
    }
    if (empty($categoryId)) {
        $errors[] = 'Please select a category.';
    }

    // Check SKU uniqueness
    if (!empty($sku)) {
        $db = getDB();
        $sql = "SELECT id FROM products WHERE sku = ?";
        $params = [$sku];
        if ($isEdit) {
            $sql .= " AND id != ?";
            $params[] = $editId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = 'SKU already exists. Please use a different one.';
        }
    }

    // ============================================
    // PROCESS IF NO ERRORS
    // ============================================
    if (empty($errors)) {
        try {
            $db = getDB();

            // Generate slug
            $slug = generateUniqueSlug($name, 'products', $isEdit ? $editId : null);

            // Handle main image upload
            $mainImage = $defaults['main_image'] ?? '';
            if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['main_image'], 'products');
                if ($upload['success']) {
                    // Delete old main image if replacing
                    if ($isEdit && !empty($mainImage)) {
                        deleteUploadedFile($mainImage);
                    }
                    $mainImage = $upload['path'];
                } else {
                    $errors[] = 'Main image: ' . $upload['message'];
                }
            }
            // Handle main image removal
            if (isset($_POST['remove_main_image']) && $_POST['remove_main_image'] === '1') {
                if (!empty($mainImage)) {
                    deleteUploadedFile($mainImage);
                }
                $mainImage = '';
            }

            // Handle additional image uploads
            $newImages = [];
            if (isset($_FILES['additional_images'])) {
                $files = $_FILES['additional_images'];
                $fileCount = is_array($files['name']) ? count($files['name']) : 0;
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name'     => $files['name'][$i],
                            'type'     => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error'    => $files['error'][$i],
                            'size'     => $files['size'][$i]
                        ];
                        $upload = uploadFile($file, 'products');
                        if ($upload['success']) {
                            $newImages[] = $upload['path'];
                        }
                    }
                }
            }

            // Delete removed additional images from disk
            if ($isEdit && !empty($defaults['images'])) {
                $oldImages = is_array($defaults['images']) ? $defaults['images'] : (json_decode($defaults['images'], true) ?: []);
                foreach ($oldImages as $oldImg) {
                    if (!in_array($oldImg, $existingImages)) {
                        deleteUploadedFile($oldImg);
                    }
                }
            }

            // Merge existing + new additional images
            $allImages = array_merge($existingImages, $newImages);

            // Build features array from description (simple: just use as-is for now)
            $features = [];

            // Prepare numeric values
            $priceVal     = round((float)$price, 2);
            $oldPriceVal  = $oldPrice !== null ? round((float)$oldPrice, 2) : null;
            $costPriceVal = $costPrice !== null ? round((float)$costPrice, 2) : null;

            if ($isEdit) {
                // ============================================
                // UPDATE EXISTING PRODUCT
                // ============================================
                $stmt = $db->prepare("
                    UPDATE products SET
                        name = ?, slug = ?, sku = ?, category_id = ?,
                        short_description = ?, description = ?,
                        price = ?, old_price = ?, cost_price = ?,
                        stock = ?, low_stock_threshold = ?,
                        main_image = ?, images = ?,
                        sizes = ?, colors = ?, finishes = ?, features = ?, tags = ?,
                        is_active = ?, is_featured = ?, is_new_arrival = ?,
                        is_top_selling = ?, is_boutique_only = ?,
                        delivery_min_days = ?, delivery_max_days = ?,
                        meta_title = ?, meta_description = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $slug, $sku ?: null, $categoryId ?: null,
                    $shortDescription ?: null, $description ?: null,
                    $priceVal, $oldPriceVal, $costPriceVal,
                    $stock, $lowStockThreshold,
                    $mainImage ?: null,
                    json_encode($allImages),
                    json_encode($sizes),
                    json_encode($colors),
                    json_encode($finishes),
                    json_encode($features),
                    json_encode($tags),
                    $isActive, $isFeatured, $isNewArrival,
                    $isTopSelling, $isBoutiqueOnly,
                    $deliveryMinDays, $deliveryMaxDays,
                    $metaTitle ?: null, $metaDescription ?: null,
                    $editId
                ]);

                logActivity('update_product', 'product', $editId, ['name' => $name]);
                setFlash('success', 'Product "' . $name . '" updated successfully.');
            } else {
                // ============================================
                // INSERT NEW PRODUCT
                // ============================================
                $stmt = $db->prepare("
                    INSERT INTO products (
                        name, slug, sku, category_id,
                        short_description, description,
                        price, old_price, cost_price,
                        stock, low_stock_threshold,
                        main_image, images,
                        sizes, colors, finishes, features, tags,
                        is_active, is_featured, is_new_arrival,
                        is_top_selling, is_boutique_only,
                        delivery_min_days, delivery_max_days,
                        meta_title, meta_description,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        ?, ?,
                        ?, ?,
                        NOW(), NOW()
                    )
                ");
                $stmt->execute([
                    $name, $slug, $sku ?: null, $categoryId ?: null,
                    $shortDescription ?: null, $description ?: null,
                    $priceVal, $oldPriceVal, $costPriceVal,
                    $stock, $lowStockThreshold,
                    $mainImage ?: null,
                    json_encode($allImages),
                    json_encode($sizes),
                    json_encode($colors),
                    json_encode($finishes),
                    json_encode($features),
                    json_encode($tags),
                    $isActive, $isFeatured, $isNewArrival,
                    $isTopSelling, $isBoutiqueOnly,
                    $deliveryMinDays, $deliveryMaxDays,
                    $metaTitle ?: null, $metaDescription ?: null
                ]);

                $editId = (int)$db->lastInsertId();
                logActivity('create_product', 'product', $editId, ['name' => $name]);
                setFlash('success', 'Product "' . $name . '" created successfully.');
            }

            // Redirect based on save action
            $saveAction = $_POST['save_action'] ?? 'close';
            if ($saveAction === 'continue') {
                redirect('product-form.php?id=' . $editId);
            } else {
                redirect('products.php');
            }

        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    // On error, repopulate form with submitted data
    $formData = [
        'name'               => $name,
        'sku'                => $sku,
        'category_id'        => $categoryId,
        'short_description'  => $shortDescription,
        'description'        => $description,
        'price'              => $price,
        'old_price'          => $oldPrice,
        'cost_price'         => $costPrice,
        'stock'              => $stock,
        'low_stock_threshold'=> $lowStockThreshold,
        'main_image'         => $defaults['main_image'] ?? '',
        'images'             => $existingImages,
        'sizes'              => $sizes,
        'colors'             => $colors,
        'finishes'           => $finishes,
        'features'           => [],
        'tags'               => $tags,
        'is_active'          => $isActive,
        'is_featured'        => $isFeatured,
        'is_new_arrival'     => $isNewArrival,
        'is_top_selling'     => $isTopSelling,
        'is_boutique_only'   => $isBoutiqueOnly,
        'delivery_min_days'  => $deliveryMinDays,
        'delivery_max_days'  => $deliveryMaxDays,
        'meta_title'         => $metaTitle,
        'meta_description'   => $metaDescription,
    ];
}

// ============================================
// FETCH CATEGORIES FOR DROPDOWN
// ============================================
$categories = [];
try {
    $db = getDB();
    $stmt = $db->query("SELECT id, name, parent_id FROM categories WHERE status = 1 ORDER BY sort_order ASC, name ASC");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    // Categories table might not exist yet
}

// ============================================
// GET FLASH MESSAGE
// ============================================
$flash = getFlash();

// Shorthand for form values
function fv($key, $data) {
    return clean((string)($data[$key] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit Product' : 'Add Product'; ?> - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        /* Header offset for fixed sidebar */
        .top-header { margin-left: var(--sidebar-width); }
        @media (max-width: 768px) {
            .top-header { margin-left: 0; }
        }

        /* Product form two-column layout */
        .product-form-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .product-form-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Tab panels */
        .tab-panel {
            display: none;
        }
        .tab-panel.active {
            display: block;
        }

        /* Description toolbar */
        .desc-toolbar {
            display: flex;
            gap: 2px;
            padding: 8px 10px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-bottom: none;
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
            flex-wrap: wrap;
        }
        .desc-toolbar button {
            width: 32px;
            height: 32px;
            background: none;
            border: 1px solid transparent;
            border-radius: 4px;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: var(--transition);
        }
        .desc-toolbar button:hover {
            background: var(--bg-card);
            color: var(--gold-primary);
            border-color: var(--border-color);
        }
        .desc-toolbar .separator {
            width: 1px;
            background: var(--border-color);
            margin: 4px 6px;
        }
        .desc-toolbar + textarea.form-control {
            border-radius: 0 0 var(--radius-sm) var(--radius-sm);
        }

        /* Color/Finish entry rows */
        .variant-row {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
        }
        .variant-row .form-control {
            flex: 1;
        }
        .variant-row input[type="color"] {
            width: 42px;
            height: 42px;
            padding: 3px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            cursor: pointer;
        }
        .variant-row .btn-remove-variant {
            width: 36px;
            height: 36px;
            flex-shrink: 0;
            background: var(--danger-bg);
            border: 1px solid rgba(231,76,60,0.2);
            color: var(--danger);
            border-radius: var(--radius-sm);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        .variant-row .btn-remove-variant:hover {
            background: var(--danger);
            color: #fff;
        }

        /* Variant list items (colors/finishes) */
        .variant-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }
        .variant-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 6px 10px;
            font-size: 12px;
            color: var(--text-primary);
        }
        .variant-chip .color-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        .variant-chip .remove-chip {
            cursor: pointer;
            color: var(--text-muted);
            font-size: 14px;
            margin-left: 2px;
            transition: var(--transition);
        }
        .variant-chip .remove-chip:hover {
            color: var(--danger);
        }

        /* Sidebar sticky */
        .product-sidebar {
            position: sticky;
            top: calc(var(--header-height) + 24px);
        }

        /* Summary info */
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-row .label { color: var(--text-secondary); }
        .summary-row .value { color: var(--text-primary); font-weight: 600; }

        /* Main image preview */
        .main-image-preview {
            width: 100%;
            max-width: 200px;
            aspect-ratio: 1;
            border-radius: var(--radius-sm);
            object-fit: cover;
            border: 1px solid var(--border-color);
            margin-bottom: 10px;
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
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <a href="products.php">Products</a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span><?php echo $isEdit ? 'Edit Product' : 'Add Product'; ?></span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>
                        <i class="fas fa-<?php echo $isEdit ? 'edit' : 'plus-circle'; ?>" style="color: var(--gold-primary); margin-right: 8px;"></i>
                        <?php echo $isEdit ? 'Edit Product' : 'Add New Product'; ?>
                    </h1>
                    <p class="subtitle">
                        <?php echo $isEdit ? 'Update product details for "' . clean($formData['name']) . '"' : 'Fill in the details to create a new product'; ?>
                    </p>
                </div>
                <a href="products.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Products
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

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="flash-message flash-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Please fix the following errors:</strong>
                        <ul style="margin: 4px 0 0 16px; font-size: 12px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo clean($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Product Form -->
            <form method="POST" action="" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                <input type="hidden" name="sizes_json" id="sizesJson" value='<?php echo json_encode($formData['sizes'] ?? []); ?>'>
                <input type="hidden" name="colors_json" id="colorsJson" value='<?php echo json_encode($formData['colors'] ?? []); ?>'>
                <input type="hidden" name="finishes_json" id="finishesJson" value='<?php echo json_encode($formData['finishes'] ?? []); ?>'>
                <input type="hidden" name="tags_json" id="tagsJson" value='<?php echo json_encode($formData['tags'] ?? []); ?>'>
                <input type="hidden" name="existing_images" id="existingImages" value='<?php echo json_encode($formData['images'] ?? []); ?>'>
                <input type="hidden" name="remove_main_image" id="removeMainImage" value="0">

                <div class="product-form-layout">

                    <!-- ============================================ -->
                    <!-- LEFT: Main Form Area                         -->
                    <!-- ============================================ -->
                    <div class="product-form-main">

                        <!-- Tabs Navigation -->
                        <div class="card" style="margin-bottom: 0; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
                            <div class="tabs" id="formTabs" style="margin-bottom: 0; border-bottom: none;">
                                <button type="button" class="tab-btn active" data-tab="general">
                                    <i class="fas fa-info-circle" style="margin-right: 4px;"></i> General
                                </button>
                                <button type="button" class="tab-btn" data-tab="pricing">
                                    <i class="fas fa-tags" style="margin-right: 4px;"></i> Pricing & Inventory
                                </button>
                                <button type="button" class="tab-btn" data-tab="media">
                                    <i class="fas fa-images" style="margin-right: 4px;"></i> Media
                                </button>
                                <button type="button" class="tab-btn" data-tab="variants">
                                    <i class="fas fa-palette" style="margin-right: 4px;"></i> Variants
                                </button>
                                <button type="button" class="tab-btn" data-tab="seo">
                                    <i class="fas fa-search" style="margin-right: 4px;"></i> SEO
                                </button>
                            </div>
                        </div>

                        <!-- Tab Panels Container -->
                        <div class="card" style="border-top-left-radius: 0; border-top-right-radius: 0;">
                            <div class="card-body">

                                <!-- ======================================== -->
                                <!-- TAB: General                             -->
                                <!-- ======================================== -->
                                <div class="tab-panel active" id="tab-general">
                                    <div class="form-group">
                                        <label class="form-label">Product Name <span style="color:var(--danger);">*</span></label>
                                        <input type="text" name="name" class="form-control" placeholder="Enter product name" value="<?php echo fv('name', $formData); ?>" required>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">SKU</label>
                                            <input type="text" name="sku" class="form-control" placeholder="e.g., DV-WATCH-001" value="<?php echo fv('sku', $formData); ?>">
                                            <div class="form-hint">Unique stock keeping unit identifier</div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Category <span style="color:var(--danger);">*</span></label>
                                            <select name="category_id" class="form-control" required>
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($formData['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                                        <?php echo clean($cat['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Short Description</label>
                                        <input type="text" name="short_description" class="form-control" placeholder="Brief product description (max 500 chars)" maxlength="500" value="<?php echo fv('short_description', $formData); ?>">
                                        <div class="form-hint">Appears in product cards and listing pages</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Full Description</label>
                                        <div class="desc-toolbar">
                                            <button type="button" onclick="insertTag('B')" title="Bold"><i class="fas fa-bold"></i></button>
                                            <button type="button" onclick="insertTag('I')" title="Italic"><i class="fas fa-italic"></i></button>
                                            <button type="button" onclick="insertTag('U')" title="Underline"><i class="fas fa-underline"></i></button>
                                            <div class="separator"></div>
                                            <button type="button" onclick="insertTag('UL')" title="Bullet List"><i class="fas fa-list-ul"></i></button>
                                            <button type="button" onclick="insertTag('OL')" title="Numbered List"><i class="fas fa-list-ol"></i></button>
                                            <div class="separator"></div>
                                            <button type="button" onclick="insertTag('H2')" title="Heading"><i class="fas fa-heading"></i></button>
                                            <button type="button" onclick="insertTag('P')" title="Paragraph"><i class="fas fa-paragraph"></i></button>
                                            <div class="separator"></div>
                                            <button type="button" onclick="insertTag('LINK')" title="Insert Link"><i class="fas fa-link"></i></button>
                                        </div>
                                        <textarea name="description" class="form-control" rows="8" placeholder="Write a detailed product description..."><?php echo fv('description', $formData); ?></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Status</label>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <label class="switch">
                                                <input type="checkbox" name="is_active" value="1" <?php echo !empty($formData['is_active']) ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                            <span style="font-size: 13px; color: var(--text-secondary);" id="statusLabel">
                                                <?php echo !empty($formData['is_active']) ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Product Flags</label>
                                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                            <div class="form-check">
                                                <input type="checkbox" name="is_featured" id="is_featured" value="1" <?php echo !empty($formData['is_featured']) ? 'checked' : ''; ?>>
                                                <label for="is_featured"><i class="fas fa-star" style="color:var(--warning);margin-right:4px;font-size:11px;"></i> Featured</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="is_new_arrival" id="is_new_arrival" value="1" <?php echo !empty($formData['is_new_arrival']) ? 'checked' : ''; ?>>
                                                <label for="is_new_arrival"><i class="fas fa-bolt" style="color:var(--purple);margin-right:4px;font-size:11px;"></i> New Arrival</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="is_top_selling" id="is_top_selling" value="1" <?php echo !empty($formData['is_top_selling']) ? 'checked' : ''; ?>>
                                                <label for="is_top_selling"><i class="fas fa-fire" style="color:var(--danger);margin-right:4px;font-size:11px;"></i> Top Selling</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="is_boutique_only" id="is_boutique_only" value="1" <?php echo !empty($formData['is_boutique_only']) ? 'checked' : ''; ?>>
                                                <label for="is_boutique_only"><i class="fas fa-gem" style="color:var(--info);margin-right:4px;font-size:11px;"></i> Boutique Only</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ======================================== -->
                                <!-- TAB: Pricing & Inventory                 -->
                                <!-- ======================================== -->
                                <div class="tab-panel" id="tab-pricing">
                                    <div class="form-row-3">
                                        <div class="form-group">
                                            <label class="form-label">Selling Price <span style="color:var(--danger);">*</span></label>
                                            <div class="input-group">
                                                <span class="input-prefix">&#8377;</span>
                                                <input type="number" name="price" class="form-control" placeholder="0.00" step="0.01" min="0" value="<?php echo fv('price', $formData); ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Old Price (MRP)</label>
                                            <div class="input-group">
                                                <span class="input-prefix">&#8377;</span>
                                                <input type="number" name="old_price" class="form-control" placeholder="0.00" step="0.01" min="0" value="<?php echo fv('old_price', $formData); ?>">
                                            </div>
                                            <div class="form-hint">Original price before discount</div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Cost Price</label>
                                            <div class="input-group">
                                                <span class="input-prefix">&#8377;</span>
                                                <input type="number" name="cost_price" class="form-control" placeholder="0.00" step="0.01" min="0" value="<?php echo fv('cost_price', $formData); ?>">
                                            </div>
                                            <div class="form-hint">Your purchase cost (hidden from customers)</div>
                                        </div>
                                    </div>

                                    <div class="form-row-3">
                                        <div class="form-group">
                                            <label class="form-label">Stock Quantity</label>
                                            <input type="number" name="stock" class="form-control" placeholder="0" min="0" value="<?php echo fv('stock', $formData); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Low Stock Threshold</label>
                                            <input type="number" name="low_stock_threshold" class="form-control" placeholder="5" min="0" value="<?php echo fv('low_stock_threshold', $formData); ?>">
                                            <div class="form-hint">Alert when stock falls below this</div>
                                        </div>
                                        <div class="form-group">
                                            <!-- spacer -->
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label">Delivery Min Days</label>
                                            <input type="number" name="delivery_min_days" class="form-control" placeholder="3" min="0" value="<?php echo fv('delivery_min_days', $formData); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Delivery Max Days</label>
                                            <input type="number" name="delivery_max_days" class="form-control" placeholder="7" min="0" value="<?php echo fv('delivery_max_days', $formData); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- ======================================== -->
                                <!-- TAB: Media                               -->
                                <!-- ======================================== -->
                                <div class="tab-panel" id="tab-media">
                                    <!-- Main Image -->
                                    <div class="form-group">
                                        <label class="form-label">Main Product Image</label>
                                        <?php if (!empty($formData['main_image'])): ?>
                                            <div style="margin-bottom: 12px;">
                                                <img src="<?php echo '../' . clean($formData['main_image']); ?>" alt="Main image" class="main-image-preview">
                                                <div style="display: flex; gap: 8px; margin-top: 8px;">
                                                    <label class="image-upload" style="padding: 12px 20px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-camera" style="font-size: 14px; margin-bottom: 0;"></i>
                                                        <span style="font-size: 12px;">Replace Image</span>
                                                        <input type="file" name="main_image" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewMainImage(event)">
                                                    </label>
                                                    <button type="button" class="btn btn-sm" style="background:var(--danger-bg);color:var(--danger);border:1px solid rgba(231,76,60,0.2);" onclick="removeMainImage()">
                                                        <i class="fas fa-trash-alt"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="image-upload" onclick="document.getElementById('mainImageInput').click()">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                                <p>Click to upload main image</p>
                                                <small>JPG, PNG, GIF or WebP (Max 5MB)</small>
                                                <input type="file" id="mainImageInput" name="main_image" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewMainImage(event)">
                                            </div>
                                        <?php endif; ?>
                                        <div id="mainImagePreview" style="margin-top: 10px;"></div>
                                    </div>

                                    <!-- Additional Images -->
                                    <div class="form-group">
                                        <label class="form-label">Additional Images</label>

                                        <!-- Existing additional images -->
                                        <div id="existingImagesGrid" class="image-preview-grid">
                                            <?php if (!empty($formData['images']) && is_array($formData['images'])): ?>
                                                <?php foreach ($formData['images'] as $idx => $img): ?>
                                                    <div class="image-preview-item" data-image-path="<?php echo clean($img); ?>">
                                                        <img src="<?php echo '../' . clean($img); ?>" alt="Additional image">
                                                        <button type="button" class="remove-img" onclick="removeExistingImage(this)" title="Remove image">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Upload new additional images -->
                                        <div class="image-upload" onclick="document.getElementById('additionalImagesInput').click()" style="margin-top: 12px;">
                                            <i class="fas fa-images"></i>
                                            <p>Click to upload additional images</p>
                                            <small>Select multiple images at once (JPG, PNG, GIF, WebP)</small>
                                            <input type="file" id="additionalImagesInput" name="additional_images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple onchange="previewAdditionalImages(event)">
                                        </div>
                                        <div id="newImagesPreview" class="image-preview-grid"></div>
                                    </div>
                                </div>

                                <!-- ======================================== -->
                                <!-- TAB: Variants                            -->
                                <!-- ======================================== -->
                                <div class="tab-panel" id="tab-variants">

                                    <!-- Sizes -->
                                    <div class="form-group">
                                        <label class="form-label">Available Sizes</label>
                                        <div class="tags-input" id="sizesTagsInput" onclick="document.getElementById('sizeInput').focus()">
                                            <?php if (!empty($formData['sizes']) && is_array($formData['sizes'])): ?>
                                                <?php foreach ($formData['sizes'] as $size): ?>
                                                    <span class="tag">
                                                        <?php echo clean(is_array($size) ? ($size['name'] ?? $size[0] ?? '') : $size); ?>
                                                        <span class="remove-tag" onclick="removeTag('sizes', this)">&times;</span>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <input type="text" id="sizeInput" placeholder="Type size and press Enter" onkeydown="handleTagInput(event, 'sizes')">
                                        </div>
                                        <div class="form-hint">Press Enter or comma to add a size (e.g., S, M, L, XL, Free Size)</div>
                                    </div>

                                    <!-- Colors -->
                                    <div class="form-group">
                                        <label class="form-label">Available Colors</label>
                                        <div class="variant-list" id="colorsList">
                                            <?php if (!empty($formData['colors']) && is_array($formData['colors'])): ?>
                                                <?php foreach ($formData['colors'] as $color): ?>
                                                    <?php
                                                        $cName = is_array($color) ? ($color['name'] ?? '') : $color;
                                                        $cHex  = is_array($color) ? ($color['hex'] ?? '#000000') : '#000000';
                                                    ?>
                                                    <div class="variant-chip" data-name="<?php echo clean($cName); ?>" data-hex="<?php echo clean($cHex); ?>">
                                                        <span class="color-dot" style="background: <?php echo clean($cHex); ?>;"></span>
                                                        <?php echo clean($cName); ?>
                                                        <span class="remove-chip" onclick="removeVariant('colors', this)">&times;</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="variant-row">
                                            <input type="text" id="colorNameInput" class="form-control" placeholder="Color name (e.g., Rose Gold)">
                                            <input type="color" id="colorHexInput" value="#d4a853">
                                            <button type="button" class="btn btn-sm btn-primary" onclick="addColor()">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        <div class="form-hint">Add color name and pick a color swatch</div>
                                    </div>

                                    <!-- Finishes -->
                                    <div class="form-group">
                                        <label class="form-label">Available Finishes</label>
                                        <div class="variant-list" id="finishesList">
                                            <?php if (!empty($formData['finishes']) && is_array($formData['finishes'])): ?>
                                                <?php foreach ($formData['finishes'] as $finish): ?>
                                                    <?php
                                                        $fName = is_array($finish) ? ($finish['name'] ?? '') : $finish;
                                                        $fHex  = is_array($finish) ? ($finish['hex'] ?? '#000000') : '#000000';
                                                    ?>
                                                    <div class="variant-chip" data-name="<?php echo clean($fName); ?>" data-hex="<?php echo clean($fHex); ?>">
                                                        <span class="color-dot" style="background: <?php echo clean($fHex); ?>;"></span>
                                                        <?php echo clean($fName); ?>
                                                        <span class="remove-chip" onclick="removeVariant('finishes', this)">&times;</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="variant-row">
                                            <input type="text" id="finishNameInput" class="form-control" placeholder="Finish name (e.g., Matte Black)">
                                            <input type="color" id="finishHexInput" value="#1a1a2e">
                                            <button type="button" class="btn btn-sm btn-primary" onclick="addFinish()">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        <div class="form-hint">Add finish name and pick a representative color</div>
                                    </div>
                                </div>

                                <!-- ======================================== -->
                                <!-- TAB: SEO                                 -->
                                <!-- ======================================== -->
                                <div class="tab-panel" id="tab-seo">
                                    <div class="form-group">
                                        <label class="form-label">Meta Title</label>
                                        <input type="text" name="meta_title" class="form-control" placeholder="SEO title for search engines" maxlength="255" value="<?php echo fv('meta_title', $formData); ?>">
                                        <div class="form-hint">Recommended: 50-60 characters</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Meta Description</label>
                                        <textarea name="meta_description" class="form-control" rows="3" placeholder="Brief description for search engine results" maxlength="500"><?php echo fv('meta_description', $formData); ?></textarea>
                                        <div class="form-hint">Recommended: 150-160 characters</div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Product Tags</label>
                                        <div class="tags-input" id="tagsTagsInput" onclick="document.getElementById('tagInput').focus()">
                                            <?php if (!empty($formData['tags']) && is_array($formData['tags'])): ?>
                                                <?php foreach ($formData['tags'] as $tag): ?>
                                                    <span class="tag">
                                                        <?php echo clean(is_string($tag) ? $tag : (is_array($tag) ? ($tag['name'] ?? '') : '')); ?>
                                                        <span class="remove-tag" onclick="removeTag('tags', this)">&times;</span>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <input type="text" id="tagInput" placeholder="Type tag and press Enter" onkeydown="handleTagInput(event, 'tags')">
                                        </div>
                                        <div class="form-hint">Press Enter or comma to add a tag (e.g., luxury, premium, gold-plated)</div>
                                    </div>
                                </div>

                            </div><!-- /.card-body -->
                        </div><!-- /.card -->
                    </div><!-- /.product-form-main -->

                    <!-- ============================================ -->
                    <!-- RIGHT: Sidebar                               -->
                    <!-- ============================================ -->
                    <div class="product-sidebar">

                        <!-- Product Summary Card -->
                        <div class="card" style="margin-bottom: 16px;">
                            <div class="card-header">
                                <h3><i class="fas fa-clipboard-list" style="color:var(--gold-primary);margin-right:8px;"></i> Product Summary</h3>
                            </div>
                            <div class="card-body">
                                <div class="summary-row">
                                    <span class="label">Name</span>
                                    <span class="value" id="summaryName"><?php echo $isEdit ? clean($formData['name']) : 'Untitled Product'; ?></span>
                                </div>
                                <div class="summary-row">
                                    <span class="label">Category</span>
                                    <span class="value" id="summaryCategory">
                                        <?php
                                            if ($isEdit && !empty($formData['category_id'])) {
                                                foreach ($categories as $cat) {
                                                    if ($cat['id'] == $formData['category_id']) {
                                                        echo clean($cat['name']);
                                                        break;
                                                    }
                                                }
                                            } else {
                                                echo 'Not selected';
                                            }
                                        ?>
                                    </span>
                                </div>
                                <div class="summary-row">
                                    <span class="label">Price</span>
                                    <span class="value" id="summaryPrice"><?php echo !empty($formData['price']) ? '₹' . number_format((float)$formData['price'], 2) : '₹0.00'; ?></span>
                                </div>
                                <div class="summary-row">
                                    <span class="label">Stock</span>
                                    <span class="value" id="summaryStock"><?php echo (int)($formData['stock'] ?? 0); ?> units</span>
                                </div>
                                <div class="summary-row">
                                    <span class="label">Status</span>
                                    <span class="value" id="summaryStatus">
                                        <?php if (!empty($formData['is_active'])): ?>
                                            <span class="badge badge-success"><span class="badge-dot" style="background:var(--success);"></span> Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger"><span class="badge-dot" style="background:var(--danger);"></span> Inactive</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($isEdit): ?>
                                    <div class="summary-row">
                                        <span class="label">Slug</span>
                                        <span class="value" style="font-size:11px;color:var(--text-muted);word-break:break-all;"><?php echo clean($formData['slug'] ?? ''); ?></span>
                                    </div>
                                <?php endif; ?>

                                <!-- Image Thumbnail -->
                                <?php if (!empty($formData['main_image'])): ?>
                                    <div style="margin-top: 12px; text-align: center;">
                                        <img src="<?php echo '../' . clean($formData['main_image']); ?>" alt="Preview" style="max-width: 100%; max-height: 140px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Save Actions Card -->
                        <div class="card" style="margin-bottom: 16px;">
                            <div class="card-header">
                                <h3><i class="fas fa-save" style="color:var(--gold-primary);margin-right:8px;"></i> Save</h3>
                            </div>
                            <div class="card-body" style="display: flex; flex-direction: column; gap: 10px;">
                                <button type="submit" name="save_action" value="close" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                    <i class="fas fa-check"></i> Save & Close
                                </button>
                                <button type="submit" name="save_action" value="continue" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                                    <i class="fas fa-arrow-right"></i> Save & Continue
                                </button>
                                <?php if ($isEdit): ?>
                                    <div style="border-top: 1px solid var(--border-color); padding-top: 10px; margin-top: 4px;">
                                        <button type="button" class="btn btn-sm" style="width:100%;justify-content:center;background:var(--danger-bg);color:var(--danger);border:1px solid rgba(231,76,60,0.2);" onclick="confirmDeleteProduct()">
                                            <i class="fas fa-trash-alt"></i> Delete Product
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Info Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-lightbulb" style="color:var(--warning);margin-right:8px;"></i> Tips</h3>
                            </div>
                            <div class="card-body" style="font-size: 12px; color: var(--text-secondary); line-height: 1.7;">
                                <p><i class="fas fa-check" style="color:var(--success);margin-right:6px;"></i> Use a clear, descriptive product name</p>
                                <p><i class="fas fa-check" style="color:var(--success);margin-right:6px;"></i> Set an old price higher than selling price to show discount</p>
                                <p><i class="fas fa-check" style="color:var(--success);margin-right:6px;"></i> Upload high-quality images (at least 800x800px)</p>
                                <p><i class="fas fa-check" style="color:var(--success);margin-right:6px;"></i> Add relevant tags for better SEO visibility</p>
                                <p><i class="fas fa-check" style="color:var(--success);margin-right:6px;"></i> Keep low stock threshold to get timely alerts</p>
                            </div>
                        </div>
                    </div><!-- /.product-sidebar -->

                </div><!-- /.product-form-layout -->
            </form>

        </div><!-- /.page-content -->
    </main>

</div><!-- /.admin-layout -->

<!-- Delete Confirmation Modal -->
<?php if ($isEdit): ?>
<div class="modal-overlay" id="deleteModal">
    <div class="modal" style="max-width: 440px;">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger); margin-right: 8px;"></i>Confirm Delete</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 12px;">Are you sure you want to delete this product?</p>
            <div style="background: var(--danger-bg); border: 1px solid rgba(231,76,60,0.2); border-radius: var(--radius-sm); padding: 12px 16px;">
                <p style="font-weight: 600; color: var(--danger); margin-bottom: 4px;"><?php echo clean($formData['name']); ?></p>
                <p style="font-size: 12px; color: var(--text-muted);">This action cannot be undone. The product will be permanently removed.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <form method="POST" action="products.php" style="display: inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" value="<?php echo $editId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Delete Product
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ============================================
// TAB NAVIGATION
// ============================================
document.querySelectorAll('#formTabs .tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        // Remove active from all tabs and panels
        document.querySelectorAll('#formTabs .tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
        // Activate clicked tab and corresponding panel
        this.classList.add('active');
        var tabId = 'tab-' + this.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
    });
});

// ============================================
// STATUS TOGGLE LABEL
// ============================================
var statusCheckbox = document.querySelector('input[name="is_active"]');
if (statusCheckbox) {
    statusCheckbox.addEventListener('change', function() {
        document.getElementById('statusLabel').textContent = this.checked ? 'Active' : 'Inactive';
    });
}

// ============================================
// LIVE SUMMARY UPDATE
// ============================================
var nameInput = document.querySelector('input[name="name"]');
var priceInput = document.querySelector('input[name="price"]');
var stockInput = document.querySelector('input[name="stock"]');
var categorySelect = document.querySelector('select[name="category_id"]');

if (nameInput) {
    nameInput.addEventListener('input', function() {
        document.getElementById('summaryName').textContent = this.value || 'Untitled Product';
    });
}
if (priceInput) {
    priceInput.addEventListener('input', function() {
        var val = parseFloat(this.value) || 0;
        document.getElementById('summaryPrice').textContent = '₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    });
}
if (stockInput) {
    stockInput.addEventListener('input', function() {
        document.getElementById('summaryStock').textContent = (parseInt(this.value) || 0) + ' units';
    });
}
if (categorySelect) {
    categorySelect.addEventListener('change', function() {
        document.getElementById('summaryCategory').textContent = this.options[this.selectedIndex].text;
    });
}

// ============================================
// DESCRIPTION TOOLBAR
// ============================================
function insertTag(tag) {
    var textarea = document.querySelector('textarea[name="description"]');
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var selected = textarea.value.substring(start, end);
    var replacement = '';

    switch(tag) {
        case 'B': replacement = '<b>' + (selected || 'bold text') + '</b>'; break;
        case 'I': replacement = '<i>' + (selected || 'italic text') + '</i>'; break;
        case 'U': replacement = '<u>' + (selected || 'underlined text') + '</u>'; break;
        case 'UL':
            replacement = '<ul>\n  <li>' + (selected || 'List item') + '</li>\n  <li>List item</li>\n</ul>';
            break;
        case 'OL':
            replacement = '<ol>\n  <li>' + (selected || 'List item') + '</li>\n  <li>List item</li>\n</ol>';
            break;
        case 'H2': replacement = '<h2>' + (selected || 'Heading') + '</h2>'; break;
        case 'P': replacement = '<p>' + (selected || 'Paragraph text') + '</p>'; break;
        case 'LINK':
            var url = prompt('Enter URL:', 'https://');
            if (url) {
                replacement = '<a href="' + url + '">' + (selected || url) + '</a>';
            } else {
                return;
            }
            break;
    }

    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    textarea.focus();
    textarea.selectionStart = start + replacement.length;
    textarea.selectionEnd = start + replacement.length;
}

// ============================================
// MAIN IMAGE HANDLING
// ============================================
function previewMainImage(event) {
    var file = event.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var previewDiv = document.getElementById('mainImagePreview');
        previewDiv.innerHTML = '<img src="' + e.target.result + '" alt="Preview" class="main-image-preview">';
    };
    reader.readAsDataURL(file);
}

function removeMainImage() {
    document.getElementById('removeMainImage').value = '1';
    // Clear preview if any
    var previewDiv = document.getElementById('mainImagePreview');
    if (previewDiv) previewDiv.innerHTML = '';
    // Clear file input
    var mainInput = document.getElementById('mainImageInput');
    if (mainInput) mainInput.value = '';
}

// ============================================
// ADDITIONAL IMAGES HANDLING
// ============================================
function previewAdditionalImages(event) {
    var files = event.target.files;
    var previewDiv = document.getElementById('newImagesPreview');

    for (var i = 0; i < files.length; i++) {
        (function(file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var item = document.createElement('div');
                item.className = 'image-preview-item';
                item.innerHTML = '<img src="' + e.target.result + '" alt="New image">' +
                    '<button type="button" class="remove-img" onclick="removeNewImage(this)" title="Remove">' +
                    '<i class="fas fa-times"></i></button>';
                previewDiv.appendChild(item);
            };
            reader.readAsDataURL(file);
        })(files[i]);
    }
}

function removeNewImage(btn) {
    btn.closest('.image-preview-item').remove();
}

function removeExistingImage(btn) {
    var item = btn.closest('.image-preview-item');
    item.remove();
    updateExistingImages();
}

function updateExistingImages() {
    var items = document.querySelectorAll('#existingImagesGrid .image-preview-item');
    var paths = [];
    items.forEach(function(item) {
        var path = item.getAttribute('data-image-path');
        if (path) paths.push(path);
    });
    document.getElementById('existingImages').value = JSON.stringify(paths);
}

// ============================================
// TAGS INPUT (Sizes & Tags)
// ============================================
function handleTagInput(event, type) {
    var input = event.target;
    var value = input.value.trim();

    if ((event.key === 'Enter' || event.key === ',' || event.key === 'Tab') && value) {
        event.preventDefault();
        // Remove trailing comma
        value = value.replace(/,$/, '').trim();
        if (!value) return;

        // Check for duplicate
        var container = input.closest('.tags-input');
        var existingTags = container.querySelectorAll('.tag');
        var isDuplicate = false;
        existingTags.forEach(function(tag) {
            if (tag.textContent.replace('×', '').trim() === value) {
                isDuplicate = true;
            }
        });

        if (!isDuplicate) {
            // Create tag element
            var tagEl = document.createElement('span');
            tagEl.className = 'tag';
            tagEl.innerHTML = value + ' <span class="remove-tag" onclick="removeTag(\'' + type + '\', this)">&times;</span>';
            container.insertBefore(tagEl, input);
            input.value = '';
            syncTagsToJson(type);
        }
    }

    // Handle backspace to remove last tag
    if (event.key === 'Backspace' && !input.value) {
        var container = input.closest('.tags-input');
        var tags = container.querySelectorAll('.tag');
        if (tags.length > 0) {
            tags[tags.length - 1].remove();
            syncTagsToJson(type);
        }
    }
}

function removeTag(type, btn) {
    btn.closest('.tag').remove();
    syncTagsToJson(type);
}

function syncTagsToJson(type) {
    var containerId = type === 'sizes' ? 'sizesTagsInput' : 'tagsTagsInput';
    var jsonId = type === 'sizes' ? 'sizesJson' : 'tagsJson';
    var container = document.getElementById(containerId);
    var tags = container.querySelectorAll('.tag');
    var values = [];
    tags.forEach(function(tag) {
        // Get text without the × button
        var text = tag.textContent.replace('×', '').trim();
        if (text) values.push(text);
    });
    document.getElementById(jsonId).value = JSON.stringify(values);
}

// ============================================
// COLORS & FINISHES (Variant Management)
// ============================================
function addColor() {
    var nameInput = document.getElementById('colorNameInput');
    var hexInput = document.getElementById('colorHexInput');
    var name = nameInput.value.trim();
    var hex = hexInput.value;

    if (!name) {
        nameInput.focus();
        return;
    }

    // Check duplicate
    var existing = document.querySelectorAll('#colorsList .variant-chip');
    for (var i = 0; i < existing.length; i++) {
        if (existing[i].getAttribute('data-name').toLowerCase() === name.toLowerCase()) {
            nameInput.value = '';
            nameInput.focus();
            return;
        }
    }

    var chip = document.createElement('div');
    chip.className = 'variant-chip';
    chip.setAttribute('data-name', name);
    chip.setAttribute('data-hex', hex);
    chip.innerHTML = '<span class="color-dot" style="background:' + hex + ';"></span> ' +
        name + ' <span class="remove-chip" onclick="removeVariant(\'colors\', this)">&times;</span>';

    document.getElementById('colorsList').appendChild(chip);
    nameInput.value = '';
    nameInput.focus();
    syncVariantsToJson('colors');
}

function addFinish() {
    var nameInput = document.getElementById('finishNameInput');
    var hexInput = document.getElementById('finishHexInput');
    var name = nameInput.value.trim();
    var hex = hexInput.value;

    if (!name) {
        nameInput.focus();
        return;
    }

    // Check duplicate
    var existing = document.querySelectorAll('#finishesList .variant-chip');
    for (var i = 0; i < existing.length; i++) {
        if (existing[i].getAttribute('data-name').toLowerCase() === name.toLowerCase()) {
            nameInput.value = '';
            nameInput.focus();
            return;
        }
    }

    var chip = document.createElement('div');
    chip.className = 'variant-chip';
    chip.setAttribute('data-name', name);
    chip.setAttribute('data-hex', hex);
    chip.innerHTML = '<span class="color-dot" style="background:' + hex + ';"></span> ' +
        name + ' <span class="remove-chip" onclick="removeVariant(\'finishes\', this)">&times;</span>';

    document.getElementById('finishesList').appendChild(chip);
    nameInput.value = '';
    nameInput.focus();
    syncVariantsToJson('finishes');
}

function removeVariant(type, btn) {
    btn.closest('.variant-chip').remove();
    syncVariantsToJson(type);
}

function syncVariantsToJson(type) {
    var listId = type === 'colors' ? 'colorsList' : 'finishesList';
    var jsonId = type === 'colors' ? 'colorsJson' : 'finishesJson';
    var chips = document.querySelectorAll('#' + listId + ' .variant-chip');
    var data = [];
    chips.forEach(function(chip) {
        data.push({
            name: chip.getAttribute('data-name'),
            hex: chip.getAttribute('data-hex')
        });
    });
    document.getElementById(jsonId).value = JSON.stringify(data);
}

// ============================================
// ALLOW ENTER ON COLOR/FINISH NAME INPUTS
// ============================================
document.getElementById('colorNameInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); addColor(); }
});
document.getElementById('finishNameInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); addFinish(); }
});

// ============================================
// DELETE PRODUCT MODAL
// ============================================
function confirmDeleteProduct() {
    document.getElementById('deleteModal').classList.add('show');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
}

// Close modal on overlay click
var deleteModal = document.getElementById('deleteModal');
if (deleteModal) {
    deleteModal.addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
});

// ============================================
// FORM VALIDATION BEFORE SUBMIT
// ============================================
document.getElementById('productForm').addEventListener('submit', function(e) {
    var name = document.querySelector('input[name="name"]').value.trim();
    var price = document.querySelector('input[name="price"]').value;
    var category = document.querySelector('select[name="category_id"]').value;

    if (!name || !price || !category) {
        // Find the tab containing the first error and switch to it
        if (!name || !category) {
            switchTab('general');
        } else if (!price) {
            switchTab('pricing');
        }
    }

    // Sync all JSON fields before submit
    syncTagsToJson('sizes');
    syncTagsToJson('tags');
    syncVariantsToJson('colors');
    syncVariantsToJson('finishes');
    updateExistingImages();
});

function switchTab(tabName) {
    document.querySelectorAll('#formTabs .tab-btn').forEach(function(b) { b.classList.remove('active'); });
    document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
    document.querySelector('.tab-btn[data-tab="' + tabName + '"]').classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
}
</script>

</body>
</html>
