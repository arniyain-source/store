<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../admin/assets/css/admin.css">
</head>
<body>
    <div class="wrapper">
        <aside class="main-sidebar sidebar-dark-primary elevation-4">
            <a href="index.php" class="brand-link">
                <span class="brand-text font-weight-light">Admin Panel</span>
            </a>
            <div class="sidebar">
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                        <li class="nav-item">
                            <a href="/admin/index.php" class="nav-link">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-shopping-cart"></i>
                                <p>Orders<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="/admin/orders.php" class="nav-link"><p>All Orders</p></a></li>
                                <li class="nav-item"><a href="/admin/order-create.php" class="nav-link"><p>Create Order</p></a></li>
                                <li class="nav-item"><a href="/admin/return-mgmt.php" class="nav-link"><p>Returns</p></a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-box"></i>
                                <p>Catalog<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="/admin/products.php" class="nav-link"><p>Products</p></a></li>
                                <li class="nav-item"><a href="/admin/categories.php" class="nav-link"><p>Categories</p></a></li>
                                <li class="nav-item"><a href="/admin/collection-mgmt.php" class="nav-link"><p>Collections</p></a></li>
                                <li class="nav-item"><a href="/admin/review-mgmt.php" class="nav-link"><p>Reviews</p></a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-users"></i>
                                <p>Customers<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="/admin/customers.php" class="nav-link"><p>All Customers</p></a></li>
                                <li class="nav-item"><a href="/admin/customer-roles.php" class="nav-link"><p>Customer Roles</p></a></li>
                            </ul>
                        </li>
                         <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-bullhorn"></i>
                                <p>Marketing<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="/admin/coupons.php" class="nav-link"><p>Coupons</p></a></li>
                                <li class="nav-item"><a href="/admin/flash-sales.php" class="nav-link"><p>Flash Sales</p></a></li>
                                <li class="nav-item"><a href="/admin/marketing-hub.php" class="nav-link"><p>Marketing Hub</p></a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-file-alt"></i>
                                <p>Content<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="/admin/blog-mgmt.php" class="nav-link"><p>Blog</p></a></li>
                                <li class="nav-item"><a href="/admin/cms-pages.php" class="nav-link"><p>CMS Pages</p></a></li>
                                <li class="nav-item"><a href="/admin/faq-manager.php" class="nav-link"><p>FAQ Manager</p></a></li>
                                <li class="nav-item"><a href="/admin/trust-badges.php" class="nav-link"><p>Trust Badges</p></a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-cogs"></i>
                                <p>Settings<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="/admin/settings.php" class="nav-link"><p>General</p></a></li>
                                <li class="nav-item"><a href="/admin/payment-settings.php" class="nav-link"><p>Payments</p></a></li>
                                <li class="nav-item"><a href="/admin/shipping-settings.php" class="nav-link"><p>Shipping</p></a></li>
                                <li class="nav-item"><a href="/admin/regional-shipping.php" class="nav-link"><p>Regional Shipping</p></a></li>
                                <li class="nav-item"><a href="/admin/currency-settings.php" class="nav-link"><p>Currency</p></a></li>
                                <li class="nav-item"><a href="/admin/language-settings.php" class="nav-link"><p>Language</p></a></li>
                                <li class="nav-item"><a href="/admin/seo-settings.php" class="nav-link"><p>SEO</p></a></li>
                                <li class="nav-item"><a href="/admin/theme-customizer.php" class="nav-link"><p>Theme</p></a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-user-shield"></i>
                                <p>Staff<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="/admin/staff-mgmt.php" class="nav-link"><p>Manage Staff</p></a></li>
                                <li class="nav-item"><a href="/admin/role-permissions.php" class="nav-link"><p>Role Permissions</p></a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a href="/admin/api-integrations.php" class="nav-link">
                                <i class="nav-icon fas fa-project-diagram"></i>
                                <p>API Integrations</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-headset"></i>
                                <p>Support<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="/admin/support-tickets.php" class="nav-link"><p>Tickets</p></a></li>
                                <li class="nav-item"><a href="/admin/support-knowledge-base.php" class="nav-link"><p>Knowledge Base</p></a></li>
                            </ul>
                        </li>
                         <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-server"></i>
                                <p>System<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="/admin/server-health.php" class="nav-link"><p>Server Health</p></a></li>
                                <li class="nav-item"><a href="/admin/backup-restore.php" class="nav-link"><p>Backup/Restore</p></a></li>
                                <li class="nav-item"><a href="/admin/module-manager.php" class="nav-link"><p>Module Manager</p></a></li>
                                <li class="nav-item"><a href="/admin/activity.php" class="nav-link"><p>Activity Log</p></a></li>
                            </ul>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="content-wrapper">
            <?php echo $content; ?>
        </div>

    </div>
    <script src="../admin/assets/js/admin.js"></script>
</body>
</html>