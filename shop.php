<?php
require_once __DIR__ . '/includes/core/app.php';
$pageTitle = "Shop";
require __DIR__ . '/includes/header.php';
?>

<div class="shop-container">
    <aside class="filters-sidebar">
        <h2>Filters</h2>
        
        <div class="filter-group">
            <h3>Search</h3>
            <input type="text" id="search-input" placeholder="Search products...">
        </div>

        <div class="filter-group">
            <h3>Category</h3>
            <div id="category-filter">
                <!-- Categories will be loaded here by JS -->
            </div>
        </div>

        <div class="filter-group">
            <h3>Price Range</h3>
            <input type="range" id="price-range" min="0" max="10000" step="100">
            <p>Price: <span id="price-value">10000</span></p>
        </div>

        <div class="filter-group">
            <h3>Sort By</h3>
            <select id="sort-by">
                <option value="popularity">Popularity</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
            </select>
        </div>
    </aside>

    <main class="product-grid-container">
        <div id="product-grid" class="product-grid">
            <!-- Products will be loaded here by JS -->
        </div>
    </main>
</div>

<!-- Quick View Modal -->
<div id="quick-view-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <div class="modal-body">
            <img id="qv-product-image" src="" alt="Product Image">
            <div class="qv-details">
                <h2 id="qv-product-name"></h2>
                <p id="qv-product-price"></p>
                <p id="qv-product-description"></p>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/shop.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
