<?php
// Get the site settings
$settings = getSiteSettings();

// Determine the page title
$title = !empty($pageTitle) 
    ? $pageTitle . ' | ' . ($settings['site_name'] ?? 'DesiVastra') 
    : ($settings['site_name'] ?? 'DesiVastra');

// Determine the meta description
$description = !empty($pageDescription)
    ? $pageDescription
    : ($settings['site_tagline'] ?? 'Exquisite Indian Ethnic Wear for every occasion.');

// Get current user info if logged in
$currentUser = null;
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $currentUser = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo clean($description); ?>">
    <title><?php echo clean($title); ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Main CSS -->
    <link rel="stylesheet" href="/assets/css/global.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?php echo time(); ?>">

    <!-- Favicon -->
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    <?php 
    // Allow including extra head content on specific pages
    if (isset($extraHeadContent)) {
        echo $extraHeadContent;
    }
    ?>
</head>
<body>
    <div class="app-container">
        <header class="global-header">
            <div class="header-inner">
                <div class="header-left">
                    <a href="index.php" class="logo">Desi<span>Vastra</span></a>
                </div>
                <div class="header-right">
                    <nav class="desktop-nav">
                        <a href="shop.php">Shop</a>
                        <a href="#categories">Categories</a>
                        <a href="#new-arrivals">New Arrivals</a>
                        <a href="#about">About Us</a>
                    </nav>
                    <div class="header-actions">
                        <button class="icon-btn" aria-label="Search"><i class="fas fa-search"></i></button>
                        <button class="icon-btn" aria-label="Account" onclick="handleAccountClick()"><i class="fas fa-user"></i></button>
                        <button class="icon-btn cart-btn" aria-label="Cart" onclick="openRightDrawer('cart')">
                            <i class="fas fa-shopping-bag"></i>
                            <span class="cart-badge" id="cart-badge">0</span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Right Drawer for Cart/Wishlist/Auth -->
        <div class="overlay" id="right-overlay" onclick="closeRightDrawer()"></div>
        <div id="right-drawer" class="drawer-right">
            <div class="drawer-header-right">
                <h3 id="drawer-title" class="drawer-title-text">Drawer</h3>
                <button class="close-btn-styled" onclick="closeRightDrawer()" title="Close"><i class="fas fa-times"></i></button>
            </div>
            <div id="drawer-content" class="drawer-body">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
