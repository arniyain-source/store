<?php
require_once __DIR__ . '/../includes/core/app.php';
requireAdminLogin();

$pageTitle = "Product Form";
require __DIR__ . '/includes/header.php';

$db = getDB();
$product = [
    'id' => null,
    'name' => '',
    'description' => '',
    'price' => '',
    'stock' => '',
];

if (isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $product = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    if (isset($_POST['id'])) {
        // Update
        $stmt = $db->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ? WHERE id = ?");
        $stmt->execute([$name, $description, $price, $stock, $_POST['id']]);
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO products (name, description, price, stock) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description, $price, $stock]);
    }

    redirect('products.php');
}
?>

<div class="main-content">
    <div class="page-header">
        <h1><?php echo $product['id'] ? 'Edit' : 'Add'; ?> Product</h1>
    </div>
    <div class="card">
        <div class="card-body">
            <form action="product-form.php" method="POST">
                <?php if ($product['id']): ?>
                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="5" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="price">Price</label>
                    <input type="number" id="price" name="price" class="form-control" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="stock">Stock</label>
                    <input type="number" id="stock" name="stock" class="form-control" value="<?php echo htmlspecialchars($product['stock']); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Save Product</button>
                <a href="products.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
