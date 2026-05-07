// ==========================================
// ARNiya Smart Hub - Shared Storefront Logic
// ==========================================

const STORAGE_KEYS = {
    cart: "arniyaCart",
    wishlist: "arniyaWishlist",
    orders: "arniyaOrders",
    login: "isLoggedIn",
    phone: "arniyaPhone"
};

function safeParse(key, fallback) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch (error) {
        console.warn(`Failed to parse ${key}`, error);
        return fallback;
    }
}

function toCurrencyNumber(value) {
    if (typeof value === "number") return value;
    return Number(String(value).replace(/[^0-9.]/g, "")) || 0;
}

function formatMoney(value) {
    if (typeof formatPrice === "function") {
        return formatPrice(value);
    }
    return `₹${toCurrencyNumber(value).toLocaleString("en-IN")}`;
}

// ── XSS Sanitization Helper ──
function sanitize(str) {
    const div = document.createElement('div');
    div.textContent = String(str || '');
    return div.innerHTML;
}

function getCatalog() {
    return typeof mockProducts !== "undefined" && Array.isArray(mockProducts) ? mockProducts : [];
}

function getProductSnapshot(id) {
    const catalog = getCatalog();
    if (!catalog.length) return null;
    return typeof getProductById === "function"
        ? getProductById(id)
        : catalog.find((product) => product.id === Number(id)) || null;
}

function resolveProduct(productInput) {
    if (productInput == null) return null;

    if (typeof productInput === "number" || typeof productInput === "string") {
        return getProductSnapshot(productInput);
    }

    if (typeof productInput === "object") {
        const liveProduct = productInput.id != null ? getProductSnapshot(productInput.id) : null;
        return {
            id: Number(productInput.id ?? liveProduct?.id ?? 0),
            name: productInput.name ?? liveProduct?.name ?? "Luxury Item",
            price: toCurrencyNumber(productInput.price ?? liveProduct?.price ?? 0),
            oldPrice: toCurrencyNumber(productInput.oldPrice ?? liveProduct?.oldPrice ?? productInput.price ?? 0),
            sku: productInput.sku ?? liveProduct?.sku ?? "ARN-000",
            img: productInput.img ?? liveProduct?.img ?? "",
            cat: productInput.cat ?? liveProduct?.cat ?? "Curated",
            sizes: productInput.sizes ?? liveProduct?.sizes ?? [],
            finishes: productInput.finishes ?? liveProduct?.finishes ?? [],
            desc: productInput.desc ?? liveProduct?.desc ?? "",
            rating: Number(productInput.rating ?? liveProduct?.rating ?? 4.8)
        };
    }

    return null;
}

function normalizeWishlist() {
    return safeParse(STORAGE_KEYS.wishlist, [])
        .map((id) => Number(id))
        .filter((id, index, array) => Number.isFinite(id) && array.indexOf(id) === index);
}

function normalizeCart() {
    return safeParse(STORAGE_KEYS.cart, [])
        .map((item) => {
            const resolved = resolveProduct(item);
            if (!resolved || !resolved.id) return null;

            return {
                id: resolved.id,
                qty: Math.max(1, Number(item.qty) || 1),
                size: item.size || "",
                finish: item.finish || "",
                name: resolved.name,
                price: toCurrencyNumber(resolved.price),
                oldPrice: toCurrencyNumber(resolved.oldPrice),
                sku: resolved.sku,
                img: resolved.img,
                cat: resolved.cat
            };
        })
        .filter(Boolean);
}

let mockCart = normalizeCart();
let mockWishlist = normalizeWishlist();

function getStoredOrders() {
    return safeParse(STORAGE_KEYS.orders, []);
}

function saveCart() {
    localStorage.setItem(STORAGE_KEYS.cart, JSON.stringify(mockCart));
    updateCartCounts();
}

function saveWishlist() {
    localStorage.setItem(STORAGE_KEYS.wishlist, JSON.stringify(mockWishlist));
    syncWishlistButtons();
}

function getCartItemsDetailed() {
    return mockCart.map((item) => {
        const liveProduct = getProductSnapshot(item.id);
        const merged = {
            ...item,
            ...(liveProduct || {})
        };

        return {
            ...merged,
            price: toCurrencyNumber(merged.price),
            oldPrice: toCurrencyNumber(merged.oldPrice),
            qty: Math.max(1, Number(item.qty) || 1),
            size: item.size || "",
            finish: item.finish || "",
            lineTotal: toCurrencyNumber(merged.price) * (Number(item.qty) || 1)
        };
    });
}

function getCartTotalQuantity() {
    return mockCart.reduce((sum, item) => sum + item.qty, 0);
}

function getCartTotalPrice() {
    return getCartItemsDetailed().reduce((sum, item) => sum + item.lineTotal, 0);
}

function getWishlistProducts() {
    return mockWishlist
        .map((id) => getProductSnapshot(id))
        .filter(Boolean);
}

function updateCartCounts() {
    const total = getCartTotalQuantity();
    document.querySelectorAll(".cart-badge").forEach((badge) => {
        badge.innerText = total;
        badge.style.display = total > 0 ? "flex" : "none";
    });
}

function renderWishlistButton(btn, isWishlisted) {
    if (!btn) return;

    btn.classList.toggle("wishlisted", isWishlisted);
    btn.classList.toggle("active", isWishlisted);
    btn.setAttribute("aria-pressed", String(isWishlisted));

    const icon = btn.querySelector("i");
    if (icon) {
        icon.classList.remove("fa-regular", "fa-solid");
        icon.classList.add(isWishlisted ? "fa-solid" : "fa-regular", "fa-heart");
    }
}

function syncWishlistButtons() {
    document.querySelectorAll("[data-product-id].trend-fav, [data-product-id].p-card-fav, [data-product-id][data-wishlist-button]").forEach((btn) => {
        const productId = Number(btn.dataset.productId);
        renderWishlistButton(btn, mockWishlist.includes(productId));
    });
}

function toggleWishlistButton(btn) {
    if (!btn) return;

    const productId = Number(btn.dataset.productId);
    if (!Number.isFinite(productId)) return;

    const wishlistIndex = mockWishlist.indexOf(productId);
    const isAdding = wishlistIndex === -1;

    if (isAdding) {
        mockWishlist.push(productId);
    } else {
        mockWishlist.splice(wishlistIndex, 1);
    }

    saveWishlist();
    const product = getProductSnapshot(productId);
    showToast(isAdding ? `${product?.name || "Item"} added to wishlist` : `${product?.name || "Item"} removed from wishlist`);
}

function toggleWishlist(btn) {
    toggleWishlistButton(btn);
}

function addToCartGlobal(productInput) {
    const resolved = resolveProduct(productInput);
    if (!resolved) {
        showToast("We could not add that item to the cart");
        return;
    }

    const size = productInput?.size || "";
    const finish = productInput?.finish || "";

    const existing = mockCart.find(
        (item) => item.id === resolved.id && item.size === size && item.finish === finish
    );

    if (existing) {
        existing.qty += Number(productInput?.qty) || 1;
    } else {
        mockCart.push({
            id: resolved.id,
            qty: Number(productInput?.qty) || 1,
            size,
            finish,
            name: resolved.name,
            price: toCurrencyNumber(resolved.price),
            oldPrice: toCurrencyNumber(resolved.oldPrice),
            sku: resolved.sku,
            img: resolved.img,
            cat: resolved.cat
        });
    }

    saveCart();
    showToast(`Added ${resolved.name} to your cart`);

    const drawer = document.getElementById("right-drawer");
    if (drawer && drawer.classList.contains("open") && drawer.dataset.view === "cart") {
        renderCartDrawer();
    }
}

function changeCartQty(productId, delta, size = "", finish = "") {
    const item = mockCart.find(
        (entry) => entry.id === Number(productId) && entry.size === size && entry.finish === finish
    );

    if (!item) return;

    item.qty += delta;
    if (item.qty <= 0) {
        removeCartItem(productId, size, finish);
        return;
    }

    saveCart();
    renderCartDrawer();
}

function removeCartItem(productId, size = "", finish = "") {
    mockCart = mockCart.filter(
        (item) => !(item.id === Number(productId) && item.size === size && item.finish === finish)
    );
    saveCart();
    renderCartDrawer();
    showToast("Item removed from cart");
}

function clearCart() {
    mockCart = [];
    saveCart();
}

function moveWishlistToCart(productId) {
    const product = getProductSnapshot(productId);
    if (!product) return;

    addToCartGlobal({
        id: product.id,
        qty: 1,
        size: product.sizes?.[0] || "",
        finish: product.finishes?.[0]?.name || product.finishes?.[0] || ""
    });

    mockWishlist = mockWishlist.filter((id) => id !== Number(productId));
    saveWishlist();

    const drawer = document.getElementById("right-drawer");
    if (drawer && drawer.classList.contains("open") && drawer.dataset.view === "wishlist") {
        renderWishlistDrawer();
    }
}

function removeWishlistItem(productId) {
    mockWishlist = mockWishlist.filter((id) => id !== Number(productId));
    saveWishlist();
    renderWishlistDrawer();
    showToast("Removed from wishlist");
}

function handleCheckout() {
    if (!mockCart.length) {
        showToast("Your cart is empty right now");
        return;
    }
    window.location.href = "checkout.html";
}

function buildCartMeta(item) {
    const meta = [];
    if (item.size) meta.push(item.size);
    if (item.finish) meta.push(item.finish);
    return meta.length ? `<div class="cart-sku">${meta.join(" · ")}</div>` : "";
}

function renderCartDrawer() {
    const title = document.getElementById("drawer-title");
    const content = document.getElementById("drawer-content");
    if (!title || !content) return;

    const items = getCartItemsDetailed();
    title.innerHTML = `Your Cart <span style="font-size:14px;color:var(--text-secondary)">(${getCartTotalQuantity()} items)</span>`;

    if (!items.length) {
        content.innerHTML = `
            <div style="padding:36px 20px; text-align:center; color:var(--text-secondary);">
                <i class="fa-solid fa-bag-shopping" style="font-size:34px; color:var(--gold-primary); margin-bottom:16px;"></i>
                <h4 style="margin-bottom:10px; color:#fff;">Your cart is empty</h4>
                <p style="line-height:1.6; margin-bottom:20px;">Explore the boutique and add a few curated pieces.</p>
                <button class="gold-btn" onclick="window.location.href='shop.html'">Browse Collection</button>
            </div>
        `;
        return;
    }

    const itemMarkup = items.map((item) => `
        <div class="cart-item">
            <div class="cart-img bg-img" style="background-image: url('${item.img}?w=180&q=80')"></div>
            <div class="cart-info">
                <h4>${sanitize(item.name)}</h4>
                <div class="cart-sku">SKU: ${sanitize(item.sku)}</div>
                ${buildCartMeta(item)}
                <div class="cart-price-row">
                    <span class="gold" style="font-weight:700;">${formatMoney(item.price)}</span>
                    <div class="qty-controls">
                        <button class="qty-btn" onclick="changeCartQty(${item.id}, -1, '${sanitize(item.size)}', '${sanitize(item.finish)}')">-</button>
                        <span style="font-weight:600; color:#fff;">${item.qty}</span>
                        <button class="qty-btn" onclick="changeCartQty(${item.id}, 1, '${sanitize(item.size)}', '${sanitize(item.finish)}')">+</button>
                    </div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                    <span style="font-size:12px; color:var(--text-secondary);">Line Total: ${formatMoney(item.lineTotal)}</span>
                    <button class="outline-btn" style="padding:6px 12px; font-size:12px;" onclick="removeCartItem(${item.id}, '${sanitize(item.size)}', '${sanitize(item.finish)}')">Remove</button>
                </div>
            </div>
        </div>
    `).join("");

    content.innerHTML = `
        ${itemMarkup}
        <div style="padding:20px; border-top:1px solid var(--glass-border); margin-top:10px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:14px; color:var(--text-secondary); font-size:13px;">
                <span>Estimated delivery</span>
                <span>3-7 business days</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:20px; font-weight:700; font-size:18px;">
                <span>Total</span>
                <span class="gold">${formatMoney(getCartTotalPrice())}</span>
            </div>
            <button class="gold-btn full-width" onclick="handleCheckout()">Proceed to Checkout</button>
        </div>
    `;
}

function renderWishlistDrawer() {
    const title = document.getElementById("drawer-title");
    const content = document.getElementById("drawer-content");
    if (!title || !content) return;

    const items = getWishlistProducts();
    title.innerHTML = `Wishlist <span style="font-size:14px;color:var(--text-secondary)">(${items.length} saved)</span>`;

    if (!items.length) {
        content.innerHTML = `
            <div style="padding:36px 20px; text-align:center; color:var(--text-secondary);">
                <i class="fa-regular fa-heart" style="font-size:34px; color:var(--gold-primary); margin-bottom:16px;"></i>
                <h4 style="margin-bottom:10px; color:#fff;">Nothing saved yet</h4>
                <p style="line-height:1.6; margin-bottom:20px;">Save pieces you want to revisit later.</p>
                <button class="gold-btn" onclick="window.location.href='shop.html'">Discover Products</button>
            </div>
        `;
        return;
    }

    content.innerHTML = items.map((product) => `
        <div class="cart-item">
            <div class="cart-img bg-img" style="background-image: url('${product.img}?w=180&q=80')"></div>
            <div class="cart-info">
                <h4>${sanitize(product.name)}</h4>
                <div class="cart-sku">${sanitize(product.cat)} · ${sanitize(product.sku)}</div>
                <div class="cart-price-row" style="margin-top:12px;">
                    <span class="gold" style="font-weight:700;">${formatMoney(product.price)}</span>
                    <div style="display:flex; gap:8px;">
                        <button class="outline-btn" style="padding:6px 12px; font-size:12px;" onclick="removeWishlistItem(${product.id})">Remove</button>
                        <button class="gold-btn" style="padding:8px 14px; font-size:12px;" onclick="moveWishlistToCart(${product.id})">Move to Cart</button>
                    </div>
                </div>
            </div>
        </div>
    `).join("");
}

function renderAccountDrawer() {
    const title = document.getElementById("drawer-title");
    const content = document.getElementById("drawer-content");
    if (!title || !content) return;

    title.innerText = "Account Dashboard";

    const phone = localStorage.getItem(STORAGE_KEYS.phone);
    const orderCount = getStoredOrders().length;

    content.innerHTML = `
        <div style="padding:20px;">
            <div style="text-align:center; margin-bottom:24px;">
                <div style="width:80px; height:80px; margin:0 auto 14px; border-radius:50%; background:var(--gold-gradient); color:#000; display:flex; align-items:center; justify-content:center; font-size:28px; font-weight:800;">A</div>
                <h2 style="margin-bottom:8px;">Arniya Member</h2>
                <p style="color:var(--text-secondary); font-size:13px;">${phone ? `+91 ${sanitize(phone)}` : "Premium access unlocked"}</p>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div style="padding:16px; background:rgba(255,255,255,0.05); border-radius:12px;">
                    <div style="font-size:12px; color:var(--text-secondary); margin-bottom:4px;">Saved Pieces</div>
                    <strong>${mockWishlist.length} item${mockWishlist.length === 1 ? "" : "s"} in wishlist</strong>
                </div>
                <div style="padding:16px; background:rgba(255,255,255,0.05); border-radius:12px;">
                    <div style="font-size:12px; color:var(--text-secondary); margin-bottom:4px;">Recent Orders</div>
                    <strong>${orderCount} completed checkout${orderCount === 1 ? "" : "s"}</strong>
                </div>
                <button class="gold-btn full-width" onclick="window.location.href='dashboard.html'">Open Dashboard</button>
                <button class="outline-btn full-width" onclick="window.location.href='dashboard.html?section=orders'">View Orders</button>
                <button class="outline-btn full-width" onclick="openRightDrawer('wishlist')">Open Wishlist</button>
                <button class="outline-btn full-width" onclick="openRightDrawer('cart')">Open Cart</button>
                <button class="outline-btn full-width" style="border-color:rgba(207,102,121,0.5); color:var(--danger);" onclick="logout()">Logout</button>
            </div>
        </div>
    `;
}

function toggleMenu() {
    const drawer = document.getElementById("menu-drawer");
    const overlay = document.getElementById("menu-overlay");
    if (!drawer || !overlay) return;

    const isOpen = drawer.classList.contains("open");
    drawer.classList.toggle("open", !isOpen);
    overlay.classList.toggle("active", !isOpen);
}

function closeMenu() {
    const drawer = document.getElementById("menu-drawer");
    const overlay = document.getElementById("menu-overlay");
    if (drawer) drawer.classList.remove("open");
    if (overlay) overlay.classList.remove("active");
}

function openRightDrawer(type) {
    const drawer = document.getElementById("right-drawer");
    const overlay = document.getElementById("right-overlay");
    if (!drawer || !overlay) return;

    drawer.dataset.view = type;
    drawer.classList.add("open");
    overlay.classList.add("active");

    if (type === "cart") renderCartDrawer();
    if (type === "wishlist") renderWishlistDrawer();
    if (type === "account") renderAccountDrawer();
}

function closeRightDrawer() {
    const drawer = document.getElementById("right-drawer");
    const overlay = document.getElementById("right-overlay");
    if (drawer) drawer.classList.remove("open");

    const sortDrawer = document.getElementById("sort-drawer");
    const filterDrawer = document.getElementById("filter-drawer");
    if (sortDrawer) sortDrawer.classList.remove("active");
    if (filterDrawer) filterDrawer.classList.remove("active");

    if (overlay) overlay.classList.remove("active");
}

function closeActivePanels() {
    closeMenu();
    closeRightDrawer();
    closeSupportPopup();
    if (typeof closeReview === "function") {
        try {
            closeReview();
        } catch (error) {
            console.warn("closeReview failed", error);
        }
    }
}

function handleAccountClick() {
    if (localStorage.getItem(STORAGE_KEYS.login) !== "true") {
        window.location.href = "login.html";
        return;
    }
    window.location.href = "dashboard.html";
}

function logout() {
    localStorage.removeItem(STORAGE_KEYS.login);
    localStorage.removeItem(STORAGE_KEYS.phone);
    showToast("Logged out successfully");
    setTimeout(() => {
        window.location.href = "index.html";
    }, 700);
}

function showToast(message) {
    let container = document.getElementById("toast-container");
    if (!container) {
        container = document.createElement("div");
        container.id = "toast-container";
        const host = document.querySelector(".app-container") || document.body;
        host.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.className = "toast";
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 2400);
}

function handleSearch(inputId, catId) {
    const input = document.getElementById(inputId);
    const categoryInput = catId ? document.getElementById(catId) : null;
    if (!input) return;

    const query = input.value.trim();
    const category = categoryInput?.value || "All";

    if (!query) {
        showToast("Please enter a search term");
        input.focus();
        return;
    }

    const params = new URLSearchParams();
    params.set("query", query);
    if (category && category !== "All") params.set("cat", category);

    showToast(`Searching for "${query}"`);
    setTimeout(() => {
        window.location.href = `shop.html?${params.toString()}`;
    }, 250);
}

function openReel() {
    const viewer = document.getElementById("video-reel-viewer");
    if (!viewer) return;

    viewer.classList.add("open");
    const video = viewer.querySelector("video");
    if (video) video.play().catch(() => {});
}

function closeReel() {
    const viewer = document.getElementById("video-reel-viewer");
    if (!viewer) return;

    viewer.classList.remove("open");
    const video = viewer.querySelector("video");
    if (video) video.pause();
}

function openSupportPopup() {
    const popup = document.getElementById("support-popup");
    const overlay = document.getElementById("support-overlay");
    if (popup) popup.classList.add("open");
    if (overlay) overlay.classList.add("open");
}

function closeSupportPopup() {
    const popup = document.getElementById("support-popup");
    const overlay = document.getElementById("support-overlay");
    if (popup) popup.classList.remove("open");
    if (overlay) overlay.classList.remove("open");
}

function hydrateGreeting() {
    if (localStorage.getItem(STORAGE_KEYS.login) !== "true") return;

    const phone = localStorage.getItem(STORAGE_KEYS.phone);
    const compactPhone = phone ? `+91 ${phone}` : "Arniya Member";

    const guestGreeting = document.getElementById("guest-greeting");
    if (guestGreeting) guestGreeting.innerText = "Arniya Member";

    document.querySelectorAll(".d-nav-item .small").forEach((label) => {
        if (label.innerText.trim().startsWith("Hello")) {
            label.innerText = `Hello, ${compactPhone}`;
        }
    });
}

function applyNavActiveState() {
    const currentPage = (window.location.pathname.split("/").pop() || "index.html").toLowerCase();
    document.querySelectorAll(".nav-item[href]").forEach((item) => {
        const target = item.getAttribute("href");
        item.classList.toggle("active", currentPage === target.toLowerCase());
    });
}

document.addEventListener("click", (event) => {
    const wishlistButton = event.target.closest(".trend-fav[data-product-id], .p-card-fav[data-product-id]");
    if (wishlistButton) {
        event.preventDefault();
        event.stopPropagation();
        toggleWishlistButton(wishlistButton);
    }
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeActivePanels();
    }
});

document.addEventListener("DOMContentLoaded", () => {
    applyNavActiveState();
    hydrateGreeting();
    updateCartCounts();
    syncWishlistButtons();

    const desktopSearchInput = document.getElementById("desktop-search-input") || document.getElementById("shop-search-input-desktop");
    const desktopSearchButton = document.getElementById("desktop-search-btn") || document.getElementById("shop-search-btn-desktop");
    const mobileSearchInput = document.getElementById("shop-search-input-mobile");

    if (desktopSearchButton && desktopSearchInput) {
        desktopSearchButton.addEventListener("click", () => handleSearch(desktopSearchInput.id, "desktop-search-cat"));
    }

    if (desktopSearchInput) {
        desktopSearchInput.addEventListener("keypress", (event) => {
            if (event.key === "Enter") handleSearch(desktopSearchInput.id, "desktop-search-cat");
        });
    }

    if (mobileSearchInput) {
        mobileSearchInput.addEventListener("keypress", (event) => {
            if (event.key === "Enter") handleSearch(mobileSearchInput.id);
        });
    }
});

// Promo Banner Slider
(function initPromoSlider() {
    const slider = document.getElementById("promoSlider");
    const dots = document.querySelectorAll(".promo-dot");
    const prev = document.getElementById("promoPrev");
    const next = document.getElementById("promoNext");

    if (!slider) return;

    let current = 0;
    const total = document.querySelectorAll(".promo-slide").length;
    let timerId = null;

    function goTo(index) {
        if (!total) return;
        current = (index + total) % total;
        const firstSlide = document.querySelector(".promo-slide");
        const gap = 3;
        const slideWidth = firstSlide ? firstSlide.offsetWidth + gap : 0;
        slider.style.transform = `translateX(-${current * slideWidth}px)`;
        dots.forEach((dot, dotIndex) => dot.classList.toggle("active", dotIndex === current));
    }

    function startAuto() {
        timerId = setInterval(() => goTo(current + 1), 4000);
    }

    function stopAuto() {
        clearInterval(timerId);
    }

    prev?.addEventListener("click", () => {
        stopAuto();
        goTo(current - 1);
        startAuto();
    });

    next?.addEventListener("click", () => {
        stopAuto();
        goTo(current + 1);
        startAuto();
    });

    dots.forEach((dot) => {
        dot.addEventListener("click", () => {
            stopAuto();
            goTo(Number(dot.dataset.idx));
            startAuto();
        });
    });

    slider.addEventListener("mouseenter", stopAuto);
    slider.addEventListener("mouseleave", startAuto);

    let touchStartX = 0;
    slider.addEventListener("touchstart", (event) => {
        touchStartX = event.touches[0].clientX;
    }, { passive: true });

    slider.addEventListener("touchend", (event) => {
        const diff = touchStartX - event.changedTouches[0].clientX;
        if (Math.abs(diff) > 40) {
            stopAuto();
            goTo(current + (diff > 0 ? 1 : -1));
            startAuto();
        }
    }, { passive: true });

    startAuto();
})();
