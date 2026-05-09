<?php
require_once 'includes/functions.php';
$db = getDB();

// Fetch all active products with category name
$products = $db->query("
    SELECT p.*, c.name as cat_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.is_active = 1 
    ORDER BY p.created_at DESC
")->fetchAll();

// Fetch distinct categories that have active products
$categories = $db->query("
    SELECT DISTINCT c.id, c.name 
    FROM categories c 
    JOIN products p ON p.category_id = c.id 
    WHERE p.is_active = 1 
    ORDER BY c.sort_order ASC, c.name ASC
")->fetchAll();

// Fallback images per category for products with no image
$fallbackImages = [
    'Sarees'    => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=540&h=960&fit=crop&q=80',
    'Suits'     => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=540&h=960&fit=crop&q=80',
    'Kurtis'    => 'https://images.unsplash.com/photo-1594938298603-c8148c4b4281?w=540&h=960&fit=crop&q=80',
    'Lehengas'  => 'https://images.unsplash.com/photo-1617627143750-d86bc21e42bb?w=540&h=960&fit=crop&q=80',
    'Dupattas'  => 'https://images.unsplash.com/photo-1550639524-a5b5c4fe5e90?w=540&h=960&fit=crop&q=80',
    'default'   => 'https://images.unsplash.com/photo-1585487000160-6ebcfceb0d03?w=540&h=960&fit=crop&q=80',
];

$mappedProducts = [];
foreach ($products as $p) {
    $sizes  = json_decode($p['sizes']  ?: '[]', true) ?: [];
    $colors = json_decode($p['colors'] ?: '[]', true) ?: [];
    $tags   = $p['tags'] ?? '';
    $cat    = $p['cat_name'] ?? 'Uncategorized';

    // Use product image or fallback
    $img = !empty($p['main_image'])
        ? $p['main_image']
        : ($fallbackImages[$cat] ?? $fallbackImages['default']);

    // Normalize size values (could be array of strings or objects)
    $sizeValues = array_map(function($s) {
        return is_array($s) ? ($s['name'] ?? $s[0] ?? '') : (string)$s;
    }, $sizes);

    $mappedProducts[] = [
        'id'          => (int)$p['id'],
        'name'        => $p['name'],
        'cat'         => $cat,
        'sku'         => $p['sku'] ?? '',
        'price'       => (float)$p['price'],
        'oldPrice'    => (float)($p['old_price'] ?? $p['price']),
        'rating'      => (float)($p['rating'] ?? 4.5),
        'colors'      => max(1, count($colors)),
        'sizes'       => $sizeValues,
        'topSelling'  => (bool)($p['is_top_selling'] ?? 0),
        'newArrival'  => (bool)($p['is_new_arrival'] ?? 0),
        'boutiqueOnly'=> (bool)($p['is_boutique_only'] ?? 0),
        'img'         => $img,
        'tags'        => $tags,
        'fabric'      => $p['fabric'] ?? '',
        'work'        => $p['work'] ?? '',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Browse our complete collection of Indian ethnic wear — Sarees, Suits, Kurtis, Lehengas and more at DesiVastra.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop All Products - DesiVastra | Indian Ethnic Wear</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css?v=4">
    <link rel="stylesheet" href="assets/css/shop.css?v=4">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
</head>
<body>
    <div class="app-container">
        
        <header class="global-header">
            <div class="header-inner">
                <div class="header-left header-left-flex">
                    <a href="index.php" class="icon-btn mobile-only" title="Back to Home" aria-label="Back to Home"><i class="fa-solid fa-arrow-left"></i></a>
                    
                    <!-- Integrated Mobile Search -->
                    <div class="search-bar mobile-only search-bar-shop">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="shop-search-input-mobile" placeholder="Search Sarees, Kurtis, Suits...">
                        <i class="fa-solid fa-camera search-cam-icon" id="lens-trigger-btn" onclick="openLens()" role="button" tabindex="0" title="Search by photo" aria-label="Visual search by photo"></i>
                    </div>

                    <a href="index.php" class="logo desktop-only">Desi<span class="gold">Vastra</span></a>
                </div>

                <!-- Desktop Search -->
                <div class="desktop-search">
                    <input type="text" id="shop-search-input-desktop" placeholder="Search ethnic wear, sarees, kurtis...">
                    <button id="shop-search-btn-desktop" title="Search" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
                
                <div class="header-right">
                    <div class="desktop-nav-items">
                        <div class="d-nav-item" onclick="handleAccountClick()">
                            <span class="small">Hello, sign in</span>
                            <span class="bold">Account &amp; Lists</span>
                        </div>
                        <div class="d-nav-item">
                            <span class="small">Returns</span>
                            <span class="bold">&amp; Orders</span>
                        </div>
                        <div class="d-nav-item cart-btn" onclick="openRightDrawer('cart')">
                            <span class="cart-badge" id="cart-badge-shop">0</span>
                            <i class="fa-solid fa-cart-shopping fa-2x"></i>
                            <span class="bold cart-label">Cart</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Desktop Secondary Bar -->
            <div class="desktop-secondary-nav">
                <div class="m-all" onclick="window.location.href='index.php'"><i class="fa-solid fa-bars"></i> All</div>
                <a href="shop.php?cat=Sarees">Sarees</a>
                <a href="shop.php?cat=Suits">Suits</a>
                <a href="shop.php?cat=Kurtis">Kurtis</a>
                <a href="shop.php?cat=Lehengas">Lehengas</a>
                <a href="shop.php?cat=Dupattas">Dupattas</a>
                <a href="shop.php" class="sec-nav-prime">✦ New Arrivals</a>
            </div>
        </header>

        <!-- Right Drawer (Cart / Wishlist) -->
        <div class="overlay" id="right-overlay" onclick="closeRightDrawer()"></div>
        <div id="right-drawer" class="drawer-right">
            <div class="drawer-header-right">
                <h3 id="drawer-title" class="drawer-title-text">Drawer</h3>
                <button class="close-btn-styled" onclick="closeRightDrawer()" title="Close" aria-label="Close drawer"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="drawer-content" class="drawer-body"></div>
        </div>

        <main class="scroll-area flex-column">
            <!-- Dynamic Category Bar from DB -->
            <div class="shop-category-bar">
                <div class="shop-cat active" onclick="filterCat(this)">All</div>
                <?php foreach ($categories as $cat): ?>
                <div class="shop-cat" onclick="filterCat(this)"><?php echo clean($cat['name']); ?></div>
                <?php endforeach; ?>
            </div>

            <!-- Product count bar -->
            <div style="padding: 8px 16px; font-size: 13px; color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
                <span id="product-count-label"><?php echo count($mappedProducts); ?> Products</span>
            </div>

            <div class="product-grid" id="main-product-grid">
                <!-- Injected via shop.js -->
            </div>
            
            <div class="loader-spinner">
                <i class="fa-solid fa-circle-notch fa-spin"></i> Loading...
            </div>
            <div class="pb-100"></div>
        </main>

        <!-- Sort / Filter Action Bar (Mobile) -->
        <div class="shop-actions-bar">
            <button class="action-btn" onclick="openSort()"><i class="fa-solid fa-arrow-up-wide-short"></i> Sort By</button>
            <div class="divider-y"></div>
            <button class="action-btn" onclick="openFilter()"><i class="fa-solid fa-filter"></i> Filter</button>
        </div>

        <!-- Sort Drawer -->
        <div class="drawer-bottom" id="sort-drawer">
            <div class="pop-header">
                <h3>Sort Products</h3>
                <button class="pop-close" onclick="closeSort()" aria-label="Close sort"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="pop-option" onclick="selectSort(this)"><span>Price: Low to High</span><i class="fa-solid fa-arrow-down-short-wide"></i></div>
            <div class="pop-option" onclick="selectSort(this)"><span>Price: High to Low</span><i class="fa-solid fa-arrow-up-wide-short"></i></div>
            <div class="pop-option active" onclick="selectSort(this)"><span>Most Popular</span><i class="fa-solid fa-fire-flame-curved"></i></div>
            <div class="pop-option" onclick="selectSort(this)"><span>New Arrivals</span><i class="fa-solid fa-certificate"></i></div>
        </div>

        <!-- Filter Drawer -->
        <div class="drawer-bottom filter-full" id="filter-drawer">
            <div class="pop-header px-20">
                <h3>Filters</h3>
                <button class="pop-close" onclick="closeFilter()" aria-label="Close filters"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="filter-body-wrapper">
                <!-- Sidebar -->
                <div class="filter-sidebar">
                    <div class="side-tab active" data-tab="price-tab">Price</div>
                    <div class="side-tab" data-tab="cat-tab">Category</div>
                    <div class="side-tab" data-tab="status-tab">Status</div>
                    <div class="side-tab" data-tab="size-tab">Sizes</div>
                </div>
                <!-- Main Content Pane -->
                <div class="filter-content-pane">
                    <!-- Price Tab -->
                    <div id="price-tab" class="f-pane active">
                        <div class="filter-label">Set Price Range</div>
                        <div class="price-readout">₹0 - ₹<span id="price-val-2">20,000</span></div>
                        <label for="price-range-slider" class="sr-only">Price Range</label>
                        <input type="range" id="price-range-slider" class="price-slider" min="0" max="20000" value="20000" step="100" aria-label="Price range" oninput="document.getElementById('price-val-2').innerText = Number(this.value).toLocaleString('en-IN')">
                        <div class="p-range-labels"><span>₹0</span><span>₹20,000</span></div>
                    </div>
                    <!-- Category Tab -->
                    <div id="cat-tab" class="f-pane">
                        <?php foreach ($categories as $cat): ?>
                        <div class="pop-option" onclick="this.classList.toggle('active')"><?php echo clean($cat['name']); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Sizes Tab -->
                    <div id="size-tab" class="f-pane">
                        <div class="filter-label">Available Sizes</div>
                        <div class="size-grid">
                            <div class="size-box" onclick="this.classList.toggle('active')">XS</div>
                            <div class="size-box" onclick="this.classList.toggle('active')">S</div>
                            <div class="size-box" onclick="this.classList.toggle('active')">M</div>
                            <div class="size-box" onclick="this.classList.toggle('active')">L</div>
                            <div class="size-box" onclick="this.classList.toggle('active')">XL</div>
                            <div class="size-box" onclick="this.classList.toggle('active')">XXL</div>
                            <div class="size-box" onclick="this.classList.toggle('active')">Free Size</div>
                        </div>
                    </div>
                    <!-- Status Tab -->
                    <div id="status-tab" class="f-pane">
                        <label class="check-row"><span>Top Selling</span><input type="checkbox"></label>
                        <label class="check-row"><span>New Arrivals</span><input type="checkbox"></label>
                        <label class="check-row"><span>Boutique Only</span><input type="checkbox"></label>
                    </div>
                </div>
            </div>
            <div class="filter-footer">
                <button class="f-footer-btn clear" onclick="clearFilters()">Clear All</button>
                <button class="f-footer-btn apply" onclick="applyFilters()">Apply Filters</button>
            </div>
        </div>

        <!-- Arniya Lens Visual Search Modal -->
        <div class="modal-full" id="lens-modal">
            <div class="lens-header">
                <button class="lens-back-btn" onclick="closeLens()" aria-label="Close visual search"><i class="fa-solid fa-arrow-left"></i></button>
                <div class="lens-header-title"><i class="fa-solid fa-camera-retro"></i><span>Visual Search</span></div>
                <div class="lens-header-badge">AI</div>
            </div>
            <div class="lens-step" id="lens-step-choose">
                <div class="lens-hero-text">
                    <h2>Search by Photo</h2>
                    <p>Find exact &amp; similar products instantly</p>
                </div>
                <div class="lens-preview-box lens-hidden" id="lens-preview-box">
                    <img id="lens-preview-img" src="" alt="Preview">
                    <button class="lens-preview-clear" onclick="clearLensImage()" aria-label="Remove selected image"><i class="fa-solid fa-xmark"></i></button>
                    <div class="lens-scan-overlay" id="lens-scan-overlay">
                        <div class="lens-scan-line"></div>
                        <div class="lens-scan-corners">
                            <span class="sc tl"></span><span class="sc tr"></span>
                            <span class="sc bl"></span><span class="sc br"></span>
                        </div>
                        <div class="lens-scan-label">Scanning...</div>
                    </div>
                </div>
                <div class="lens-source-row" id="lens-source-row">
                    <input type="file" id="lens-gallery-input" accept="image/*" class="lens-hidden" aria-label="Upload from gallery" onchange="handleLensFile(event)">
                    <input type="file" id="lens-camera-input" accept="image/*" capture="environment" class="lens-hidden" aria-label="Capture with camera" onchange="handleLensFile(event)">
                    <button class="lens-src-btn" onclick="document.getElementById('lens-gallery-input').click()">
                        <div class="lens-src-icon"><i class="fa-regular fa-images"></i></div>
                        <span>Gallery</span><small>Upload from phone</small>
                    </button>
                    <button class="lens-src-btn" onclick="document.getElementById('lens-camera-input').click()">
                        <div class="lens-src-icon camera"><i class="fa-solid fa-camera"></i></div>
                        <span>Camera</span><small>Take a new photo</small>
                    </button>
                </div>
                <div class="lens-or-row"><span></span><em>or try a sample</em><span></span></div>
                <div class="lens-samples-row" id="lens-samples-row">
                    <button class="lens-sample lens-sample-0" onclick="useSampleImage(0)" aria-label="Sample 1"></button>
                    <button class="lens-sample lens-sample-1" onclick="useSampleImage(1)" aria-label="Sample 2"></button>
                    <button class="lens-sample lens-sample-2" onclick="useSampleImage(2)" aria-label="Sample 3"></button>
                    <button class="lens-sample lens-sample-3" onclick="useSampleImage(3)" aria-label="Sample 4"></button>
                </div>
                <button class="lens-search-cta lens-hidden" id="lens-search-cta" onclick="runLensSearch()">
                    <i class="fa-solid fa-magnifying-glass"></i> Find Similar Products
                </button>
            </div>
            <div class="lens-step lens-hidden" id="lens-step-loading">
                <div class="lens-ai-loader">
                    <div class="lens-ai-ring">
                        <svg viewBox="0 0 100 100"><circle class="ring-track" cx="50" cy="50" r="42"/><circle class="ring-fill" cx="50" cy="50" r="42"/></svg>
                        <i class="fa-solid fa-camera-retro"></i>
                    </div>
                    <div class="lens-ai-steps" id="lens-ai-steps">
                        <div class="ai-step active" id="ai-s1"><i class="fa-solid fa-circle-notch fa-spin"></i> Uploading image…</div>
                        <div class="ai-step" id="ai-s2"><i class="fa-solid fa-circle-notch fa-spin"></i> Running Vision AI…</div>
                        <div class="ai-step" id="ai-s3"><i class="fa-solid fa-circle-notch fa-spin"></i> Matching products…</div>
                        <div class="ai-step" id="ai-s4"><i class="fa-solid fa-circle-notch fa-spin"></i> Ranking results…</div>
                    </div>
                </div>
            </div>
            <div class="lens-step lens-hidden" id="lens-step-results">
                <div class="lens-result-header">
                    <img class="lens-result-thumb" id="lens-result-thumb" src="" alt="">
                    <div class="lens-result-meta">
                        <div class="lens-result-tags" id="lens-result-tags"></div>
                        <p class="lens-result-count" id="lens-result-count"></p>
                    </div>
                    <button class="lens-new-search" onclick="resetLens()"><i class="fa-solid fa-rotate-left"></i> New</button>
                </div>
                <div class="lens-section-label">Exact Match</div>
                <div class="lens-exact-card" id="lens-exact-card"></div>
                <div class="lens-section-label">Similar Products</div>
                <div class="lens-results-grid" id="lens-results-grid"></div>
            </div>
        </div>

    </div><!-- /.app-container -->

    <!-- Bottom Mobile Nav -->
    <nav class="bottom-nav">
        <a href="index.php" class="nav-item"><i class="fa-solid fa-house"></i><span>Home</span></a>
        <div class="nav-item" onclick="openRightDrawer('wishlist')"><i class="fa-regular fa-heart"></i><span>Wishlist</span></div>
        <a href="shop.php" class="nav-item center-hex active" aria-label="Shop"><div class="hex-bg"><i class="fa-solid fa-gem"></i></div></a>
        <div class="nav-item" onclick="openRightDrawer('cart')">
            <div class="icon-badge-wrapper"><i class="fa-solid fa-bag-shopping"></i><span class="cart-badge" id="cart-badge-mobile">0</span></div>
            <span>Cart</span>
        </div>
        <div class="nav-item" onclick="handleAccountClick()"><i class="fa-regular fa-user"></i><span>Account</span></div>
    </nav>
    
    <script src="assets/js/global.js?v=4"></script>
    <script>
    // Inject real DB products replacing mockData.js
    const mockProducts = <?php echo json_encode($mappedProducts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;

    // Update product count label when filters change
    const _origRenderProducts = window.renderProducts;
    document.addEventListener('DOMContentLoaded', function() {
        // Hook into renderProducts to update count label
        const origRP = window.renderProducts;
        if (typeof origRP === 'function') {
            window.renderProducts = function(products) {
                origRP(products || window.getVisibleProducts());
                const shown = (products || window.getVisibleProducts() || []).length;
                const lbl = document.getElementById('product-count-label');
                if (lbl) lbl.textContent = shown + ' Product' + (shown !== 1 ? 's' : '');
            };
        }
    });
    </script>
    <script src="assets/js/shop.js?v=4"></script>
</body>
</html>
