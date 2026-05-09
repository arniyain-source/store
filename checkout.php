<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Complete your order securely at DesiVastra.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Checkout - ARNiya Smart Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/checkout.css">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
</head>
<body>
    <div class="app-container checkout-page">
        <header class="checkout-header">
            <a href="shop.php" class="checkout-back"><i class="fa-solid fa-arrow-left"></i> Continue Shopping</a>
            <div class="checkout-brand">Arniya<span class="gold">Hub</span></div>
            <div class="checkout-lock"><i class="fa-solid fa-shield-halved"></i> Secure Checkout</div>
        </header>

        <main class="checkout-layout">
            <section class="checkout-panel">
                <div class="checkout-hero">
                    <span class="checkout-eyebrow">Final Step</span>
                    <h1>Confirm your order</h1>
                    <p>Review your delivery details, choose a payment method, and place your boutique order with confidence.</p>
                </div>

                <div class="checkout-card">
                    <div class="checkout-card-header">
                        <h3>Delivery Details</h3>
                        <span>India shipping enabled</span>
                    </div>
                    <div class="checkout-form-grid">
                        <label class="checkout-field">
                            <span>Full Name</span>
                            <input type="text" id="checkout-name" placeholder="Enter full name">
                        </label>
                        <label class="checkout-field">
                            <span>Mobile Number</span>
                            <input type="tel" id="checkout-phone" placeholder="10-digit mobile number">
                        </label>
                        <label class="checkout-field checkout-field-full">
                            <span>Street Address</span>
                            <input type="text" id="checkout-address" placeholder="House number, street, area">
                        </label>
                        <label class="checkout-field">
                            <span>City</span>
                            <input type="text" id="checkout-city" placeholder="City">
                        </label>
                        <label class="checkout-field">
                            <span>Pincode</span>
                            <input type="text" id="checkout-pincode" maxlength="6" placeholder="6-digit pincode">
                        </label>
                    </div>
                </div>

                <div class="checkout-card">
                    <div class="checkout-card-header">
                        <h3>Payment Method</h3>
                        <span>Mock payment flow</span>
                    </div>
                    <div class="payment-options" id="payment-options">
                        <button type="button" class="payment-option active" data-payment="UPI">
                            <i class="fa-brands fa-google-pay"></i>
                            <span>UPI</span>
                        </button>
                        <button type="button" class="payment-option" data-payment="Card">
                            <i class="fa-regular fa-credit-card"></i>
                            <span>Card</span>
                        </button>
                        <button type="button" class="payment-option" data-payment="COD">
                            <i class="fa-solid fa-money-bill-wave"></i>
                            <span>Cash on Delivery</span>
                        </button>
                    </div>
                </div>

                <div class="checkout-card trust-card">
                    <div class="trust-points">
                        <div class="trust-point">
                            <i class="fa-solid fa-truck-fast"></i>
                            <div>
                                <strong>Fast dispatch</strong>
                                <span>Orders typically leave within 24 hours</span>
                            </div>
                        </div>
                        <div class="trust-point">
                            <i class="fa-solid fa-box-open"></i>
                            <div>
                                <strong>Premium packaging</strong>
                                <span>Every order ships in boutique presentation packaging</span>
                            </div>
                        </div>
                        <div class="trust-point">
                            <i class="fa-solid fa-shield-halved"></i>
                            <div>
                                <strong>Protected payments</strong>
                                <span>All transactions are SSL secured in this demo storefront</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="checkout-summary">
                <div class="summary-card">
                    <div class="checkout-card-header">
                        <h3>Order Summary</h3>
                        <span id="checkout-item-count">0 items</span>
                    </div>
                    <div id="checkout-items" class="checkout-items"></div>
                    <div class="summary-totals">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span id="summary-subtotal">₹0</span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span>Free</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span id="summary-total">₹0</span>
                        </div>
                    </div>
                    <button type="button" class="gold-btn full-width checkout-submit" id="place-order-btn">Place Order</button>
                    <p class="checkout-note">By placing your order, you agree to our boutique order policy and delivery terms.</p>
                </div>
                <div id="checkout-success" class="summary-card success-card" style="display:none;"></div>
            </aside>
        </main>

        <div id="toast-container"></div>
    </div>

    <script src="assets/js/global.js"></script>
    <script src="assets/js/mockData.js"></script>
    <script src="assets/js/checkout.js"></script>
</body>
</html>
