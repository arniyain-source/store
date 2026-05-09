<?php
require_once __DIR__ . '/includes/core/app.php';
$db = getDB();

// Categories from DB
$db_categories = $db->query("SELECT * FROM categories WHERE status=1 ORDER BY sort_order ASC, name ASC LIMIT 8")->fetchAll();

// Category fallback images
$catImgs = [
    'Sarees'   => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=200&q=80',
    'Suits'    => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=200&q=80',
    'Kurtis'   => 'https://images.unsplash.com/photo-1594938298603-c8148c4b4281?w=200&q=80',
    'Lehengas' => 'https://images.unsplash.com/photo-1617627143750-d86bc21e42bb?w=200&q=80',
    'Dupattas' => 'https://images.unsplash.com/photo-1550639524-a5b5c4fe5e90?w=200&q=80',
    'default'  => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=200&q=80',
];
$catEmoji = ['Sarees'=>'&#129467;','Suits'=>'&#128084;','Kurtis'=>'&#128120;','Lehengas'=>'&#128120;','Dupattas'=>'&#129493;'];

// Product fallback images
$pImgs = [
    'Sarees'   => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=400&q=80',
    'Suits'    => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=400&q=80',
    'Kurtis'   => 'https://images.unsplash.com/photo-1594938298603-c8148c4b4281?w=400&q=80',
    'Lehengas' => 'https://images.unsplash.com/photo-1617627143750-d86bc21e42bb?w=400&q=80',
    'Dupattas' => 'https://images.unsplash.com/photo-1550639524-a5b5c4fe5e90?w=400&q=80',
    'default'  => 'https://images.unsplash.com/photo-1585487000160-6ebcfceb0d03?w=400&q=80',
];

function pimg($p, $pImgs) {
    if (!empty($p['main_image'])) return $p['main_image'];
    return $pImgs[$p['category_name'] ?? ''] ?? $pImgs['default'];
}

function pdiscount($p) {
    if (!empty($p['old_price']) && $p['old_price'] > $p['price'])
        return round((1 - $p['price']/$p['old_price'])*100);
    return 0;
}

// Trending
$trending = $db->query("SELECT p.*,c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 AND p.is_top_selling=1 ORDER BY p.id DESC LIMIT 5")->fetchAll();
if (empty($trending)) $trending = $db->query("SELECT p.*,c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 ORDER BY p.id DESC LIMIT 5")->fetchAll();

// Featured
$featured = $db->query("SELECT p.*,c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 AND p.is_featured=1 ORDER BY p.id DESC LIMIT 5")->fetchAll();
if (empty($featured)) $featured = $db->query("SELECT p.*,c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 ORDER BY RANDOM() LIMIT 5")->fetchAll();

// New Arrivals
$arrivals = $db->query("SELECT p.*,c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 AND p.is_new_arrival=1 ORDER BY p.id DESC LIMIT 5")->fetchAll();
if (empty($arrivals)) $arrivals = $db->query("SELECT p.*,c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 ORDER BY p.id DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="DesiVastra - Premium luxury fashion, watches, jewelry, accessories and perfumes. Shop the finest collection at best prices.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DesiVastra - Luxury Fashion & Accessories</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://images.unsplash.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css?v=3">
    <link rel="stylesheet" href="assets/css/home.css?v=3">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
</head>
<body>
    <div class="app-container">
        
        <!-- Global Header -->
        <header class="global-header">

            <!-- Desktop Top Utility Bar -->
            <div class="desktop-top-bar">
                <div class="dtb-inner">
                    <div class="dtb-left">
                        <i class="fa-solid fa-shield-halved"></i> Secure Checkout &nbsp;|&nbsp;
                        <i class="fa-solid fa-truck-fast"></i> Free shipping on orders ₹999+
                    </div>
                    <div class="dtb-right">
                        <a href="#">Help Center</a>
                        <span>|</span>
                        <a href="#" onclick="openSupportPopup()"><i class="fa-solid fa-headset"></i> Live Support 24/7</a>
                        <span>|</span>
                        <a href="login.php">Sign In</a>
                    </div>
                </div>
            </div>

            <!-- Main Header Row -->
            <div class="header-inner">
                <!-- Left: Menu + Logo -->
                <div class="header-left">
                    <button class="hdr-icon-btn mobile-only" onclick="toggleMenu()" aria-label="Toggle Menu"><i class="fa-solid fa-bars"></i></button>
                    <button class="d-menu-btn desktop-only" onclick="toggleMenu()" aria-label="Desktop Menu">
                        <i class="fa-solid fa-bars"></i>
                        <span>All</span>
                    </button>
                </div>

                <!-- Center: Branding Logo -->
                <div class="header-center">
                    <a href="index.php" class="logo">Desi<span class="gold">Vastra</span></a>
                    <span class="logo-badge mobile-only">ETHNIC</span>
                </div>

                <!-- Desktop: Enhanced Search with Category -->
                <div class="desktop-search">
                    <select class="search-cat-select" id="desktop-search-cat" aria-label="Search Category">
                        <option>All</option>
                        <option>Watches</option>
                        <option>Jewelry</option>
                        <option>Accessories</option>
                        <option>Perfumes</option>
                    </select>
                    <input type="text" id="desktop-search-input" placeholder="Search DesiVastra...">
                    <button id="desktop-search-btn" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>

                <!-- Mobile: Support 24/7 Button -->
                <button class="support-24-btn mobile-only" onclick="openSupportPopup()" aria-label="24/7 Support">
                    <span class="support-pulse"></span>
                    <i class="fa-solid fa-headset"></i>
                    <span>24/7</span>
                </button>

                <!-- Desktop Nav Items -->
                <div class="desktop-nav-items">
                    <!-- Delivery Location -->
                    <div class="d-nav-item d-location">
                        <span class="small"><i class="fa-solid fa-location-dot" style="color:var(--gold-light)"></i> Deliver to</span>
                        <span class="bold">India 🇮🇳</span>
                    </div>
                    <!-- Account -->
                    <div class="d-nav-item" onclick="handleAccountClick()">
                        <span class="small">Hello, Guest</span>
                        <span class="bold">Account &amp; Lists <i class="fa-solid fa-caret-down" style="font-size:10px"></i></span>
                    </div>
                    <!-- Returns -->
                    <div class="d-nav-item" onclick="handleAccountClick()">
                        <span class="small">Returns</span>
                        <span class="bold">&amp; Orders</span>
                    </div>
                    <!-- Wishlist -->
                    <div class="d-nav-item d-icon-item" onclick="openRightDrawer('wishlist')" title="Wishlist">
                        <div class="d-icon-wrapper">
                            <i class="fa-regular fa-heart"></i>
                        </div>
                        <span class="bold">Wishlist</span>
                    </div>
                    <!-- Cart -->
                    <div class="d-nav-item cart-btn" onclick="openRightDrawer('cart')">
                        <span class="cart-badge" id="cart-badge-desktop">0</span>
                        <i class="fa-solid fa-cart-shopping fa-2x"></i>
                        <span class="bold" style="margin-bottom:2px;">Cart</span>
                    </div>
                </div>
            </div>

            <!-- Gold Shine Accent Line (Mobile Only) -->
            <div class="header-gold-line mobile-only"></div>

            <!-- Desktop Secondary Nav -->
            <div class="desktop-secondary-nav">
                <a href="shop.php" class="sec-nav-deals"><i class="fa-solid fa-bolt"></i> Today's Deals</a>
                <a href="shop.php">New Arrivals</a>
                <a href="shop.php">Best Sellers</a>
                <?php foreach($db_categories as $cat): ?>
                <a href="shop.php?cat=<?php echo urlencode($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></a>
                <?php endforeach; ?>
                <a href="shop.php" class="sec-nav-prime">&#10022; DesiVastra Prime</a>
            </div>
        </header>

        <!-- Support Popup Modal -->
        <div id="support-overlay" class="support-overlay" onclick="closeSupportPopup()"></div>
        <div id="support-popup" class="support-popup">
            <div class="support-popup-header">
                <div class="support-popup-icon">
                    <i class="fa-solid fa-headset"></i>
                </div>
                <div>
                    <h3>Support Center</h3>
                    <p>We're online &amp; ready to help</p>
                </div>
                <button class="close-btn-styled" onclick="closeSupportPopup()" aria-label="Close Support"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="support-popup-status">
                <span class="status-dot"></span> Available 24/7 · Avg. response &lt; 2 min
            </div>
            <div class="support-popup-actions">
                <a href="https://wa.me/919876543210" class="support-action-btn whatsapp-btn">
                    <div class="s-btn-icon"><i class="fa-brands fa-whatsapp"></i></div>
                    <div class="s-btn-text">
                        <span class="s-btn-title">Chat on WhatsApp</span>
                        <span class="s-btn-sub">Instant reply guaranteed</span>
                    </div>
                    <i class="fa-solid fa-chevron-right s-arrow"></i>
                </a>
                <a href="tel:+919876543210" class="support-action-btn call-btn">
                    <div class="s-btn-icon"><i class="fa-solid fa-phone"></i></div>
                    <div class="s-btn-text">
                        <span class="s-btn-title">Call Us Now</span>
                        <span class="s-btn-sub">+91 98765 43210</span>
                    </div>
                    <i class="fa-solid fa-chevron-right s-arrow"></i>
                </a>
            </div>
            <div class="support-popup-footer">
                <i class="fa-solid fa-shield-halved"></i> Your privacy is protected
            </div>
        </div>

        <!-- Drawers -->
        <div class="overlay" id="menu-overlay" onclick="toggleMenu()"></div>
        <div id="menu-drawer" class="drawer-left">
            <div class="drawer-header" style="display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:16px;">
                    <i class="fa-solid fa-circle-user fa-2x" style="color:var(--text-secondary)"></i>
                    <h2 id="guest-greeting" style="font-weight:600;">Hello, Guest</h2>
                </div>
                <button class="close-btn-styled" onclick="toggleMenu()" aria-label="Close Menu"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="drawer-body">
                <ul class="menu-list">
                    <li class="menu-title" style="margin-top: 10px;">Trending</li>
                    <li class="menu-link" onclick="window.location.href='shop.php'"><div class="m-left"><i class="fa-solid fa-fire"></i> Best Sellers</div> <i class="fa-solid fa-chevron-right arrow"></i></li>
                    <li class="menu-link" onclick="window.location.href='shop.php'"><div class="m-left"><i class="fa-solid fa-bolt"></i> New Releases</div> <i class="fa-solid fa-chevron-right arrow"></i></li>
                    <li class="menu-title">Shop by Category</li>
                    <?php foreach($db_categories as $cat): ?>
                    <li class="menu-link" onclick="window.location.href='shop.php?cat=<?php echo urlencode($cat['name']); ?>'"><div class="m-left"><i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($cat['name']); ?></div> <i class="fa-solid fa-chevron-right arrow"></i></li>
                    <?php endforeach; ?>
                    <li class="menu-title">Account</li>
                    <li class="menu-link" onclick="handleAccountClick()"><div class="m-left"><i class="fa-regular fa-user"></i> Your Account</div> <i class="fa-solid fa-chevron-right arrow"></i></li>
                    <li class="menu-link" onclick="handleAccountClick()"><div class="m-left"><i class="fa-solid fa-box-open"></i> Your Orders</div> <i class="fa-solid fa-chevron-right arrow"></i></li>
                    <li class="menu-link" onclick="openRightDrawer('wishlist')"><div class="m-left"><i class="fa-regular fa-heart"></i> Wishlist</div> <i class="fa-solid fa-chevron-right arrow"></i></li>
                </ul>
                <div class="drawer-footer-menu">
                    <p class="drawer-f-title">Support & Info</p>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms & Conditions</a>
                    <a href="#">About DesiVastra</a>
                    <a href="mailto:support@desivastra.com"><i class="fa-solid fa-envelope" style="margin-right:8px;"></i>support@desivastra.com</a>
                    
                    <div class="drawer-socials">
                        <i class="fa-brands fa-instagram"></i>
                        <i class="fa-brands fa-facebook-f"></i>
                        <i class="fa-brands fa-twitter"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="overlay" id="right-overlay" onclick="closeRightDrawer()"></div>
        <div id="right-drawer" class="drawer-right">
            <div class="drawer-header-right">
                <h3 id="drawer-title" style="font-size: 18px;">Drawer</h3>
                <button class="close-btn-styled" onclick="closeRightDrawer()" aria-label="Close Drawer"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="drawer-content" class="drawer-body"></div>
        </div>

        <!-- Video Reel Viewer -->
        <div id="video-reel-viewer">
            <button class="close-reel" onclick="closeReel()" aria-label="Close Reel"><i class="fa-solid fa-xmark"></i></button>
            <div class="reel-scroll-container">
                <div class="reel-item">
                    <div class="reel-item-bg" style="background-image: url('https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&q=80')">
                        <i class="fa-solid fa-play"></i>
                    </div>
                </div>
                <div class="reel-item">
                    <div class="reel-item-bg" style="background-image: url('https://images.unsplash.com/photo-1599643478524-fb66f7f2b1d6?w=600&q=80')">
                        <i class="fa-solid fa-play"></i>
                    </div>
                </div>
            </div>
        </div>

        <main class="scroll-area flex-column">
            
            <!-- Section 1: Hero Slider -->
            <div class="hero-slider" id="heroSlider">

                <!-- Slide 1 -->
                <div class="slide bg-img active" style="background-image: url('https://images.unsplash.com/photo-1617305988165-27f940898eb2?auto=format&fit=crop&w=1200&q=80')">
                    <div class="slide-badge">🔥 Limited Edition</div>
                    <div class="slide-content">
                        <span class="slide-tag">New Collection</span>
                        <h2>The Obsidian<br>Collection</h2>
                        <p>Luxury redefined. Crafted for the few.</p>
                        <div class="slide-actions">
                            <a href="shop.php"><button class="gold-btn">Shop Now</button></a>
                            <a href="shop.php"><button class="slide-ghost-btn">Explore All</button></a>
                        </div>
                    </div>
                </div>

                <!-- Slide 2 -->
                <div class="slide bg-img" style="background-image: url('https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=1200&q=80')">
                    <div class="slide-badge">⭐ Best Seller</div>
                    <div class="slide-content">
                        <span class="slide-tag">Watches</span>
                        <h2>Gold Chronograph<br>Edition</h2>
                        <p>Timeless precision. Pure gold finish.</p>
                        <div class="slide-actions">
                            <a href="product.php?id=1"><button class="gold-btn">View Details</button></a>
                            <a href="shop.php"><button class="slide-ghost-btn">Browse More</button></a>
                        </div>
                    </div>
                </div>

                <!-- Slide 3 -->
                <div class="slide bg-img" style="background-image: url('https://images.unsplash.com/photo-1599643478524-fb66f7f2b1d6?auto=format&fit=crop&w=1200&q=80')">
                    <div class="slide-badge">💎 Premium Pick</div>
                    <div class="slide-content">
                        <span class="slide-tag">Jewelry</span>
                        <h2>Signature Platinum<br>Ring</h2>
                        <p>Wear your story. Own the moment.</p>
                        <div class="slide-actions">
                            <a href="product.php?id=2"><button class="gold-btn">Shop Now</button></a>
                            <a href="shop.php"><button class="slide-ghost-btn">View Collection</button></a>
                        </div>
                    </div>
                </div>

                <!-- Slide Progress Bar -->
                <div class="slide-progress"><div class="slide-progress-bar" id="slideProgressBar"></div></div>
            </div>

            <!-- Section 2: Categories -->
            <div class="cat-section">
                <div class="cat-section-header">
                    <div class="cat-sec-left">
                        <span class="cat-sec-icon"><i class="fa-solid fa-layer-group"></i></span>
                        <h3>Shop by Category</h3>
                    </div>
                    <a href="shop.php" class="cat-view-all">View All <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="category-slider">

                    <?php foreach($db_categories as $cat):
                        $cimg = $catImgs[$cat['name']] ?? $catImgs['default'];
                        $cemoji = $catEmoji[$cat['name']] ?? '&#127801;';
                    ?>
                    <a href="shop.php?cat=<?php echo urlencode($cat['name']); ?>" class="cat-item">
                        <div class="cat-icon-wrap">
                            <div class="cat-icon bg-img" style="background-image: url('<?php echo $cimg; ?>')"></div>
                            <span class="cat-emoji"><?php echo $cemoji; ?></span>
                        </div>
                        <span class="cat-label"><?php echo htmlspecialchars($cat['name']); ?></span>
                    </a>
                    <?php endforeach; ?>
                    <a href="shop.php" class="cat-item">
                        <div class="cat-icon-wrap cat-icon-wrap--sale">
                            <div class="cat-icon" style="background: linear-gradient(135deg,#cf6679,#8c1c30); display:flex; align-items:center; justify-content:center; font-size:22px;">&#128293;</div>
                            <span class="cat-badge-sale">HOT</span>
                        </div>
                        <span class="cat-label" style="color:var(--danger);">Sale</span>
                    </a>
                    <a href="shop.php" class="cat-item">
                        <div class="cat-icon-wrap">
                            <div class="cat-icon" style="background: var(--gold-gradient); display:flex; align-items:center; justify-content:center; font-size:22px;">&#10022;</div>
                        </div>
                        <span class="cat-label">All Items</span>
                    </a>

                </div>
            </div>

            <!-- Section 3: Top Trending -->
            <div class="section-container trend-section">
                <div class="trend-heading-bg">
                    <div class="trend-heading-left">
                        <div class="trend-icon-wrap">
                            <i class="fa-solid fa-fire trend-icon-fire"></i>
                        </div>
                        <div class="trend-title-wrap">
                            <h3 class="trend-main-title">Top <span class="trend-title-gold">Trending</span></h3>
                            <p class="trend-subtitle">Most loved picks this week</p>
                        </div>
                    </div>
                    <div class="trend-heading-right">
                        <span class="trend-live-badge"><span class="trend-live-dot"></span> LIVE</span>
                        <a href="shop.php" class="section-see-all">See All <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="horizontal-scroll trending-scroll">

                    <?php foreach($trending as $p):
                        $img = pimg($p, $pImgs);
                        $disc = pdiscount($p);
                        $sizes = json_decode($p['sizes'] ?: '[]', true) ?: [];
                        $colors = json_decode($p['colors'] ?: '[]', true) ?: [];
                        $badge = $p['is_top_selling'] ? '<span class="trend-badge hot">&#128293; Hot</span>' : ($p['is_new_arrival'] ? '<span class="trend-badge new">&#10024; New</span>' : '');
                    ?>
                    <a href="product.php?id=<?php echo $p['id']; ?>" class="trend-card">
                        <div class="trend-img-wrap">
                            <div class="trend-img bg-img" style="background-image: url('<?php echo $img; ?>')"></div>
                            <?php echo $badge; ?>
                            <div class="trend-rating-pill"><i class="fa-solid fa-star"></i> <?php echo number_format((float)($p['rating']??4.5),1); ?> &middot; <?php echo rand(200,2000); ?></div>
                            <button class="trend-fav" aria-label="Add to Favorites" data-product-id="<?php echo $p['id']; ?>"><i class="fa-regular fa-heart"></i></button>
                        </div>
                        <div class="trend-info">
                            <p class="trend-name"><?php echo htmlspecialchars($p['name']); ?></p>
                            <div class="trend-meta-row">
                                <span class="trend-sku"><?php echo htmlspecialchars($p['sku'] ?? 'DV-'.str_pad($p['id'],3,'0',STR_PAD_LEFT)); ?></span>
                                <span class="trend-clr-count"><?php echo count($colors) ?: 1; ?> Colour<?php echo count($colors)>1?'s':''; ?></span>
                            </div>
                            <div class="trend-sizes">
                                <?php foreach(array_slice($sizes,0,4) as $sz):
                                    $szl = is_array($sz)?($sz['name']??$sz['value']??''):$sz;
                                ?><span class="t-size"><?php echo htmlspecialchars($szl); ?></span><?php endforeach; ?>
                                <?php if(empty($sizes)): ?><span class="t-size">Free Size</span><?php endif; ?>
                            </div>
                            <div class="trend-price-row">
                                <span class="trend-price"><span class="trend-curr">&#8377;</span><?php echo number_format($p['price']); ?></span>
                                <?php if($disc>0): ?><span class="trend-disc"><i class="fa-solid fa-arrow-trend-down"></i> <?php echo $disc; ?>% off</span><?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Promo Sliding Banner ── -->

            <div class="promo-banner-wrap">
                <div class="promo-slider" id="promoSlider">

                    <!-- Slide 1 -->
                    <a href="shop.php" class="promo-slide" aria-label="Promo Slide">
                        <div class="promo-photo bg-img" style="background-image:url('https://images.unsplash.com/photo-1617038220319-276d3cfab638?w=900&q=85')"></div>
                    </a>

                    <!-- Slide 2 -->
                    <a href="shop.php" class="promo-slide" aria-label="Promo Slide">
                        <div class="promo-photo bg-img" style="background-image:url('https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=900&q=85')"></div>
                    </a>

                    <!-- Slide 3 -->
                    <a href="shop.php" class="promo-slide" aria-label="Promo Slide">
                        <div class="promo-photo bg-img" style="background-image:url('https://images.unsplash.com/photo-1543087903-1ac2ec7aa8c5?w=900&q=85')"></div>
                    </a>

                </div>
            </div>


            <!-- Section 4: Featured Products -->
            <div class="section-container feat-section dark-bg-block">
                <div class="feat-heading-bg">
                    <div class="feat-heading-left">
                        <div class="feat-icon-wrap">
                            <i class="fa-solid fa-gem feat-icon-gem"></i>
                        </div>
                        <div class="feat-title-wrap">
                            <h3 class="feat-main-title">Featured <span class="feat-title-shine">Selection</span></h3>
                            <p class="feat-subtitle">Handpicked luxury essentials</p>
                        </div>
                    </div>
                    <div class="feat-heading-right">
                        <span class="feat-count-badge"><i class="fa-solid fa-layer-group"></i> 5 Items</span>
                        <a href="shop.php" class="section-see-all">See All <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="horizontal-scroll feat-scroll">
                    <?php foreach($featured as $p):
                        $img = pimg($p, $pImgs);
                        $disc = pdiscount($p);
                        $fbadge = $p['is_top_selling']?'<span class="feat-badge">&#128293; Hot</span>':($p['is_new_arrival']?'<span class="feat-badge new">&#10024; New</span>':'');
                    ?>
                    <a href="product.php?id=<?php echo $p['id']; ?>" class="feat-card">
                        <div class="feat-img bg-img" style="background-image: url('<?php echo $img; ?>')">
                            <?php echo $fbadge; ?>
                            <div class="feat-price-tag">
                                <span class="feat-tag-hole"></span>
                                <span class="feat-tag-curr">&#8377;</span>
                                <span class="feat-tag-num"><?php echo number_format($p['price']); ?></span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Section 5: New Arrivals -->
            <div class="section-container na-section">

                <!-- Premium Heading -->
                <div class="na-heading-bg">
                    <div class="na-heading-left">
                        <div class="na-icon-wrap">
                            <i class="fa-solid fa-star-of-life na-icon"></i>
                        </div>
                        <div class="na-title-wrap">
                            <h3 class="na-main-title">New <span class="na-title-shine">Arrivals</span></h3>
                            <p class="na-subtitle">Fresh drops — just landed</p>
                        </div>
                    </div>
                    <div class="na-heading-right">
                        <span class="na-new-badge">✦ NEW</span>
                        <a href="shop.php" class="section-see-all">See All <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>

                <!-- Editorial Grid: 1 tall left + 2×2 right -->
                <div class="na-grid">
                    <?php
                    $na_first = true;
                    foreach($arrivals as $p):
                        $img = pimg($p, $pImgs);
                        $disc = pdiscount($p);
                        $nabadge = $p['is_new_arrival']?'<span class="na-card-badge">New</span>':($p['is_top_selling']?'<span class="na-card-badge sale">Hot</span>':'');
                        $naclass = $na_first ? 'na-card na-card-tall' : 'na-card';
                        $na_first = false;
                    ?>
                    <a href="product.php?id=<?php echo $p['id']; ?>" class="<?php echo $naclass; ?>">
                        <div class="na-photo bg-img" style="background-image:url('<?php echo $img; ?>')"></div>
                        <?php echo $nabadge; ?>
                        <div class="na-price-tag"><span class="na-tag-curr">&#8377;</span><span class="na-tag-num"><?php echo number_format($p['price']); ?></span></div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Section 6: Signature Brand Banner -->
            <div class="sig-banner-wrap">
                <div class="sig-banner bg-img" style="background-image: url('https://images.unsplash.com/photo-1598532163257-ae3c6b2524b6?w=1000&q=85')">
                    <div class="sig-overlay">
                        <div class="sig-glass-box">
                            <span class="sig-eyebrow">The Heritage Collection</span>
                            <h2 class="sig-title">Desi<span class="sig-title-gold">Vastra</span></h2>
                            <p class="sig-desc">Experience true craftsmanship &amp; unparalleled luxury</p>
                            <a href="shop.php" class="sig-btn">
                                <span>Explore Luxury</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 7: Watch & Shop Reels -->
            <div class="section-container reels-section">
                <!-- Premium Heading -->
                <div class="feat-heading-bg reels-heading">
                    <div class="feat-heading-left">
                        <div class="feat-icon-wrap" style="background: linear-gradient(135deg, rgba(230,0,80,0.2), rgba(0,0,0,0)); border-color: rgba(230,0,80,0.4);">
                            <i class="fa-solid fa-play feat-icon-gem" style="color: #ff0050; animation: pulse 2s infinite;"></i>
                        </div>
                        <div class="feat-title-wrap">
                            <h3 class="feat-main-title">Watch &amp; <span style="color:#ff0050;">Shop</span></h3>
                            <p class="feat-subtitle">Trending product reels</p>
                        </div>
                    </div>
                </div>

                <div class="reels-scroll">
                    <!-- Reel 1 -->
                    <div class="reel-card bg-img" style="background-image: url('https://images.unsplash.com/photo-1594035910387-fea47794261f?w=400&h=700&fit=crop')" onclick="openReel()">
                        <div class="reel-actions">
                            <div class="reel-views"><i class="fa-solid fa-play"></i> 124K</div>
                        </div>
                        <div class="reel-overlay"></div>
                        <div class="feat-price-tag"><span class="feat-tag-curr">₹</span><span class="feat-tag-num">4,100</span></div>
                        <div class="reel-play-btn"><i class="fa-solid fa-play"></i></div>
                    </div>

                    <!-- Reel 2 -->
                    <div class="reel-card bg-img" style="background-image: url('https://images.unsplash.com/photo-1623998021446-45cb9c278bc3?w=400&h=700&fit=crop')" onclick="openReel()">
                        <div class="reel-actions">
                            <div class="reel-views"><i class="fa-solid fa-play"></i> 89K</div>
                        </div>
                        <div class="reel-overlay"></div>
                        <div class="feat-price-tag"><span class="feat-tag-curr">₹</span><span class="feat-tag-num">320</span></div>
                        <div class="reel-play-btn"><i class="fa-solid fa-play"></i></div>
                    </div>

                    <!-- Reel 3 -->
                    <div class="reel-card bg-img" style="background-image: url('https://images.unsplash.com/photo-1617038220319-276d3cfab638?w=400&h=700&fit=crop')" onclick="openReel()">
                        <div class="reel-actions">
                            <div class="reel-views"><i class="fa-solid fa-play"></i> 210K</div>
                        </div>
                        <div class="reel-overlay"></div>
                        <div class="feat-price-tag"><span class="feat-tag-curr">₹</span><span class="feat-tag-num">4,200</span></div>
                        <div class="reel-play-btn"><i class="fa-solid fa-play"></i></div>
                    </div>

                    <!-- Reel 4 -->
                    <div class="reel-card bg-img" style="background-image: url('https://images.unsplash.com/photo-1599643478524-fb66f7f2b1d6?w=400&h=700&fit=crop')" onclick="openReel()">
                        <div class="reel-actions">
                            <div class="reel-views"><i class="fa-solid fa-play"></i> 55K</div>
                        </div>
                        <div class="reel-overlay"></div>
                        <div class="feat-price-tag"><span class="feat-tag-curr">₹</span><span class="feat-tag-num">1,850</span></div>
                        <div class="reel-play-btn"><i class="fa-solid fa-play"></i></div>
                    </div>
                </div>
            </div>

            <!-- Section 8: Customer Reviews -->
            <div class="section-container">
                <div class="feat-heading-bg" style="margin-bottom:16px; border:none; padding: 0 16px;">
                    <div class="feat-heading-left">
                        <div class="feat-title-wrap">
                            <h3 class="feat-main-title">Inner <span style="color:var(--gold-primary);">Circle</span></h3>
                            <p class="feat-subtitle">What our elite clients say</p>
                        </div>
                    </div>
                </div>

                <div class="reviews-marquee-wrap">
                    <div class="reviews-marquee-track">
                        <!-- Review 1 -->
                        <div class="premium-review-card">
                            <div class="pr-stars">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            </div>
                            <p class="pr-text">"The gold chronograph is absolutely stunning. Perfect premium feel. It completely exceeded my expectations."</p>
                            <div class="pr-user">
                                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop" class="pr-avatar" alt="User">
                                <div class="pr-details">
                                    <span class="pr-name">James R.</span>
                                    <span class="pr-verified"><i class="fa-solid fa-circle-check" style="color: #28c066;"></i> Verified Buyer</span>
                                </div>
                            </div>
                        </div>

                        <!-- Review 2 -->
                        <div class="premium-review-card">
                            <div class="pr-stars">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            </div>
                            <p class="pr-text">"Incredible quality and fast shipping. The packaging itself feels like a true luxury experience. Highly recommended."</p>
                            <div class="pr-user">
                                <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop" class="pr-avatar" alt="User">
                                <div class="pr-details">
                                    <span class="pr-name">Sarah M.</span>
                                    <span class="pr-verified"><i class="fa-solid fa-circle-check" style="color: #28c066;"></i> Verified Buyer</span>
                                </div>
                            </div>
                        </div>

                        <!-- Review 1 (Copy for loop) -->
                        <div class="premium-review-card">
                            <div class="pr-stars">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            </div>
                            <p class="pr-text">"The gold chronograph is absolutely stunning. Perfect premium feel. It completely exceeded my expectations."</p>
                            <div class="pr-user">
                                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop" class="pr-avatar" alt="User">
                                <div class="pr-details">
                                    <span class="pr-name">James R.</span>
                                    <span class="pr-verified"><i class="fa-solid fa-circle-check" style="color: #28c066;"></i> Verified Buyer</span>
                                </div>
                            </div>
                        </div>

                        <!-- Review 2 (Copy for loop) -->
                        <div class="premium-review-card">
                            <div class="pr-stars">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            </div>
                            <p class="pr-text">"Incredible quality and fast shipping. The packaging itself feels like a true luxury experience. Highly recommended."</p>
                            <div class="pr-user">
                                <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop" class="pr-avatar" alt="User">
                                <div class="pr-details">
                                    <span class="pr-name">Sarah M.</span>
                                    <span class="pr-verified"><i class="fa-solid fa-circle-check" style="color: #28c066;"></i> Verified Buyer</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 8.5: Trust Promise -->
            <div class="section-container" style="margin-bottom: 30px;">
                <div class="feat-heading-bg" style="margin-bottom:16px; border:none; padding: 0 16px;">
                    <div class="feat-heading-left" style="width: 100%; justify-content: center;">
                        <div class="feat-icon-wrap" style="background: rgba(212,175,55,0.1); border-color: rgba(212,175,55,0.3); width: 36px; height: 36px; flex-shrink: 0;">
                            <i class="fa-solid fa-handshake" style="color: var(--gold-primary); font-size: 16px; animation: pulse 2s infinite;"></i>
                        </div>
                        <div class="feat-title-wrap">
                            <h3 class="feat-main-title">DesiVastra <span style="color:var(--gold-primary);">Promise</span></h3>
                            <p class="feat-subtitle">Why thousands choose us</p>
                        </div>
                    </div>
                </div>

                <div class="trust-grid">
                    <div class="trust-item">
                        <div class="trust-icon-box"><i class="fa-solid fa-truck-fast"></i></div>
                        <h4>Fast Delivery</h4>
                        <p>Express global shipping</p>
                    </div>
                    <div class="trust-item">
                        <div class="trust-icon-box"><i class="fa-solid fa-boxes-stacked"></i></div>
                        <h4>Full Stock</h4>
                        <p>Immediate availability</p>
                    </div>
                    <div class="trust-item">
                        <div class="trust-icon-box"><i class="fa-solid fa-gem"></i></div>
                        <h4>Premium Quality</h4>
                        <p>Authentic craftsmanship</p>
                    </div>
                    <div class="trust-item">
                        <div class="trust-icon-box"><i class="fa-solid fa-shield-halved"></i></div>
                        <h4>Secure Payment</h4>
                        <p>100% safe checkout</p>
                    </div>
                </div>
            </div>

            <!-- Section 9: Footer -->
            <footer class="premium-footer">
                <div class="footer-top">
                    <div class="footer-brand">
                        <h2 class="footer-logo-premium">Desi<span style="color: var(--gold-primary);">Vastra</span></h2>
                        <p class="footer-tagline">Authentic Indian Ethnic Wear. Crafted for You.</p>
                    </div>
                </div>

                <div class="footer-links-grid">
                    <div class="footer-col">
                        <h4>Explore</h4>
                        <a href="#">New Arrivals</a>
                        <a href="#">Signature Collection</a>
                        <a href="#">Trending Reels</a>
                    </div>
                    <div class="footer-col">
                        <h4>Assistance</h4>
                        <a href="#">Track Order</a>
                        <a href="#">Returns &amp; Policy</a>
                        <a href="#">Concierge</a>
                    </div>
                </div>

                <div class="footer-socials">
                    <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
                </div>

                <div class="footer-bottom">
                    <div class="footer-legal">
                        <a href="#">Privacy Policy</a>
                        <span class="dot-separator">•</span>
                        <a href="#">Terms of Service</a>
                    </div>
                    <p class="copyright-text">© 2026 DesiVastra. All rights reserved.</p>
                </div>
            </footer>
            
            <div class="pb-100"></div>
        </main>

        <nav class="bottom-nav">
            <a href="index.php" class="nav-item active">
                <i class="fa-solid fa-house"></i><span>Home</span>
            </a>
            <div class="nav-item" onclick="openRightDrawer('wishlist')">
                <i class="fa-regular fa-heart"></i><span>Wishlist</span>
            </div>
            <a href="shop.php" class="nav-item center-hex" aria-label="Shop">
                <div class="hex-bg"><i class="fa-solid fa-gem"></i></div>
            </a>
            <div class="nav-item" onclick="openRightDrawer('cart')">
                <div class="icon-badge-wrapper">
                    <i class="fa-solid fa-bag-shopping"></i><span class="cart-badge" id="cart-badge-mobile">0</span>
                </div>
                <span>Cart</span>
            </div>
            <div class="nav-item" onclick="handleAccountClick()">
                <i class="fa-regular fa-user"></i><span>Account</span>
            </div>
        </nav>
    </div>
    <script src="assets/js/mockData.js?v=3"></script>
    <script src="assets/js/global.js?v=3"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let loggedIn = localStorage.getItem('isLoggedIn');
            if(loggedIn === 'true') document.getElementById('guest-greeting').innerText = 'DesiVastra Member';
        });

        // ── Hero Slider Controller ──
        let heroIndex = 0;

        function heroGoTo(n) {
            const slides = document.querySelectorAll('#heroSlider .slide');
            if(slides.length === 0) return;
            slides[heroIndex].classList.remove('active');
            heroIndex = (n + slides.length) % slides.length;
            slides[heroIndex].classList.add('active');
        }

        function heroSlide(dir) { 
            heroGoTo(heroIndex + dir); 
        }
    </script>
</body>
</html>
