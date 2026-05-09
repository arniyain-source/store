<?php
/**
 * Shared admin layout components
 */
require_once __DIR__ . '/../../includes/functions.php';
// Auth handled at page level before HTML output

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$admin = getCurrentAdmin();
$csrf = generateCSRF();

// Get pending orders count for badge
$pendingOrdersCount = 0;
$pendingTicketsCount = 0;
$lowStockCount = 0;
try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'pending'");
    $pendingOrdersCount = (int)$stmt->fetch()['cnt'];
} catch (Exception $e) {}
try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM products WHERE stock <= low_stock_threshold AND is_active = 1");
    $lowStockCount = (int)$stmt->fetch()['cnt'];
} catch (Exception $e) {}

// Helper: active class
function navActive($pages) {
    global $currentPage;
    $pages = is_array($pages) ? $pages : [$pages];
    return in_array($currentPage, $pages) ? 'active' : '';
}
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">DV</div>
        <div class="brand-text">
            <h2>DesiVastra</h2>
            <span>Admin Panel</span>
        </div>
    </div>

    <nav class="sidebar-nav">

        <!-- MAIN -->
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <a href="index.php" class="nav-item <?php echo navActive('index'); ?>">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
        </div>

        <!-- CATALOG -->
        <div class="nav-section">
            <div class="nav-section-title">Catalog</div>
            <a href="products.php" class="nav-item <?php echo navActive(['products','product-form']); ?>">
                <i class="fas fa-box-open"></i> Products
            </a>
            <a href="categories.php" class="nav-item <?php echo navActive('categories'); ?>">
                <i class="fas fa-tags"></i> Categories
            </a>
            <a href="inventory-mgmt.php" class="nav-item <?php echo navActive('inventory-mgmt'); ?>">
                <i class="fas fa-warehouse"></i> Inventory
                <?php if ($lowStockCount > 0): ?>
                    <span class="badge badge-warning"><?php echo $lowStockCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="ai-product-create.php" class="nav-item <?php echo navActive('ai-product-create'); ?>">
                <i class="fas fa-robot"></i> AI Product Create
            </a>
            <a href="catalog-mgmt.php" class="nav-item <?php echo navActive('catalog-mgmt'); ?>">
                <i class="fas fa-layer-group"></i> Catalog Manager
            </a>
        </div>

        <!-- SALES -->
        <div class="nav-section">
            <div class="nav-section-title">Sales</div>
            <a href="orders.php" class="nav-item <?php echo navActive(['orders','order-detail','order-create']); ?>">
                <i class="fas fa-shopping-bag"></i> Orders
                <?php if ($pendingOrdersCount > 0): ?>
                    <span class="badge"><?php echo $pendingOrdersCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="return-mgmt.php" class="nav-item <?php echo navActive(['return-mgmt','return-detail']); ?>">
                <i class="fas fa-undo-alt"></i> Returns
            </a>
            <a href="coupons.php" class="nav-item <?php echo navActive('coupons'); ?>">
                <i class="fas fa-percent"></i> Coupons
            </a>
            <a href="marketing-hub.php" class="nav-item <?php echo navActive('marketing-hub'); ?>">
                <i class="fas fa-bullhorn"></i> Marketing Hub
            </a>
            <a href="flash-sales.php" class="nav-item <?php echo navActive('flash-sales'); ?>">
                <i class="fas fa-bolt"></i> Flash Sales
            </a>
        </div>

        <!-- PEOPLE -->
        <div class="nav-section">
            <div class="nav-section-title">People</div>
            <a href="customers.php" class="nav-item <?php echo navActive('customers'); ?>">
                <i class="fas fa-users"></i> Customers
            </a>
            <a href="customer-roles.php" class="nav-item <?php echo navActive('customer-roles'); ?>">
                <i class="fas fa-user-tag"></i> Customer Roles
            </a>
            <a href="reviews.php" class="nav-item <?php echo navActive(['reviews','review-mgmt']); ?>">
                <i class="fas fa-star"></i> Reviews
            </a>
            <a href="support-tickets.php" class="nav-item <?php echo navActive(['support-tickets','ticket-detail']); ?>">
                <i class="fas fa-headset"></i> Support Tickets
            </a>
            <a href="staff-mgmt.php" class="nav-item <?php echo navActive('staff-mgmt'); ?>">
                <i class="fas fa-user-shield"></i> Staff
            </a>
            <a href="supplier-mgmt.php" class="nav-item <?php echo navActive('supplier-mgmt'); ?>">
                <i class="fas fa-handshake"></i> Suppliers
            </a>
        </div>

        <!-- ANALYTICS -->
        <div class="nav-section">
            <div class="nav-section-title">Analytics</div>
            <a href="reports.php" class="nav-item <?php echo navActive('reports'); ?>">
                <i class="fas fa-chart-line"></i> Reports
            </a>
            <a href="activity.php" class="nav-item <?php echo navActive('activity'); ?>">
                <i class="fas fa-history"></i> Activity Log
            </a>
            <a href="server-health.php" class="nav-item <?php echo navActive('server-health'); ?>">
                <i class="fas fa-server"></i> Server Health
            </a>
        </div>

        <!-- CONTENT -->
        <div class="nav-section">
            <div class="nav-section-title">Content</div>
            <a href="cms-pages.php" class="nav-item <?php echo navActive('cms-pages'); ?>">
                <i class="fas fa-file-alt"></i> CMS Pages
            </a>
            <a href="seo-settings.php" class="nav-item <?php echo navActive('seo-settings'); ?>">
                <i class="fas fa-search"></i> SEO Settings
            </a>
        </div>

        <!-- SYSTEM -->
        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <a href="settings.php" class="nav-item <?php echo navActive('settings'); ?>">
                <i class="fas fa-cog"></i> General Settings
            </a>
            <a href="shipping-settings.php" class="nav-item <?php echo navActive('shipping-settings'); ?>">
                <i class="fas fa-truck"></i> Shipping
            </a>
            <a href="payment-settings.php" class="nav-item <?php echo navActive('payment-settings'); ?>">
                <i class="fas fa-credit-card"></i> Payment
            </a>
            <a href="notification-settings.php" class="nav-item <?php echo navActive('notification-settings'); ?>">
                <i class="fas fa-bell"></i> Notifications
            </a>
            <a href="api-integrations.php" class="nav-item <?php echo navActive('api-integrations'); ?>">
                <i class="fas fa-plug"></i> API Integrations
            </a>
            <a href="language-settings.php" class="nav-item <?php echo navActive('language-settings'); ?>">
                <i class="fas fa-language"></i> Language
            </a>
            <a href="backup-restore.php" class="nav-item <?php echo navActive('backup-restore'); ?>">
                <i class="fas fa-database"></i> Backup & Restore
            </a>
            <a href="module-manager.php" class="nav-item <?php echo navActive('module-manager'); ?>">
                <i class="fas fa-puzzle-piece"></i> Modules
            </a>
        </div>

    </nav>

    <div class="sidebar-footer">
        <a href="<?php echo SITE_URL; ?>" target="_blank" class="nav-item">
            <i class="fas fa-external-link-alt"></i> View Website
        </a>
    </div>
</aside>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Main Content -->
<main class="main-content">

<!-- Top Header -->
<header class="top-header">
    <div class="header-left">
        <button class="menu-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search products, orders, customers..." id="globalSearch" onkeyup="handleGlobalSearch(event)">
        </div>
    </div>

    <div class="header-right">
        <a href="orders.php?status=pending" class="header-btn" data-tooltip="Pending Orders">
            <i class="fas fa-bell"></i>
            <?php if ($pendingOrdersCount > 0): ?>
                <span class="notif-dot"></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo SITE_URL; ?>" target="_blank" class="header-btn desktop-only" data-tooltip="View Website">
            <i class="fas fa-external-link-alt"></i>
        </a>

        <div class="user-menu" onclick="toggleUserDropdown()">
            <div class="user-avatar"><?php echo strtoupper(substr($admin['name'] ?? 'A', 0, 1)); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo clean($admin['name'] ?? 'Admin'); ?></div>
                <div class="user-role"><?php echo clean($admin['role'] ?? 'admin'); ?></div>
            </div>
            <i class="fas fa-chevron-down" style="font-size:10px;color:var(--text-muted);margin-left:4px"></i>

            <div class="dropdown-menu" id="userDropdown">
                <a href="profile.php" class="dropdown-item"><i class="fas fa-user"></i> My Profile</a>
                <a href="settings.php" class="dropdown-item"><i class="fas fa-cog"></i> Settings</a>
                <a href="qa-checklist.php" class="dropdown-item"><i class="fas fa-tasks"></i> QA Checklist</a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</header>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}

function toggleUserDropdown() {
    document.getElementById('userDropdown').classList.toggle('show');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-menu')) {
        document.getElementById('userDropdown').classList.remove('show');
    }
});

function handleGlobalSearch(e) {
    if (e.key === 'Enter') {
        const q = e.target.value.trim();
        if (q) window.location.href = 'products.php?search=' + encodeURIComponent(q);
    }
}

window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('show');
    }
});
</script>
