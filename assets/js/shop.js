const shopState = {
    query: "",
    category: "All",
    sort: "Most Popular",
    filters: {
        maxPrice: 5000,
        categories: [],
        statuses: [],
        sizes: []
    }
};

function getShopGrid() {
    return document.getElementById("main-product-grid");
}

function getShopLoader() {
    return document.querySelector(".loader-spinner");
}

function syncSearchInputs(value) {
    ["shop-search-input-mobile", "shop-search-input-desktop"].forEach((id) => {
        const input = document.getElementById(id);
        if (input && input.value !== value) {
            input.value = value;
        }
    });
}

function syncCategoryTabs() {
    document.querySelectorAll(".shop-cat").forEach((tab) => {
        tab.classList.toggle("active", tab.innerText.trim() === shopState.category);
    });
}

function updateShopUrl() {
    const params = new URLSearchParams();
    if (shopState.query) params.set("query", shopState.query);
    if (shopState.category !== "All") params.set("cat", shopState.category);
    history.replaceState({}, "", `shop.html${params.toString() ? `?${params.toString()}` : ""}`);
}

function parseShopUrlState() {
    const params = new URLSearchParams(window.location.search);
    const category = params.get("cat");
    const query = params.get("query") || "";

    if (category) {
        shopState.category = category;
    }
    shopState.query = query;

    syncSearchInputs(query);
    syncCategoryTabs();
}

function getSelectedStatuses() {
    return Array.from(document.querySelectorAll("#status-tab .check-row input:checked"))
        .map((input) => input.closest(".check-row")?.querySelector("span")?.innerText.trim())
        .filter(Boolean);
}

function getSelectedFilterCategories() {
    return Array.from(document.querySelectorAll("#cat-tab .pop-option.active"))
        .map((option) => option.innerText.trim())
        .filter(Boolean);
}

function getSelectedSizes() {
    return Array.from(document.querySelectorAll("#size-tab .size-box.active"))
        .map((box) => box.innerText.trim())
        .filter(Boolean);
}

function applyCurrentFiltersToState() {
    const slider = document.querySelector(".price-slider");
    shopState.filters = {
        maxPrice: slider ? Number(slider.value) : 5000,
        categories: getSelectedFilterCategories(),
        statuses: getSelectedStatuses(),
        sizes: getSelectedSizes()
    };
}

function matchesQuery(product) {
    if (!shopState.query) return true;
    const haystack = `${product.name} ${product.sku} ${product.cat} ${product.tags}`.toLowerCase();
    return haystack.includes(shopState.query.toLowerCase());
}

function matchesCategory(product) {
    return shopState.category === "All" || product.cat === shopState.category;
}

function matchesFilterCategories(product) {
    return !shopState.filters.categories.length || shopState.filters.categories.includes(product.cat);
}

function matchesPrice(product) {
    return product.price <= shopState.filters.maxPrice;
}

function matchesStatuses(product) {
    const checks = {
        "Top Selling": product.topSelling,
        "New Arrivals": product.newArrival,
        "Boutique Only": product.boutiqueOnly
    };

    return shopState.filters.statuses.every((status) => checks[status]);
}

function matchesSizes(product) {
    return !shopState.filters.sizes.length || product.sizes.some((size) => shopState.filters.sizes.includes(size));
}

function sortProducts(products) {
    const sorted = [...products];

    if (shopState.sort === "Price: Low to High") {
        sorted.sort((a, b) => a.price - b.price);
    } else if (shopState.sort === "Price: High to Low") {
        sorted.sort((a, b) => b.price - a.price);
    } else if (shopState.sort === "New Arrivals") {
        sorted.sort((a, b) => Number(b.newArrival) - Number(a.newArrival) || b.id - a.id);
    } else {
        sorted.sort((a, b) => Number(b.topSelling) - Number(a.topSelling) || b.rating - a.rating);
    }

    return sorted;
}

function getVisibleProducts() {
    return sortProducts(
        mockProducts.filter((product) =>
            matchesQuery(product) &&
            matchesCategory(product) &&
            matchesFilterCategories(product) &&
            matchesPrice(product) &&
            matchesStatuses(product) &&
            matchesSizes(product)
        )
    );
}

function renderEmptyState(message, icon = "fa-magnifying-glass") {
    const grid = getShopGrid();
    if (!grid) return;

    grid.innerHTML = `
        <div style="grid-column:1/-1; padding:100px 20px; text-align:center; color:rgba(255,255,255,0.45);">
            <i class="fa-solid ${icon}" style="font-size:40px; margin-bottom:20px; color:var(--gold-primary); opacity:0.35;"></i>
            <p>${message}</p>
        </div>
    `;
}

function renderProducts(products = getVisibleProducts()) {
    const grid = getShopGrid();
    if (!grid) return;

    if (!products.length) {
        renderEmptyState("No curated items match your current search and filters.", "fa-filter-circle-xmark");
        return;
    }

    grid.innerHTML = products.map((product) => `
        <a href="product.html?id=${product.id}" class="p-card">
            <div class="p-card-inner">
                <div class="p-card-img" style="background-image:url('${product.img}?auto=format&fit=crop&crop=entropy&w=540&h=960&q=80')">
                    <button class="p-card-fav" data-product-id="${product.id}" onclick="toggleFav(event, this)">
                        <i class="fa-regular fa-heart"></i>
                    </button>
                    <div class="p-card-overlay">
                        <div class="p-card-footer-left">
                            <div class="p-card-title">${product.name}</div>
                            <div class="p-price-row">
                                <div class="shop-price-tag">${formatMoney(product.price)}</div>
                                <div class="p-card-old">${formatMoney(product.oldPrice)}</div>
                                ${product.oldPrice && product.oldPrice > product.price ? `<div class="p-card-discount">${Math.round((1 - product.price / product.oldPrice) * 100)}% OFF</div>` : ""}
                            </div>
                            <div class="p-card-footer-right">
                                <div class="color-badge">${product.colors} Colours</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    `).join("");

    if (typeof syncWishlistButtons === "function") {
        syncWishlistButtons();
    }
}

function renderWithLoader(callback) {
    const grid = getShopGrid();
    const loader = getShopLoader();
    if (!grid || !loader) {
        callback();
        return;
    }

    grid.style.opacity = "0.3";
    loader.style.display = "block";

    setTimeout(() => {
        callback();
        grid.style.opacity = "1";
        loader.style.display = "none";
    }, 250);
}

function refreshShop() {
    updateShopUrl();
    renderWithLoader(() => renderProducts(getVisibleProducts()));
}

function toggleFav(event, btn) {
    event.preventDefault();
    event.stopPropagation();

    if (typeof toggleWishlistButton === "function") {
        toggleWishlistButton(btn);
    }
}

function clearFilters() {
    shopState.filters = {
        maxPrice: 5000,
        categories: [],
        statuses: [],
        sizes: []
    };

    document.querySelectorAll("#cat-tab .pop-option.active").forEach((option) => option.classList.remove("active"));
    document.querySelectorAll("#size-tab .size-box.active").forEach((box) => box.classList.remove("active"));
    document.querySelectorAll("#status-tab .check-row input").forEach((input) => { input.checked = false; });

    const slider = document.querySelector(".price-slider");
    if (slider) slider.value = 5000;

    const priceValue = document.getElementById("price-val-2");
    if (priceValue) priceValue.innerText = "5,000";

    showToast("Filters cleared");
    refreshShop();
}

function applyFilters() {
    applyCurrentFiltersToState();
    closeFilter();
    refreshShop();
    const count = getVisibleProducts().length;
    showToast(`Showing ${count} matching item${count === 1 ? "" : "s"}`);
}

function filterCat(btn) {
    shopState.category = btn.innerText.trim();
    syncCategoryTabs();
    refreshShop();
}

function openSort() {
    document.getElementById("sort-drawer")?.classList.add("active");
    document.getElementById("right-overlay")?.classList.add("active");
}

function closeSort() {
    document.getElementById("sort-drawer")?.classList.remove("active");
    document.getElementById("right-overlay")?.classList.remove("active");
}

function openFilter() {
    document.getElementById("filter-drawer")?.classList.add("active");
    document.getElementById("right-overlay")?.classList.add("active");

    const firstTab = document.querySelector(".side-tab");
    if (firstTab) {
        firstTab.click();
    }
}

function closeFilter() {
    document.getElementById("filter-drawer")?.classList.remove("active");
    document.getElementById("right-overlay")?.classList.remove("active");
}

function selectSort(option) {
    document.querySelectorAll("#sort-drawer .pop-option").forEach((item) => item.classList.remove("active"));
    option.classList.add("active");
    shopState.sort = option.querySelector("span")?.innerText.trim() || "Most Popular";

    closeSort();
    refreshShop();
    showToast(`Sorted by ${shopState.sort}`);
}

/* ══════════════════════════════════════════
   ARNIYA LENS — Meesho-style Visual Search
   ══════════════════════════════════════════ */

let lensImageSrc = null; // holds the chosen image URL

// Sample image URLs (products/accessories)
const LENS_SAMPLES = [
    "https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&q=80",
    "https://images.unsplash.com/photo-1611085583191-a3b181a88401?w=600&q=80",
    "https://images.unsplash.com/photo-1606760227091-3dd870d97f1d?w=600&q=80",
    "https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=600&q=80"
];

// AI-generated tag sets per sample
const LENS_TAG_SETS = [
    ["Watch", "Luxury", "Gold", "Accessory"],
    ["Jewelry", "Ring", "Silver", "Premium"],
    ["Bag", "Handbag", "Fashion", "Leather"],
    ["Bag", "Travel", "Backpack", "Style"]
];

function openLens() {
    const modal = document.getElementById("lens-modal");
    if (!modal) return;
    modal.classList.add("active");
    document.body.style.overflow = "hidden";
    resetLens();
}

function closeLens() {
    const modal = document.getElementById("lens-modal");
    if (!modal) return;
    modal.classList.remove("active");
    document.body.style.overflow = "";
}

function resetLens() {
    lensImageSrc = null;
    // Reset file inputs
    ["lens-gallery-input", "lens-camera-input"].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = "";
    });
    // Show step 1, hide others
    showLensStep("lens-step-choose");
    // Restore inner element visibility
    const srcRow = document.getElementById("lens-source-row");
    const box    = document.getElementById("lens-preview-box");
    const cta    = document.getElementById("lens-search-cta");
    const scanO  = document.getElementById("lens-scan-overlay");
    if (srcRow) srcRow.classList.remove("lens-hidden");
    if (box)    box.classList.add("lens-hidden");
    if (cta)    cta.classList.add("lens-hidden");
    if (scanO)  scanO.classList.add("lens-hidden");
    // Reset AI steps
    document.querySelectorAll(".ai-step").forEach((s, i) => {
        s.className = "ai-step" + (i === 0 ? " active" : "");
    });
}

function showLensStep(id) {
    ["lens-step-choose", "lens-step-loading", "lens-step-results"].forEach(sid => {
        const el = document.getElementById(sid);
        if (!el) return;
        if (sid === id) {
            el.classList.remove("lens-hidden");
        } else {
            el.classList.add("lens-hidden");
        }
    });
}

function setLensPreview(src) {
    const box    = document.getElementById("lens-preview-box");
    const img    = document.getElementById("lens-preview-img");
    const cta    = document.getElementById("lens-search-cta");
    const scanO  = document.getElementById("lens-scan-overlay");
    const srcRow = document.getElementById("lens-source-row");

    if (src) {
        if (img) img.src = src;
        if (box) box.classList.remove("lens-hidden");
        if (cta) cta.classList.remove("lens-hidden");
        if (srcRow) srcRow.classList.add("lens-hidden");
        // Run scan animation briefly
        if (scanO) {
            scanO.classList.remove("lens-hidden");
            setTimeout(() => { scanO.classList.add("lens-hidden"); }, 1800);
        }
    } else {
        if (box) box.classList.add("lens-hidden");
        if (cta) cta.classList.add("lens-hidden");
        if (srcRow) srcRow.classList.remove("lens-hidden");
        if (scanO) scanO.classList.add("lens-hidden");
        if (img) img.src = "";
    }
}

function clearLensImage() {
    lensImageSrc = null;
    setLensPreview(null);
    ["lens-gallery-input", "lens-camera-input"].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = "";
    });
}

function handleLensFile(event) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        lensImageSrc = e.target.result;
        setLensPreview(lensImageSrc);
        // Randomly pick a tag set for this upload
        window._lensSampleIdx = Math.floor(Math.random() * LENS_TAG_SETS.length);
    };
    reader.readAsDataURL(file);
}

function useSampleImage(idx) {
    lensImageSrc = LENS_SAMPLES[idx];
    window._lensSampleIdx = idx;
    // Meesho style: tap sample = instant search (no intermediate preview)
    runLensSearch();
}

function runLensSearch() {
    if (!lensImageSrc) return;
    showLensStep("lens-step-loading");

    // Animate AI steps sequentially
    const steps = ["ai-s1", "ai-s2", "ai-s3", "ai-s4"];
    steps.forEach((id, i) => {
        setTimeout(() => {
            document.querySelectorAll(".ai-step").forEach(s => s.classList.remove("active"));
            const el = document.getElementById(id);
            if (el) el.classList.add("active", "done");
        }, i * 700);
    });

    // After all steps, show results
    setTimeout(() => buildLensResults(), steps.length * 700 + 400);
}

function buildLensResults() {
    const tagIdx = window._lensSampleIdx ?? 0;
    const tags   = LENS_TAG_SETS[tagIdx] || ["Fashion", "Style"];
    const keyword = tags[0].toLowerCase();

    // Try to match products by tag keyword, fallback to top-rated
    let matched = mockProducts.filter(p =>
        (p.name + " " + p.cat + " " + (p.tags||"")
        ).toLowerCase().includes(keyword)
    );
    if (matched.length < 3) {
        matched = [...mockProducts].sort((a,b) => b.rating - a.rating);
    }
    matched = matched.slice(0, 8);

    // Exact match = first product
    const exact = matched[0];
    const similar = matched.slice(1);

    // Populate result header
    document.getElementById("lens-result-thumb").src = lensImageSrc;
    document.getElementById("lens-result-count").textContent =
        `${matched.length} products found`;
    document.getElementById("lens-result-tags").innerHTML =
        tags.map(t => `<span class="lens-tag">${t}</span>`).join("");

    // Exact match card
    document.getElementById("lens-exact-card").innerHTML = exact ? `
        <a href="product.html?id=${exact.id}" class="lens-exact-item" onclick="closeLens()">
            <div class="lens-exact-img" style="background-image:url('${exact.img}?auto=format&fit=crop&w=600&q=80')"></div>
            <div class="lens-exact-info">
                <div class="lens-exact-match-badge">✓ Best Match</div>
                <div class="lens-exact-name">${exact.name}</div>
                <div class="lens-exact-price">${formatMoney(exact.price)}
                    <span class="lens-exact-old">${formatMoney(exact.oldPrice)}</span>
                    <span class="lens-exact-off">${Math.round((1-exact.price/exact.oldPrice)*100)}% OFF</span>
                </div>
            </div>
        </a>` : "";

    // Similar products grid
    document.getElementById("lens-results-grid").innerHTML = similar.map(p => `
        <a href="product.html?id=${p.id}" class="lens-sim-card" onclick="closeLens()">
            <div class="lens-sim-img" style="background-image:url('${p.img}?auto=format&fit=crop&w=400&q=80')"></div>
            <div class="lens-sim-info">
                <div class="lens-sim-name">${p.name}</div>
                <div class="lens-sim-price">${formatMoney(p.price)}</div>
                ${p.oldPrice > p.price ? `<div class="lens-sim-off">${Math.round((1-p.price/p.oldPrice)*100)}% OFF</div>` : ""}
            </div>
        </a>
    `).join("");

    showLensStep("lens-step-results");
}

// Legacy function kept for compatibility
function simulateLensSearch() { runLensSearch(); }

document.addEventListener("click", (event) => {
    const tab = event.target.closest(".side-tab");
    if (!tab) return;

    const tabId = tab.dataset.tab;
    document.querySelectorAll(".side-tab").forEach((item) => item.classList.remove("active"));
    document.querySelectorAll(".f-pane").forEach((pane) => pane.classList.remove("active"));

    tab.classList.add("active");
    document.getElementById(tabId)?.classList.add("active");
});

document.addEventListener("DOMContentLoaded", () => {
    parseShopUrlState();
    renderProducts(getVisibleProducts());

    const inputs = [
        document.getElementById("shop-search-input-mobile"),
        document.getElementById("shop-search-input-desktop")
    ].filter(Boolean);

    inputs.forEach((input) => {
        input.addEventListener("input", (event) => {
            shopState.query = event.target.value.trim();
            syncSearchInputs(shopState.query);
            refreshShop();
        });
    });
});
