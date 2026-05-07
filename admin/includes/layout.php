<?php
/**
 * Shared admin layout components
 */
require_once __DIR__ . '/../../includes/functions.php';
// Auth handled at page level before HTML output
// requireAdminLogin(); // disabled here to prevent headers-already-sent

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$admin = getCurrentAdmin();
$csrf = generateCSRF();

// Get pending orders count for badge
$pendingOrdersCount = 0;
try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'pending'");
    $pendingOrdersCount = (int)$stmt->fetch()['cnt'];
} catch (Exception $e) {}
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
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <a href="index.php" class="nav-item <?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Catalog</div>
            <a href="products.php" class="nav-item <?php echo in_array($currentPage, ['products','product-form']) ? 'active' : ''; ?>">
                <i class="fas fa-box-open"></i> Products
            </a>
            <a href="categories.php" class="nav-item <?php echo $currentPage === 'categories' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i> Categories
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Sales</div>
            <a href="orders.php" class="nav-item <?php echo in_array($currentPage, ['orders','order-detail']) ? 'active' : ''; ?>">
                <i class="fas fa-shopping-bag"></i> Orders
                <?php if ($pendingOrdersCount > 0): ?>
                    <span class="badge"><?php echo $pendingOrdersCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="coupons.php" class="nav-item <?php echo $currentPage === 'coupons' ? 'active' : ''; ?>">
                <i class="fas fa-percent"></i> Coupons
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">People</div>
            <a href="customers.php" class="nav-item <?php echo $currentPage === 'customers' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Customers
            </a>
            <a href="reviews.php" class="nav-item <?php echo $currentPage === 'reviews' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> Reviews
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Analytics</div>
            <a href="reports.php" class="nav-item <?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Reports
            </a>
            <a href="activity.php" class="nav-item <?php echo $currentPage === 'activity' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Activity Log
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <a href="settings.php" class="nav-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Settings
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
