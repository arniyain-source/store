let selectedPaymentMethod = "UPI";

function checkoutParse(key, fallback) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch (error) {
        console.warn(`Unable to parse ${key}`, error);
        return fallback;
    }
}

function getCheckoutItems() {
    return typeof getCartItemsDetailed === "function" ? getCartItemsDetailed() : [];
}

function renderCheckoutItems() {
    const itemsContainer = document.getElementById("checkout-items");
    const itemCount = document.getElementById("checkout-item-count");
    const subtotal = document.getElementById("summary-subtotal");
    const total = document.getElementById("summary-total");
    const items = getCheckoutItems();

    if (!itemsContainer || !itemCount || !subtotal || !total) return;

    if (!items.length) {
        itemsContainer.innerHTML = `
            <div style="text-align:center; padding:24px 12px;">
                <i class="fa-solid fa-bag-shopping" style="font-size:30px; color:var(--gold-primary); margin-bottom:12px;"></i>
                <h4 style="margin-bottom:8px;">No items in cart</h4>
                <p style="color:var(--text-secondary); line-height:1.6;">Add a few pieces from the boutique before heading to checkout.</p>
                <a href="shop.php" class="gold-btn" style="display:inline-flex; margin-top:18px;">Return to Shop</a>
            </div>
        `;
        itemCount.innerText = "0 items";
        subtotal.innerText = formatMoney(0);
        total.innerText = formatMoney(0);
        document.getElementById("place-order-btn").disabled = true;
        document.getElementById("place-order-btn").style.opacity = "0.6";
        return;
    }

    itemCount.innerText = `${items.length} item${items.length === 1 ? "" : "s"}`;
    subtotal.innerText = formatMoney(getCartTotalPrice());
    total.innerText = formatMoney(getCartTotalPrice());

    itemsContainer.innerHTML = items.map((item) => `
        <div class="checkout-item">
            <div class="checkout-item-image" style="background-image:url('${item.img}?w=160&q=80')"></div>
            <div class="checkout-item-info">
                <h4>${item.name}</h4>
                <div class="checkout-item-meta">${item.sku}${item.size ? ` · ${item.size}` : ""}${item.finish ? ` · ${item.finish}` : ""}</div>
                <div class="checkout-item-row">
                    <span>Qty ${item.qty}</span>
                    <strong>${formatMoney(item.lineTotal)}</strong>
                </div>
            </div>
        </div>
    `).join("");
}

function hydrateCheckoutForm() {
    const storedPhone = localStorage.getItem("arniyaPhone") || "";
    const storedName = localStorage.getItem("arniyaUserName") || "";
    const nameInput = document.getElementById("checkout-name");
    const phoneInput = document.getElementById("checkout-phone");

    if (nameInput) nameInput.value = localStorage.getItem("isLoggedIn") === "true" ? (storedName || "Arniya Member") : "";
    if (phoneInput) phoneInput.value = storedPhone;
}

function bindPaymentOptions() {
    document.querySelectorAll(".payment-option").forEach((button) => {
        button.addEventListener("click", () => {
            document.querySelectorAll(".payment-option").forEach((option) => option.classList.remove("active"));
            button.classList.add("active");
            selectedPaymentMethod = button.dataset.payment || "UPI";
        });
    });
}

function validateCheckoutForm() {
    const name = document.getElementById("checkout-name")?.value.trim() || "";
    const phone = document.getElementById("checkout-phone")?.value.replace(/\D/g, "") || "";
    const address = document.getElementById("checkout-address")?.value.trim() || "";
    const city = document.getElementById("checkout-city")?.value.trim() || "";
    const pincode = document.getElementById("checkout-pincode")?.value.trim() || "";

    if (!name || !address || !city) {
        showToast("Please complete all delivery fields");
        return null;
    }

    if (phone.length !== 10) {
        showToast("Enter a valid 10-digit mobile number");
        return null;
    }

    if (!/^\d{6}$/.test(pincode)) {
        showToast("Enter a valid 6-digit pincode");
        return null;
    }

    return { name, phone, address, city, pincode };
}

function placeOrder() {
    const items = getCheckoutItems();
    if (!items.length) {
        showToast("Your cart is empty");
        return;
    }

    const customer = validateCheckoutForm();
    if (!customer) return;

    const button = document.getElementById("place-order-btn");
    button.disabled = true;
    button.innerHTML = "<i class=\"fa-solid fa-circle-notch fa-spin\"></i> Processing...";

    setTimeout(() => {
        const orders = checkoutParse("arniyaOrders", []);
        const orderId = `ARN${Date.now().toString().slice(-8)}`;
        const order = {
            id: orderId,
            createdAt: new Date().toISOString(),
            payment: selectedPaymentMethod,
            customer,
            total: getCartTotalPrice(),
            items: items.map((item) => ({
                id: item.id,
                name: item.name,
                qty: item.qty,
                size: item.size,
                finish: item.finish,
                total: item.lineTotal
            }))
        };

        orders.unshift(order);
        localStorage.setItem("arniyaOrders", JSON.stringify(orders));
        localStorage.setItem("arniyaPhone", customer.phone);

        clearCart();
        renderCheckoutItems();

        const successCard = document.getElementById("checkout-success");
        if (successCard) {
            successCard.style.display = "block";
            successCard.innerHTML = `
                <i class="fa-solid fa-circle-check"></i>
                <h3>Order Confirmed</h3>
                <p>Your boutique order <strong>${orderId}</strong> has been placed successfully. A confirmation summary has been saved in your account drawer.</p>
                <a href="index.php" class="gold-btn" style="display:inline-flex;">Back to Home</a>
            `;
        }

        button.innerHTML = "Place Order";
        button.disabled = false;
        showToast("Order placed successfully");
    }, 1000);
}

document.addEventListener("DOMContentLoaded", () => {
    hydrateCheckoutForm();
    renderCheckoutItems();
    bindPaymentOptions();
    document.getElementById("place-order-btn")?.addEventListener("click", placeOrder);
});
