<?php
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

$db    = getDB();
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { setFlash('error', 'Invalid token.'); redirect('flash-sales.php'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $productId  = (int)$_POST['product_id'];
        $salePrice  = (float)$_POST['sale_price'];
        $startsAt   = sanitize($_POST['starts_at'] ?? '');
        $endsAt     = sanitize($_POST['ends_at'] ?? '');
        // We store flash sales as a coupon with product discount or as a product price override
        // Simple approach: update product old_price and set sale price + badge
        $db->prepare("UPDATE products SET old_price = price, price = ? WHERE id = ?")->execute([$salePrice, $productId]);
        setFlash('success', 'Flash sale applied to product.');
    }
    redirect('flash-sales.php');
}

$products = $db->query("SELECT id, name, price, old_price, main_image, sku FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flash Sales - DesiVastra Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/layout.php'; ?>
    <div class="page-content">
        <div class="page-header">
            <div>
                <div class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><span class="separator"><i class="fas fa-chevron-right"></i></span><span>Flash Sales</span></div>
                <h1><i class="fas fa-bolt" style="color:var(--gold-primary);margin-right:8px;"></i>Flash Sales</h1>
                <p class="subtitle">Create time-limited deals and discount events.</p>
            </div>
            <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
                <i class="fas fa-plus"></i> Create Flash Sale
            </button>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <?php echo clean($flash['message']); ?>
                <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h3><i class="fas fa-bolt" style="color:var(--gold-primary);margin-right:8px;"></i>Products with Active Discounts</h3></div>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Product</th><th>SKU</th><th>Current Price</th><th>Old Price (MRP)</th><th>Discount</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($products as $p): $disc = $p['old_price'] > $p['price'] ? round((1 - $p['price']/$p['old_price'])*100) : 0; ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <?php if ($p['main_image']): ?><img src="../<?php echo clean($p['main_image']); ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;"><?php endif; ?>
                                    <strong><?php echo clean($p['name']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo clean($p['sku']); ?></td>
                            <td>₹<?php echo number_format($p['price'], 2); ?></td>
                            <td><?php echo $p['old_price'] ? '₹' . number_format($p['old_price'], 2) : '—'; ?></td>
                            <td><?php echo $disc ? "<span class='badge badge-success'>{$disc}% OFF</span>" : '—'; ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="openSale(<?php echo $p['id']; ?>, '<?php echo clean($p['name']); ?>', <?php echo $p['price']; ?>)">
                                    <i class="fas fa-bolt"></i> Apply Sale
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width:460px;">
        <div class="modal-header">
            <h3><i class="fas fa-bolt" style="color:var(--gold-primary);margin-right:8px;"></i>Apply Flash Sale</h3>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-body" style="display:flex;flex-direction:column;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Product</label>
                    <select name="product_id" id="saleProductId" class="form-control" required>
                        <option value="">Select product...</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo clean($p['name']); ?> — ₹<?php echo number_format($p['price'],2); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Flash Sale Price (₹)</label>
                    <input type="number" name="sale_price" class="form-control" placeholder="0.00" step="0.01" min="0" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Apply Sale</button>
            </div>
        </form>
    </div>
</div>
<script>
function openSale(id, name, price) {
    document.getElementById('saleProductId').value = id;
    document.getElementById('addModal').classList.add('active');
}
</script>
</body></html>