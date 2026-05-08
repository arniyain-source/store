<?php
/**
 * Checkout Page - DesiVastra
 */
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Secure Checkout - DesiVastra";
$extraCSS = '<link rel="stylesheet" href="assets/css/checkout.css">';

// Pre-fill data if logged in
$customer = null;
$defaultAddress = null;
if (isset($_SESSION['customer_id'])) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch();

    if ($customer) {
        $stmt = $db->prepare("SELECT * FROM addresses WHERE customer_id = ? AND is_default = 1 LIMIT 1");
        $stmt->execute([$customer['id']]);
        $defaultAddress = $stmt->fetch();
    }
}

include 'templates/head.php';
?>
<div class="app-container checkout-page">
    <?php include 'templates/header.php'; ?>

    <main class="scroll-area">
        <div class="checkout-layout" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
            
            <!-- Mobile Return Link -->
            <div class="checkout-header-mobile mobile-only" style="margin-bottom: 20px;">
                <a href="shop.php" style="color: var(--gold-light); font-size: 14px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-arrow-left"></i> Return to Shop
                </a>
            </div>

            <div class="checkout-grid" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px;">
                
                <div class="checkout-main">
                    <!-- Step 1: Order Summary -->
                    <div class="checkout-card" style="background: var(--bg-card-solid); border: 1px solid var(--glass-border); border-radius: 12px; margin-bottom: 24px; overflow: hidden;">
                        <div class="checkout-card-header" style="padding: 15px 20px; border-bottom: 1px solid var(--glass-border); background: rgba(255,255,255,0.02);">
                            <h3 style="font-size: 16px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;">
                                <span style="width: 24px; height: 24px; background: var(--gold-primary); color: #000; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px;">1</span>
                                Order Summary
                            </h3>
                        </div>
                        <div id="checkout-items-list" style="padding: 20px;">
                            <!-- Placeholder for JS populated items -->
                            <div class="checkout-item-placeholder" style="display: flex; gap: 15px; margin-bottom: 15px;">
                                <div style="width: 60px; height: 60px; background: var(--bg-dark); border-radius: 8px;"></div>
                                <div style="flex: 1;">
                                    <div style="height: 14px; width: 60%; background: var(--bg-dark); margin-bottom: 8px;"></div>
                                    <div style="height: 12px; width: 30%; background: var(--bg-dark);"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Shipping Address -->
                    <div class="checkout-card" style="background: var(--bg-card-solid); border: 1px solid var(--glass-border); border-radius: 12px; margin-bottom: 24px; overflow: hidden;">
                        <div class="checkout-card-header" style="padding: 15px 20px; border-bottom: 1px solid var(--glass-border); background: rgba(255,255,255,0.02);">
                            <h3 style="font-size: 16px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;">
                                <span style="width: 24px; height: 24px; background: var(--gold-primary); color: #000; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px;">2</span>
                                Shipping Address
                            </h3>
                        </div>
                        <div class="checkout-form" style="padding: 25px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group" style="grid-column: span 2;">
                                    <label style="display: block; font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px; font-weight: 600;">Full Name</label>
                                    <input type="text" id="ship-name" value="<?php echo clean($customer['name'] ?? ''); ?>" placeholder="Enter full name" style="width: 100%; padding: 14px; background: var(--bg-dark); border: 1px solid var(--glass-border); border-radius: 8px; color: #fff; outline: none; border-color: var(--gold-dark);">
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label style="display: block; font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px; font-weight: 600;">Phone Number</label>
                                    <input type="tel" id="ship-phone" value="<?php echo clean($customer['phone'] ?? ''); ?>" placeholder="10-digit mobile number" style="width: 100%; padding: 14px; background: var(--bg-dark); border: 1px solid var(--glass-border); border-radius: 8px; color: #fff; outline: none;">
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label style="display: block; font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px; font-weight: 600;">Address Line 1</label>
                                    <input type="text" id="ship-address1" value="<?php echo clean($defaultAddress['address_line1'] ?? ''); ?>" placeholder="House No, Building, Street" style="width: 100%; padding: 14px; background: var(--bg-dark); border: 1px solid var(--glass-border); border-radius: 8px; color: #fff; outline: none;">
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label style="display: block; font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px; font-weight: 600;">Address Line 2 (Optional)</label>
                                    <input type="text" id="ship-address2" value="<?php echo clean($defaultAddress['address_line2'] ?? ''); ?>" placeholder="Landmark, Area" style="width: 100%; padding: 14px; background: var(--bg-dark); border: 1px solid var(--glass-border); border-radius: 8px; color: #fff; outline: none;">
                                </div>
                                <div class="form-group">
                                    <label style="display: block; font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px; font-weight: 600;">City</label>
                                    <input type="text" id="ship-city" value="<?php echo clean($defaultAddress['city'] ?? ''); ?>" placeholder="City" style="width: 100%; padding: 14px; background: var(--bg-dark); border: 1px solid var(--glass-border); border-radius: 8px; color: #fff; outline: none;">
                                </div>
                                <div class="form-group">
                                    <label style="display: block; font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px; font-weight: 600;">State</label>
                                    <input type="text" id="ship-state" value="<?php echo clean($defaultAddress['state'] ?? ''); ?>" placeholder="State" style="width: 100%; padding: 14px; background: var(--bg-dark); border: 1px solid var(--glass-border); border-radius: 8px; color: #fff; outline: none;">
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label style="display: block; font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px; font-weight: 600;">Pincode</label>
                                    <input type="text" id="ship-pincode" value="<?php echo clean($defaultAddress['pincode'] ?? ''); ?>" maxlength="6" placeholder="6-digit Pincode" style="width: 100%; padding: 14px; background: var(--bg-dark); border: 1px solid var(--glass-border); border-radius: 8px; color: #fff; outline: none;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Payment Method -->
                    <div class="checkout-card" style="background: var(--bg-card-solid); border: 1px solid var(--glass-border); border-radius: 12px; margin-bottom: 24px; overflow: hidden;">
                        <div class="checkout-card-header" style="padding: 15px 20px; border-bottom: 1px solid var(--glass-border); background: rgba(255,255,255,0.02);">
                            <h3 style="font-size: 16px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;">
                                <span style="width: 24px; height: 24px; background: var(--gold-primary); color: #000; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px;">3</span>
                                Payment Method
                            </h3>
                        </div>
                        <div class="payment-selection" style="padding: 25px;">
                            <div style="display: flex; flex-direction: column; gap: 15px;">
                                <label class="payment-option-label" style="display: flex; align-items: center; gap: 15px; padding: 16px; border: 1px solid var(--glass-border); border-radius: 10px; cursor: pointer; transition: 0.3s;" id="pay-razorpay-wrap">
                                    <input type="radio" name="payment_method" value="razorpay" checked style="accent-color: var(--gold-primary); transform: scale(1.2);">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #fff;">Online Payment / UPI</div>
                                        <div style="font-size: 11px; color: var(--text-secondary);">Credit/Debit Cards, Netbanking, GooglePay, PhonePe</div>
                                    </div>
                                    <i class="fa-solid fa-credit-card" style="color: var(--gold-light); font-size: 18px;"></i>
                                </label>

                                <label class="payment-option-label" style="display: flex; align-items: center; gap: 15px; padding: 16px; border: 1px solid var(--glass-border); border-radius: 10px; cursor: pointer; transition: 0.3s;" id="pay-cod-wrap">
                                    <input type="radio" name="payment_method" value="cod" style="accent-color: var(--gold-primary); transform: scale(1.2);">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #fff;">Cash on Delivery (COD)</div>
                                        <div style="font-size: 11px; color: var(--gold-light);">Extra ₹50 handling fee applies</div>
                                    </div>
                                    <i class="fa-solid fa-hand-holding-dollar" style="color: var(--text-secondary); font-size: 18px;"></i>
                                </label>

                                <label class="payment-option-label" style="display: flex; align-items: center; gap: 15px; padding: 16px; border: 1px solid var(--glass-border); border-radius: 10px; cursor: pointer; transition: 0.3s;" id="pay-bank-wrap">
                                    <input type="radio" name="payment_method" value="bank_transfer" style="accent-color: var(--gold-primary); transform: scale(1.2);">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #fff;">Direct Bank Transfer</div>
                                        <div style="font-size: 11px; color: var(--text-secondary);">Transfer directly to our bank account</div>
                                    </div>
                                    <i class="fa-solid fa-building-columns" style="color: var(--text-secondary); font-size: 18px;"></i>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sticky Order Totals Sidebar -->
                <div class="checkout-sidebar">
                    <div class="summary-sticky" style="position: sticky; top: 100px;">
                        <div class="card" style="background: var(--bg-card-solid); border: 1px solid var(--gold-primary); border-radius: 12px; padding: 25px; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                            <h3 style="font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px;">Price Details</h3>
                            
                            <div style="display: flex; flex-direction: column; gap: 14px; border-bottom: 1px solid var(--glass-border); padding-bottom: 20px; margin-bottom: 20px;">
                                <div style="display: flex; justify-content: space-between; font-size: 14px; color: var(--text-secondary);">
                                    <span>Subtotal</span>
                                    <span id="summ-subtotal" style="color: #fff; font-weight: 600;">₹0</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 14px; color: var(--text-secondary);">
                                    <span>GST (5%)</span>
                                    <span id="summ-gst" style="color: #fff; font-weight: 600;">₹0</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 14px; color: var(--text-secondary);">
                                    <span>Shipping Fee</span>
                                    <span id="summ-delivery" style="color: var(--success); font-weight: 700;">FREE</span>
                                </div>
                                <div id="cod-row" style="display: none; justify-content: space-between; font-size: 14px; color: var(--text-secondary);">
                                    <span>COD Handling</span>
                                    <span style="color: var(--danger); font-weight: 600;">+₹50</span>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; font-size: 22px; font-weight: 800; color: var(--gold-light); margin-bottom: 25px;">
                                <span>Grand Total</span>
                                <span id="summ-total">₹0</span>
                            </div>

                            <button type="button" class="gold-btn full-width" id="place-order-btn" style="height: 56px; font-size: 16px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">
                                Place Order <i class="fa-solid fa-check-circle" style="margin-left: 10px;"></i>
                            </button>

                            <div style="text-align: center; margin-top: 20px;">
                                <a href="shop.php" style="color: var(--text-muted); font-size: 12px; text-decoration: none; border-bottom: 1px solid var(--text-muted);">
                                    <i class="fa-solid fa-cart-shopping"></i> Edit Shopping Cart
                                </a>
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; align-items: center; gap: 10px; margin-top: 25px; color: var(--text-muted); font-size: 12px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-lock" style="color: var(--success);"></i> 256-bit SSL Secure Checkout
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-truck" style="color: var(--gold-primary);"></i> Estimated Delivery: 3-5 Business Days
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <div class="pb-100"></div>
    </main>

    <?php include 'templates/footer.php'; ?>
</div>

<script src="assets/js/checkout.js"></script>
<script>
    // Payment selection UI styling logic
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    const codRow = document.getElementById('cod-row');

    paymentRadios.forEach(radio => {
        radio.addEventListener('change', (e) => {
            // Reset all wraps
            document.querySelectorAll('.payment-option-label').forEach(wrap => {
                wrap.style.borderColor = 'var(--glass-border)';
                wrap.style.background = 'transparent';
            });
            
            // Highlight selected
            const wrap = e.target.closest('.payment-option-label');
            wrap.style.borderColor = 'var(--gold-primary)';
            wrap.style.background = 'rgba(184,137,42,0.05)';
            
            // Show/Hide COD row
            if (e.target.value === 'cod') {
                codRow.style.display = 'flex';
            } else {
                codRow.style.display = 'none';
            }

            // Recalculate totals (handled in checkout.js)
            if(window.recalculateTotals) window.recalculateTotals();
        });
    });

    // Initialize UI for checked radio
    document.querySelector('input[name="payment_method"]:checked').dispatchEvent(new Event('change'));
</script>
</body>
</html>