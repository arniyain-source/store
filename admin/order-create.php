<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Manual Order Create - DesiVastra Admin
 */

$csrf = generateCSRF();
$errors = [];
$success = false;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_order') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token.";
    } else {
        $db = getDB();
        $db->beginTransaction();

        try {
            $orderNumber = generateOrderNumber();
            $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
            $customerName = sanitize($_POST['customer_name'] ?? '');
            $customerPhone = sanitize($_POST['customer_phone'] ?? '');
            $customerEmail = sanitize($_POST['customer_email'] ?? '');
            
            $address = [
                'address' => sanitize($_POST['shipping_address'] ?? ''),
                'city' => sanitize($_POST['shipping_city'] ?? ''),
                'state' => sanitize($_POST['shipping_state'] ?? ''),
                'pincode' => sanitize($_POST['shipping_pincode'] ?? ''),
            ];

            $subtotal = (float)($_POST['order_subtotal'] ?? 0);
            $discount = (float)($_POST['order_discount'] ?? 0);
            $shipping = (float)($_POST['order_shipping'] ?? 0);
            $cod_charge = (float)($_POST['order_cod_charge'] ?? 0);
            $total = ($subtotal - $discount) + $shipping + $cod_charge;

            $paymentMethod = sanitize($_POST['payment_method'] ?? 'COD');
            $paymentStatus = sanitize($_POST['payment_status'] ?? 'pending');
            $orderStatus = sanitize($_POST['order_status'] ?? 'confirmed');

            // Insert into orders table
            $stmt = $db->prepare("INSERT INTO orders (order_number, customer_id, customer_name, customer_email, customer_phone, shipping_address, subtotal, discount, shipping_cost, total, payment_method, payment_status, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $orderNumber, $customerId, $customerName, $customerEmail, $customerPhone, 
                json_encode($address), $subtotal, $discount, $shipping, $total, 
                $paymentMethod, $paymentStatus, $orderStatus
            ]);
            $orderId = $db->lastInsertId();

            // Insert order items
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                $itemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, total) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($_POST['items'] as $item) {
                    $pId = (int)$item['id'];
                    $pName = sanitize($item['name']);
                    $pPrice = (float)$item['price'];
                    $pQty = (int)$item['qty'];
                    $pTotal = $pPrice * $pQty;
                    $itemStmt->execute([$orderId, $pId, $pName, $pPrice, $pQty, $pTotal]);
                    
                    // Deduct stock
                    $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$pQty, $pId]);
                }
            }

            $db->commit();
            logActivity('create_order_manual', 'order', $orderId, ['order_number' => $orderNumber]);
            setFlash('success', "Order #$orderNumber created successfully.");
            header('Location: orders.php');
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Error creating order: " . $e->getMessage();
        }
    }
}

// Fetch Categories and initial Products for the UI
$db = getDB();
$categories = $db->query("SELECT id, name FROM categories WHERE status = 1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Order - DesiVastra Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .order-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .item-row { display: grid; grid-template-columns: 3fr 1fr 1fr 1fr 40px; gap: 12px; align-items: end; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .summary-row.total { font-size: 18px; font-weight: 700; color: var(--gold-primary); margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border-color); }
        #customerResults, #productResults { position: absolute; z-index: 100; background: var(--bg-card-solid); border: 1px solid var(--border-color); width: 100%; max-height: 200px; overflow-y: auto; display: none; }
        .search-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color); }
        .search-item:hover { background: rgba(184, 137, 42, 0.1); }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/layout.php'; ?>
    <div class="page-content">
        <div class="page-header">
            <div>
                <div class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a>
                    <span class="separator"><i class="fas fa-chevron-right"></i></span>
                    <a href="orders.php">Orders</a>
                    <span class="separator"><i class="fas fa-chevron-right"></i></span>
                    <span>Create Order</span>
                </div>
                <h1>Manual Order Creation</h1>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="flash-message flash-error">
                <?php foreach ($errors as $err) echo "<p>$err</p>"; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="orderForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="create_order">
            
            <div class="order-grid">
                <!-- Left Column: Items and Customer -->
                <div class="flex-column" style="gap: 24px;">
                    <!-- Customer Selection -->
                    <div class="card">
                        <div class="card-header"><h3><i class="fas fa-user"></i> Customer Details</h3></div>
                        <div class="card-body">
                            <div style="position:relative; margin-bottom: 16px;">
                                <label class="form-label">Search Existing Customer (Name/Phone)</label>
                                <input type="text" id="customerSearch" class="form-control" placeholder="Type to search...">
                                <div id="customerResults"></div>
                            </div>
                            <input type="hidden" name="customer_id" id="customerId">
                            <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                                <div class="form-group">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="customer_name" id="customerName" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="text" name="customer_phone" id="customerPhone" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="customer_email" id="customerEmail" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Items -->
                    <div class="card">
                        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                            <h3><i class="fas fa-box"></i> Order Items</h3>
                            <div style="position:relative; width: 250px;">
                                <input type="text" id="productSearch" class="form-control" placeholder="Search by SKU or Name...">
                                <div id="productResults"></div>
                            </div>
                        </div>
                        <div class="card-body" id="itemsList">
                            <!-- Dynamic Rows Here -->
                            <p id="emptyItems" style="text-align:center; color:var(--text-muted); padding: 20px;">No items added yet. Search products to add them.</p>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div class="card">
                        <div class="card-header"><h3><i class="fas fa-truck"></i> Shipping Address</h3></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Delivery Address *</label>
                                <textarea name="shipping_address" id="shippingAddress" class="form-control" rows="2" required></textarea>
                            </div>
                            <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                                <div class="form-group">
                                    <label class="form-label">City *</label>
                                    <input type="text" name="shipping_city" id="shippingCity" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">State *</label>
                                    <input type="text" name="shipping_state" id="shippingState" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Pincode *</label>
                                    <input type="text" name="shipping_pincode" id="shippingPincode" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Summary and Payment -->
                <div class="flex-column" style="gap: 24px;">
                    <div class="card">
                        <div class="card-header"><h3><i class="fas fa-calculator"></i> Order Summary</h3></div>
                        <div class="card-body">
                            <div class="summary-row"><span>Subtotal</span><span id="displaySubtotal">₹0.00</span></div>
                            <div class="summary-row">
                                <span>Discount</span>
                                <input type="number" name="order_discount" id="orderDiscount" class="form-control" value="0" style="width:80px; padding:4px;" onchange="calculateGrandTotal()">
                            </div>
                            <div class="summary-row">
                                <span>Shipping</span>
                                <input type="number" name="order_shipping" id="orderShipping" class="form-control" value="0" style="width:80px; padding:4px;" onchange="calculateGrandTotal()">
                            </div>
                            <div class="summary-row">
                                <span>COD Charge</span>
                                <input type="number" name="order_cod_charge" id="orderCodCharge" class="form-control" value="0" style="width:80px; padding:4px;" onchange="calculateGrandTotal()">
                            </div>
                            <div class="summary-row total"><span>Total</span><span id="displayTotal">₹0.00</span></div>
                            <input type="hidden" name="order_subtotal" id="orderSubtotal" value="0">
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h3><i class="fas fa-credit-card"></i> Payment & Status</h3></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-control">
                                    <option value="COD">Cash on Delivery (COD)</option>
                                    <option value="UPI">UPI Transfer</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Prepaid">Online Prepaid</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Payment Status</label>
                                <select name="payment_status" class="form-control">
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Order Status</label>
                                <select name="order_status" class="form-control">
                                    <option value="confirmed">Confirmed</option>
                                    <option value="processing">Processing</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary full-width" style="margin-top:20px;">
                                <i class="fas fa-check-circle"></i> Create Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let items = [];

// --- AJAX SEARCH CUSTOMER ---
const customerSearch = document.getElementById('customerSearch');
customerSearch.addEventListener('input', function() {
    const q = this.value;
    if(q.length < 3) return;
    fetch(`ajax/search-customers.php?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            let html = '';
            data.forEach(c => {
                html += `<div class="search-item" onclick='selectCustomer(${JSON.stringify(c)})'>
                    <strong>${c.name}</strong> (${c.phone})
                </div>`;
            });
            document.getElementById('customerResults').innerHTML = html;
            document.getElementById('customerResults').style.display = 'block';
        });
});

function selectCustomer(c) {
    document.getElementById('customerId').value = c.id;
    document.getElementById('customerName').value = c.name;
    document.getElementById('customerPhone').value = c.phone;
    document.getElementById('customerEmail').value = c.email || '';
    document.getElementById('shippingAddress').value = c.address || '';
    document.getElementById('shippingCity').value = c.city || '';
    document.getElementById('shippingState').value = c.state || '';
    document.getElementById('shippingPincode').value = c.pincode || '';
    document.getElementById('customerResults').style.display = 'none';
    customerSearch.value = '';
}

// --- AJAX SEARCH PRODUCT ---
const productSearch = document.getElementById('productSearch');
productSearch.addEventListener('input', function() {
    const q = this.value;
    if(q.length < 2) return;
    fetch(`ajax/search-products.php?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            let html = '';
            data.forEach(p => {
                html += `<div class="search-item" onclick='addItem(${JSON.stringify(p)})'>
                    <strong>${p.sku || 'N/A'}</strong> - ${p.name} (₹${p.price})
                </div>`;
            });
            document.getElementById('productResults').innerHTML = html;
            document.getElementById('productResults').style.display = 'block';
        });
});

function addItem(p) {
    if(items.find(i => i.id === p.id)) {
        alert('Item already added');
        return;
    }
    items.push({...p, qty: 1});
    renderItems();
    document.getElementById('productResults').style.display = 'none';
    productSearch.value = '';
}

function removeItem(id) {
    items = items.filter(i => i.id !== id);
    renderItems();
}

function updateQty(id, qty) {
    let item = items.find(i => i.id === id);
    if(item) item.qty = parseInt(qty) || 1;
    calculateGrandTotal();
}

function updatePrice(id, price) {
    let item = items.find(i => i.id === id);
    if(item) item.price = parseFloat(price) || 0;
    calculateGrandTotal();
}

function renderItems() {
    const list = document.getElementById('itemsList');
    if(items.length === 0) {
        list.innerHTML = `<p id="emptyItems" style="text-align:center; color:var(--text-muted); padding: 20px;">No items added yet. Search products to add them.</p>`;
        calculateGrandTotal();
        return;
    }

    let html = `
        <div class="item-row" style="font-size:11px; font-weight:700; color:var(--text-muted); border-bottom:2px solid var(--border-color); padding-bottom:8px;">
            <div>PRODUCT</div>
            <div>PRICE</div>
            <div>QTY</div>
            <div>TOTAL</div>
            <div></div>
        </div>
    `;

    items.forEach((item, index) => {
        html += `
            <div class="item-row">
                <div class="truncate">
                    <input type="hidden" name="items[${index}][id]" value="${item.id}">
                    <input type="hidden" name="items[${index}][name]" value="${item.name}">
                    <span style="font-weight:600; display:block;">${item.name}</span>
                    <small style="color:var(--gold-primary);">SKU: ${item.sku || 'N/A'}</small>
                </div>
                <div>
                    <input type="number" name="items[${index}][price]" class="form-control" value="${item.price}" style="padding:4px;" onchange="updatePrice(${item.id}, this.value)">
                </div>
                <div>
                    <input type="number" name="items[${index}][qty]" class="form-control" value="${item.qty}" min="1" style="padding:4px;" onchange="updateQty(${item.id}, this.value)">
                </div>
                <div style="font-weight:700;">₹${(item.price * item.qty).toFixed(2)}</div>
                <div>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${item.id})"><i class="fas fa-times"></i></button>
                </div>
            </div>
        `;
    });

    list.innerHTML = html;
    calculateGrandTotal();
}

function calculateGrandTotal() {
    let subtotal = 0;
    items.forEach(i => { subtotal += i.price * i.qty; });

    const discount = parseFloat(document.getElementById('orderDiscount').value) || 0;
    const shipping = parseFloat(document.getElementById('orderShipping').value) || 0;
    const cod = parseFloat(document.getElementById('orderCodCharge').value) || 0;

    const total = (subtotal - discount) + shipping + cod;

    document.getElementById('orderSubtotal').value = subtotal;
    document.getElementById('displaySubtotal').innerText = '₹' + subtotal.toLocaleString('en-IN');
    document.getElementById('displayTotal').innerText = '₹' + total.toLocaleString('en-IN');
}

// Close search results on click outside
document.addEventListener('click', function(e) {
    if(!e.target.closest('#customerSearch')) document.getElementById('customerResults').style.display = 'none';
    if(!e.target.closest('#productSearch')) document.getElementById('productResults').style.display = 'none';
});
</script>
</body>
</html>