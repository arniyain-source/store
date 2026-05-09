document.addEventListener('DOMContentLoaded', function () {
    // Initialize everything needed for the product page
    initProductGallery();
    initProductOptions();
    initQuantitySelector();
    initAddToCart();
    initReviewSlider();
    initReviewReadMore();
});

function initProductGallery() {
    const mainImage = document.querySelector('.main-product-image img');
    const thumbnails = document.querySelectorAll('.thumbnail-item img');

    if (!mainImage || thumbnails.length === 0) return;

    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function () {
            // Remove active class from all thumbnails
            thumbnails.forEach(t => t.parentElement.classList.remove('active'));
            // Add active class to the clicked thumbnail
            this.parentElement.classList.add('active');
            // Update the main image source
            mainImage.src = this.src;
        });
    });
}

function initProductOptions() {
    const optionGroups = document.querySelectorAll('.product-options .option-group');
    optionGroups.forEach(group => {
        const buttons = group.querySelectorAll('.option-btn');
        buttons.forEach(button => {
            button.addEventListener('click', function () {
                buttons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
            });
        });
    });
}

function initQuantitySelector() {
    const quantityInput = document.getElementById('quantity-input');
    const btnPlus = document.querySelector('.quantity-btn.plus');
    const btnMinus = document.querySelector('.quantity-btn.minus');

    if (!quantityInput || !btnPlus || !btnMinus) return;

    btnPlus.addEventListener('click', () => {
        let currentValue = parseInt(quantityInput.value, 10);
        quantityInput.value = currentValue + 1;
    });

    btnMinus.addEventListener('click', () => {
        let currentValue = parseInt(quantityInput.value, 10);
        if (currentValue > 1) {
            quantityInput.value = currentValue - 1;
        }
    });
}

function initAddToCart() {
    const addToCartButton = document.querySelector('.btn-add-to-cart');
    if (!addToCartButton) return;

    addToCartButton.addEventListener('click', function () {
        const productId = this.dataset.productId;
        const quantity = document.getElementById('quantity-input').value;
        
        const selectedSizeEl = document.querySelector('.size-options .option-btn.active');
        const selectedColorEl = document.querySelector('.color-options .option-btn.active');
        
        const size = selectedSizeEl ? selectedSizeEl.dataset.value : null;
        const color = selectedColorEl ? selectedColorEl.dataset.value : null;

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        if (size) formData.append('size', size);
        if (color) formData.append('color', color);

        fetch('/api/cart.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Product added to cart!', 'success');
                // Optionally, update a mini-cart UI element here
            } else {
                showToast(data.message || 'Could not add to cart.', 'error');
            }
        })
        .catch(error => {
            showToast('An error occurred.', 'error');
            console.error('Add to cart error:', error);
        });
    });
}

function initReviewSlider() {
    const track = document.getElementById('reviewsTrack');
    const prevBtn = document.querySelector('.rev-nav-btn.prev');
    const nextBtn = document.querySelector('.rev-nav-btn.next');
    
    if (!track || !prevBtn || !nextBtn) return;

    let currentIndex = 0;
    const reviews = track.children;
    const totalReviews = reviews.length;
    
    if (totalReviews <= 1) {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'none';
        return;
    }

    function updateSliderPosition() {
        const cardWidth = reviews[0].offsetWidth;
        const gap = 20; // As defined in CSS
        track.style.transform = `translateX(-${currentIndex * (cardWidth + gap)}px)`;
    }

    nextBtn.addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % totalReviews;
        updateSliderPosition();
    });

    prevBtn.addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + totalReviews) % totalReviews;
        updateSliderPosition();
    });
    
    window.addEventListener('resize', updateSliderPosition);
}

function initReviewReadMore() {
    const reviewTexts = document.querySelectorAll('.rev-text');
    const maxLength = 150;

    reviewTexts.forEach(reviewText => {
        const fullText = reviewText.innerHTML; // Use innerHTML to preserve line breaks
        if (fullText.length > maxLength) {
            const truncatedText = fullText.substring(0, maxLength) + '...';
            reviewText.innerHTML = truncatedText;

            const readMoreBtn = document.createElement('button');
            readMoreBtn.textContent = 'Read More';
            readMoreBtn.className = 'read-more-btn';
            
            reviewText.parentElement.appendChild(readMoreBtn);

            readMoreBtn.addEventListener('click', function () {
                if (this.textContent === 'Read More') {
                    reviewText.innerHTML = fullText;
                    this.textContent = 'Read Less';
                } else {
                    reviewText.innerHTML = truncatedText;
                    this.textContent = 'Read More';
                }
            });
        }
    });
}

// A simple toast notification utility
function showToast(message, type = 'info') {
    let toast = document.querySelector('.toast-notification');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast-notification';
        document.body.appendChild(toast);
    }

    toast.textContent = message;
    toast.className = `toast-notification show ${type}`;

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}
