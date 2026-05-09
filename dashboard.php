<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Manage your orders, addresses and profile on DesiVastra.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - ARNiya Smart Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
</head>
<body>
    <div class="app-container">

        <!-- Global Header (matches sitewide design) -->
        <header class="global-header">
            <div class="desktop-top-bar">
                <div class="dtb-inner">
                    <div class="dtb-left">
                        <i class="fa-solid fa-shield-halved"></i> Secure Account &nbsp;|&nbsp;
                        <i class="fa-solid fa-truck-fast"></i> Premium concierge support
                    </div>
                    <div class="dtb-right">
                        <a href="index.php">Home</a>
                        <span>|</span>
                        <a href="#" onclick="openSupportPopup(); return false;"><i class="fa-solid fa-headset"></i> Live Support 24/7</a>
                        <span>|</span>
                        <a href="#" onclick="logout(); return false;">Sign Out</a>
                    </div>
                </div>
            </div>

            <div class="header-inner">
                <div class="header-left">
                    <button class="hdr-icon-btn mobile-only" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
                    <button class="d-menu-btn desktop-only" onclick="toggleMenu()">
                        <i class="fa-solid fa-bars"></i><span>All</span>
                    </button>
                </div>
                <div class="header-center">
                    <a href="index.php" class="logo">Arniya<span class="gold">Hub</span></a>
                    <span class="logo-badge mobile-only">DASHBOARD</span>
                </div>
                <div class="desktop-search">
                    <select class="search-cat-select"><option>All</option><option>Watches</option><option>Jewelry</option><option>Accessories</option></select>
                    <input type="text" placeholder="Search ARNiya Smart Hub..." onkeydown="if(event.key==='Enter'){window.location.href='shop.php'}">
                    <button onclick="window.location.href='shop.php'"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
                <button class="support-24-btn mobile-only" onclick="openSupportPopup()">
                    <span class="support-pulse"></span>
                    <i class="fa-solid fa-headset"></i><span>24/7</span>
                </button>
                <div class="desktop-nav-items">
                    <div class="d-nav-item d-location">
                        <span class="small"><i class="fa-solid fa-location-dot" style="color:var(--gold-light)"></i> Deliver to</span>
                        <span class="bold">India 🇮🇳</span>
                    </div>
                    <div class="d-nav-item">
                        <span class="small">Hello, <span id="hdr-username">Member</span></span>
                        <span class="bold">Account &amp; Lists</span>
                    </div>
                    <div class="d-nav-item d-icon-item" onclick="openRightDrawer('wishlist')">
                        <div class="d-icon-wrapper"><i class="fa-regular fa-heart"></i></div>
                        <span class="bold">Wishlist</span>
                    </div>
                    <div class="d-nav-item cart-btn" onclick="openRightDrawer('cart')">
                        <span class="cart-badge">0</span>
                        <i class="fa-solid fa-cart-shopping fa-2x"></i>
                        <span class="bold" style="margin-bottom:2px;">Cart</span>
                    </div>
                </div>
            </div>
            <div class="header-gold-line mobile-only"></div>
            <div class="desktop-secondary-nav">
                <a href="shop.php" class="sec-nav-deals"><i class="fa-solid fa-bolt"></i> Today's Deals</a>
                <a href="shop.php">New Arrivals</a>
                <a href="shop.php">Best Sellers</a>
                <a href="shop.php">Watches</a>
                <a href="shop.php">Jewelry</a>
                <a href="shop.php">Accessories</a>
                <a href="shop.php">Gift Cards</a>
                <a href="dashboard.php" class="sec-nav-prime">✦ My Dashboard</a>
            </div>
        </header>

        <!-- Support popup (reused) -->
        <div id="support-overlay" class="support-overlay" onclick="closeSupportPopup()"></div>
        <div id="support-popup" class="support-popup">
            <div class="support-popup-header">
                <div class="support-popup-icon"><i class="fa-solid fa-headset"></i></div>
                <div><h3>Support Center</h3><p>We're online &amp; ready to help</p></div>
                <button class="close-btn-styled" onclick="closeSupportPopup()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="support-popup-status"><span class="status-dot"></span> Available 24/7 · Avg. response &lt; 2 min</div>
            <div class="support-popup-actions">
                <a href="https://wa.me/919876543210" class="support-action-btn whatsapp-btn">
                    <div class="s-btn-icon"><i class="fa-brands fa-whatsapp"></i></div>
                    <div class="s-btn-text"><span class="s-btn-title">Chat on WhatsApp</span><span class="s-btn-sub">Instant reply guaranteed</span></div>
                    <i class="fa-solid fa-chevron-right s-arrow"></i>
                </a>
                <a href="tel:+919876543210" class="support-action-btn call-btn">
                    <div class="s-btn-icon"><i class="fa-solid fa-phone"></i></div>
                    <div class="s-btn-text"><span class="s-btn-title">Call Us Now</span><span class="s-btn-sub">+91 98765 43210</span></div>
                    <i class="fa-solid fa-chevron-right s-arrow"></i>
                </a>
            </div>
            <div class="support-popup-footer"><i class="fa-solid fa-shield-halved"></i> Your privacy is protected</div>
        </div>

        <!-- Drawers (reused for menu / cart / wishlist / account) -->
        <div class="overlay" id="menu-overlay" onclick="toggleMenu()"></div>
        <div id="menu-drawer" class="drawer-left">
            <div class="drawer-header">
                <div style="display:flex; align-items:center; gap:16px;">
                    <i class="fa-solid fa-circle-user fa-2x" style="color:var(--gold-light)"></i>
                    <h2 id="guest-greeting">Arniya Member</h2>
                </div>
                <button class="close-btn-styled" onclick="toggleMenu()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="drawer-body">
                <ul class="menu-list">
                    <li class="menu-title">Browse</li>
                    <li class="menu-link" onclick="window.location.href='index.php'"><div class="m-left"><i class="fa-solid fa-house"></i> Home</div><i class="fa-solid fa-chevron-right"></i></li>
                    <li class="menu-link" onclick="window.location.href='shop.php'"><div class="m-left"><i class="fa-solid fa-bag-shopping"></i> Shop</div><i class="fa-solid fa-chevron-right"></i></li>
                    <li class="menu-title">Account</li>
                    <li class="menu-link" onclick="window.location.href='dashboard.php'"><div class="m-left"><i class="fa-regular fa-user"></i> My Dashboard</div><i class="fa-solid fa-chevron-right"></i></li>
                    <li class="menu-link" onclick="openRightDrawer('wishlist')"><div class="m-left"><i class="fa-regular fa-heart"></i> Wishlist</div><i class="fa-solid fa-chevron-right"></i></li>
                    <li class="menu-link" onclick="openRightDrawer('cart')"><div class="m-left"><i class="fa-solid fa-bag-shopping"></i> Cart</div><i class="fa-solid fa-chevron-right"></i></li>
                    <li class="menu-link" onclick="logout()"><div class="m-left"><i class="fa-solid fa-right-from-bracket"></i> Logout</div><i class="fa-solid fa-chevron-right"></i></li>
                </ul>
            </div>
        </div>

        <div class="overlay" id="right-overlay" onclick="closeRightDrawer()"></div>
        <aside id="right-drawer" class="drawer-right">
            <div class="drawer-header-right">
                <h2 id="drawer-title">Cart</h2>
                <button class="close-btn-styled" onclick="closeRightDrawer()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="drawer-body" id="drawer-content"></div>
        </aside>

        <main class="scroll-area">
            <div class="dash-wrap">

                <!-- Mobile sidebar toggle bar -->
                <div class="dash-mobile-bar">
                    <div class="current">
                        <i class="fa-solid fa-table-columns" style="color:var(--gold-primary); margin-right:8px;"></i>
                        <span id="mobile-current-section">Profile</span>
                    </div>
                    <button onclick="toggleDashSidebar()"><i class="fa-solid fa-bars"></i> Menu</button>
                </div>

                <!-- Sidebar -->
                <aside class="dash-sidebar" id="dashSidebar">
                    <div class="dash-userbox">
                        <div class="dash-avatar" id="dash-avatar-letter">A</div>
                        <div class="dash-user-meta">
                            <div class="dash-user-name truncate" id="dash-username">Arniya Member</div>
                            <div class="dash-user-role" id="dash-userrole">Customer</div>
                        </div>
                    </div>

                    <div class="dash-tabs" id="dash-tabs">
                        <!-- tabs injected by JS based on user type -->
                    </div>
                </aside>

                <!-- Main panel -->
                <section class="dash-main">

                    <!-- PROFILE -->
                    <div class="dash-panel active" data-panel="profile">
                        <div class="dash-panel-head">
                            <h2><i class="fa-regular fa-id-card"></i>Profile Details</h2>
                            <button class="order-btn primary" onclick="showToast('Edit profile is a frontend mock')"><i class="fa-solid fa-pen-to-square"></i> Edit Profile</button>
                        </div>
                        <div class="dash-profile-grid">
                            <div class="dash-info-row"><label>Full Name</label><strong id="p-name">Arniya Member</strong></div>
                            <div class="dash-info-row"><label>User Type</label><strong id="p-type">Customer</strong></div>
                            <div class="dash-info-row"><label>Mobile</label><strong id="p-mobile">+91 98765 43210</strong></div>
                            <div class="dash-info-row"><label>Email</label><strong id="p-email">member@arniyahub.com</strong></div>
                            <div class="dash-info-row"><label>City</label><strong id="p-city">Mumbai</strong></div>
                            <div class="dash-info-row"><label>State</label><strong id="p-state">Maharashtra</strong></div>
                            <div class="dash-info-row" id="p-business-row"><label>Business Name</label><strong id="p-business">—</strong></div>
                            <div class="dash-info-row"><label>Member Since</label><strong>Apr 2025</strong></div>
                        </div>
                    </div>

                    <!-- MY ORDERS -->
                    <div class="dash-panel" data-panel="orders">
                        <div class="dash-panel-head">
                            <h2><i class="fa-solid fa-box-open"></i>My Orders</h2>
                            <button class="order-btn" onclick="window.location.href='shop.php'"><i class="fa-solid fa-cart-plus"></i> Continue Shopping</button>
                        </div>
                        <div class="dash-stats" id="orders-stats"></div>
                        <div id="orders-list"></div>
                    </div>

                    <!-- ORDER DETAILS (drill-in) -->
                    <div class="dash-panel" data-panel="order-detail">
                        <button class="order-detail-back" onclick="showPanel('orders')"><i class="fa-solid fa-arrow-left"></i> Back to My Orders</button>
                        <div id="order-detail-body"></div>
                    </div>

                    <!-- BULK ORDER INQUIRY (Wholesale) -->
                    <div class="dash-panel" data-panel="bulk">
                        <div class="dash-panel-head"><h2><i class="fa-solid fa-boxes-stacked"></i>Bulk Order Inquiry</h2></div>
                        <form class="dash-form-grid" onsubmit="event.preventDefault(); showToast('Bulk inquiry submitted (mock)')">
                            <div><label>Product / SKU</label><div class="input-group"><input type="text" placeholder="e.g. AH-W-01" style="background:transparent; border:none; color:#fff; padding:14px 16px; font-family:inherit; font-size:14px; width:100%; outline:none;"></div></div>
                            <div><label>Quantity</label><div class="input-group"><input type="number" min="1" placeholder="e.g. 50" style="background:transparent; border:none; color:#fff; padding:14px 16px; font-family:inherit; font-size:14px; width:100%; outline:none;"></div></div>
                            <div><label>Set Quantity</label><div class="input-group"><input type="number" min="1" placeholder="e.g. 10" style="background:transparent; border:none; color:#fff; padding:14px 16px; font-family:inherit; font-size:14px; width:100%; outline:none;"></div></div>
                            <div><label>Required By</label><div class="input-group"><input type="date" style="background:transparent; border:none; color:#fff; padding:14px 16px; font-family:inherit; font-size:14px; width:100%; outline:none;"></div></div>
                            <div style="grid-column:1/-1;"><label>Notes</label><div class="input-group"><textarea placeholder="Any special requirements" rows="3" style="background:transparent; border:none; color:#fff; padding:14px 16px; font-family:inherit; font-size:14px; width:100%; outline:none; resize:vertical;"></textarea></div></div>
                            <div style="grid-column:1/-1;"><button type="submit" class="gold-btn full-width">Submit Inquiry</button></div>
                        </form>
                    </div>

                    <!-- BOOKING HISTORY (Retailer) -->
                    <div class="dash-panel" data-panel="booking">
                        <div class="dash-panel-head"><h2><i class="fa-solid fa-clock-rotate-left"></i>Product Booking History</h2></div>
                        <div id="booking-list"></div>
                    </div>

                    <!-- CUSTOMER ORDERS (Reseller) -->
                    <div class="dash-panel" data-panel="customer-orders">
                        <div class="dash-panel-head"><h2><i class="fa-solid fa-users"></i>Customer Order Details</h2></div>
                        <div id="reseller-customer-list"></div>
                    </div>

                    <!-- MARGIN DETAILS (Reseller) -->
                    <div class="dash-panel" data-panel="margin">
                        <div class="dash-panel-head"><h2><i class="fa-solid fa-percent"></i>Margin Details</h2></div>
                        <div id="margin-list"></div>
                    </div>

                    <!-- RESELLER PROFIT -->
                    <div class="dash-panel" data-panel="profit">
                        <div class="dash-panel-head"><h2><i class="fa-solid fa-chart-line"></i>Reseller Profit</h2></div>
                        <div class="dash-stats">
                            <div class="dash-stat"><div class="dash-stat-label">This Month</div><div class="dash-stat-value">₹12,450</div></div>
                            <div class="dash-stat"><div class="dash-stat-label">Last 3 Months</div><div class="dash-stat-value">₹38,920</div></div>
                            <div class="dash-stat"><div class="dash-stat-label">Total Orders</div><div class="dash-stat-value">42</div></div>
                            <div class="dash-stat"><div class="dash-stat-label">Avg Margin</div><div class="dash-stat-value">18%</div></div>
                        </div>
                        <div id="profit-list"></div>
                    </div>

                    <!-- WISHLIST -->
                    <div class="dash-panel" data-panel="wishlist">
                        <div class="dash-panel-head">
                            <h2><i class="fa-regular fa-heart"></i>My Wishlist</h2>
                            <button class="order-btn" onclick="openRightDrawer('wishlist')"><i class="fa-solid fa-eye"></i> Open Drawer</button>
                        </div>
                        <div id="wishlist-list"></div>
                    </div>

                    <!-- ADDRESS BOOK -->
                    <div class="dash-panel" data-panel="address">
                        <div class="dash-panel-head">
                            <h2><i class="fa-solid fa-location-dot"></i>Address Book</h2>
                            <button class="order-btn primary" onclick="showToast('Add address (mock)')"><i class="fa-solid fa-plus"></i> Add Address</button>
                        </div>
                        <div id="address-list"></div>
                    </div>

                    <!-- PAYMENT DETAILS -->
                    <div class="dash-panel" data-panel="payment">
                        <div class="dash-panel-head">
                            <h2><i class="fa-regular fa-credit-card"></i>Payment Details</h2>
                            <button class="order-btn primary" onclick="showToast('Add payment method (mock)')"><i class="fa-solid fa-plus"></i> Add Method</button>
                        </div>
                        <div id="payment-list"></div>
                    </div>

                    <!-- SUPPORT -->
                    <div class="dash-panel" data-panel="support">
                        <div class="dash-panel-head"><h2><i class="fa-solid fa-headset"></i>Support</h2></div>
                        <div class="dash-profile-grid">
                            <div class="dash-info-row" style="cursor:pointer;" onclick="window.open('https://wa.me/919876543210', '_blank')">
                                <label>WhatsApp</label><strong>+91 98765 43210 →</strong>
                            </div>
                            <div class="dash-info-row" style="cursor:pointer;" onclick="window.location.href='tel:+919876543210'">
                                <label>Call Us</label><strong>+91 98765 43210 →</strong>
                            </div>
                            <div class="dash-info-row" style="cursor:pointer;" onclick="window.location.href='mailto:support@arniyahub.com'">
                                <label>Email Support</label><strong>support@arniyahub.com →</strong>
                            </div>
                            <div class="dash-info-row" style="cursor:pointer;" onclick="openSupportPopup()">
                                <label>Live Support</label><strong>Available 24/7 →</strong>
                            </div>
                        </div>
                        <form class="dash-form-grid" style="margin-top:22px;" onsubmit="event.preventDefault(); showToast('Support ticket sent (mock)'); this.reset();">
                            <div style="grid-column:1/-1;"><label>Subject</label><div class="input-group"><input type="text" required placeholder="Brief subject" style="background:transparent; border:none; color:#fff; padding:14px 16px; font-family:inherit; font-size:14px; width:100%; outline:none;"></div></div>
                            <div style="grid-column:1/-1;"><label>Message</label><div class="input-group"><textarea required placeholder="How can we help?" rows="4" style="background:transparent; border:none; color:#fff; padding:14px 16px; font-family:inherit; font-size:14px; width:100%; outline:none; resize:vertical;"></textarea></div></div>
                            <div style="grid-column:1/-1;"><button type="submit" class="gold-btn full-width">Send Message</button></div>
                        </form>
                    </div>

                </section>
            </div>

            <div class="pb-100"></div>
        </main>

        <!-- Mobile bottom nav -->
        <nav class="bottom-nav">
            <a href="index.php" class="nav-item"><i class="fa-solid fa-house"></i><span>Home</span></a>
            <div class="nav-item" onclick="openRightDrawer('wishlist')"><i class="fa-regular fa-heart"></i><span>Wishlist</span></div>
            <a href="shop.php" class="nav-item center-hex"><div class="hex-bg"><i class="fa-solid fa-gem"></i></div></a>
            <div class="nav-item" onclick="openRightDrawer('cart')">
                <div class="icon-badge-wrapper"><i class="fa-solid fa-bag-shopping"></i><span class="cart-badge">0</span></div>
                <span>Cart</span>
            </div>
            <a href="dashboard.php" class="nav-item active"><i class="fa-solid fa-circle-user"></i><span>Account</span></a>
        </nav>
    </div>

    <script src="assets/js/mockData.js"></script>
    <script src="assets/js/global.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
