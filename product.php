<?php
/**
 * Single Product Page - DesiVastra
 */
require_once 'includes/functions.php';

// Get Product ID from URL
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fallback images per category
$catFallbacks = [
    'Sarees'   => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=800&q=80',
    'Suits'    => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=800&q=80',
    'Kurtis'   => 'https://images.unsplash.com/photo-1594938298603-c8148c4b4281?w=800&q=80',
    'Lehengas' => 'https://images.unsplash.com/photo-1617627143750-d86bc21e42bb?w=800&q=80',
    'Dupattas' => 'https://images.unsplash.com/photo-1550639524-a5b5c4fe5e90?w=800&q=80',
    'default'  => 'https://images.unsplash.com/photo-1585487000160-6ebcfceb0d03?w=800&q=80',
];

// Fetch Product Data
$db = getDB();
$stmt = $db->prepare("SELECT p.*, c.name as category_name 
                      FROM products p 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      WHERE p.id = ? AND p.is_active = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();

// Redirect if not found
if (!$product) {
    header('Location: shop.php');
    exit;
}

// Product image with fallback
$pimg = !empty($product['main_image']) 
    ? $product['main_image'] 
    : ($catFallbacks[$product['category_name'] ?? ''] ?? $catFallbacks['default']);

// Parse sizes and colors
$sizes  = json_decode($product['sizes']  ?: '[]', true) ?: [];
$colors = json_decode($product['colors'] ?: '[]', true) ?: [];

// Related products (same category, different ID)
$related = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1 ORDER BY RANDOM() LIMIT 6");
$related->execute([$product['category_id'], $productId]);
$related_products = $related->fetchAll();
// If no same-category, fallback to any products
if (empty($related_products)) {
    $rel2 = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id != ? AND p.is_active = 1 ORDER BY RANDOM() LIMIT 6");
    $rel2->execute([$productId]);
    $related_products = $rel2->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Explore premium product details, sizes, colors and features at DesiVastra.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($product['name'] ?? 'Product'); ?> - DesiVastra</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://images.unsplash.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css?v=3">
    <link rel="stylesheet" href="assets/css/product.css?v=4">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
</head>
<body>
    <div class="app-container">
        
        <header class="global-header">
            <div class="header-inner">
                <div class="header-left mobile-only" style="flex:1; justify-content:space-between">
                    <a href="shop.php" class="icon-btn" aria-label="Back to Shop"><i class="fa-solid fa-arrow-left"></i></a>
                    <div class="product-title-header truncate" style="flex:1; text-align:center; font-weight:600; margin:0 15px;"><?php echo clean(substr($product['name'] ?? 'Product', 0, 25)); ?>...</div>
                    <div class="icon-badge-wrapper" onclick="openRightDrawer('cart')" style="cursor: pointer; padding: 8px;">
                        <i class="fa-solid fa-bag-shopping" style="font-size: 20px;"></i><span class="cart-badge">0</span>
                    </div>
                </div>

                <!-- Desktop Amazon Search -->
                <div class="header-left desktop-only" style="align-items:center;">
                    <a href="index.php" class="logo" style="padding-right: 20px;">Desi<span class="gold">Vastra</span></a>
                </div>

                <div class="desktop-search">
                    <input type="text" id="shop-search-input-desktop" placeholder="Search ethnic wear, sarees, kurtis...">
                    <button id="shop-search-btn-desktop" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
                
                <div class="header-right">
                    <!-- Desktop Nav -->
                    <div class="desktop-nav-items">
                        <div class="d-nav-item" onclick="handleAccountClick()">
                            <span class="small">Hello, sign in</span>
                            <span class="bold">Account & Lists</span>
                        </div>
                        <div class="d-nav-item">
                            <span class="small">Returns</span>
                            <span class="bold">& Orders</span>
                        </div>
                        <div class="d-nav-item cart-btn" onclick="openRightDrawer('cart')">
                            <span class="cart-badge" id="&">0</span>
                            <i class="fa-solid fa-cart-shopping fa-2x"></i>
                            <span class="bold" style="margin-bottom:2px;">Cart</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- DesiVastra Secondary Nav -->
            <div class="desktop-secondary-nav">
                <div class="m-all" onclick="window.location.href='index.php'"><i class="fa-solid fa-bars"></i> All</div>
                <a href="shop.php">New Arrivals</a>
                <a href="shop.php?cat=Sarees">Sarees</a>
                <a href="shop.php?cat=Suits">Suits</a>
                <a href="shop.php?cat=Kurtis">Kurtis</a>
                <a href="shop.php?cat=Lehengas">Lehengas</a>
                <a href="shop.php?cat=Dupattas">Dupattas</a>
            </div>
        </header>

        <!-- Drawers for Cart/Wishlist access from Product Page -->
        <div class="overlay" id="right-overlay" onclick="closeRightDrawer(); if(typeof closeReview === 'function') closeReview();"></div>
        <div id="right-drawer" class="drawer-right">
            <div class="drawer-header-right">
                <h3 id="drawer-title" style="font-size: 18px;">Drawer</h3>
                <button class="close-btn-styled" onclick="closeRightDrawer()" aria-label="Close Drawer"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="drawer-content" class="drawer-body"></div>
        </div>

        <main class="scroll-area flex-column">
            <!-- Primary Product Showcase -->
            <div class="product-main-showcase">
                <div class="product-wrapper">
                    
                    <!-- Left: Gallery Architecture -->
                    <div class="product-gallery">
                        <div class="thumbnail-column">
                            <div class="thumb active" style="background-image: url('<?php echo $pimg; ?>?w=100')" onclick="updateMainImg(this, '<?php echo $pimg; ?>')"></div>
                            <?php 
                            $gallery_imgs = json_decode($product['gallery_images'] ?? '[]', true) ?: [];
                            foreach(array_slice($gallery_imgs, 0, 3) as $gimg): ?>
                            <div class="thumb" style="background-image: url('<?php echo clean($gimg); ?>?w=100')" onclick="updateMainImg(this, '<?php echo clean($gimg); ?>')"></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="main-img-container">
                            <div class="p-exclusive-badge"><?php echo clean($product['category_name'] ?? 'Premium'); ?> Collection</div>
                            <div id="main-product-img" class="p-slide bg-img" style="background-image: url('<?php echo $pimg; ?>')"></div>
                            <?php if (!empty($product['video_url'])): ?>
                            <button class="watch-video-btn" onclick="openReel()">
                                <i class="fa-solid fa-play"></i> Watch Video
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- â”€â”€ MOBILE HORIZONTAL THUMBNAIL GALLERY STRIP â”€â”€ -->
                    <div class="mobile-gallery-strip mobile-only" id="mobile-gallery-strip">
                        <button class="mg-thumb mg-thumb-active" data-img="<?php echo $pimg; ?>"
                            style="background-image:url('<?php echo $pimg; ?>?w=120&q=80')"
                            onclick="switchMobileGallery(this)" aria-label="Product image 1"></button>
                        <?php foreach(array_slice($gallery_imgs, 0, 3) as $gi): ?>
                        <button class="mg-thumb" data-img="<?php echo clean($gi); ?>"
                            style="background-image:url('<?php echo clean($gi); ?>?w=120&q=80')"
                            onclick="switchMobileGallery(this)" aria-label="Product image"></button>
                        <?php endforeach; ?>
                        <!-- Video Thumb -->
                        <button class="mg-thumb mg-thumb-video" onclick="openReel()" aria-label="Watch product video">
                            <div class="mg-video-overlay">
                                <i class="fa-solid fa-play"></i>
                                <span>Video</span>
                            </div>
                            <div class="mg-thumb-bg" style="background-image:url('<?php echo $pimg; ?>?w=120&q=80')"></div>
                        </button>
                    </div>

                    <!-- Center: Product Discovery (Title, Variants, Specs) -->
                    <div class="product-info-column">
                        <div class="p-details ">
                            <h1 class="p-title"><?php echo htmlspecialchars($product["name"] ?? "Product Name"); ?></h1>
                            
                            <div class="p-rating">
                                <div class="stars-pill">4.8 <i class="fa-solid fa-star"></i></div>
                                <span class="review-count">| 1,240 Verified Reviews</span>
                            </div>

                            <div class="p-price-box">
                                <span class="p-main-price">â‚¹<?php echo number_format($product["price"] ?? 0); ?></span>
                                <span class="p-old-price">â‚¹2,999</span>
                                <span class="p-discount-tag">15% OFF</span>
                            </div>

                            <div class="variant-selector">
                                <?php if (!empty($sizes)): ?>
                                <h3>Select Size</h3>
                                <div class="size-options">
                                    <?php foreach($sizes as $idx => $sz):
                                        $szLabel = is_array($sz) ? ($sz['name'] ?? $sz['value'] ?? '') : $sz;
                                    ?>
                                    <div class="s-pill <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="selectSize(this, '<?php echo addslashes($szLabel); ?>')"><?php echo clean($szLabel); ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <h3>Select Size</h3>
                                <div class="size-options">
                                    <div class="s-pill active" onclick="selectSize(this, 'Free Size')">Free Size</div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="variant-selector">
                                <?php if (!empty($colors)): ?>
                                <h3>Select Color</h3>
                                <div class="colors">
                                    <?php foreach($colors as $idx => $clr):
                                        $hex   = is_array($clr) ? ($clr['hex'] ?? '#e5c100') : '#e5c100';
                                        $cname = is_array($clr) ? ($clr['name'] ?? '') : $clr;
                                    ?>
                                    <div class="v-color <?php echo $idx === 0 ? 'active' : ''; ?>" style="background: <?php echo $hex; ?>" title="<?php echo clean($cname); ?>" onclick="selectColor(this, '<?php echo addslashes($cname); ?>')"></div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Mobile Flow CTA (Dual-Action Boutique Layout) -->
                            <div class="mobile-flow-cta-wrapper mobile-only" id="mobile-trigger-btn">
                                <div class="m-flow-upper-actions">
                                    <button type="button" class="m-flow-icon-btn" onclick="shareProduct()"><i class="fa-solid fa-share-nodes"></i> Share</button>
                                    <button type="button" class="m-flow-icon-btn product-wishlist-btn" data-product-id="<?php echo $product["id"]; ?>" data-wishlist-button="true" onclick="toggleProductWishlist(this)"><i class="fa-regular fa-heart"></i> Wishlist</button>
                                </div>
                                <div class="m-flow-button-row">
                                    <button type="button" class="m-flow-btn gold flex-1"
                                        onclick="addToCartAPI(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, '<?php echo clean($product['main_image']); ?>', '<?php echo clean($product['sku']); ?>', window._selectedSize||'', window._selectedColor||'', 1)">
                                        Add to Cart
                                    </button>
                                    <button type="button" class="m-flow-btn orange flex-1"
                                        onclick="addToCartAPI(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, '<?php echo clean($product['main_image']); ?>', '<?php echo clean($product['sku']); ?>', window._selectedSize||'', window._selectedColor||'', 1); setTimeout(()=>window.location.href='checkout.php',400);">
                                        Buy Now
                                    </button>
                                </div>
                            </div>

                            <div class="product-specifications">
                                <h3>Product Description</h3>
                                <p><?php echo nl2br(clean($product['description'] ?? 'Premium quality product from DesiVastra collection.')); ?></p>
                            </div>

                            <!-- Integrated Logistics Section -->
                            <div class="bb-delivery-check ">
                                <div class="del-label">
                                    <i class="fa-solid fa-truck-fast gold"></i>
                                    <span>Delivery & Logistics</span>
                                </div>
                                <div class="del-input-group">
                                    <input type="text" id="pincode-input" placeholder="Enter 6-digit India Pincode" maxlength="6">
                                    <button class="del-check-btn" onclick="checkDelivery()">Check</button>
                                </div>
                                <div class="del-est-msg" style="display: none;"></div>
                            </div>
                        </div>

                        <!-- Mobile-Only Reviews Snapshot -->
                        <div class="reviews-snapshot-section mobile-only ">
                            <div class="snapshot-header"><h3>Customer Feedback</h3></div>
                            <div class="rating-display">
                                <span class="rating-num">4.8</span>
                                <div class="rating-stars">
                                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star-half-stroke"></i>
                                </div>
                            </div>
                            <button class="write-review-btn" onclick="openReview()">Write Review</button>
                        </div>

                        <!-- Secure Checkout Banner (Above Reviews) -->
                        <div class="secure-checkout-banner-wide">
                            <div class="sc-banner-inner">
                                <div class="sc-b-left">
                                    <i class="fa-solid fa-shield-halved gold"></i>
                                    <div class="sc-b-text">
                                        <span class="title">Secure DesiVastra Checkout</span>
                                        <span class="sub">256-bit SSL Encrypted Transaction</span>
                                    </div>
                                </div>
                                <div class="sc-b-right">
                                    <div class="payment-method-icon"><i class="fa-brands fa-google-pay"></i></div>
                                    <div class="payment-method-icon"><span class="upi-text">PhonePe</span></div>
                                    <div class="payment-method-icon"><span class="upi-text">Paytm</span></div>
                                    <div class="payment-method-icon"><i class="fa-solid fa-building-columns"></i></div>
                                </div>
                            </div>
                        </div>

                        <div class="p-reviews ">
                            <div class="reviews-header-row">
                                <h3>Genuine Experiences</h3>
                                <div class="review-nav desktop-only">
                                    <button class="rev-nav-btn prev" aria-label="Previous Review"><i class="fa-solid fa-chevron-left"></i></button>
                                    <button class="rev-nav-btn next" aria-label="Next Review"><i class="fa-solid fa-chevron-right"></i></button>
                                </div>
                            </div>
                            
                            <div class="reviews-slider-viewport">
                                <div class="reviews-track" id="reviewsTrack">
                                    <!-- Slide 1 -->
                                    <div class="review-card-premium">
                                        <div class="rev-header">
                                            <div class="rev-top-row">
                                                <div class="stars-pill mini">5 <i class="fa-solid fa-star"></i></div>
                                                <div class="verified-badge"><i class="fa-solid fa-circle-check"></i> VERIFIED</div>
                                            </div>
                                            <span class="rev-name">John D.</span>
                                        </div>
                                        <p class="rev-text">"Stunning design. One of the few pieces that looks better in person than in the photos."</p>
                                    </div>
                                    <!-- Slide 2 -->
                                    <div class="review-card-premium">
                                        <div class="rev-header">
                                            <div class="rev-top-row">
                                                <div class="stars-pill mini">5 <i class="fa-solid fa-star"></i></div>
                                                <div class="verified-badge"><i class="fa-solid fa-circle-check"></i> VERIFIED</div>
                                            </div>
                                            <span class="rev-name">Sarah K.</span>
                                        </div>
                                        <p class="rev-text">"The ethnic wear is gorgeous. A true statement piece for my wardrobe."</p>
                                    </div>
                                    <!-- Slide 3 -->
                                    <div class="review-card-premium">
                                        <div class="rev-header">
                                            <div class="rev-top-row">
                                                <div class="stars-pill mini">5 <i class="fa-solid fa-star"></i></div>
                                                <div class="verified-badge"><i class="fa-solid fa-circle-check"></i> VERIFIED</div>
                                            </div>
                                            <span class="rev-name">Michael R.</span>
                                        </div>
                                        <p class="rev-text">"Exceeded all expectations. The fit and finish of this piece are perfect."</p>
                                    </div>
                                    <!-- Slide 4 -->
                                    <div class="review-card-premium">
                                        <div class="rev-header">
                                            <div class="rev-top-row">
                                                <div class="stars-pill mini">5 <i class="fa-solid fa-star"></i></div>
                                                <div class="verified-badge"><i class="fa-solid fa-circle-check"></i> VERIFIED</div>
                                            </div>
                                            <span class="rev-name">Elena V.</span>
                                        </div>
                                        <p class="rev-text">"Packaging was as beautiful as the product. DesiVastra is my new go-to for ethnic wear."</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Sidebar: Buy Box & Reviews Snapshot (Desktop Only) -->
                    <div class="product-sidebar desktop-only">
                        <div class="desktop-buy-box">
                            <div class="buy-box-inner">
                                <div class="bb-price-header">
                                    <span class="currency">&#8377;</span>
                                    <span class="amount"><?php echo number_format($product['price'] ?? 0); ?></span>
                                </div>
                                <?php if (!empty($product['old_price']) && $product['old_price'] > $product['price']): ?>
                                <div style="font-size:13px; color:var(--text-secondary); margin-top:4px;">
                                    <s>&#8377;<?php echo number_format($product['old_price']); ?></s>
                                    <span style="color:var(--gold-primary); margin-left:6px;"><?php echo round((1 - $product['price']/$product['old_price'])*100); ?>% OFF</span>
                                </div>
                                <?php endif; ?>
                                <div class="bb-stock">
                                    <div class="status-dot-pulse"></div>
                                    <span>In Stock. Ready to Ship.</span>
                                </div>
                                
                                <div class="bb-actions">
                                    <button type="button" class="bb-action-btn gold" id="add-to-cart-btn"
                                        onclick="addToCartAPI(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, '<?php echo clean($product['main_image']); ?>', '<?php echo clean($product['sku']); ?>', window._selectedSize||'', window._selectedColor||'', 1)">
                                        <i class="fa-solid fa-cart-plus"></i> Add to Cart
                                    </button>
                                    <button type="button" class="bb-action-btn white" id="buy-now-btn"
                                        onclick="addToCartAPI(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, '<?php echo clean($product['main_image']); ?>', '<?php echo clean($product['sku']); ?>', window._selectedSize||'', window._selectedColor||'', 1); setTimeout(()=>window.location.href='checkout.php', 400);">
                                        Buy Now
                                    </button>
                                </div>
                                
                                <div class="secure-checkout-compact">
                                    <div class="scc-line">
                                        <i class="fa-solid fa-shield-halved gold"></i>
                                        <span>Secure DesiVastra Checkout</span>
                                    </div>
                                    <div class="scc-icons">
                                        <i class="fa-brands fa-cc-visa"></i>
                                        <i class="fa-brands fa-cc-mastercard"></i>
                                        <i class="fa-brands fa-google-pay"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="reviews-snapshot-section">
                            <div class="snapshot-header"><h3>Customer Reviews</h3></div>
                            <div class="rating-display">
                                <span class="rating-num">4.8</span>
                                <div class="rating-stars">
                                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star-half-stroke"></i>
                                </div>
                                <span class="rating-total">1,240 ratings</span>
                            </div>

                            <div class="rating-bars">
                                <div class="rating-row"><span>5 star</span><div class="bar-container"><div class="bar-fill" style="width: 82%"></div></div><span class="pct">82%</span></div>
                                <div class="rating-row"><span>4 star</span><div class="bar-container"><div class="bar-fill" style="width: 12%"></div></div><span class="pct">12%</span></div>
                                <div class="rating-row"><span>3 star</span><div class="bar-container"><div class="bar-fill" style="width: 4%"></div></div><span class="pct">4%</span></div>
                            </div>
                            
                            <div class="review-cta">
                                <button class="write-review-btn" onclick="openReview()">Write a product review</button>
                            </div>
                        </div>
                    </div>
            </div> <!-- End Product Grid -->

            <!-- Related Products Section (Featured Style) -->
            <div class="related-products-section ">
                <div class="feat-heading-bg">
                    <div class="feat-heading-left">
                        <div class="feat-icon-wrap">
                            <i class="fa-solid fa-gem feat-icon-gem"></i>
                        </div>
                        <div class="feat-title-wrap">
                            <h3 class="feat-main-title">Related <span class="feat-title-shine">Boutique</span></h3>
                            <p class="feat-subtitle">Complements your current selection</p>
                        </div>
                    </div>
                    <div class="feat-heading-right">
                        <span class="feat-count-badge"><i class="fa-solid fa-layer-group"></i> 8 Items</span>
                    </div>
                </div>
                
                <div class="horizontal-scroll related-grid">
                    <?php foreach($related_products as $rp):
                        $rimg = !empty($rp['main_image']) 
                            ? $rp['main_image'] 
                            : ($catFallbacks[$rp['category_name'] ?? ''] ?? $catFallbacks['default']);
                    ?>
                    <a href="product.php?id=<?php echo $rp['id']; ?>" class="related-card">
                        <div class="r-card-img bg-img" style="background-image: url('<?php echo clean($rimg); ?>')">
                            <?php if ($rp['is_new_arrival']): ?><span class="r-badge">New</span><?php elseif($rp['is_top_selling']): ?><span class="r-badge">Hot</span><?php endif; ?>
                        </div>
                        <div class="r-card-info">
                            <h4 class="r-name"><?php echo clean($rp['name']); ?></h4>
                            <div class="r-row">
                                <span class="r-price">&#8377;<?php echo number_format($rp['price']); ?></span>
                                <span class="r-rating"><?php echo number_format((float)($rp['rating'] ?? 4.5), 1); ?> <i class="fa-solid fa-star"></i></span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php if (empty($related_products)): ?>
                    <p style="color:var(--text-secondary); padding:20px;">No related products found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pb-100"></div>
        </main>

        <!-- Video Reel Viewer -->
        <div id="video-reel-viewer" class="overlay" style="z-index: 5000">
            <button class="close-reel" onclick="closeReel()" aria-label="Close Video"><i class="fa-solid fa-xmark"></i></button>
            <div class="reel-container">
                <?php $vid = !empty($product['video_url']) ? $product['video_url'] : 'https://assets.mixkit.co/videos/preview/mixkit-woman-spinning-in-a-beautiful-dress-41314-large.mp4'; ?>
                <video id="reel-video" loop autoplay muted src="<?php echo clean($vid); ?>"></video>
                <div class="reel-info">
                    <h3>The Artisanship</h3>
                    <p>Experience the finest ethnic wear crafted with passion.</p>
                </div>
            </div>
        </div>

        <!-- Consolidated Smart Sticky Footer (Mobile Only) -->
        <div class="mobile-sticky-cta mobile-only">
            <div class="m-sticky-actions">
                <button type="button" class="m-sticky-icon-btn" onclick="shareProduct()" aria-label="Share Product"><i class="fa-solid fa-share-nodes"></i></button>
                <button type="button" class="m-sticky-icon-btn product-wishlist-btn" data-product-id="<?php echo $product["id"]; ?>" data-wishlist-button="true" onclick="toggleProductWishlist(this)" aria-label="Add to Wishlist"><i class="fa-regular fa-heart"></i></button>
            </div>
            <button type="button" class="m-cta-btn m-cta-add">
                Add to Cart
            </button>
        </div>

        <!-- Notification Toast (Shared) -->
        <div id="toast-container"></div>

        <!-- Review Form Drawer -->
        <div id="review-drawer" class="drawer-bottom review-form-drawer">
            <div class="drawer-handle"></div>
            
            <div class="review-form-header">
                <div class="header-main">
                    <i class="fa-solid fa-pen-nib gold"></i>
                    <h3>Experience Review</h3>
                </div>
                <button class="close-review-btn" onclick="closeReview()" aria-label="Close Review"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="review-form-content px-20">
                <div id="review-input-step">
                    <div class="star-rating-select">
                        <i class="fa-solid fa-star" data-index="1" onclick="setRating(1)"></i>
                        <i class="fa-solid fa-star" data-index="2" onclick="setRating(2)"></i>
                        <i class="fa-solid fa-star" data-index="3" onclick="setRating(3)"></i>
                        <i class="fa-solid fa-star" data-index="4" onclick="setRating(4)"></i>
                        <i class="fa-solid fa-star" data-index="5" onclick="setRating(5)"></i>
                    </div>
                    <p class="rating-label">Share your satisfaction level</p>
                    
                    <div class="input-group-premium">
                        <textarea id="review-text" placeholder="Tell us about the craftsmanship, feel, and quality..."></textarea>
                        <div class="textarea-glow"></div>
                    </div>
                    
                    <div class="photo-upload-grid">
                        <label for="review-img-input" class="upload-card">
                            <div class="uc-inner">
                                <i class="fa-solid fa-camera-retro"></i>
                                <span>Add Showcase Photos</span>
                                <small>Max 3 photos (JPG, PNG)</small>
                            </div>
                        </label>
                        <input type="file" id="review-img-input" hidden multiple>
                    </div>

                    <div class="review-footer-actions">
                        <button class="submit-review-premium" onclick="submitReview()">
                            <span>Submit Review</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Success State (Hidden by default) -->
                <div id="review-success-step" class="success-anim-container" style="display: none;">
                    <div class="success-icon-wrap">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h2>Feedback Received!</h2>
                    <p>Your premium review of the <strong id="review-success-product"><?php echo htmlspecialchars($product['name'] ?? 'product'); ?></strong> has been submitted for verification.</p>
                    <button class="gold-btn full-width" style="margin-top: 30px" onclick="closeReview()">Back to Product</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/global.js?v=4"></script>
    <script>
    // Inject real DB products so product.js getProductById() works correctly
    <?php
    function buildJsProduct(array $p, array $fallbacks): array {
        $cat    = $p['category_name'] ?? 'Uncategorized';
        $img    = !empty($p['main_image']) ? $p['main_image'] : ($fallbacks[$cat] ?? $fallbacks['default']);
        $sizes  = json_decode($p['sizes']  ?: '[]', true) ?: [];
        $colors = json_decode($p['colors'] ?: '[]', true) ?: [];
        $sizeVals = array_map(fn($s) => is_array($s) ? ($s['name'] ?? '') : (string)$s, $sizes);
        
        // Build gallery images
        $gallery = json_decode($p['images'] ?? '[]', true) ?: [];
        $allImages = array_merge([$img], $gallery);
        
        // Build features from DB or default
        $dbFeatures = json_decode($p['features'] ?? '[]', true) ?: [];
        if (empty($dbFeatures)) {
            $dbFeatures = [
                ['icon' => 'fa-solid fa-shield-halved', 'label' => 'Quality Assurance'],
                ['icon' => 'fa-solid fa-droplet', 'label' => 'Wash Care Safe'],
                ['icon' => 'fa-solid fa-gem', 'label' => 'Premium Fabric'],
                ['icon' => 'fa-solid fa-hand-holding-heart', 'label' => 'Handcrafted']
            ];
        }
        
        // Fetch real reviews from DB if available, else fallback to standard DesiVastra reviews
        global $db;
        $testimonials = [];
        try {
            if ($db) {
                $revStmt = $db->prepare("SELECT customer_name as name, rating, review as text FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 4");
                $revStmt->execute([$p['id']]);
                $testimonials = $revStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch(Exception $e) {}
        
        if (empty($testimonials)) {
            $testimonials = [
                ['name' => 'Priya S.', 'rating' => 5, 'text' => 'Absolutely stunning quality! The fabric feels premium and the fit is perfect.'],
                ['name' => 'Neha R.', 'rating' => 5, 'text' => 'Beautiful design. It looks even better in person than in the photos.'],
                ['name' => 'Anjali M.', 'rating' => 4, 'text' => 'Loved the collection! Highly recommend DesiVastra for ethnic wear.']
            ];
        }

        return [
            'id'           => (int)$p['id'],
            'name'         => $p['name'],
            'sku'          => $p['sku'] ?? 'DV-' . $p['id'],
            'cat'          => $cat,
            'price'        => (float)$p['price'],
            'oldPrice'     => (float)($p['old_price'] ?? $p['price']),
            'rating'       => (float)($p['rating'] ?? 4.8),
            'reviews'      => (int)($p['reviews_count'] ?? 1240),
            'img'          => $img,
            'images'       => $allImages,
            'sizes'        => $sizeVals,
            'finishes'     => $colors,
            'desc'         => $p['description'] ?? $p['short_description'] ?? 'Premium quality product from DesiVastra.',
            'stockLabel'   => ((int)($p['stock'] ?? 10)) > 5 ? 'In Stock. Ready to Ship.' : 'Limited Stock',
            'topSelling'   => (bool)($p['is_top_selling'] ?? 0),
            'newArrival'   => (bool)($p['is_new_arrival'] ?? 0),
            'boutiqueOnly' => (bool)($p['is_boutique_only'] ?? 0),
            'tags'         => $p['tags'] ?? '',
            'deliveryDays' => [(int)($p['delivery_min_days'] ?? 3), (int)($p['delivery_max_days'] ?? 7)],
            'testimonials' => $testimonials,
            'features'     => $dbFeatures,
            'reelTitle'    => $p['name'],
            'reelDescription' => $cat . ' Collection',
            'video'        => !empty($p['video_url']) ? $p['video_url'] : null,
        ];
    }
    $allForJs = [];
    $allForJs[] = buildJsProduct($product, $catFallbacks);
    foreach ($related_products as $rp) { $allForJs[] = buildJsProduct($rp, $catFallbacks); }
    ?>
    const mockProducts = <?php echo json_encode($allForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    
    function getProductById(id) {
        const numericId = Number(id);
        return mockProducts.find((product) => product.id === numericId) || null;
    }

    function getRelatedProducts(id, limit = 4) {
        return mockProducts.filter((product) => product.id !== Number(id)).slice(0, limit);
    }
    </script>
    <script src="assets/js/product.js?v=4"></script>
    <script>
        // selectSize / selectColor bridge for any static onclick in PHP HTML output
        function selectSize(el, size) {
            document.querySelectorAll('.s-pill').forEach(p => p.classList.remove('active'));
            el.classList.add('active');
            window._selectedSize = size;
        }
        function selectColor(el, color) {
            document.querySelectorAll('.v-color').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            window._selectedColor = color;
        }
    </script>
</body>
</html>

