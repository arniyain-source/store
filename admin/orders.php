<?php
require_once __DIR__ . '/../includes/core/app.php';
requireAdminLogin();

$pageTitle = "Order Management";
$pageHeading = "Order Management";

require __DIR__ . '/includes/header.php';

$db = getDB();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $_POST['order_id']]);
    $redirect_url = 'orders.php';
    if (isset($_GET['customer_name'])) {
        $redirect_url .= '?customer_name=' . urlencode($_GET['customer_name']);
    }
    redirect($redirect_url);
}

// Base query
$query = "SELECT * FROM orders";
$params = [];

// Filter by customer name if provided
if (isset($_GET['customer_name']) && !empty($_GET['customer_name'])) {
    $customer_name = urldecode($_GET['customer_name']);
    $query .= " WHERE customer_name = ?";
    $params[] = $customer_name;
    $pageHeading = "Orders for " . htmlspecialchars($customer_name);
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

?>

<div class="main-content">
    <div class="page-header">
        <h1><?php echo $pageHeading; ?></h1>
        <?php if (isset($_GET['customer_name'])): ?>
            <a href="orders.php" class="btn btn-sm btn-secondary">Clear Filter</a>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header">
            <a href="order-create.php" class="btn btn-primary">Create New Order</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></td>
                                <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <form action="orders.php<?php echo isset($_GET['customer_name']) ? '?customer_name='.urlencode($_GET['customer_name']) : '' ?>" method="POST" class="status-form">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                            <option value="Pending" <?php if($order['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                                            <option value="Shipped" <?php if($order['status'] == 'Shipped') echo 'selected'; ?>>Shipped</option>
                                            <option value="Delivered" <?php if($order['status'] == 'Delivered') echo 'selected'; ?>>Delivered</option>
                                            <option value="Cancelled" <?php if($order['status'] == 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-secondary">View</a>
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
