<?php
require_once __DIR__ . '/../includes/core/app.php';
requireAdminLogin();

$pageTitle = "Create Order";
require __DIR__ . '/includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    if (empty($_POST['customer_name']) || empty($_POST['address']) || empty($_POST['products'])) {
        $error = "Customer details and at least one product are required.";
    } else {
        $db->beginTransaction();
        try {
            // 1. Create the order
            $total_amount = 0;
            foreach ($_POST['products'] as $p) {
                $total_amount += $p['price'] * $p['quantity'];
            }
            
            $order_stmt = $db->prepare(
                "INSERT INTO orders (customer_name, address, phone, total_amount, status) VALUES (?, ?, ?, ?, ?)"
            );
            $order_stmt->execute([
                $_POST['customer_name'],
                $_POST['address'],
                $_POST['phone'],
                $total_amount,
                $_POST['status']
            ]);
            $order_id = $db->lastInsertId();

            // 2. Add order items
            $item_stmt = $db->prepare(
                "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)"
            );
            foreach ($_POST['products'] as $p) {
                 $item_stmt->execute([
                    $order_id,
                    $p['id'],
                    $p['quantity'],
                    $p['price']
                ]);
            }
            
            $db->commit();
            redirect('order-detail.php?id=' . $order_id);

        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error creating order: " . $e->getMessage();
        }
    }
}

// Fetch products for selection
$products = $db->query("SELECT id, name, price FROM products WHERE stock > 0 ORDER BY name ASC")->fetchAll();

?>

<div class="main-content">
    <div class="page-header">
        <h1>Create New Order</h1>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="order-create.php" method="POST">
                
                <h4>Customer Details</h4>
                <hr class="my-3">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="customer_name">Full Name</label>
                        <input type="text" id="customer_name" name="customer_name" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label for="address">Full Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3" required></textarea>
                </div>

                <h4 class="mt-4">Order Items</h4>
                <hr class="my-3">
                <div id="order-items-container">
                    <!-- Product items will be added here via JS -->
                </div>
                <button type="button" id="add-product-btn" class="btn btn-sm btn-secondary">+ Add Product</button>

                <h4 class="mt-4">Order Status</h4>
                <hr class="my-3">
                <div class="form-group">
                    <select name="status" class="form-control">
                        <option value="Pending" selected>Pending</option>
                        <option value="Shipped">Shipped</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Create Order</button>
                    <a href="orders.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const products = <?php echo json_encode($products); ?>;

document.getElementById('add-product-btn').addEventListener('click', function() {
    const container = document.getElementById('order-items-container');
    const itemIndex = container.children.length;

    const productOptions = products.map(p => `<option value="${p.id}" data-price="${p.price}">${p.name}</option>`).join('');

    const itemHtml = `
        <div class="form-row align-items-end order-item mb-2">
            <div class="form-group col-md-6">
                <label>Product</label>
                <select name="products[${itemIndex}][id]" class="form-control product-select" required>
                    <option value="">Select a product</option>
                    ${productOptions}
                </select>
            </div>
            <div class="form-group col-md-2">
                <label>Quantity</label>
                <input type="number" name="products[${itemIndex}][quantity]" class="form-control" value="1" min="1" required>
            </div>
             <div class="form-group col-md-2">
                <label>Price</label>
                <input type="number" step="0.01" name="products[${itemIndex}][price]" class="form-control price-input" required>
            </div>
            <div class="form-group col-md-2">
                <button type="button" class="btn btn-danger btn-sm remove-item-btn">Remove</button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', itemHtml);
});

document.getElementById('order-items-container').addEventListener('change', function(e) {
    if (e.target.classList.contains('product-select')) {
        const selectedOption = e.target.options[e.target.selectedIndex];
        const price = selectedOption.dataset.price;
        e.target.closest('.order-item').querySelector('.price-input').value = price;
    }
});

document.getElementById('order-items-container').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-item-btn')) {
        e.target.closest('.order-item').remove();
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
