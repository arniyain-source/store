<?php
/**
 * Single Product Page - DesiVastra
 */
require_once 'includes/functions.php';

// Get Product ID from URL
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

// Decode JSON fields for gallery, sizes, colors
$gallery = json_decode($product['images'] ?? '[]', true);
if (!is_array($gallery)) $gallery = [];

// Set Template Variables
$pageTitle = clean($product['name']) . " - DesiVastra";
$extraCSS = "assets/css/product.css";

include 'templates/head.php';
?>
<div class="app-container">
    <?php include 'templates/header.php'; ?>

    <main class="scroll-area product-page-wrapper">
        <div class="container">
            <!-- Breadcrumb -->
            <nav class="breadcrumb-nav">
                <a href="index.php">Home</a>
                <i class="fa-solid fa-chevron-right"></i>
                <a href="shop.php?category=<?php echo $product['category_id']; ?>"><?php echo clean($product['category_name'] ?? 'Shop'); ?></a>
                <i class="fa-solid fa-chevron-right"></i>
                <span><?php echo clean($product['name']); ?></span>
            </nav>

            <div class="product-main-grid">
                <!-- Left: Gallery System -->
                <div class="product-gallery">
                    <div class="main-image-container">
                        <img src="<?php echo clean($product['main_image']); ?>" id="main-product-img" alt="<?php echo clean($product['name']); ?>">
                        <?php if ($product['is_new_arrival']): ?>
                            <span class="badge new-badge">New Arrival</span>
                        <?php endif; ?>
                    </div>
                    <div class="thumbnail-grid" id="thumb-grid">
                        <div class="thumb-item active" onclick="updateGallery('<?php echo clean($product['main_image']); ?>', this)">
                            <img src="<?php echo clean($product['main_image']); ?>" alt="Thumbnail Main">
                        </div>
                        <?php foreach ($gallery as $img): ?>
                            <div class="thumb-item" onclick="updateGallery('<?php echo clean($img); ?>', this)">
                                <img src="<?php echo clean($img); ?>" alt="Thumbnail Gallery">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right: Product Details -->
                <div class="product-info-panel">
                    <div class="info-header">
                        <span class="sku-label">SKU: <?php echo clean($product['sku']); ?></span>
                        <h1 class="product-title"><?php echo clean($product['name']); ?></h1>
                        
                        <div class="price-box">
                            <span class="current-price">₹<?php echo number_format($product['price']); ?></span>
                            <?php if ($product['old_price'] > $product['price']): ?>
                                <span class="old-price">₹<?php echo number_format($product['old_price']); ?></span>
                                <span class="discount-perc">
                                    <?php echo round((($product['old_price'] - $product['price']) / $product['old_price']) * 100); ?>% OFF
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="tax-info">Inclusive of all taxes</p>
                    </div>

                    <!-- Description -->
                    <div class="info-section">
                        <p class="short-desc"><?php echo clean($product['short_description'] ?? ''); ?></p>
                    </div>

                    <!-- Attributes (Placeholders for now) -->
                    <div class="product-attributes">
                        <div class="attr-row"><strong>Fabric:</strong> <?php echo clean($product['fabric'] ?? 'Premium Quality'); ?></div>
                        <div class="attr-row"><strong>Work:</strong> <?php echo clean($product['work'] ?? 'Exquisite Craftsmanship'); ?></div>
                    </div>

                    <!-- Action Buttons (Desktop) -->
                    <div class="desktop-actions desktop-only">
                        <button class="gold-btn buy-now-btn" onclick="addToCart(<?php echo $product['id']; ?>)">
                            <i class="fa-solid fa-cart-shopping"></i> Add to Cart
                        </button>
                        <button class="outline-btn buy-now-btn" onclick="buyNow(<?php echo $product['id']; ?>)">
                            Buy Now
                        </button>
                    </div>

                    <!-- Full Description -->
                    <div class="info-section">
                        <h3>Product Details</h3>
                        <div class="description-text">
                            <?php echo nl2br(clean($product['description'])); ?>
                        </div>
                    </div>

                    <!-- Trust Badges -->
                    <div class="trust-badges-horizontal">
                        <div class="t-badge"><i class="fa-solid fa-truck-fast"></i> <span>Fast Delivery</span></div>
                        <div class="t-badge"><i class="fa-solid fa-shield-halved"></i> <span>Secure Payment</span></div>
                        <div class="t-badge"><i class="fa-solid fa-rotate-left"></i> <span>Easy Return</span></div>
                    </div>
                </div>
            </div>

            <!-- Similar Products Section -->
            <section class="related-section">
                <h2 class="section-title">Similar Products</h2>
                <div class="product-grid" id="similar-products">
                    <!-- Dynamic products will be loaded here by product.js -->
                </div>
            </section>
        </div>
    </main>

    <!-- Meesho-style Sticky Bottom Buttons (Mobile Only) -->
    <div class="mobile-action-bar mobile-only">
        <a href="https://wa.me/<?php echo getSetting('site_whatsapp', '919876543210'); ?>?text=I%20am%20interested%20in%20<?php echo urlencode($product['name']); ?>%20(SKU:%20<?php echo $product['sku']; ?>)" class="wa-btn">
            <i class="fa-brands fa-whatsapp"></i>
            <span>WhatsApp Inquiry</span>
        </a>
        <button class="share-btn" onclick="shareProduct()">
            <i class="fa-solid fa-share-nodes"></i>
            <span>Share Product</span>
        </button>
    </div>

    <?php 
    $extraJS = '<script src="assets/js/product.js"></script>
    <script>
        // Set current product data for sharing
        const currentProduct = {
            id: ' . $product['id'] . ',
            name: "' . addslashes(clean($product['name'])) . '",
            sku: "' . clean($product['sku']) . '",
            price: "' . $product['price'] . '",
            img: "' . clean($product['main_image']) . '",
            desc: "' . addslashes(preg_replace( "/\r|\n/", " ", clean($product['description']))) . '"
        };

        function shareProduct() {
            const shareText = `*Product Details*\n\n*SKU :-* ${currentProduct.sku}\n*Price :-* ₹${currentProduct.price}/-\n\n${currentProduct.desc}\n\nReady Stock | Premium Collection`;
            
            if (navigator.share) {
                navigator.share({
                    title: currentProduct.name,
                    text: shareText,
                    url: window.location.href
                }).catch(console.error);
            } else {
                const waUrl = `https://wa.me/?text=${encodeURIComponent(shareText)}`;
                window.open(waUrl, "_blank");
            }
        }
        
        function updateGallery(src, el) {
            document.getElementById("main-product-img").src = src;
            document.querySelectorAll(".thumb-item").forEach(item => item.classList.remove("active"));
            el.classList.add("active");
        }
    </script>';

    include 'templates/footer.php'; 
    ?>
</div>