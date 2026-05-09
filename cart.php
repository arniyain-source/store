<?php
require_once __DIR__ . '/includes/core/app.php';
$pageTitle = "Shopping Cart";
require __DIR__ . '/includes/header.php';
?>

<div class="cart-container">
    <h1>Shopping Cart</h1>
    <div id="cart-items-container">
        <!-- Cart items will be loaded here by JS -->
    </div>
    <div class="cart-summary">
        <h3>Total: <span id="cart-total">$0.00</span></h3>
        <a href="checkout.php" class="btn-checkout">Proceed to Checkout</a>
    </div>
</div>

<script src="assets/js/cart.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
