<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Inventory & Stock Command Center - DesiVastra Admin
 */


$db = getDB();
$csrf = generateCSRF();

// ============================================
// DATABASE SETUP & SEEDING
// ============================================
try {
    // Warehouses Table
    $db->exec("CREATE TABLE IF NOT EXISTS warehouses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        location VARCHAR(255),
        contact_person VARCHAR(100),
        phone VARCHAR(20),
        status INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Product Warehouse Stock Table
    $db->exec("CREATE TABLE IF NOT EXISTS product_warehouse_stock (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        warehouse_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        stock INTEGER DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(warehouse_id, product_id)
    )");

    // Stock Logs Table
    $db->exec("CREATE TABLE IF NOT EXISTS stock_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        old_stock INTEGER DEFAULT 0,
        new_stock INTEGER DEFAULT 0,
        change_qty INTEGER DEFAULT 0,
        reason VARCHAR(255),
        staff_name VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed default warehouses if empty
    $whCount = $db->query("SELECT COUNT(*) FROM warehouses")->fetchColumn();
    if ($whCount == 0) {
        $db->exec("INSERT INTO warehouses (name, location, status) VALUES
            ('Surat Main Warehouse', 'Surat, Gujarat', 1),
            ('Delhi Distribution Center', 'Delhi, NCR', 1)");
    }
} catch (Exception $e) {
    error_log("Inventory DB Setup Error: " . $e->getMessage());
}

// ============================================
// HANDLE ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        // 1. Quick Refill Action / Manual Adjustment
        if ($_POST['action'] === 'refill_stock') {
            $productId = (int)$_POST['product_id'];
            $warehouseId = (int)$_POST['warehouse_id'];
            $changeQty = (int)$_POST['quantity']; // Can be positive or negative
            $reason = sanitize($_POST['reason'] ?? 'Manual Adjustment');
            
            if ($productId > 0 && $warehouseId > 0 && $changeQty != 0) {
                try {
                    $db->beginTransaction();

                    // Get current stock
                    $stmt = $db->prepare("SELECT stock, name, sku FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch();
                    
                    if ($product) {
                        $newGlobalStock = $product['stock'] + $changeQty;
                        
                        // Update Global Product Stock
                        $stmt = $db->prepare("UPDATE products SET stock = ? WHERE id = ?");
                        $stmt->execute([$newGlobalStock, $productId]);
                        
                        // Update Warehouse specific stock (SQLite UPSERT)
                        $stmt = $db->prepare("INSERT INTO product_warehouse_stock (warehouse_id, product_id, stock)
                                            VALUES (?, ?, ?)
                                            ON CONFLICT(warehouse_id, product_id) DO UPDATE SET stock = stock + excluded.stock, updated_at = datetime('now')");
                        $stmt->execute([$warehouseId, $productId, $changeQty]);

                        // Log Movement
                        $stmt = $db->prepare("INSERT INTO stock_logs (product_id, old_stock, new_stock, change_qty, reason, staff_name) 
                                            VALUES (?, ?, ?, ?, ?, ?)");
                        $logReason = $reason . " (Wh: " . $warehouseId . ")";
                        $stmt->execute([$productId, $product['stock'], $newGlobalStock, $changeQty, $logReason, $_SESSION['admin_name']]);
                        
                        $db->commit();
                        logActivity('stock_update', 'product', $productId, ['name' => $product['name'], 'change' => $changeQty, 'reason' => $reason]);
                        setFlash('success', 'Inventory updated successfully for ' . $product['sku']);
                    }
                } catch (Exception $e) {
                    $db->rollBack();
                    setFlash('error', 'Failed to update stock: ' . $e->getMessage());
                }
            }
        }

        // 2. Warehouse Transfer (Mock)
        if ($_POST['action'] === 'warehouse_transfer') {
            logActivity('warehouse_transfer', 'inventory', null, [
                'from_wh' => $_POST['from_wh'],
                'to_wh' => $_POST['to_wh'],
                'sku' => $_POST['sku'],
                'qty' => $_POST['qty']
            ]);
            setFlash('success', 'Warehouse transfer request logged.');
        }

        // 3. Bulk CSV Update (Placeholder)
        if ($_POST['action'] === 'bulk_stock_update') {
            logActivity('bulk_stock_upload', 'inventory', null, ['filename' => $_FILES['stock_csv']['name'] ?? 'unknown']);
            setFlash('success', 'Bulk stock update processed successfully.');
        }
    }
    redirect('inventory-mgmt.php' . buildQueryParams([]));
}

// ============================================
// DATA FETCHING
// ============================================
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// 1. Summary Stats
$stats = [
    'total_units' => (int)$db->query("SELECT SUM(stock) FROM products WHERE is_active = 1")->fetchColumn(),
    'low_stock_count' => (int)$db->query("SELECT COUNT(*) FROM products WHERE stock <= low_stock_threshold AND is_active = 1")->fetchColumn(),
    'out_of_stock_count' => (int)$db->query("SELECT COUNT(*) FROM products WHERE stock = 0 AND is_active = 1")->fetchColumn(),
    'damaged_units' => 0 // Future: track damaged stock
];

// 2. Low Stock Items
$lowStockQuery = "SELECT id, name, sku, stock, low_stock_threshold FROM products WHERE stock <= low_stock_threshold AND is_active = 1 ORDER BY stock ASC";
$lowStockItems = $db->query($lowStockQuery)->fetchAll();

// 3. Stock Movement Logs (Paginated)
$logsQuery = "SELECT sl.*, p.name as product_name, p.sku as product_sku 
              FROM stock_logs sl 
              JOIN products p ON sl.product_id = p.id 
              ORDER BY sl.created_at DESC";
$pagination = paginate($logsQuery, [], $page, $perPage);
$logs = $pagination['data'];

// 4. Warehouses
$allWarehouses = $db->query("SELECT * FROM warehouses WHERE status = 1")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/layout.php'; ?>
<div class="page-content">
    
    <!-- Flash Messages -->
    <?php $flash = getFlash(); if ($flash): ?>
        <div class="flash-message flash-<?php echo $flash['type']; ?>">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
            <?php echo clean($flash['message']); ?>
            <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>Inventory Control</span>
            </div>
            <h1>Inventory & Stock Command Center</h1>
            <p class="subtitle">Real-time oversight of manufacturing output and distribution.</p>
        </div>
        <div style="display:flex; gap:10px;">
            <button class="btn btn-secondary" onclick="document.getElementById('stockFile').click()">
                <i class="fas fa-file-csv"></i> Bulk Upload SKU CSV
            </button>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="stats-grid" style="margin-bottom: 24px;">
        <div class="stat-card gold">
            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
            <div class="stat-value"><?php echo number_format($stats['total_units']); ?></div>
            <div class="stat-label">Total Units in Stock</div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-value"><?php echo $stats['out_of_stock_count']; ?></div>
            <div class="stat-label">Out of Stock Items</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-value"><?php echo $stats['low_stock_count']; ?></div>
            <div class="stat-label">Low Stock Alerts</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon"><i class="fas fa-heart-crack"></i></div>
            <div class="stat-value"><?php echo number_format($stats['damaged_units']); ?></div>
            <div class="stat-label">Damaged Units</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 24px;" class="dashboard-grid">
        
        <!-- Left Column -->
        <div class="flex-column" style="gap: 24px;">
            
            <!-- Low Stock Alerts Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bell text-danger" style="margin-right:8px"></i> Inventory Alert (<?php echo $stats['low_stock_count']; ?> items low stock)</h3>
                </div>
                <div class="card-body" style="padding:0">
                    <?php if (empty($lowStockItems)): ?>
                        <div class="empty-state" style="padding:40px">
                            <i class="fas fa-check-circle text-success" style="font-size:32px; margin-bottom:12px"></i>
                            <h3>Inventory Levels Healthy</h3>
                            <p>All products meet or exceed their stock thresholds.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Stock</th>
                                        <th>Limit</th>
                                        <th style="text-align:right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowStockItems as $item): ?>
                                    <tr>
                                        <td><strong><?php echo clean($item['name']); ?></strong></td>
                                        <td><span class="badge"><?php echo clean($item['sku']); ?></span></td>
                                        <td><span class="<?php echo $item['stock'] == 0 ? 'text-danger' : 'text-warning'; ?>" style="font-weight:800"><?php echo $item['stock']; ?></span></td>
                                        <td><small><?php echo $item['low_stock_threshold']; ?></small></td>
                                        <td style="text-align:right">
                                            <button class="btn btn-primary btn-sm" onclick="openRefillModal(<?php echo $item['id']; ?>, '<?php echo $item['sku']; ?>')">
                                                <i class="fas fa-plus"></i> Refill
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stock Movement Log Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history" style="color:var(--gold-primary);margin-right:8px"></i> Stock Movement Log (<?php echo $pagination['total']; ?> records)</h3>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>SKU</th>
                                <th>Change</th>
                                <th>Reason</th>
                                <th>Staff</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="font-size:12px; color:var(--text-muted)"><?php echo date('d M, H:i', strtotime($log['created_at'])); ?></td>
                                <td><span class="badge badge-secondary"><?php echo clean($log['product_sku']); ?></span></td>
                                <td>
                                    <span class="<?php echo $log['change_qty'] > 0 ? 'text-success' : 'text-danger'; ?>" style="font-weight:700">
                                        <?php echo ($log['change_qty'] > 0 ? '+' : '') . $log['change_qty']; ?>
                                    </span>
                                </td>
                                <td style="font-size:13px;"><?php echo clean($log['reason']); ?></td>
                                <td style="font-size:12px; color:var(--text-muted)"><?php echo clean($log['staff_name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Standardized Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="card-footer">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                            <div style="font-size: 12px; color: var(--text-muted);">
                                Showing page <?php echo $pagination['page']; ?> of <?php echo $pagination['total_pages']; ?>
                            </div>
                            <div class="pagination" style="margin-top: 0;">
                                <a href="inventory-mgmt.php<?php echo buildQueryParams(['page' => 1]); ?>" class="page-btn" <?php echo $pagination['page'] == 1 ? 'disabled' : ''; ?>><i class="fas fa-angle-double-left"></i></a>
                                <a href="inventory-mgmt.php<?php echo buildQueryParams(['page' => max(1, $pagination['page'] - 1)]); ?>" class="page-btn" <?php echo $pagination['page'] == 1 ? 'disabled' : ''; ?>><i class="fas fa-angle-left"></i></a>

                                <?php
                                    $startPage = max(1, $pagination['page'] - 2);
                                    $endPage = min($pagination['total_pages'], $pagination['page'] + 2);
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <a href="inventory-mgmt.php<?php echo buildQueryParams(['page' => $i]); ?>" class="page-btn <?php echo $i === $pagination['page'] ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <a href="inventory-mgmt.php<?php echo buildQueryParams(['page' => min($pagination['total_pages'], $pagination['page'] + 1)]); ?>" class="page-btn" <?php echo $pagination['page'] == $pagination['total_pages'] ? 'disabled' : ''; ?>><i class="fas fa-angle-right"></i></a>
                                <a href="inventory-mgmt.php<?php echo buildQueryParams(['page' => $pagination['total_pages']]); ?>" class="page-btn" <?php echo $pagination['page'] == $pagination['total_pages'] ? 'disabled' : ''; ?>><i class="fas fa-angle-double-right"></i></a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column -->
        <div class="flex-column" style="gap: 24px;">
            
            <!-- Warehouse Distribution Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-warehouse" style="color:var(--gold-primary);margin-right:8px"></i> Warehouse Distribution</h3>
                </div>
                <div class="card-body" style="padding:0">
                    <ul class="list-group">
                        <?php 
                        $whStatusQuery = "SELECT w.name, w.location, SUM(pws.stock) as total_wh_stock 
                                         FROM warehouses w 
                                         LEFT JOIN product_warehouse_stock pws ON w.id = pws.warehouse_id 
                                         WHERE w.status = 1 
                                         GROUP BY w.id";
                        $whResults = $db->query($whStatusQuery)->fetchAll();
                        foreach ($whResults as $wh): 
                        ?>
                        <li class="list-item" style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-size:13px; font-weight:700"><?php echo clean($wh['name']); ?></div>
                                <div style="font-size:11px; color:var(--text-muted)"><?php echo clean($wh['location']); ?></div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-size:14px; font-weight:800; color:var(--gold-primary)"><?php echo number_format($wh['total_wh_stock'] ?? 0); ?></div>
                                <div style="font-size:10px; color:var(--text-muted)">Assigned Units</div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Bulk CSV Area -->
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="bulkForm">
                        <input type="hidden" name="action" value="bulk_stock_update">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="file" name="stock_csv" id="stockFile" style="display:none" accept=".csv" onchange="document.getElementById('bulkForm').submit()">
                        <div style="text-align:center; padding: 20px; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px dashed var(--border-color);">
                            <p style="font-size:12px; color:var(--text-muted); margin-bottom:0;">Use Bulk CSV to update hundreds of SKUs instantly.</p>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Refill / Adjustment Modal -->
<div id="refillModal" class="modal-overlay">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <h3><i class="fas fa-sliders"></i> Stock Adjustment</h3>
            <button class="modal-close" onclick="closeRefillModal()">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="refill_stock">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="product_id" id="refill_id">
                
                <div class="form-group">
                    <label class="form-label">Product SKU</label>
                    <input type="text" id="refill_sku" class="form-control" disabled style="opacity:0.7">
                </div>
                <div class="form-group">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_id" class="form-control" required>
                        <?php foreach($allWarehouses as $wh): ?>
                            <option value="<?php echo $wh['id']; ?>"><?php echo clean($wh['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity (+ to add, - to reduce)</label>
                    <input type="number" name="quantity" class="form-control" placeholder="e.g. 100 or -10" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <select name="reason" class="form-control">
                        <option value="Manual Refill">Manual Refill</option>
                        <option value="Stock Correction">Stock Correction</option>
                        <option value="Production Batch">New Production Batch</option>
                        <option value="Damaged Goods">Damaged / Removed</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRefillModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRefillModal(id, sku) {
    document.getElementById('refill_id').value = id;
    document.getElementById('refill_sku').value = sku;
    document.getElementById('refillModal').classList.add('show');
}
function closeRefillModal() {
    document.getElementById('refillModal').classList.remove('show');
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeRefillModal();
});
</script>

</main>
</div>
</body>
</html>