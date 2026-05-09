let currentProduct = null;
let currentRating = 0;
let currentReviewIndex = 0;
let reviewSliderTimer = null;
let selectedSize = "";
let selectedFinish = "";

// ── XSS Sanitization Helper ──
function sanitize(str) {
    const div = document.createElement('div');
    div.textContent = String(str || '');
    return div.innerHTML;
}

// ── Bridge functions for static HTML onclick handlers ──
// product.php references updateMainImg() and selectSize() in static HTML
// These bridge to the actual JS functions to prevent ReferenceError
function updateMainImg(el, url) {
    updateMainImage(url);
}
function selectSize(el, size) {
    const pill = document.querySelector(`.s-pill[data-size="${size}"]`);
    if (pill) pill.click();
    else { selectedSize = size; showToast(`Size updated to ${size}`); }
}

function getProductFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return getProductById(params.get("id") || 1);
}

function calculateDiscount(price, oldPrice) {
    if (!oldPrice || oldPrice <= price) return 0;
    return Math.round(((oldPrice - price) / oldPrice) * 100);
}

function setText(selector, value) {
    const node = document.querySelector(selector);
    if (node) node.textContent = value;
}

function setAllText(selector, value) {
    document.querySelectorAll(selector).forEach((node) => {
        node.textContent = value;
    });
}

function updateMainImage(url) {
    const mainImage = document.getElementById("main-product-img");
    if (!mainImage) return;

    mainImage.style.backgroundImage = `url('${url}?auto=format&fit=crop&w=900&q=80')`;
    mainImage.classList.add("flash-anim");
    setTimeout(() => mainImage.classList.remove("flash-anim"), 300);
}

function renderGallery(product) {
    const thumbnailColumn = document.querySelector(".thumbnail-column");
    const mainImage = document.getElementById("main-product-img");
    const galleryImages = product.images?.length ? product.images : [product.img];

    if (mainImage) {
        mainImage.style.backgroundImage = `url('${galleryImages[0]}?auto=format&fit=crop&w=900&q=80')`;
    }

    // ── Desktop thumbnail column ──
    if (thumbnailColumn) {
        thumbnailColumn.innerHTML = galleryImages.map((image, index) => `
            <button type="button" class="thumb ${index === 0 ? "active" : ""}" data-image="${image}" aria-label="View product image ${index + 1}" style="background-image:url('${image}?w=120&q=80')"></button>
        `).join("");

        thumbnailColumn.querySelectorAll(".thumb").forEach((thumb) => {
            thumb.addEventListener("click", () => {
                thumbnailColumn.querySelectorAll(".thumb").forEach((item) => item.classList.remove("active"));
                thumb.classList.add("active");
                updateMainImage(thumb.dataset.image);
            });
        });
    }

    // ── Mobile gallery strip ──
    renderMobileGalleryStrip(galleryImages);
}

function renderMobileGalleryStrip(images) {
    const strip = document.getElementById("mobile-gallery-strip");
    if (!strip) return;

    // Use only the actual product images
    const allImgs = [...images];

    const imgThumbs = allImgs.map((img, i) => `
        <button class="mg-thumb ${i === 0 ? 'mg-thumb-active' : ''}"
            data-img="${img}"
            style="background-image:url('${img}?w=120&q=80')"
            onclick="switchMobileGallery(this)"
            aria-label="Product image ${i + 1}">
        </button>
    `).join("");

    const videoThumb = currentProduct.video ? `
        <button class="mg-thumb mg-thumb-video" onclick="openReel()" aria-label="Watch product video">
            <div class="mg-video-overlay">
                <i class="fa-solid fa-play"></i>
                <span>Video</span>
            </div>
            <div class="mg-thumb-bg" style="background-image:url('${allImgs[0]}?w=120&q=80')"></div>
        </button>
    ` : '';

    strip.innerHTML = imgThumbs + videoThumb;
}

function switchMobileGallery(thumb) {
    const strip = document.getElementById("mobile-gallery-strip");
    if (!strip) return;
    strip.querySelectorAll(".mg-thumb").forEach((t) => t.classList.remove("mg-thumb-active"));
    thumb.classList.add("mg-thumb-active");
    updateMainImage(thumb.dataset.img);
}

function renderSizes(product) {
    const sizeContainer = document.querySelector(".size-options");
    if (!sizeContainer) return;

    selectedSize = product.sizes?.[0] || "";
    sizeContainer.innerHTML = (product.sizes || []).map((size, index) => `
        <button type="button" class="s-pill ${index === 0 ? "active" : ""}" data-size="${size}">${size}</button>
    `).join("");

    sizeContainer.querySelectorAll(".s-pill").forEach((pill) => {
        pill.addEventListener("click", () => {
            sizeContainer.querySelectorAll(".s-pill").forEach((item) => item.classList.remove("active"));
            pill.classList.add("active");
            selectedSize = pill.dataset.size;
            showToast(`Size updated to ${selectedSize}`);
        });
    });
}

function renderFinishes(product) {
    const colorContainer = document.querySelector(".colors");
    if (!colorContainer) return;

    selectedFinish = product.finishes?.[0]?.name || product.finishes?.[0] || "";
    colorContainer.innerHTML = (product.finishes || []).map((finish, index) => {
        const name = typeof finish === "string" ? finish : finish.name;
        const hex = typeof finish === "string" ? "#d4af37" : finish.hex;

        return `
            <button
                type="button"
                class="v-color ${index === 0 ? "active" : ""}"
                data-finish="${name}"
                style="background:${hex}"
                title="${name}"
                aria-label="${name}"
            ></button>
        `;
    }).join("");

    colorContainer.querySelectorAll(".v-color").forEach((button, index) => {
        button.addEventListener("click", () => {
            colorContainer.querySelectorAll(".v-color").forEach((item) => item.classList.remove("active"));
            button.classList.add("active");
            selectedFinish = button.dataset.finish;

            const nextImage = currentProduct.images?.[index] || currentProduct.images?.[0] || currentProduct.img;
            updateMainImage(nextImage);
            showToast(`Finish switched to ${selectedFinish}`);
        });
    });
}

function renderFeatures(product) {
    const grid = document.querySelector(".features-grid");
    if (!grid || !product.features?.length) return;

    grid.innerHTML = product.features.map((feature) => `
        <div class="feat">
            <i class="${feature.icon}"></i>
            <span class="feat-text">${feature.label}</span>
        </div>
    `).join("");
}

function renderRelatedProducts(productId) {
    const relatedGrid = document.querySelector(".related-grid");
    if (!relatedGrid) return;

    const relatedProducts = getRelatedProducts(productId, 4);
    relatedGrid.innerHTML = relatedProducts.map((product, index) => `
        <a href="product.php?id=${product.id}" class="related-card">
            <div class="r-card-img bg-img" style="background-image:url('${product.img}?w=500&q=80')">
                <span class="r-badge">${index === 0 ? "Premium" : index === 1 ? "Hot" : index === 2 ? "New" : "Curated"}</span>
            </div>
            <div class="r-card-info">
                <h4 class="r-name">${sanitize(product.name)}</h4>
                <div class="r-row">
                    <span class="r-price">${formatMoney(product.price)}</span>
                    <span class="r-rating">${product.rating.toFixed(1)} <i class="fa-solid fa-star"></i></span>
                </div>
            </div>
        </a>
    `).join("");
}

function wireProductButtons() {
    const addButtons = [
        document.querySelector(".m-flow-btn.gold"),
        document.querySelector(".bb-action-btn.gold")
    ].filter(Boolean);

    const buyButtons = [
        document.querySelector(".m-flow-btn.orange"),
        document.querySelector(".bb-action-btn.white"),
        document.querySelector(".m-cta-btn.m-cta-add")
    ].filter(Boolean);

    addButtons.forEach((button) => {
        button.onclick = () => addCurrentProductToCart(false);
    });

    buyButtons.forEach((button) => {
        button.onclick = () => addCurrentProductToCart(true);
    });
}

function syncProductWishlistButtons() {
    document.querySelectorAll(".product-wishlist-btn").forEach((button) => {
        button.dataset.productId = String(currentProduct.id);
    });

    if (typeof syncWishlistButtons === "function") {
        syncWishlistButtons();
    }
}

function toggleProductWishlist(button) {
    if (!currentProduct || !button) return;
    button.dataset.productId = String(currentProduct.id);
    toggleWishlistButton(button);
    syncProductWishlistButtons();
}

function addCurrentProductToCart(openCart) {
    if (!currentProduct) return;

    addToCartGlobal({
        id: currentProduct.id,
        qty: 1,
        size: selectedSize,
        finish: selectedFinish
    });

    if (openCart) {
        setTimeout(() => openRightDrawer("cart"), 200);
    }
}

function shareProduct() {
    if (!currentProduct) return;

    const shareUrl = window.location.href;
    const shareText = `${currentProduct.name} · ${formatMoney(currentProduct.price)} · ${shareUrl}`;

    if (navigator.share) {
        navigator.share({
            title: currentProduct.name,
            text: shareText,
            url: shareUrl
        }).catch(() => {});
        return;
    }

    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(shareText)
            .then(() => showToast("Product link copied to clipboard"))
            .catch(() => showToast("Unable to copy right now"));
        return;
    }

    showToast("Sharing is not available on this browser");
}

function openReview() {
    document.getElementById("review-drawer")?.classList.add("active");
    document.getElementById("right-overlay")?.classList.add("active");
}

function closeReview() {
    document.getElementById("review-drawer")?.classList.remove("active");
    document.getElementById("right-overlay")?.classList.remove("active");

    setTimeout(() => {
        currentRating = 0;
        document.querySelectorAll(".star-rating-select i").forEach((star) => star.classList.remove("active"));
        const reviewText = document.getElementById("review-text");
        if (reviewText) reviewText.value = "";
        const ratingLabel = document.querySelector(".rating-label");
        if (ratingLabel) ratingLabel.innerText = "Share your satisfaction level";
        const inputStep = document.getElementById("review-input-step");
        if (inputStep) inputStep.style.display = "block";
        const successStep = document.getElementById("review-success-step");
        if (successStep) successStep.style.display = "none";

        const button = document.querySelector(".submit-review-premium");
        if (button) {
            button.disabled = false;
            button.style.opacity = "1";
            button.innerHTML = "<span>Submit Review</span><i class=\"fa-solid fa-arrow-right\"></i>";
        }
    }, 200);
}

function setRating(value) {
    currentRating = value;
    const labels = ["", "Hated it", "Disliked it", "It was okay", "Liked it", "Loved it"];

    document.querySelectorAll(".star-rating-select i").forEach((star, index) => {
        star.classList.toggle("active", index < value);
    });

    const ratingLabel = document.querySelector(".rating-label");
    if (ratingLabel) ratingLabel.innerText = labels[value];
}

function getStoredReviews() {
    const allReviews = safeParse("desivastraReviews", {});
    return allReviews[currentProduct.id] || [];
}

function saveStoredReview(review) {
    const allReviews = safeParse("desivastraReviews", {});
    const existing = allReviews[currentProduct.id] || [];
    allReviews[currentProduct.id] = [review, ...existing].slice(0, 6);
    localStorage.setItem("desivastraReviews", JSON.stringify(allReviews));
}

function getReviewCards() {
    const baseReviews = currentProduct.testimonials || [];
    const userReviews = getStoredReviews();
    return [...userReviews, ...baseReviews];
}

function renderReviews() {
    const track = document.getElementById("reviewsTrack");
    if (!track) return;

    track.innerHTML = getReviewCards().map((review) => `
        <div class="review-card-premium">
            <div class="rev-header">
                <div class="rev-top-row">
                    <div class="stars-pill mini">${Number(review.rating) || 0} <i class="fa-solid fa-star"></i></div>
                    <div class="verified-badge"><i class="fa-solid fa-circle-check"></i> VERIFIED</div>
                </div>
                <span class="rev-name">${sanitize(review.name)}</span>
            </div>
            <p class="rev-text">"${sanitize(review.text)}"</p>
        </div>
    `).join("");
}

function submitReview() {
    if (!currentRating) {
        showToast("Please select a rating first");
        return;
    }

    const reviewText = document.getElementById("review-text");
    const copy = reviewText?.value.trim() || `Loved the finish, packaging, and overall premium feel of the ${currentProduct.name}.`;
    const button = document.querySelector(".submit-review-premium");
    if (!button) return;

    button.disabled = true;
    button.style.opacity = "0.7";
    button.innerHTML = "<i class=\"fa-solid fa-circle-notch fa-spin\"></i> Publishing...";

    setTimeout(() => {
        saveStoredReview({
            name: "You",
            rating: currentRating,
            text: copy
        });

        renderReviews();
        initReviewsSlider();
        document.getElementById("review-input-step").style.display = "none";
        document.getElementById("review-success-step").style.display = "block";
        showToast("Review published successfully");
    }, 800);
}

function initReveal() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add("visible");
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll(".ash-reveal").forEach((element) => observer.observe(element));
}

function initStickyCta() {
    const stickyCta = document.querySelector(".mobile-sticky-cta");
    const flowTrigger = document.getElementById("mobile-trigger-btn");
    if (!stickyCta || !flowTrigger) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting && entry.boundingClientRect.top < 0) {
                stickyCta.classList.add("visible");
            } else {
                stickyCta.classList.remove("visible");
            }
        });
    }, { threshold: 0 });

    observer.observe(flowTrigger);
}

function moveReviews(direction) {
    const track = document.getElementById("reviewsTrack");
    const cards = track ? Array.from(track.children) : [];
    if (!track || !cards.length) return;

    const visibleCount = window.innerWidth < 1024 ? 1 : 2;
    const maxIndex = Math.max(0, cards.length - visibleCount);

    if (direction === "next") {
        currentReviewIndex = currentReviewIndex >= maxIndex ? 0 : currentReviewIndex + 1;
    } else {
        currentReviewIndex = currentReviewIndex <= 0 ? maxIndex : currentReviewIndex - 1;
    }

    const gap = 20;
    const cardWidth = cards[0].offsetWidth + gap;
    track.style.transform = `translateX(-${currentReviewIndex * cardWidth}px)`;
}

function initReviewsSlider() {
    clearInterval(reviewSliderTimer);
    currentReviewIndex = 0;

    const track = document.getElementById("reviewsTrack");
    if (!track) return;

    track.style.transform = "translateX(0)";

    const cards = Array.from(track.children);
    const visibleCount = window.innerWidth < 1024 ? 1 : 2;
    if (cards.length <= visibleCount) return;

    reviewSliderTimer = setInterval(() => moveReviews("next"), 4000);

    const prevButton = document.querySelector(".rev-nav-btn.prev");
    const nextButton = document.querySelector(".rev-nav-btn.next");

    if (prevButton) prevButton.onclick = () => moveReviews("prev");
    if (nextButton) nextButton.onclick = () => moveReviews("next");
}

function initRelatedSlider() {
    const grid = document.querySelector(".related-grid");
    if (!grid) return;

    let isDragging = false;
    let startX = 0;
    let scrollLeft = 0;

    grid.addEventListener("mousedown", (event) => {
        isDragging = true;
        startX = event.pageX - grid.offsetLeft;
        scrollLeft = grid.scrollLeft;
    });

    grid.addEventListener("mouseleave", () => {
        isDragging = false;
    });

    grid.addEventListener("mouseup", () => {
        isDragging = false;
    });

    grid.addEventListener("mousemove", (event) => {
        if (!isDragging) return;
        event.preventDefault();
        const currentX = event.pageX - grid.offsetLeft;
        const distance = (currentX - startX) * 1.5;
        grid.scrollLeft = scrollLeft - distance;
    });
}

function checkDelivery() {
    const input = document.getElementById("pincode-input");
    const result = document.querySelector(".del-est-msg");
    const button = document.querySelector(".del-check-btn");
    if (!input || !result || !button) return;

    const pincode = input.value.trim();
    if (!/^\d{6}$/.test(pincode)) {
        input.style.borderColor = "#ff4d4d";
        showToast("Please enter a valid 6-digit India pincode");
        return;
    }

    input.style.borderColor = "var(--gold-primary)";
    button.disabled = true;
    button.innerHTML = "<i class=\"fa-solid fa-circle-notch fa-spin\"></i>";

    setTimeout(() => {
        const [minDays, maxDays] = currentProduct.deliveryDays || [3, 7];
        result.style.display = "block";
        result.innerHTML = `
            <div class="del-success-pill">
                <i class="fa-solid fa-truck-fast"></i>
                Expected delivery in <strong>${minDays} to ${maxDays} days</strong> for <strong>${pincode}</strong>
            </div>
        `;
        button.disabled = false;
        button.innerText = "Check";
        showToast("Delivery options updated");
    }, 900);
}

function populateProductPage(product) {
    const discount = calculateDiscount(product.price, product.oldPrice);

    document.title = `${product.name} - DesiVastra`;
    setText(".product-title-header", product.name);
    setText(".p-title", product.name);
    setText(".p-main-price", formatMoney(product.price));
    setText(".p-old-price", formatMoney(product.oldPrice));
    setText(".p-discount-tag", discount ? `${discount}% OFF` : "Premium Pick");
    setText(".bb-price-header .amount", product.price.toLocaleString("en-IN"));
    setText(".bb-stock span", product.stockLabel);
    setText(".product-specifications p", product.desc);
    setText(".review-count", `| ${product.reviews.toLocaleString("en-IN")} Verified Reviews`);
    const ratingPill = document.querySelector(".p-rating .stars-pill");
    if (ratingPill) ratingPill.innerHTML = `${product.rating.toFixed(1)} <i class="fa-solid fa-star"></i>`;
    setAllText(".rating-num", product.rating.toFixed(1));
    setText(".rating-total", `${product.reviews.toLocaleString("en-IN")} ratings`);
    setText("#review-success-product", product.name);
    setText(".reel-info h3", product.reelTitle);
    setText(".reel-info p", product.reelDescription);

    renderGallery(product);
    renderSizes(product);
    renderFinishes(product);
    renderFeatures(product);
    renderRelatedProducts(product.id);
    renderReviews();
    wireProductButtons();
    syncProductWishlistButtons();
}

document.addEventListener("DOMContentLoaded", () => {
    currentProduct = getProductFromUrl();
    if (!currentProduct) {
        document.title = "Product Not Found - DesiVastra";
        const main = document.querySelector(".scroll-area");
        if (main) {
            main.innerHTML = `
                <div style="text-align:center; padding:80px 20px;">
                    <i class="fa-solid fa-circle-exclamation" style="font-size:48px; color:var(--gold-primary); margin-bottom:20px;"></i>
                    <h2 style="margin-bottom:12px;">Product Not Found</h2>
                    <p style="color:var(--text-secondary); margin-bottom:24px;">The product you are looking for does not exist or has been removed.</p>
                    <a href="shop.php" class="gold-btn" style="display:inline-flex;">Browse Collection</a>
                </div>
            `;
        }
        return;
    }
    populateProductPage(currentProduct);
    initReveal();
    initStickyCta();
    initReviewsSlider();
    initRelatedSlider();

    window.addEventListener("resize", initReviewsSlider);
});
