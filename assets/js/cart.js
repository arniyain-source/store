document.addEventListener('DOMContentLoaded', function () {
    const cartItemsContainer = document.getElementById('cart-items-container');
    const cartTotalSpan = document.getElementById('cart-total');

    function renderCart(cart) {
        cartItemsContainer.innerHTML = ''; // Clear existing items

        if (Object.keys(cart.items).length === 0) {
            cartItemsContainer.innerHTML = '<p>Your cart is empty.</p>';
            cartTotalSpan.textContent = '₹0.00';
            return;
        }

        let table = document.createElement('table');
        table.className = 'cart-table';
        table.innerHTML = `
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        `;
        const tbody = table.querySelector('tbody');

        for (const key in cart.items) {
            const item = cart.items[key];
            let tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="product-info">
                        <img src="${item.main_image}" alt="${item.name}" class="product-image">
                        <div>
                            <p class="product-name">${item.name}</p>
                            <small>${item.size ? 'Size: ' + item.size : ''} ${item.color ? 'Color: ' + item.color : ''}</small>
                        </div>
                    </div>
                </td>
                <td>₹${item.price.toFixed(2)}</td>
                <td>
                    <div class="quantity-selector">
                        <button class="quantity-btn minus" data-key="${key}">-</button>
                        <input type="text" value="${item.quantity}" readonly>
                        <button class="quantity-btn plus" data-key="${key}">+</button>
                    </div>
                </td>
                <td>₹${(item.price * item.quantity).toFixed(2)}</td>
                <td><button class="remove-btn" data-key="${key}">Remove</button></td>
            `;
            tbody.appendChild(tr);
        }

        cartItemsContainer.appendChild(table);
        cartTotalSpan.textContent = `₹${cart.total.toFixed(2)}`;

        addCartEventListeners();
    }

    function fetchCart() {
        fetch('api/cart.php?action=get')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderCart(data.cart);
                } else {
                    cartItemsContainer.innerHTML = '<p>Error loading cart.</p>';
                }
            });
    }

    function updateCart(key, quantity) {
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('item_key', key);
        formData.append('quantity', quantity);

        fetch('api/cart.php', {
            method: 'POST',
            body: formData
        })
        .then(() => fetchCart()); // Re-render the cart after update
    }

    function removeFromCart(key) {
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('item_key', key);

        fetch('api/cart.php', {
            method: 'POST',
            body: formData
        })
        .then(() => fetchCart()); // Re-render the cart after removal
    }

    function addCartEventListeners() {
        document.querySelectorAll('.quantity-btn.minus').forEach(btn => {
            btn.onclick = () => {
                const key = btn.dataset.key;
                const currentQuantity = parseInt(btn.nextElementSibling.value, 10);
                if (currentQuantity > 1) {
                    updateCart(key, currentQuantity - 1);
                }
            };
        });

        document.querySelectorAll('.quantity-btn.plus').forEach(btn => {
            btn.onclick = () => {
                const key = btn.dataset.key;
                const currentQuantity = parseInt(btn.previousElementSibling.value, 10);
                updateCart(key, currentQuantity + 1);
            };
        });

        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.onclick = () => {
                const key = btn.dataset.key;
                removeFromCart(key);
            };
        });
    }

    // Initial cart load
    fetchCart();
});
