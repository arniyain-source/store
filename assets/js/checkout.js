document.addEventListener('DOMContentLoaded', function() {
    const checkoutItemsContainer = document.getElementById('checkout-items');
    const subtotalSpan = document.getElementById('summary-subtotal');
    const totalSpan = document.getElementById('summary-total');
    const itemCountSpan = document.getElementById('checkout-item-count');
    const placeOrderBtn = document.getElementById('place-order-btn');

    function renderSummary(cart) {
        checkoutItemsContainer.innerHTML = '';
        if (Object.keys(cart.items).length === 0) {
            window.location.href = 'cart.php'; // Redirect if cart is empty
            return;
        }

        Object.values(cart.items).forEach(item => {
            const itemElem = document.createElement('div');
            itemElem.className = 'checkout-item';
            itemElem.innerHTML = `
                <img src="${item.main_image}" alt="${item.name}" class="item-image">
                <div class="item-details">
                    <p class="item-name">${item.name}</p>
                    <p class="item-sub-details">Qty: ${item.quantity}</p>
                </div>
                <p class="item-price">₹${(item.price * item.quantity).toFixed(2)}</p>
            `;
            checkoutItemsContainer.appendChild(itemElem);
        });

        subtotalSpan.textContent = `₹${cart.total.toFixed(2)}`;
        totalSpan.textContent = `₹${cart.total.toFixed(2)}`; // Assuming no extra charges for now
        itemCountSpan.textContent = `${Object.keys(cart.items).length} items`;
    }

    function fetchCartSummary() {
        fetch('api/cart.php?action=get')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderSummary(data.cart);
                } else {
                    // Handle error
                }
            });
    }

    placeOrderBtn.addEventListener('click', function() {
        // Form validation
        const name = document.getElementById('checkout-name').value;
        const phone = document.getElementById('checkout-phone').value;
        const address = document.getElementById('checkout-address').value;
        const city = document.getElementById('checkout-city').value;
        const pincode = document.getElementById('checkout-pincode').value;
        
        if (!name || !phone || !address || !city || !pincode) {
            showToast('Please fill all delivery details.', 'error');
            return;
        }

        const paymentMethod = document.querySelector('.payment-option.active').dataset.payment;

        const orderData = new FormData();
        orderData.append('action', 'place_order');
        orderData.append('customer_name', name);
        orderData.append('phone', phone);
        orderData.append('address', `${address}, ${city}, ${pincode}`);
        orderData.append('payment_method', paymentMethod);

        fetch('api/checkout.php', {
            method: 'POST',
            body: orderData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = `order_confirmation.php?order_id=${data.order_id}`;
            } else {
                showToast(data.message || 'Order placement failed.', 'error');
            }
        });
    });

    // Payment option selection
    document.querySelectorAll('.payment-option').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.payment-option').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });

    fetchCartSummary(); // Initial load
});
