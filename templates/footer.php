<?php
/**
 * Global Footer Template - DesiVastra / ArniyaHub
 */
?>

        </main> <!-- Close .scroll-area or main -->

        <!-- Section 9: Footer -->
        <footer class="premium-footer">
            <div class="footer-top">
                <div class="footer-brand">
                    <h2 class="footer-logo-premium">Arniya<span style="color: var(--gold-primary);">Hub</span></h2>
                    <p class="footer-tagline">Designed for Luxury. Crafted for You.</p>
                </div>
            </div>

            <div class="footer-links-grid">
                <div class="footer-col">
                    <h4>Explore</h4>
                    <a href="shop.php">New Arrivals</a>
                    <a href="shop.php">Signature Collection</a>
                    <a href="#">Trending Reels</a>
                </div>
                <div class="footer-col">
                    <h4>Assistance</h4>
                    <a href="#">Track Order</a>
                    <a href="#">Returns &amp; Policy</a>
                    <a href="#" onclick="openSupportPopup()">Concierge</a>
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
                <p class="copyright-text">© <?php echo date('Y'); ?> Arniya Smart Hub. All rights reserved.</p>
            </div>
        </footer>

        <!-- Mobile Bottom Navigation (App-Like) -->
        <nav class="bottom-nav">
            <a href="index.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-house"></i>
                <span>Home</span>
            </a>
            <div class="nav-item" onclick="openRightDrawer('wishlist')">
                <i class="fa-regular fa-heart"></i>
                <span>Wishlist</span>
            </div>
            <a href="shop.php" class="nav-item center-hex" aria-label="Shop">
                <div class="hex-bg"><i class="fa-solid fa-gem"></i></div>
            </a>
            <div class="nav-item" onclick="openRightDrawer('cart')">
                <div class="icon-badge-wrapper">
                    <i class="fa-solid fa-bag-shopping"></i>
                    <span class="cart-badge" id="cart-count-badge">0</span>
                </div>
                <span>Cart</span>
            </div>
            <div class="nav-item" onclick="handleAccountClick()">
                <i class="fa-regular fa-user"></i>
                <span>Account</span>
            </div>
        </nav>

    </div> <!-- Close .app-container -->

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
            <span class="status-dot"></span> Available 24/7 &middot; Avg. response &lt; 2 min
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
                <li class="menu-link" onclick="window.location.href='shop.php'"><div class="m-left"><i class="fa-regular fa-clock"></i> Watches</div> <i class="fa-solid fa-chevron-right arrow"></i></li>
                <li class="menu-link" onclick="window.location.href='shop.php'"><div class="m-left"><i class="fa-regular fa-gem"></i> Jewelry</div> <i class="fa-solid fa-chevron-right arrow"></i></li>
                <li class="menu-link" onclick="window.location.href='shop.php'"><div class="m-left"><i class="fa-solid fa-glasses"></i> Accessories</div> <i class="fa-solid fa-chevron-right arrow"></i></li>
                <li class="menu-title">Account</li>
                <li class="menu-link" onclick="handleAccountClick()"><div class="m-left"><i class="fa-regular fa-user"></i> Your Account</div> <i class="fa-solid fa-chevron-right arrow"></i></li>
                <li class="menu-link" onclick="handleAccountClick()"><div class="m-left"><i class="fa-solid fa-box-open"></i> Your Orders</div> <i class="fa-solid fa-chevron-right arrow"></i></li>
                <li class="menu-link" onclick="openRightDrawer('wishlist')"><div class="m-left"><i class="fa-regular fa-heart"></i> Wishlist</div> <i class="fa-solid fa-chevron-right arrow"></i></li>
            </ul>
            <div class="drawer-footer-menu">
                <p class="drawer-f-title">Support & Info</p>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms & Conditions</a>
                <a href="#">About ARNiya</a>
                <a href="mailto:support@arniyahub.com"><i class="fa-solid fa-envelope" style="margin-right:8px;"></i>support@arniyahub.com</a>
                
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

    <!-- Global UI Components -->
    <div id="toast-container"></div>

    <!-- Global Scripts -->
    <script src="assets/js/mockData.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/global.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/share.js?v=<?php echo time(); ?>"></script>
    
    <?php if (isset($extraJS)) echo $extraJS; ?>

    <script>
        // Update cart badge count on load
        document.addEventListener('DOMContentLoaded', () => {
            const updateBadge = () => {
                const cart = JSON.parse(localStorage.getItem('cart')) || [];
                const badge = document.getElementById('cart-count-badge');
                if(badge) badge.innerText = cart.length;
            };
            updateBadge();
            // Listen for storage changes
            window.addEventListener('storage', updateBadge);
        });
    </script>
</body>
</html>