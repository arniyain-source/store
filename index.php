<?php
/**
 * index.php - Dynamic Home Page
 * Improved UI/UX while preserving Luxury Gold Brand Identity
 */
require_once 'includes/functions.php';

// Prepare variables for head.php
$pageTitle = "DesiVastra - Luxury Fashion & Accessories";
$extraCSS = "assets/css/home.css";

include 'templates/head.php';
?>

<div class="app-container">
    <?php include 'templates/header.php'; ?>

    <main class="scroll-area flex-column">
        
        <!-- Section 1: Hero Slider (Logic Ready) -->
        <div class="hero-slider" id="heroSlider">
            <?php
            // Placeholder: In a real scenario, this would come from a 'banners' table
            $slides = [
                [
                    'badge' => '🔥 Limited Edition',
                    'tag' => 'New Collection',
                    'title' => 'The Obsidian<br>Collection',
                    'desc' => 'Luxury redefined. Crafted for the few.',
                    'img' => 'https://images.unsplash.com/photo-1617305988165-27f940898eb2?auto=format&fit=crop&w=1200&q=80',
                    'link' => 'shop.php'
                ],
                [
                    'badge' => '⭐ Best Seller',
                    'tag' => 'Watches',
                    'title' => 'Gold Chronograph<br>Edition',
                    'desc' => 'Timeless precision. Pure gold finish.',
                    'img' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=1200&q=80',
                    'link' => 'product.php?id=1'
                ],
                [
                    'badge' => '💎 Premium Pick',
                    'tag' => 'Jewelry',
                    'title' => 'Signature Platinum<br>Ring',
                    'desc' => 'Wear your story. Own the moment.',
                    'img' => 'https://images.unsplash.com/photo-1599643478524-fb66f7f2b1d6?auto=format&fit=crop&w=1200&q=80',
                    'link' => 'product.php?id=2'
                ]
            ];

            foreach ($slides as $index => $slide): ?>
                <div class="slide bg-img <?php echo $index === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo $slide['img']; ?>')">
                    <div class="slide-badge"><?php echo $slide['badge']; ?></div>
                    <div class="slide-content">
                        <span class="slide-tag"><?php echo $slide['tag']; ?></span>
                        <h2><?php echo $slide['title']; ?></h2>
                        <p><?php echo $slide['desc']; ?></p>
                        <div class="slide-actions">
                            <a href="<?php echo $slide['link']; ?>"><button class="gold-btn">Shop Now</button></a>
                            <a href="shop.php"><button class="slide-ghost-btn">Explore All</button></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="slide-progress"><div class="slide-progress-bar" id="slideProgressBar"></div></div>
        </div>

        <!-- Section 2: App-Style Categories -->
        <div class="cat-section">
            <div class="cat-section-header">
                <div class="cat-sec-left">
                    <span class="cat-sec-icon"><i class="fa-solid fa-layer-group"></i></span>
                    <h3>Shop by Category</h3>
                </div>
                <a href="shop.php" class="cat-view-all">View All <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="category-slider">
                <?php
                // Placeholder: Fetch from categories table
                $categories = [
                    ['name' => 'Watches', 'img' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=200&q=80', 'emoji' => '⌚'],
                    ['name' => 'Jewelry', 'img' => 'https://images.unsplash.com/photo-1599643478524-fb66f7f2b1d6?w=200&q=80', 'emoji' => '💍'],
                    ['name' => 'Accessories', 'img' => 'https://images.unsplash.com/photo-1623998021446-45cb9c278bc3?w=200&q=80', 'emoji' => '🕶'],
                    ['name' => 'Perfumes', 'img' => 'https://images.unsplash.com/photo-1594035910387-fea47794261f?w=200&q=80', 'emoji' => '🌸'],
                    ['name' => 'Bags', 'img' => 'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=200&q=80', 'emoji' => '👜']
                ];

                foreach ($categories as $cat): ?>
                    <a href="shop.php" class="cat-item">
                        <div class="cat-icon-wrap">
                            <div class="cat-icon bg-img" style="background-image: url('<?php echo $cat['img']; ?>')"></div>
                            <span class="cat-emoji"><?php echo $cat['emoji']; ?></span>
                        </div>
                        <span class="cat-label"><?php echo $cat['name']; ?></span>
                    </a>
                <?php endforeach; ?>
                
                <a href="shop.php" class="cat-item">
                    <div class="cat-icon-wrap cat-icon-wrap--sale">
                        <div class="cat-icon" style="background: linear-gradient(135deg,#cf6679,#8c1c30); display:flex; align-items:center; justify-content:center; font-size:22px;">🔥</div>
                        <span class="cat-badge-sale">HOT</span>
                    </div>
                    <span class="cat-label" style="color:var(--danger);">Sale</span>
                </a>
            </div>
        </div>

        <!-- Section 3: Trending Products -->
        <div class="section-container trend-section">
            <div class="trend-heading-bg">
                <div class="trend-heading-left">
                    <div class="trend-icon-wrap"><i class="fa-solid fa-fire trend-icon-fire"></i></div>
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
                <?php
                // Placeholder loop for Trending Products
                for($i = 1; $i <= 5; $i++): ?>
                    <a href="product.php" class="trend-card">
                        <div class="trend-img-wrap">
                            <div class="trend-img bg-img" style="background-image: url('https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&q=80')"></div>
                            <span class="trend-badge hot">🔥 Hot</span>
                            <div class="trend-rating-pill"><i class="fa-solid fa-star"></i> 4.9 &middot; 2.1k</div>
                            <button class="trend-fav" aria-label="Add to Favorites"><i class="fa-regular fa-heart"></i></button>
                        </div>
                        <div class="trend-info">
                            <p class="trend-name">Premium Luxury Item <?php echo $i; ?></p>
                            <div class="trend-meta-row">
                                <span class="trend-sku">ARN-00<?php echo $i; ?></span>
                                <span class="trend-clr-count">Multiple Colours</span>
                            </div>
                            <div class="trend-price-row">
                                <span class="trend-price"><span class="trend-curr">₹</span>2,499</span>
                                <span class="trend-disc"><i class="fa-solid fa-arrow-trend-down"></i> 19% off</span>
                            </div>
                        </div>
                    </a>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Section 4: Promo Banner -->
        <div class="promo-banner-wrap">
            <div class="promo-slider" id="promoSlider">
                <a href="shop.php" class="promo-slide">
                    <div class="promo-photo bg-img" style="background-image:url('https://images.unsplash.com/photo-1617038220319-276d3cfab638?w=900&q=85')"></div>
                </a>
            </div>
        </div>

        <!-- Section 5: Featured Products -->
        <div class="section-container feat-section dark-bg-block">
            <div class="feat-heading-bg">
                <div class="feat-heading-left">
                    <div class="feat-icon-wrap"><i class="fa-solid fa-gem feat-icon-gem"></i></div>
                    <div class="feat-title-wrap">
                        <h3 class="feat-main-title">Featured <span class="feat-title-shine">Selection</span></h3>
                        <p class="feat-subtitle">Handpicked luxury essentials</p>
                    </div>
                </div>
                <div class="feat-heading-right">
                    <a href="shop.php" class="section-see-all">See All <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="horizontal-scroll feat-scroll">
                <?php
                // Placeholder loop for Featured Products
                for($i = 1; $i <= 5; $i++): ?>
                    <a href="product.php" class="feat-card">
                        <div class="feat-img bg-img" style="background-image: url('https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=400&q=80')">
                            <div class="feat-price-tag">
                                <span class="feat-tag-hole"></span>
                                <span class="feat-tag-curr">₹</span>
                                <span class="feat-tag-num">900</span>
                            </div>
                        </div>
                    </a>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Section 6: New Arrivals Editorial Grid -->
        <div class="section-container na-section">
            <div class="na-heading-bg">
                <div class="na-heading-left">
                    <div class="na-icon-wrap"><i class="fa-solid fa-star-of-life na-icon"></i></div>
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
            <div class="na-grid">
                <a href="product.php" class="na-card na-card-tall">
                    <div class="na-photo bg-img" style="background-image:url('https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=500&q=85')"></div>
                    <div class="na-price-tag"><span class="na-tag-curr">₹</span><span class="na-tag-num">2,499</span></div>
                </a>
                <a href="product.php" class="na-card">
                    <div class="na-photo bg-img" style="background-image:url('https://images.unsplash.com/photo-1599643478524-fb66f7f2b1d6?w=400&q=80')"></div>
                    <div class="na-price-tag"><span class="na-tag-curr">₹</span><span class="na-tag-num">1,850</span></div>
                </a>
                <a href="product.php" class="na-card">
                    <div class="na-photo bg-img" style="background-image:url('https://images.unsplash.com/photo-1617038220319-276d3cfab638?w=400&q=80')"></div>
                    <div class="na-price-tag"><span class="na-tag-curr">₹</span><span class="na-tag-num">4,200</span></div>
                </a>
                <a href="product.php" class="na-card">
                    <div class="na-photo bg-img" style="background-image:url('https://images.unsplash.com/photo-1623998021446-45cb9c278bc3?w=400&q=80')"></div>
                    <div class="na-price-tag"><span class="na-tag-curr">₹</span><span class="na-tag-num">320</span></div>
                </a>
                <a href="product.php" class="na-card">
                    <div class="na-photo bg-img" style="background-image:url('https://images.unsplash.com/photo-1594035910387-fea47794261f?w=400&q=80')"></div>
                    <div class="na-price-tag"><span class="na-tag-curr">₹</span><span class="na-tag-num">4,100</span></div>
                </a>
            </div>
        </div>

        <!-- Section 7: Signature Brand Banner -->
        <div class="sig-banner-wrap">
            <div class="sig-banner bg-img" style="background-image: url('https://images.unsplash.com/photo-1598532163257-ae3c6b2524b6?w=1000&q=85')">
                <div class="sig-overlay">
                    <div class="sig-glass-box">
                        <span class="sig-eyebrow">The Heritage Collection</span>
                        <h2 class="sig-title">Arniya <span class="sig-title-gold">Signature</span></h2>
                        <p class="sig-desc">Experience true craftsmanship & unparalleled luxury</p>
                        <a href="shop.php" class="sig-btn">
                            <span>Explore Luxury</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 8: Trust Promise -->
        <div class="section-container" style="margin-bottom: 30px;">
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

        <div class="pb-100"></div>
    </main>

    <?php include 'templates/footer.php'; ?>
</div>

<script>
    // Hero Slider Controller
    let heroIndex = 0;
    function heroGoTo(n) {
        const slides = document.querySelectorAll('#heroSlider .slide');
        if(slides.length === 0) return;
        slides[heroIndex].classList.remove('active');
        heroIndex = (n + slides.length) % slides.length;
        slides[heroIndex].classList.add('active');
    }
    // Auto slide
    setInterval(() => heroGoTo(heroIndex + 1), 5000);
</script>

</body>
</html>