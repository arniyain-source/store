// =========================================
// ARNiya Smart Hub - Dashboard logic
// Frontend-only: user type aware, mock orders
// =========================================

(function () {
    // Optional demo seed: ?demo=wholesale|retailer|reseller|customer
    // Lets a visitor preview each dashboard variant without going through the
    // full login/register flow. Frontend-only mock data.
    const demoType = new URLSearchParams(window.location.search).get('demo');
    if (demoType && ['wholesale', 'retailer', 'reseller', 'customer'].includes(demoType)) {
        localStorage.setItem('isLoggedIn', 'true');
        localStorage.setItem('arniyaUserType', demoType);
        if (!localStorage.getItem('arniyaUserName')) {
            const demoNames = { wholesale: 'Vikram Traders', retailer: 'Rohit Retail Co.', reseller: 'Anjali Sharma', customer: 'Arniya Member' };
            localStorage.setItem('arniyaUserName', demoNames[demoType]);
        }
        if (!localStorage.getItem('arniyaPhone')) localStorage.setItem('arniyaPhone', '9876543210');
        if (!localStorage.getItem('arniyaEmail')) localStorage.setItem('arniyaEmail', 'demo@arniyahub.com');
        if (!localStorage.getItem('arniyaCity')) localStorage.setItem('arniyaCity', 'Mumbai');
        if (!localStorage.getItem('arniyaState')) localStorage.setItem('arniyaState', 'Maharashtra');
    }

    // Require login — bounce to login page if not signed in
    if (localStorage.getItem('isLoggedIn') !== 'true') {
        window.location.href = 'login.php';
        return;
    }

    const userType = (localStorage.getItem('arniyaUserType') || 'customer').toLowerCase();
    const userName = localStorage.getItem('arniyaUserName') || 'Arniya Member';
    const phone = localStorage.getItem('arniyaPhone') || '';
    const email = localStorage.getItem('arniyaEmail') || 'member@arniyahub.com';
    const city = localStorage.getItem('arniyaCity') || 'Mumbai';
    const state = localStorage.getItem('arniyaState') || 'Maharashtra';
    const business = localStorage.getItem('arniyaBusiness') || '';

    // ── Tab definitions per user type ──
    const TAB_LIBRARY = {
        profile:        { icon: 'fa-regular fa-id-card',         label: 'Profile Details' },
        orders:         { icon: 'fa-solid fa-box-open',          label: 'My Orders' },
        bulk:           { icon: 'fa-solid fa-boxes-stacked',     label: 'Bulk Order Inquiry' },
        booking:        { icon: 'fa-solid fa-clock-rotate-left', label: 'Product Booking History' },
        'customer-orders': { icon: 'fa-solid fa-users',          label: 'Customer Order Details' },
        margin:         { icon: 'fa-solid fa-percent',           label: 'Margin Details' },
        profit:         { icon: 'fa-solid fa-chart-line',        label: 'Reseller Profit' },
        wishlist:       { icon: 'fa-regular fa-heart',           label: 'Wishlist' },
        address:        { icon: 'fa-solid fa-location-dot',      label: 'Address Book' },
        payment:        { icon: 'fa-regular fa-credit-card',     label: 'Payment Details' },
        support:        { icon: 'fa-solid fa-headset',           label: 'Support' }
    };

    const TAB_FLOW = {
        wholesale: ['profile', 'orders', 'bulk', 'wishlist', 'address', 'payment', 'support'],
        retailer:  ['profile', 'orders', 'booking', 'address', 'payment', 'support'],
        reseller:  ['profile', 'orders', 'customer-orders', 'margin', 'profit', 'address', 'payment', 'support'],
        customer:  ['profile', 'orders', 'wishlist', 'address', 'support']
    };

    const ROLE_LABELS = {
        wholesale: 'Wholesale User',
        retailer:  'Retailer User',
        reseller:  'Reseller User',
        customer:  'Customer User'
    };

    // ── Mock orders per user type ──
    const ORDER_TEMPLATES = {
        wholesale: [
            { id: 'AH-WH-90123', date: 'Apr 22, 2026', img: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30', name: 'Gold Chronograph Watch', sku: 'AH-W-01', qty: 60, setQty: 10, price: 149940, status: 'shipped', payment: 'paid', delivery: 'In Transit' },
            { id: 'AH-WH-90120', date: 'Apr 14, 2026', img: 'https://images.unsplash.com/photo-1599643478524-fb66f7f2b1d6', name: 'Platinum Ring', sku: 'AH-J-02', qty: 100, setQty: 25, price: 185000, status: 'delivered', payment: 'paid', delivery: 'Delivered Apr 18' },
            { id: 'AH-WH-90118', date: 'Apr 5, 2026',  img: 'https://images.unsplash.com/photo-1606760227091-3dd870d97f1d', name: 'Designer Sunglasses Set', sku: 'AH-A-03', qty: 80, setQty: 20, price: 96000, status: 'pending', payment: 'cod', delivery: 'Awaiting Dispatch' }
        ],
        retailer: [
            { id: 'AH-RT-77013', date: 'Apr 23, 2026', img: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30', name: 'Gold Chronograph Watch', sku: 'AH-W-01', qty: 6, price: 14994, status: 'shipped', delivery: 'In Transit · ETA Apr 27' },
            { id: 'AH-RT-77009', date: 'Apr 17, 2026', img: 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a', name: 'Pearl Drop Earrings', sku: 'AH-J-04', qty: 12, price: 23880, status: 'delivered', delivery: 'Delivered Apr 21' },
            { id: 'AH-RT-77002', date: 'Apr 9, 2026',  img: 'https://images.unsplash.com/photo-1599643478524-fb66f7f2b1d6', name: 'Platinum Ring', sku: 'AH-J-02', qty: 4, price: 7400, status: 'cancelled', delivery: 'Cancelled by customer' }
        ],
        reseller: [
            { id: 'AH-RS-55204', date: 'Apr 24, 2026', img: 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a', name: 'Pearl Drop Earrings', sku: 'AH-J-04', customer: 'Priya Sharma', custMobile: '+91 98201 22456', custAddress: '12, Linking Road, Bandra West, Mumbai 400050', sellingPrice: 2990, basePrice: 1990, status: 'shipped', delivery: 'Out for Delivery' },
            { id: 'AH-RS-55198', date: 'Apr 19, 2026', img: 'https://images.unsplash.com/photo-1606760227091-3dd870d97f1d', name: 'Designer Sunglasses', sku: 'AH-A-03', customer: 'Rahul Mehta', custMobile: '+91 99102 34567', custAddress: 'B-204, Sector 21, Noida 201301', sellingPrice: 1890, basePrice: 1200, status: 'delivered', delivery: 'Delivered Apr 22' },
            { id: 'AH-RS-55190', date: 'Apr 12, 2026', img: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30', name: 'Gold Chronograph Watch', sku: 'AH-W-01', customer: 'Anita Desai', custMobile: '+91 98445 11122', custAddress: '5, MG Road, Bengaluru 560001', sellingPrice: 3299, basePrice: 2499, status: 'confirmed', delivery: 'Packing in progress' }
        ],
        customer: [
            { id: 'AH-CT-44012', date: 'Apr 24, 2026', img: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30', name: 'Gold Chronograph Watch', sku: 'AH-W-01', qty: 1, price: 2499, address: 'Home · 12 Park Street, Mumbai 400001', payment: 'paid', status: 'shipped', delivery: 'Out for Delivery · ETA Apr 26' },
            { id: 'AH-CT-44005', date: 'Apr 16, 2026', img: 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a', name: 'Pearl Drop Earrings', sku: 'AH-J-04', qty: 2, price: 3980, address: 'Home · 12 Park Street, Mumbai 400001', payment: 'paid', status: 'delivered', delivery: 'Delivered Apr 19' },
            { id: 'AH-CT-43998', date: 'Apr 6, 2026',  img: 'https://images.unsplash.com/photo-1599643478524-fb66f7f2b1d6', name: 'Platinum Ring', sku: 'AH-J-02', qty: 1, price: 1850, address: 'Office · Lower Parel, Mumbai', payment: 'cod', status: 'pending', delivery: 'Awaiting confirmation' }
        ]
    };

    // Merge real orders from localStorage with template orders
    // Real orders (placed via checkout) take priority and appear first
    const realOrders = (typeof safeParse === 'function') ? safeParse('arniyaOrders', []) : (function() { try { return JSON.parse(localStorage.getItem('arniyaOrders') || '[]'); } catch(e) { return []; } })();
    
    // Format real orders to match template structure
    const formattedRealOrders = realOrders.map(function(o) {
        const firstItem = (o.items && o.items[0]) || {};
        return {
            id: o.id,
            date: new Date(o.createdAt).toLocaleDateString('en-IN', { month: 'short', day: 'numeric', year: 'numeric' }),
            img: firstItem.img || 'https://images.unsplash.com/photo-1523275335684-37898b6baf30',
            name: firstItem.name || 'Luxury Item',
            sku: firstItem.sku || 'ARN-000',
            qty: firstItem.qty || 1,
            price: o.total || 0,
            address: o.customer ? (o.customer.city || '') : '',
            payment: (o.payment === 'COD') ? 'cod' : 'paid',
            status: 'pending',
            delivery: 'Order confirmed'
        };
    });

    const templateOrders = ORDER_TEMPLATES[userType] || ORDER_TEMPLATES.customer;
    const orders = [...formattedRealOrders, ...templateOrders];

    // ── Format helpers ──
    function money(v) { return '\u20B9' + Number(v).toLocaleString('en-IN'); }

    function statusBadge(status) {
        const map = {
            pending: ['status-pending', 'Pending'],
            confirmed: ['status-confirmed', 'Confirmed'],
            shipped: ['status-shipped', 'Shipped'],
            delivered: ['status-delivered', 'Delivered'],
            cancelled: ['status-cancelled', 'Cancelled'],
            returned: ['status-returned', 'Returned']
        };
        const [cls, label] = map[status] || ['status-pending', status];
        return `<span class="status-badge ${cls}"><i class="fa-solid fa-circle"></i> ${label}</span>`;
    }
    function paymentBadge(payment) {
        if (payment === 'paid') return `<span class="status-badge status-paid"><i class="fa-solid fa-check"></i> Paid</span>`;
        if (payment === 'cod') return `<span class="status-badge status-cod"><i class="fa-solid fa-money-bill"></i> COD</span>`;
        return '';
    }

    // ── Build sidebar ──
    function buildSidebar() {
        const tabs = TAB_FLOW[userType] || TAB_FLOW.customer;
        const container = document.getElementById('dash-tabs');
        const items = tabs.map((key) => {
            const def = TAB_LIBRARY[key];
            return `<button class="dash-tab" data-tab="${key}" onclick="showPanel('${key}')"><i class="${def.icon}"></i><span>${def.label}</span></button>`;
        }).join('');
        container.innerHTML = `
            <div class="dash-section-title">Account</div>
            ${items}
            <div class="dash-section-title">Session</div>
            <button class="dash-tab danger" onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></button>
        `;
    }

    // ── Render profile fields ──
    function fillProfile() {
        const initial = (userName || 'A').trim().charAt(0).toUpperCase();
        document.getElementById('dash-avatar-letter').textContent = initial;
        document.getElementById('dash-username').textContent = userName;
        document.getElementById('dash-userrole').textContent = ROLE_LABELS[userType] || 'Member';
        const hdrName = document.getElementById('hdr-username');
        if (hdrName) hdrName.textContent = userName.split(' ')[0];

        document.getElementById('p-name').textContent = userName;
        document.getElementById('p-type').textContent = ROLE_LABELS[userType] || 'Member';
        document.getElementById('p-mobile').textContent = phone ? `+91 ${phone}` : '+91 98765 43210';
        document.getElementById('p-email').textContent = email;
        document.getElementById('p-city').textContent = city;
        document.getElementById('p-state').textContent = state;
        const bizRow = document.getElementById('p-business-row');
        if (business) {
            document.getElementById('p-business').textContent = business;
        } else if (userType === 'customer') {
            bizRow.style.display = 'none';
        } else {
            document.getElementById('p-business').textContent = `${ROLE_LABELS[userType]} Account`;
        }
    }

    // ── Render orders list (Meesho-style) ──
    function renderOrders() {
        const list = document.getElementById('orders-list');
        const stats = document.getElementById('orders-stats');

        const total = orders.length;
        const delivered = orders.filter((o) => o.status === 'delivered').length;
        const inTransit = orders.filter((o) => o.status === 'shipped' || o.status === 'confirmed').length;
        const pending = orders.filter((o) => o.status === 'pending').length;

        stats.innerHTML = `
            <div class="dash-stat"><div class="dash-stat-label">Total Orders</div><div class="dash-stat-value">${total}</div></div>
            <div class="dash-stat"><div class="dash-stat-label">In Transit</div><div class="dash-stat-value">${inTransit}</div></div>
            <div class="dash-stat"><div class="dash-stat-label">Delivered</div><div class="dash-stat-value">${delivered}</div></div>
            <div class="dash-stat"><div class="dash-stat-label">Pending</div><div class="dash-stat-value">${pending}</div></div>
        `;

        if (!orders.length) {
            list.innerHTML = emptyState('fa-box-open', 'No orders yet', 'Start exploring our luxury collection to place your first order.', 'shop.php', 'Browse Shop');
            return;
        }

        list.innerHTML = orders.map((o) => orderCardHTML(o)).join('');
    }

    function orderCardHTML(o) {
        const statuses = [];
        statuses.push(statusBadge(o.status));
        if (o.payment) statuses.push(paymentBadge(o.payment));
        if (o.delivery) statuses.push(`<span class="status-badge status-shipped"><i class="fa-solid fa-truck"></i> ${escapeHtml(o.delivery)}</span>`);

        const meta = [];
        if (o.sku) meta.push(`<span><i class="fa-solid fa-barcode"></i>${o.sku}</span>`);
        if (o.qty != null) meta.push(`<span><i class="fa-solid fa-cubes"></i>Qty: ${o.qty}</span>`);
        if (o.setQty != null) meta.push(`<span><i class="fa-solid fa-layer-group"></i>Set: ${o.setQty}</span>`);

        let customerBlock = '';
        if (userType === 'reseller' && o.customer) {
            customerBlock = `
                <div class="order-customer">
                    <div class="row"><strong>Customer:</strong> <span>${escapeHtml(o.customer)}</span></div>
                    <div class="row"><strong>Mobile:</strong> <span>${escapeHtml(o.custMobile || '')}</span></div>
                    <div class="row"><strong>Ship To:</strong> <span>${escapeHtml(o.custAddress || '')}</span></div>
                </div>
            `;
        }

        let marginBlock = '';
        if (userType === 'reseller' && o.basePrice != null && o.sellingPrice != null) {
            const margin = o.sellingPrice - o.basePrice;
            marginBlock = `
                <div class="margin-breakdown">
                    <div class="margin-cell">Base<strong>${money(o.basePrice)}</strong></div>
                    <div class="margin-cell">Selling<strong>${money(o.sellingPrice)}</strong></div>
                    <div class="margin-cell">Margin<strong>${money(margin)}</strong></div>
                    <div class="margin-cell">Final<strong>${money(o.sellingPrice)}</strong></div>
                </div>
            `;
        }

        let addressLine = '';
        if (userType === 'customer' && o.address) {
            addressLine = `<div style="font-size:12px; color:var(--text-secondary); margin-bottom:6px;"><i class="fa-solid fa-location-dot" style="color:var(--gold-primary); margin-right:4px;"></i>${escapeHtml(o.address)}</div>`;
        }

        const priceShown = o.price != null ? money(o.price) : (o.sellingPrice != null ? money(o.sellingPrice) : '');

        return `
            <div class="order-card">
                <div class="order-head">
                    <div class="order-id">Order ID: <strong>${o.id}</strong></div>
                    <div class="order-date"><i class="fa-regular fa-calendar"></i> ${o.date}</div>
                </div>
                <div class="order-body">
                    <div class="order-img bg-img" style="background-image: url('${o.img}?w=240&q=80')"></div>
                    <div class="order-info">
                        <div class="order-title">${escapeHtml(o.name)}</div>
                        <div class="order-meta">${meta.join('')}</div>
                        ${addressLine}
                        ${priceShown ? `<div class="order-price">${priceShown}</div>` : ''}
                        ${customerBlock}
                        ${marginBlock}
                        <div class="order-statuses">${statuses.join('')}</div>
                    </div>
                </div>
                <div class="order-actions">${actionButtonsFor(o)}</div>
            </div>
        `;
    }

    function actionButtonsFor(o) {
        const buttons = [];
        const trackable = o.status === 'shipped' || o.status === 'confirmed' || o.status === 'delivered';

        // Common: details
        buttons.push(`<button class="order-btn primary" onclick="openOrderDetail('${o.id}')"><i class="fa-solid fa-eye"></i> View Details</button>`);

        // Tracking
        if (trackable) {
            buttons.push(`<button class="order-btn" onclick="openOrderDetail('${o.id}')"><i class="fa-solid fa-truck"></i> Tracking</button>`);
        }

        // Invoice (all except wholesale-pending? show always)
        if (o.status !== 'cancelled') {
            buttons.push(`<button class="order-btn" onclick="showToast('Downloading invoice ${o.id}...')"><i class="fa-solid fa-file-invoice"></i> Invoice</button>`);
        }

        // Per-type extras
        if (userType === 'wholesale') {
            buttons.push(`<button class="order-btn" onclick="reorder('${o.id}')"><i class="fa-solid fa-rotate-right"></i> Reorder</button>`);
            buttons.push(`<button class="order-btn" onclick="showPanel('support')"><i class="fa-solid fa-headset"></i> Support</button>`);
        }
        if (userType === 'retailer') {
            if (o.status === 'pending' || o.status === 'confirmed') {
                buttons.push(`<button class="order-btn danger" onclick="cancelOrder('${o.id}')"><i class="fa-solid fa-ban"></i> Cancel Request</button>`);
            }
            if (o.status === 'delivered') {
                buttons.push(`<button class="order-btn danger" onclick="returnOrder('${o.id}')"><i class="fa-solid fa-rotate-left"></i> Return Request</button>`);
            }
        }
        if (userType === 'reseller') {
            buttons.push(`<button class="order-btn" onclick="showPanel('support')"><i class="fa-solid fa-headset"></i> Support</button>`);
        }
        if (userType === 'customer') {
            if (o.status === 'pending' || o.status === 'confirmed') {
                buttons.push(`<button class="order-btn danger" onclick="cancelOrder('${o.id}')"><i class="fa-solid fa-ban"></i> Cancel</button>`);
            }
            if (o.status === 'delivered') {
                buttons.push(`<button class="order-btn danger" onclick="returnOrder('${o.id}')"><i class="fa-solid fa-rotate-left"></i> Return</button>`);
            }
            buttons.push(`<button class="order-btn" onclick="showPanel('support')"><i class="fa-solid fa-circle-question"></i> Help</button>`);
        }

        return buttons.join('');
    }

    // ── Order detail drilldown ──
    window.openOrderDetail = function (orderId) {
        const o = orders.find((x) => x.id === orderId);
        const body = document.getElementById('order-detail-body');
        if (!o) {
            body.innerHTML = emptyState('fa-circle-question', 'Order not found', 'We could not find that order in your history.');
            showPanel('order-detail');
            return;
        }

        const stages = ['pending', 'confirmed', 'shipped', 'delivered'];
        const currentIndex = stages.indexOf(o.status);
        const trackingHtml = o.status === 'cancelled'
            ? `<div class="dash-info-row" style="border-color:rgba(207,102,121,0.25); background:rgba(207,102,121,0.06);"><label style="color:var(--danger);">Order Cancelled</label><strong>${escapeHtml(o.delivery || 'Cancelled')}</strong></div>`
            : `
                <div class="order-tracking-steps">
                    ${stages.map((s, i) => `
                        <div class="track-step ${i <= currentIndex ? 'done' : ''}">
                            <div class="track-dot"><i class="fa-solid fa-${i <= currentIndex ? 'check' : 'circle'}"></i></div>
                            <div class="track-label">${s.charAt(0).toUpperCase() + s.slice(1)}</div>
                        </div>
                    `).join('')}
                </div>
            `;

        body.innerHTML = `
            <div class="dash-panel-head">
                <h2><i class="fa-solid fa-receipt"></i>Order ${o.id}</h2>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="order-btn" onclick="showToast('Downloading invoice ${o.id}...')"><i class="fa-solid fa-file-invoice"></i> Invoice</button>
                    <button class="order-btn primary" onclick="showPanel('support')"><i class="fa-solid fa-headset"></i> Get Help</button>
                </div>
            </div>

            ${trackingHtml}

            <div class="order-card">
                <div class="order-body">
                    <div class="order-img bg-img" style="background-image: url('${o.img}?w=240&q=80'); width:120px; height:120px;"></div>
                    <div class="order-info">
                        <div class="order-title">${escapeHtml(o.name)}</div>
                        <div class="order-meta">
                            <span><i class="fa-solid fa-barcode"></i>SKU: ${o.sku}</span>
                            ${o.qty != null ? `<span><i class="fa-solid fa-cubes"></i>Qty: ${o.qty}</span>` : ''}
                            <span><i class="fa-regular fa-calendar"></i>${o.date}</span>
                        </div>
                        ${o.price != null ? `<div class="order-price">${money(o.price)}</div>` : ''}
                        ${o.sellingPrice != null && o.price == null ? `<div class="order-price">${money(o.sellingPrice)}</div>` : ''}
                        <div class="order-statuses">
                            ${statusBadge(o.status)}
                            ${o.payment ? paymentBadge(o.payment) : ''}
                        </div>
                    </div>
                </div>
            </div>

            <div class="dash-profile-grid" style="margin-top:18px;">
                ${o.delivery ? `<div class="dash-info-row"><label>Delivery Status</label><strong>${escapeHtml(o.delivery)}</strong></div>` : ''}
                ${o.address ? `<div class="dash-info-row"><label>Delivery Address</label><strong>${escapeHtml(o.address)}</strong></div>` : ''}
                ${o.customer ? `<div class="dash-info-row"><label>Customer</label><strong>${escapeHtml(o.customer)}</strong></div>` : ''}
                ${o.custMobile ? `<div class="dash-info-row"><label>Customer Mobile</label><strong>${escapeHtml(o.custMobile)}</strong></div>` : ''}
                ${o.custAddress ? `<div class="dash-info-row"><label>Customer Address</label><strong>${escapeHtml(o.custAddress)}</strong></div>` : ''}
                ${o.basePrice != null ? `<div class="dash-info-row"><label>Base Price</label><strong>${money(o.basePrice)}</strong></div>` : ''}
                ${o.sellingPrice != null ? `<div class="dash-info-row"><label>Selling Price</label><strong>${money(o.sellingPrice)}</strong></div>` : ''}
                ${o.basePrice != null && o.sellingPrice != null ? `<div class="dash-info-row"><label>Your Margin</label><strong style="color:var(--gold-light)">${money(o.sellingPrice - o.basePrice)}</strong></div>` : ''}
            </div>
        `;

        showPanel('order-detail');
    };

    window.reorder = function (orderId) {
        showToast('Reordering ' + orderId + '...');
    };
    window.cancelOrder = function (orderId) {
        showToast('Cancel request sent for ' + orderId);
    };
    window.returnOrder = function (orderId) {
        showToast('Return request sent for ' + orderId);
    };

    // ── Booking history (retailer) ──
    function renderBooking() {
        const list = document.getElementById('booking-list');
        if (!list) return;
        const rows = [
            { id: 'BK-3401', name: 'Gold Chronograph Watch', sku: 'AH-W-01', date: 'Apr 22, 2026', qty: 6, status: 'Confirmed' },
            { id: 'BK-3398', name: 'Pearl Drop Earrings', sku: 'AH-J-04', date: 'Apr 17, 2026', qty: 12, status: 'Booked' },
            { id: 'BK-3392', name: 'Designer Sunglasses', sku: 'AH-A-03', date: 'Apr 9, 2026', qty: 8, status: 'Cancelled' }
        ];
        list.innerHTML = rows.map((r) => `
            <div class="order-card">
                <div class="order-head">
                    <div class="order-id">Booking ID: <strong>${r.id}</strong></div>
                    <div class="order-date">${r.date}</div>
                </div>
                <div class="order-body">
                    <div class="order-info">
                        <div class="order-title">${r.name}</div>
                        <div class="order-meta">
                            <span><i class="fa-solid fa-barcode"></i>${r.sku}</span>
                            <span><i class="fa-solid fa-cubes"></i>Qty: ${r.qty}</span>
                            <span><i class="fa-solid fa-circle-info"></i>${r.status}</span>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // ── Reseller customer list & margins ──
    function renderResellerLists() {
        const custList = document.getElementById('reseller-customer-list');
        const marginList = document.getElementById('margin-list');
        const profitList = document.getElementById('profit-list');
        if (!custList || !marginList || !profitList) return;

        custList.innerHTML = orders.map((o) => `
            <div class="order-card">
                <div class="order-head">
                    <div class="order-id">Order: <strong>${o.id}</strong></div>
                    <div class="order-date">${o.date}</div>
                </div>
                <div class="order-customer">
                    <div class="row"><strong>${escapeHtml(o.customer || '')}</strong> · ${escapeHtml(o.custMobile || '')}</div>
                    <div class="row">${escapeHtml(o.custAddress || '')}</div>
                </div>
                <div class="order-actions">
                    <button class="order-btn" onclick="window.location.href='tel:${(o.custMobile||'').replace(/\\s/g,'')}'"><i class="fa-solid fa-phone"></i> Call</button>
                    <button class="order-btn primary" onclick="openOrderDetail('${o.id}')"><i class="fa-solid fa-eye"></i> View Order</button>
                </div>
            </div>
        `).join('');

        marginList.innerHTML = orders.map((o) => `
            <div class="order-card">
                <div class="order-head">
                    <div class="order-id">${escapeHtml(o.name)} · <strong>${o.id}</strong></div>
                    <div class="order-date">${o.date}</div>
                </div>
                <div class="margin-breakdown">
                    <div class="margin-cell">Base<strong>${money(o.basePrice)}</strong></div>
                    <div class="margin-cell">Selling<strong>${money(o.sellingPrice)}</strong></div>
                    <div class="margin-cell">Margin<strong>${money(o.sellingPrice - o.basePrice)}</strong></div>
                    <div class="margin-cell">% Profit<strong>${Math.round(((o.sellingPrice - o.basePrice) / o.basePrice) * 100)}%</strong></div>
                </div>
            </div>
        `).join('');

        profitList.innerHTML = orders.filter((o) => o.status !== 'cancelled').map((o) => `
            <div class="order-card">
                <div class="order-head">
                    <div class="order-id">${escapeHtml(o.name)} · <strong>${o.id}</strong></div>
                    <div class="order-date">${o.date}</div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0;">
                    <span style="font-size:13px; color:var(--text-secondary);">Profit earned</span>
                    <span style="font-size:18px; font-weight:700; color:var(--gold-light);">${money(o.sellingPrice - o.basePrice)}</span>
                </div>
            </div>
        `).join('');
    }

    // ── Wishlist (use shared global storage if available) ──
    function renderWishlistPanel() {
        const list = document.getElementById('wishlist-list');
        if (!list) return;
        const products = (typeof getWishlistProducts === 'function') ? getWishlistProducts() : [];
        if (!products.length) {
            list.innerHTML = emptyState('fa-heart', 'Your wishlist is empty', 'Save pieces you love to revisit them later.', 'shop.php', 'Discover Products');
            return;
        }
        list.innerHTML = products.map((p) => `
            <div class="order-card">
                <div class="order-body">
                    <div class="order-img bg-img" style="background-image: url('${p.img}?w=240&q=80')"></div>
                    <div class="order-info">
                        <div class="order-title">${escapeHtml(p.name)}</div>
                        <div class="order-meta"><span><i class="fa-solid fa-barcode"></i>${p.sku}</span><span><i class="fa-solid fa-tag"></i>${p.cat}</span></div>
                        <div class="order-price">${money(p.price)}</div>
                        <div class="order-actions">
                            <button class="order-btn primary" onclick="moveWishlistToCart(${p.id}); renderWishlistPanelExternal();"><i class="fa-solid fa-cart-plus"></i> Move to Cart</button>
                            <button class="order-btn danger" onclick="removeWishlistItem(${p.id}); renderWishlistPanelExternal();"><i class="fa-solid fa-trash"></i> Remove</button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }
    window.renderWishlistPanelExternal = renderWishlistPanel;

    // ── Address book (mock) ──
    function renderAddress() {
        const list = document.getElementById('address-list');
        if (!list) return;
        const addresses = [
            { tag: 'Home', name: userName, line: `${city}, ${state}`, full: 'Flat 12, Park Avenue, Near Marine Drive', phone: phone ? `+91 ${phone}` : '+91 98765 43210', primary: true },
            { tag: 'Office', name: business || `${userName} (Work)`, line: `${city}, ${state}`, full: 'Office 304, Trade Tower, Lower Parel', phone: phone ? `+91 ${phone}` : '+91 98765 43210', primary: false }
        ];
        list.innerHTML = addresses.map((a) => `
            <div class="address-card">
                ${a.primary ? '<span class="addr-tag">Default</span>' : ''}
                <h4><i class="fa-solid fa-${a.tag === 'Home' ? 'house' : 'briefcase'}" style="color:var(--gold-primary); margin-right:6px;"></i>${a.tag} · ${escapeHtml(a.name)}</h4>
                <p>${escapeHtml(a.full)}<br>${escapeHtml(a.line)}<br><strong style="color:#fff;">${escapeHtml(a.phone)}</strong></p>
                <div class="addr-actions">
                    <button class="order-btn" onclick="showToast('Edit address (mock)')"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
                    <button class="order-btn danger" onclick="showToast('Delete address (mock)')"><i class="fa-solid fa-trash"></i> Delete</button>
                </div>
            </div>
        `).join('');
    }

    // ── Payment methods (mock) ──
    function renderPayment() {
        const list = document.getElementById('payment-list');
        if (!list) return;
        const cards = [
            { brand: 'Visa', last4: '4824', expiry: '08/28', name: userName },
            { brand: 'UPI', last4: '@arniya', expiry: 'Linked', name: 'Default UPI' }
        ];
        list.innerHTML = cards.map((c) => `
            <div class="address-card">
                <h4><i class="fa-${c.brand === 'UPI' ? 'solid fa-mobile-screen' : 'brands fa-cc-visa'}" style="color:var(--gold-primary); margin-right:6px;"></i>${c.brand} ${c.brand === 'UPI' ? c.last4 : '•••• ' + c.last4}</h4>
                <p>${escapeHtml(c.name)} · Expiry: ${c.expiry}</p>
                <div class="addr-actions">
                    <button class="order-btn" onclick="showToast('Manage payment (mock)')"><i class="fa-solid fa-pen-to-square"></i> Manage</button>
                    <button class="order-btn danger" onclick="showToast('Remove payment (mock)')"><i class="fa-solid fa-trash"></i> Remove</button>
                </div>
            </div>
        `).join('');
    }

    function emptyState(icon, title, msg, link, linkLabel) {
        const cta = link ? `<button class="gold-btn" onclick="window.location.href='${link}'">${linkLabel}</button>` : '';
        return `
            <div class="dash-empty">
                <i class="fa-solid ${icon}"></i>
                <h3>${title}</h3>
                <p>${msg}</p>
                ${cta}
            </div>
        `;
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ── Panel switching ──
    window.showPanel = function (key) {
        document.querySelectorAll('.dash-panel').forEach((p) => p.classList.remove('active'));
        const target = document.querySelector(`.dash-panel[data-panel="${key}"]`);
        if (target) target.classList.add('active');
        document.querySelectorAll('.dash-tab').forEach((t) => t.classList.toggle('active', t.dataset.tab === key));

        const def = TAB_LIBRARY[key];
        const cur = document.getElementById('mobile-current-section');
        if (cur && def) cur.textContent = def.label;
        if (window.matchMedia('(max-width: 900px)').matches) {
            const sb = document.getElementById('dashSidebar');
            if (sb) sb.classList.remove('open');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        if (key === 'wishlist') renderWishlistPanel();

        const url = new URL(window.location.href);
        url.searchParams.set('section', key);
        history.replaceState({}, '', url.toString());
    };

    window.toggleDashSidebar = function () {
        const sb = document.getElementById('dashSidebar');
        if (sb) sb.classList.toggle('open');
    };

    // ── Init ──
    document.addEventListener('DOMContentLoaded', () => {
        buildSidebar();
        fillProfile();
        renderOrders();
        renderBooking();
        renderResellerLists();
        renderAddress();
        renderPayment();
        renderWishlistPanel();

        // Pick initial panel
        const params = new URLSearchParams(window.location.search);
        const requested = params.get('section');
        const allowed = TAB_FLOW[userType] || TAB_FLOW.customer;
        const initial = requested && allowed.includes(requested) ? requested : 'profile';
        showPanel(initial);

        // Active styling for first tab
        const firstTab = document.querySelector(`.dash-tab[data-tab="${initial}"]`);
        if (firstTab) firstTab.classList.add('active');
    });
})();
