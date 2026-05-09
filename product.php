<?php
require_once __DIR__ . '/includes/core/app.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId === 0) {
    header('Location: shop.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ? AND p.is_active = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: shop.php');
    exit;
}

// Safely decode JSON properties
$images = json_decode($product['images'] ?: '[]', true) ?: [];
$sizes  = json_decode($product['sizes']  ?: '[]', true) ?: [];
$colors = json_decode($product['colors'] ?: '[]', true) ?: [];

// Ensure the main image is included in the gallery
$main_image_url = !empty($product['main_image']) ? $product['main_image'] : 'assets/images/placeholder.jpg';
if (!in_array($main_image_url, $images)) {
    array_unshift($images, $main_image_url);
}

// Fetch approved reviews for this product
$review_stmt = $db->prepare("SELECT * FROM reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC");
$review_stmt->execute([$productId]);
$reviews = $review_stmt->fetchAll();

// Calculate average rating
$avg_rating = 0;
$rating_count = count($reviews);
if ($rating_count > 0) {
    $total_rating = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($total_rating / $rating_count, 1);
}

$pageTitle = $product['name'];
require __DIR__ . '/includes/header.php';
?>

<main class="product-page">
    <div class="product-container">
        <!-- Interactive Image Gallery -->
        <div class="product-gallery-container">
            <div class="main-product-image">
                <img src="<?php echo htmlspecialchars($images[0]); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            </div>
            <?php if (count($images) > 1): ?>
                <div class="thumbnail-gallery">
                    <?php foreach ($images as $index => $img): ?>
                        <div class="thumbnail-item <?php echo $index === 0 ? 'active' : ''; ?>">
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="Thumbnail <?php echo $index + 1; ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Product Details and Actions -->
        <div class="product-details-container">
            <div class="breadcrumb-trail">
                <a href="shop.php">Shop</a> / <a href="shop.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a>
            </div>
            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <div class="product-meta-info">
                <div class="product-price">
                    <span>₹<?php echo number_format($product['price']); ?></span>
                    <?php if (!empty($product['old_price']) && $product['old_price'] > $product['price']): ?>
                        <span class="old-price">₹<?php echo number_format($product['old_price']); ?></span>
                        <span class="discount-badge"><?php echo round((($product['old_price'] - $product['price']) / $product['old_price']) * 100); ?>% OFF</span>
                    <?php endif; ?>
                </div>
                <div class="product-rating">
                    <span class="rating-value"><?php echo $avg_rating; ?> ★</span>
                    <span>(<?php echo $rating_count; ?> customer reviews)</span>
                </div>
            </div>

            <div class="product-description">
                <?php echo nl2br(htmlspecialchars($product['short_description'])); ?>
            </div>

            <!-- Product Options -->
            <?php if (!empty($sizes)): ?>
                <div class="product-options size-options">
                    <div class="option-group">
                        <label class="option-label">Size:</label>
                        <?php foreach ($sizes as $index => $size): ?>
                            <button class="option-btn <?php echo $index === 0 ? 'active' : ''; ?>" data-value="<?php echo htmlspecialchars($size); ?>"><?php echo htmlspecialchars($size); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($colors)): ?>
                <div class="product-options color-options">
                     <div class="option-group">
                        <label class="option-label">Color:</label>
                        <?php foreach ($colors as $index => $color): ?>
                            <button class="option-btn <?php echo $index === 0 ? 'active' : ''; ?>" data-value="<?php echo htmlspecialchars($color); ?>"><?php echo htmlspecialchars($color); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quantity and Add to Cart -->
            <div class="product-actions">
                <div class="quantity-selector">
                    <button class="quantity-btn minus">-</button>
                    <input type="text" id="quantity-input" value="1" readonly>
                    <button class="quantity-btn plus">+</button>
                </div>
                <button class="btn-add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                    <i class="fas fa-shopping-cart"></i> Add to Cart
                </button>
            </div>
            <div class="stock-info">
                <?php if ($product['stock'] > 0): ?>
                    <span class="in-stock"><i class="fas fa-check-circle"></i> In Stock (<?php echo $product['stock']; ?> available)</span>
                <?php else: ?>
                    <span class="out-of-stock"><i class="fas fa-times-circle"></i> Out of Stock</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Enhanced Reviews Section -->
    <div class="p-reviews">
        <div class="reviews-header-row">
            <h3>Customer Experiences</h3>
             <?php if (count($reviews) > 1): ?>
                <div class="review-nav">
                    <button class="rev-nav-btn prev" aria-label="Previous Review"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="rev-nav-btn next" aria-label="Next Review"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="reviews-slider-viewport">
            <div class="reviews-track" id="reviewsTrack">
                <?php if (empty($reviews)): ?>
                    <div class="review-card-premium no-reviews">
                        <p>This product has no reviews yet. Be the first to share your thoughts!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card-premium">
                            <div class="rev-header">
                                <div class="stars-pill mini"><?php echo $review['rating']; ?> <i class="fa-solid fa-star"></i></div>
                                <span class="rev-name"><?php echo htmlspecialchars($review['customer_name']); ?></span>
                            </div>
                            <p class="rev-text"><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Improved Review Submission Form -->
    <div class="review-form-container">
        <h3>Write a Review</h3>
        <form id="review-form">
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="reviewer-name">Name</label>
                    <input type="text" id="reviewer-name" name="name" class="form-control" required placeholder="e.g. John Doe">
                </div>
                <div class="form-group">
                    <label for="review-rating">Rating</label>
                    <select id="review-rating" name="rating" class="form-control" required>
                        <option value="" disabled selected>Select a rating</option>
                        <option value="5">★★★★★ (5/5)</option>
                        <option value="4">★★★★☆ (4/5)</option>
                        <option value="3">★★★☆☆ (3/5)</option>
                        <option value="2">★★☆☆☆ (2/5)</option>
                        <option value="1">★☆☆☆☆ (1/5)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="review-text">Review</label>
                <textarea id="review-text" name="review" class="form-control" rows="4" required placeholder="Share your experience with this product..."></textarea>
            </div>
            <button type="submit" class="btn-submit-review">Submit Review</button>
            <div id="review-form-message" class="form-message"></div>
        </form>
    </div>
</main>

<script src="assets/js/product.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const reviewForm = document.getElementById('review-form');
    const messageDiv = document.getElementById('review-form-message');

    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(reviewForm);
            const submitButton = reviewForm.querySelector('button[type="submit"]');
            
            messageDiv.textContent = '';
            messageDiv.className = 'form-message';
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            fetch('api/submit_review.php', { // Corrected endpoint
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    reviewForm.reset();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('An unexpected error occurred.', 'error');
                console.error('Review submission error:', error);
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = 'Submit Review';
            });
        });
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
