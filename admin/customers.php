<?php
require_once __DIR__ . '/../includes/core/app.php';
requireAdminLogin();

$pageTitle = "Customer Management";
require __DIR__ . '/includes/header.php';

$db = getDB();

// Fetch customer data (aggregated from orders)
$customers = $db->query("
    SELECT 
        customer_name, 
        phone, 
        address,
        COUNT(id) as total_orders, 
        SUM(total_amount) as total_spent,
        MAX(created_at) as last_order_date
    FROM orders 
    WHERE customer_name IS NOT NULL AND customer_name != ''
    GROUP BY customer_name, phone, address
    ORDER BY total_spent DESC
")->fetchAll();

?>

<div class="main-content">
    <div class="page-header">
        <h1>Customer Management</h1>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Total Orders</th>
                            <th>Total Spent</th>
                            <th>Last Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                <td><?php echo htmlspecialchars($customer['address']); ?></td>
                                <td><?php echo $customer['total_orders']; ?></td>
                                <td>₹<?php echo number_format($customer['total_spent'], 2); ?></td>
                                <td><?php echo date('d M Y', strtotime($customer['last_order_date'])); ?></td>
                                <td>
                                    <a href="orders.php?customer_name=<?php echo urlencode($customer['customer_name']); ?>" class="btn btn-sm btn-secondary">View Orders</a>
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
