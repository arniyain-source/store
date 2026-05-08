<header class="global-header">
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
            <a href="index.php" class="logo">Arniya<span class="gold">Hub</span></a>
            <span class="logo-badge mobile-only">LUXURY</span>
        </div>

        <!-- Desktop: Amazon-style Search with Photo Search -->
        <div class="desktop-search">
            <select class="search-cat-select" id="desktop-search-cat" aria-label="Search Category">
                <option>All</option>
                <option>Watches</option>
                <option>Jewelry</option>
                <option>Accessories</option>
                <option>Perfumes</option>
            </select>
            <div style="position:relative; flex:1; display:flex;">
                <input type="text" id="desktop-search-input" placeholder="Search ARNiya Smart Hub...">
                <button type="button" class="photo-search-trigger" onclick="triggerPhotoSearch()" title="Search by Photo" style="background:none; border:none; position:absolute; right:10px; top:50%; transform:translateY(-50%); color:#666; cursor:pointer; font-size:18px;">
                    <i class="fa-solid fa-camera"></i>
                </button>
            </div>
            <button id="desktop-search-btn" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
        </div>

        <!-- Desktop Nav Items -->
        <div class="desktop-nav-items">
            <div class="d-nav-item d-location">
                <span class="small"><i class="fa-solid fa-location-dot" style="color:var(--gold-light)"></i> Deliver to</span>
                <span class="bold">India 🇮🇳</span>
            </div>
            <div class="d-nav-item" onclick="window.location.href='login.php'">
                <span class="small">Hello, Guest</span>
                <span class="bold">Account &amp; Lists <i class="fa-solid fa-caret-down" style="font-size:10px"></i></span>
            </div>
            <div class="d-nav-item" onclick="window.location.href='dashboard.php'">
                <span class="small">Returns</span>
                <span class="bold">&amp; Orders</span>
            </div>
            <div class="d-nav-item cart-btn" onclick="openRightDrawer('cart')">
                <span class="cart-badge" id="cart-badge-count">0</span>
                <i class="fa-solid fa-cart-shopping fa-2x"></i>
                <span class="bold" style="margin-bottom:2px;">Cart</span>
            </div>
        </div>

        <!-- Mobile: Support 24/7 Button -->
        <button class="support-24-btn mobile-only" onclick="openSupportPopup()" aria-label="24/7 Support">
            <span class="support-pulse"></span>
            <i class="fa-solid fa-headset"></i>
            <span>24/7</span>
        </button>
    </div>

    <!-- Sticky Mobile Search Bar (Step 2 Improvement) -->
    <div class="mobile-sticky-search mobile-only">
        <div class="m-search-inner">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" placeholder="Search for products, SKUs, colors..." readonly onclick="window.location.href='shop.php'">
            <button type="button" class="m-photo-search-btn" onclick="triggerPhotoSearch()" aria-label="Search by Photo">
                <i class="fa-solid fa-camera"></i>
            </button>
        </div>
    </div>

    <div class="header-gold-line mobile-only"></div>

    <!-- Desktop Secondary Nav -->
    <div class="desktop-secondary-nav">
        <a href="shop.php" class="sec-nav-deals"><i class="fa-solid fa-bolt"></i> Today's Deals</a>
        <a href="shop.php">New Arrivals</a>
        <a href="shop.php">Best Sellers</a>
        <a href="shop.php">Watches</a>
        <a href="shop.php">Jewelry</a>
        <a href="shop.php">Accessories</a>
        <a href="shop.php" class="sec-nav-prime">✦ ARNiya Prime</a>
    </div>

    <!-- Hidden Photo Search Input -->
    <input type="file" id="photo-search-input" accept="image/*" style="display:none" onchange="handlePhotoUpload(this)">
</header>

<script>
function triggerPhotoSearch() {
    document.getElementById('photo-search-input').click();
}

function handlePhotoUpload(input) {
    if (input.files && input.files[0]) {
        // Show loading state or redirect immediately to results page
        // For Step 6 AI logic, this will eventually post to api/photo-search.php
        window.location.href = 'shop.php?action=photo_search&status=analyzing';
    }
}
</script>

<style>
/* Header Styles for Search Components */
.mobile-sticky-search {
    padding: 8px 12px;
    background: #0d1117;
    position: sticky;
    top: 60px;
    z-index: 999;
}

.m-search-inner {
    display: flex;
    align-items: center;
    background: #fff;
    border-radius: 8px;
    height: 40px;
    padding: 0 12px;
    gap: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.m-search-inner .search-icon {
    color: #888;
    font-size: 14px;
}

.m-search-inner input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 14px;
    color: #333;
    font-family: inherit;
}

.m-photo-search-btn {
    background: none;
    border: none;
    color: var(--gold-primary);
    font-size: 18px;
    padding: 5px;
    cursor: pointer;
}

.photo-search-trigger:hover {
    color: var(--gold-primary) !important;
}
</style>