document.addEventListener('DOMContentLoaded', function() {

    const LOADER_HTML = '<div class="loader"><div></div><div></div><div></div></div>';
    const mainContent = document.querySelector('.dash-main');

    async function fetchDashboardData() {
        mainContent.innerHTML = LOADER_HTML;

        try {
            const response = await fetch('/api/user/dashboard.php');
            if (!response.ok) {
                if (response.status === 401) {
                    window.location.href = 'login.php';
                    return;
                }
                throw new Error('Failed to fetch dashboard data.');
            }

            const data = await response.json();
            if (data.success) {
                renderDashboard(data);
            } else {
                throw new Error(data.message || 'Could not load dashboard.');
            }

        } catch (error) {
            mainContent.innerHTML = `<div class="dash-empty"><i class="fas fa-exclamation-triangle"></i><h3>Error</h3><p>${error.message}</p><a href="dashboard.php" class="btn">Try Again</a></div>`;
        }
    }

    function renderDashboard(data) {
        const { user, orders, addresses, stats } = data;
        
        updateHeader(user);
        buildSidebar(user);
        mainContent.innerHTML = buildPanels(user, orders, addresses, stats);
        
        attachEventListeners();
        
        const urlParams = new URLSearchParams(window.location.search);
        const initialPanel = urlParams.get('section') || 'profile';
        showPanel(initialPanel);
    }

    function updateHeader(user) {
        document.getElementById('hdr-username').textContent = user.name.split(' ')[0];
        document.querySelector('.logo-badge').style.display = 'inline-block';
    }

    function buildSidebar(user) {
        const sidebar = document.getElementById('dashSidebar');
        sidebar.innerHTML = `
            <div class="dash-userbox">
                <div class="dash-avatar">${user.name.charAt(0)}</div>
                <div class="dash-user-meta">
                    <div class="dash-user-name truncate">${user.name}</div>
                    <div class="dash-user-role">${user.user_type_formatted}</div>
                </div>
            </div>
            <div class="dash-tabs">
                <div class="dash-section-title">Account</div>
                <button class="dash-tab" data-tab="profile"><i class="fas fa-id-card"></i><span>Profile</span></button>
                <button class="dash-tab" data-tab="orders"><i class="fas fa-box-open"></i><span>Orders</span></button>
                <button class="dash-tab" data-tab="addresses"><i class="fas fa-map-marker-alt"></i><span>Addresses</span></button>
                <button class="dash-tab" data-tab="wishlist"><i class="fas fa-heart"></i><span>Wishlist</span></button>
                 <div class="dash-section-title">Session</div>
                <button class="dash-tab danger" id="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></button>
            </div>
        `;
    }

    function buildPanels(user, orders, addresses, stats) {
        return `
            ${createProfilePanel(user, stats)}
            ${createOrdersPanel(orders, stats)}
            ${createAddressesPanel(addresses)}
            ${createOrderDetailPanel()}
            <div class="dash-panel" data-panel="wishlist">
                <div class="dash-panel-head">
                    <h2><i class="fa-regular fa-heart"></i>My Wishlist</h2>
                </div>
                <div id="wishlist-list"></div>
            </div>
        `;
    }
    
    function createProfilePanel(user, stats) {
        return `
        <div class="dash-panel" data-panel="profile">
            <div class="dash-panel-head">
                <h2><i class="fa-regular fa-id-card"></i>Profile Overview</h2>
            </div>
            <div class="dash-stats">
                 <div class="dash-stat">
                    <div class="dash-stat-label">Total Orders</div>
                    <div class="dash-stat-value">${stats.total_orders}</div>
                </div>
                <div class="dash-stat">
                    <div class="dash-stat-label">Total Spent</div>
                    <div class="dash-stat-value">₹${stats.total_spent.toLocaleString('en-IN')}</div>
                </div>
                 <div class="dash-stat">
                    <div class="dash-stat-label">Joined</div>
                    <div class="dash-stat-value">${new Date(user.created_at).toLocaleDateString('en-GB', { year: 'numeric', month: 'short' })}</div>
                </div>
            </div>
            <div class="dash-profile-grid">
                <div class="dash-info-row"><label>Full Name</label><strong>${user.name}</strong></div>
                <div class="dash-info-row"><label>Email</label><strong>${user.email}</strong></div>
                <div class="dash-info-row"><label>Phone</label><strong>${user.phone || 'Not provided'}</strong></div>
            </div>
        </div>`;
    }

    function createOrdersPanel(orders, stats) {
        if (!orders || orders.length === 0) {
             return `
             <div class="dash-panel" data-panel="orders">
                <div class="dash-panel-head"><h2><i class="fa-solid fa-box-open"></i>My Orders</h2></div>
                <div class="dash-empty">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>No orders yet</h3>
                    <p>Looks like you haven't placed any orders. Let's change that!</p>
                    <a href="shop.php" class="btn">Start Shopping</a>
                </div>
            </div>`;
        }

        const orderCards = orders.map(order => `
            <div class="order-card" data-order-id="${order.id}">
                <div class="order-head">
                    <div class="order-id">Order ID: <strong>${order.order_number}</strong></div>
                     <div class="order-date"><i class="fa-regular fa-calendar"></i> ${new Date(order.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric'})}</div>
                </div>
                <div class="order-body">
                    <div class="order-info">
                        <div class="order-meta">
                           <span><i class="fas fa-hashtag"></i>Items: ${order.item_count}</span>
                           <span><i class="fas fa-credit-card"></i>Payment: ${order.payment_method}</span>
                        </div>
                         <div class="order-price">₹${parseFloat(order.total).toLocaleString('en-IN')}</div>
                        <div class="order-statuses">
                            <span class="status-badge status-${order.status.toLowerCase()}">${order.status}</span>
                            <span class="status-badge status-${order.payment_status.toLowerCase()}">${order.payment_status}</span>
                        </div>
                    </div>
                </div>
                <div class="order-actions">
                    <button class="order-btn primary view-order-details"><i class="fas fa-eye"></i> View Details</button>
                </div>
            </div>
        `).join('');

        return `
        <div class="dash-panel" data-panel="orders">
            <div class="dash-panel-head"><h2><i class="fa-solid fa-box-open"></i>My Orders</h2></div>
            <div id="orders-list">${orderCards}</div>
        </div>`;
    }
    
    function createAddressesPanel(addresses) {
        if (!addresses || addresses.length === 0) {
            return `
            <div class="dash-panel" data-panel="addresses">
                <div class="dash-panel-head"><h2><i class="fa-solid fa-map-marker-alt"></i>Address Book</h2></div>
                 <div class="dash-empty">
                    <i class="fas fa-map-marked-alt"></i>
                    <h3>No addresses found</h3>
                    <p>Add an address to make checkout faster.</p>
                </div>
            </div>`;
        }

        const addressCards = addresses.map(addr => `
            <div class="address-card">
                ${addr.is_default ? '<span class="addr-tag">Default</span>' : ''}
                <h4><i class="fas fa-map-pin"></i> ${addr.label}</h4>
                <p>
                    <strong>${addr.name}</strong><br>
                    ${addr.address_line1}, ${addr.address_line2 ? addr.address_line2 + ',' : ''}<br>
                    ${addr.city}, ${addr.state} - ${addr.pincode}<br>
                    Phone: ${addr.phone}
                </p>
            </div>
        `).join('');

        return `
        <div class="dash-panel" data-panel="addresses">
            <div class="dash-panel-head"><h2><i class="fa-solid fa-map-marker-alt"></i>Address Book</h2></div>
            <div id="address-list">${addressCards}</div>
        </div>`;
    }
    
    function createOrderDetailPanel() {
        return `<div class="dash-panel" data-panel="order-detail"><div id="order-detail-body"></div></div>`;
    }

    async function fetchAndShowOrderDetails(orderId) {
        const detailBody = document.getElementById('order-detail-body');
        detailBody.innerHTML = LOADER_HTML;
        showPanel('order-detail');
        
        try {
            const response = await fetch(`/api/user/dashboard.php?action=get_order&order_id=${orderId}`);
            if (!response.ok) throw new Error('Could not fetch order details.');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Error loading details.');

            const { order, items } = data;
            
            const itemsHtml = items.map(item => `
                <div class="order-item">
                    <img src="${item.product_image || 'assets/images/placeholder.png'}" alt="${item.product_name}" class="item-img">
                    <div class="item-details">
                        <div class="item-name">${item.product_name}</div>
                        <div class="item-meta">SKU: ${item.sku || 'N/A'} | Qty: ${item.quantity}</div>
                    </div>
                    <div class="item-price">₹${parseFloat(item.price).toLocaleString('en-IN')}</div>
                </div>
            `).join('');

            detailBody.innerHTML = `
                <button class="order-detail-back" onclick="showPanel('orders')"><i class="fa-solid fa-arrow-left"></i> Back to My Orders</button>
                <div class="dash-panel-head"><h2>Order Details</h2></div>
                <div class="order-detail-summary">
                    <div><span>Order ID</span><strong>${order.order_number}</strong></div>
                    <div><span>Date</span><strong>${new Date(order.created_at).toLocaleDateString('en-GB', { day: 'long', month: 'long', year: 'numeric' })}</strong></div>
                    <div><span>Total</span><strong>₹${parseFloat(order.total).toLocaleString('en-IN')}</strong></div>
                    <div><span>Status</span><strong class="status-text status-${order.status.toLowerCase()}">${order.status}</strong></div>
                </div>
                <div class="order-detail-items-container">
                    <h3>Items in this order</h3>
                    ${itemsHtml}
                </div>
                 <div class="order-detail-address">
                    <h3>Shipping Address</h3>
                    <p>
                        <strong>${order.shipping_address.name}</strong><br>
                        ${order.shipping_address.address_line1}, <br>
                        ${order.shipping_address.city}, ${order.shipping_address.state} - ${order.shipping_address.pincode}<br>
                        Phone: ${order.shipping_address.phone}
                    </p>
                </div>
            `;

        } catch(error) {
            detailBody.innerHTML = `<div class="dash-empty"><p>${error.message}</p></div>`;
        }
    }


    function attachEventListeners() {
        document.getElementById('logout-btn').addEventListener('click', () => {
            localStorage.clear();
            window.location.href = 'login.php';
        });
        
        document.querySelectorAll('.dash-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                if(tab.id !== 'logout-btn') showPanel(tab.dataset.tab);
            });
        });

        document.querySelectorAll('.view-order-details').forEach(button => {
            button.addEventListener('click', (e) => {
                const orderId = e.target.closest('.order-card').dataset.orderId;
                fetchAndShowOrderDetails(orderId);
            });
        });
    }

    window.showPanel = function(panelId) {
        document.querySelectorAll('.dash-panel').forEach(p => p.classList.remove('active'));
        document.querySelector(`[data-panel="${panelId}"]`).classList.add('active');

        document.querySelectorAll('.dash-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`[data-tab="${panelId}"]`).classList.add('active');

        document.getElementById('mobile-current-section').textContent = document.querySelector(`[data-tab="${panelId}"] span`).textContent;
        
        const url = new URL(window.location);
        url.searchParams.set('section', panelId);
        history.pushState({}, '', url);
    };
    
    // Initial Load
    fetchDashboardData();
});
