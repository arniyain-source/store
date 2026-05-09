<?php
require_once __DIR__ . '/../includes/core/app.php';
requireAdminLogin();

$pageTitle = "Product Management";
require __DIR__ . '/includes/header.php';

$db = getDB();
$products = $db->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
?>

<div class="main-content">
    <div class="page-header">
        <h1>Product Management</h1>
    </div>
    <div class="card">
        <div class="card-header">
            <a href="product-form.php" class="btn btn-primary">Add New Product</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo $product['id']; ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td>₹<?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo $product['stock']; ?></td>
                                <td><?php echo $product['created_at']; ?></td>
                                <td>
                                    <a href="product-form.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                    <a href="delete-product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
