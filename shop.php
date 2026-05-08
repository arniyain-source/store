<?php
/**
 * Shop Page - DesiVastra E-Commerce
 * Dynamic product listing with sidebar filters and mobile drawer
 */
require_once 'includes/functions.php';

// Get filter parameters from URL for initial state
$category_filter = sanitize($_GET['category'] ?? '');
$price_filter = sanitize($_GET['price'] ?? '');
$sort_option = sanitize($_GET['sort'] ?? 'newest');

$pageTitle = "Shop - DesiVastra Luxury Collection";
$extraCSS = "assets/css/shop.css";

include 'templates/head.php';
?>

<div class="app-container">
    <?php include 'templates/header.php'; ?>

    <main class="scroll-area shop-page">
        <div class="shop-container">
            <!-- ── Desktop Sidebar Filter ── -->
            <aside class="shop-sidebar desktop-only">
                <div class="filter-group">
                    <h4 class="filter-title">Categories</h4>
                    <ul class="filter-list">
                        <li><label class="filter-item"><input type="checkbox" name="cat" value="watches"> <span>Watches</span></label></li>
                        <li><label class="filter-item"><input type="checkbox" name="cat" value="jewelry"> <span>Jewelry</span></label></li>
                        <li><label class="filter-item"><input type="checkbox" name="cat" value="accessories"> <span>Accessories</span></label></li>
                        <li><label class="filter-item"><input type="checkbox" name="cat" value="perfumes"> <span>Perfumes</span></label></li>
                    </ul>
                </div>

                <div class="filter-group">
                    <h4 class="filter-title">Price Range</h4>
                    <ul class="filter-list">
                        <li><label class="filter-item"><input type="radio" name="price" value="0-500"> <span>Under ₹500</span></label></li>
                        <li><label class="filter-item"><input type="radio" name="price" value="500-1000"> <span>₹500 - ₹1000</span></label></li>
                        <li><label class="filter-item"><input type="radio" name="price" value="1000-5000"> <span>₹1000 - ₹5000</span></label></li>
                        <li><label class="filter-item"><input type="radio" name="price" value="5000+"> <span>Above ₹5000</span></label></li>
                    </ul>
                </div>

                <div class="filter-group">
                    <h4 class="filter-title">Fabric</h4>
                    <ul class="filter-list">
                        <li><label class="filter-item"><input type="checkbox" name="fabric" value="silk"> <span>Silk</span></label></li>
                        <li><label class="filter-item"><input type="checkbox" name="fabric" value="cotton"> <span>Cotton</span></label></li>
                        <li><label class="filter-item"><input type="checkbox" name="fabric" value="georgette"> <span>Georgette</span></label></li>
                    </ul>
                </div>
            </aside>

            <!-- ── Main Content ── -->
            <section class="shop-main">
                <!-- Shop Toolbar -->
                <div class="shop-toolbar">
                    <div class="toolbar-left">
                        <button class="filter-toggle-btn mobile-only" onclick="toggleFilterDrawer()">
                            <i class="fa-solid fa-filter"></i> Filters
                        </button>
                        <span class="results-count" id="product-count">Showing 0 products</span>
                    </div>
                    
                    <div class="toolbar-right">
                        <select id="sort-select" class="sort-dropdown" onchange="handleSortChange()">
                            <option value="newest">Newest Arrivals</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="rating">Best Rating</option>
                        </select>
                    </div>
                </div>

                <!-- Active Filters Chips -->
                <div id="active-filters" class="active-filters-container">
                    <!-- Dynamically populated via shop.js -->
                </div>

                <!-- Product Grid -->
                <div class="product-grid" id="shop-product-grid">
                    <!-- Placeholder loop for initial skeleton or server-side rendered cards -->
                    <?php for($i=0; $i<8; $i++): ?>
                        <div class="product-card-placeholder">
                            <!-- This will be replaced by actual product data via JS or dynamic PHP loop -->
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- Load More System -->
                <div class="load-more-wrap">
                    <button id="load-more-btn" class="gold-btn" onclick="loadMoreProducts()">
                        <span>Load More Products</span>
                        <i class="fa-solid fa-rotate-right"></i>
                    </button>
                </div>
            </section>
        </div>
    </main>

    <!-- ── Mobile Filter Drawer ── -->
    <div class="overlay" id="filter-overlay" onclick="toggleFilterDrawer()"></div>
    <div id="filter-drawer" class="drawer-right">
        <div class="drawer-header-right">
            <h3>Filters</h3>
            <button class="close-btn-styled" onclick="toggleFilterDrawer()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="drawer-body">
            <div class="mobile-filter-content" style="padding: 20px;">
                <!-- Content injected via JS or template -->
            </div>
        </div>
        <div class="drawer-footer-menu" style="display: flex; gap: 10px;">
            <button class="outline-btn" style="flex: 1;" onclick="resetFilters()">Clear</button>
            <button class="gold-btn" style="flex: 2;" onclick="applyFilters()">Apply</button>
        </div>
    </div>

    <?php 
    $extraJS = '<script src="assets/js/shop.js?v='.time().'"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            if (typeof initShop === "function") {
                initShop({
                    category: "' . $category_filter . '",
                    sort: "' . $sort_option . '"
                });
            }
        });
        function toggleFilterDrawer() {
            document.getElementById("filter-drawer").classList.toggle("open");
            document.getElementById("filter-overlay").classList.toggle("active");
        }
    </script>';
    include 'templates/footer.php'; 
    ?>
</div>