<?php
require_once __DIR__ . '/../includes/core/app.php';
requireAdminLogin();

$pageTitle = "Dashboard";
require __DIR__ . '/includes/header.php';

$db = getDB();

// Fetch dashboard stats
$total_sales = $db->query("SELECT SUM(total_amount) FROM orders WHERE status = 'Delivered'")->fetchColumn();
$total_orders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$total_products = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_customers = $db->query("SELECT COUNT(DISTINCT customer_name) FROM orders WHERE customer_name IS NOT NULL AND customer_name != ''")->fetchColumn();

// Fetch recent orders
$recent_orders = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Fetch top selling products
$top_products = $db->query("
    SELECT p.name, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    GROUP BY p.name
    ORDER BY total_sold DESC
    LIMIT 5
")->fetchAll();

?>

<div class="main-content">
    <div class="page-header">
        <h1>Dashboard</h1>
        <p class="lead">Welcome to your e-commerce control center.</p>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="card card-stat">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-p-circle bg-primary text-white">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="mb-0">₹<?php echo number_format($total_sales, 2); ?></h4>
                            <p class="mb-0">Total Revenue</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card card-stat">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-p-circle bg-info text-white">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="mb-0"><?php echo $total_orders; ?></h4>
                            <p class="mb-0">Total Orders</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card card-stat">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-p-circle bg-success text-white">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="mb-0"><?php echo $total_customers; ?></h4>
                            <p class="mb-0">Total Customers</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card card-stat">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-p-circle bg-warning text-white">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="mb-0"><?php echo $total_products; ?></h4>
                            <p class="mb-0">Total Products</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row mt-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Recent Orders</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>ID</th><th>Customer</th><th>Total</th><th>Status</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><span class="badge badge-<?php echo strtolower($order['status']); ?>"><?php echo $order['status']; ?></span></td>
                                        <td><a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-secondary">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Top Selling Products</h5>
                </div>
                 <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Product</th><th>Units Sold</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo $product['total_sold']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
