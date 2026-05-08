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