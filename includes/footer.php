</div> <!-- Closing app-container -->

    <footer class="global-footer">
        <div class="footer-inner">
            <div class="footer-section about-us">
                <h4>DesiVastra</h4>
                <p>Bringing you the finest collection of Indian ethnic wear, crafted with love and tradition. From vibrant sarees to elegant kurtis, find your perfect style with us.</p>
            </div>
            <div class="footer-section links">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="/shop.php">Shop All</a></li>
                    <li><a href="/index.php#categories">Categories</a></li>
                    <li><a href="/index.php#new-arrivals">New Arrivals</a></li>
                    <li><a href="/index.php#about">About Us</a></li>
                    <li><a href="/admin/login.php">Admin Login</a></li>
                </ul>
            </div>
            <div class="footer-section contact">
                <h4>Contact Us</h4>
                <p><i class="fas fa-envelope"></i> <a href="mailto:support@desivastra.com">support@desivastra.com</a></p>
                <p><i class="fas fa-phone"></i> +91 12345 67890</p>
                <div class="social-icons">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> DesiVastra. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Global JS -->
    <script src="/assets/js/global.js?v=<?php echo time(); ?>"></script>

    <!-- Page-specific scripts can be loaded here -->
    <?php 
    if (isset($extraScripts)) {
        foreach ($extraScripts as $script) {
            echo '<script src="' . $script . '?v=' . time() . '"></script>\n';
        }
    }
    ?>

</body>
</html>
