<?php
/**
 * Local SQLite Database Setup for DesiVastra
 * Run once: php setup_local.php
 */

$dbPath = __DIR__ . '/store_local.sqlite';
echo "Setting up SQLite database at: $dbPath\n";

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA journal_mode = WAL');

    // ADMINS (password = Admin@123)
    $db->exec('CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT "admin",
        avatar VARCHAR(255) DEFAULT NULL,
        last_login DATETIME DEFAULT NULL,
        status INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('INSERT OR IGNORE INTO admins (name, email, password, role) VALUES
        ("Super Admin", "admin@desivastra.in", "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi", "super_admin")');
    echo "✓ Admins table\n";

    // CATEGORIES
    $db->exec('CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        description TEXT DEFAULT NULL,
        image VARCHAR(255) DEFAULT NULL,
        icon VARCHAR(50) DEFAULT NULL,
        parent_id INTEGER DEFAULT NULL,
        sort_order INTEGER DEFAULT 0,
        status INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('INSERT OR IGNORE INTO categories (name, slug, icon, sort_order) VALUES
        ("Sarees", "sarees", "fa-female", 1),
        ("Suits", "suits", "fa-vest", 2),
        ("Kurtis", "kurtis", "fa-tshirt", 3),
        ("Lehengas", "lehengas", "fa-magic", 4),
        ("Dupattas", "dupattas", "fa-scarf", 5)');
    echo "✓ Categories table\n";

    // PRODUCTS
    $db->exec('CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(280) NOT NULL UNIQUE,
        sku VARCHAR(50) DEFAULT NULL,
        category_id INTEGER DEFAULT NULL,
        catalog_id INTEGER DEFAULT NULL,
        short_description VARCHAR(500) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        fabric VARCHAR(255) DEFAULT NULL,
        work VARCHAR(255) DEFAULT NULL,
        blouse_details TEXT DEFAULT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        old_price DECIMAL(10,2) DEFAULT NULL,
        reseller_price DECIMAL(10,2) DEFAULT 0.00,
        wholesale_price DECIMAL(10,2) DEFAULT 0.00,
        purchase_cost DECIMAL(10,2) DEFAULT NULL,
        stock INTEGER DEFAULT 0,
        low_stock_threshold INTEGER DEFAULT 5,
        main_image VARCHAR(255) DEFAULT NULL,
        video_url VARCHAR(500) DEFAULT NULL,
        images TEXT DEFAULT NULL,
        sizes TEXT DEFAULT NULL,
        colors TEXT DEFAULT NULL,
        finishes TEXT DEFAULT NULL,
        features TEXT DEFAULT NULL,
        tags TEXT DEFAULT NULL,
        rating DECIMAL(2,1) DEFAULT 0.0,
        reviews_count INTEGER DEFAULT 0,
        is_featured INTEGER DEFAULT 0,
        is_new_arrival INTEGER DEFAULT 0,
        is_top_selling INTEGER DEFAULT 0,
        is_boutique_only INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        delivery_min_days INTEGER DEFAULT 3,
        delivery_max_days INTEGER DEFAULT 7,
        meta_title VARCHAR(255) DEFAULT NULL,
        meta_description VARCHAR(500) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('INSERT OR IGNORE INTO products (name, slug, sku, category_id, short_description, price, old_price, stock, rating, reviews_count, is_featured, is_new_arrival, is_top_selling, is_active) VALUES
        ("Banarasi Silk Saree - Royal Blue", "banarasi-silk-saree-royal-blue", "SAR-001", 1, "Exquisite Banarasi silk with intricate gold zari weaving", 4500, 6000, 25, 4.8, 142, 1, 0, 1, 1),
        ("Chikankari Cotton Suit", "chikankari-cotton-suit-white", "SUIT-001", 2, "Elegant hand-embroidered chikankari on pure cotton", 2200, 3000, 40, 4.6, 89, 1, 1, 0, 1),
        ("Bandhani Print Kurti - Red", "bandhani-print-kurti-red", "KUR-001", 3, "Vibrant Bandhani tie-dye kurti in pure cotton", 850, 1200, 80, 4.5, 234, 0, 1, 1, 1),
        ("Bridal Lehenga - Maroon Gold", "bridal-lehenga-maroon-gold", "LEH-001", 4, "Stunning bridal lehenga with heavy embroidery and stone work", 18500, 25000, 8, 4.9, 67, 1, 0, 1, 1),
        ("Chanderi Dupatta - Pastel Pink", "chanderi-dupatta-pastel-pink", "DUP-001", 5, "Lightweight chanderi silk dupatta with delicate border", 650, 900, 60, 4.3, 45, 0, 1, 0, 1),
        ("Kanjivaram Silk Saree - Green", "kanjivaram-silk-saree-green", "SAR-002", 1, "Pure Kanjivaram silk with traditional temple border", 8500, 11000, 15, 4.9, 98, 1, 0, 1, 1),
        ("Anarkali Suit - Navy Blue", "anarkali-suit-navy-blue", "SUIT-002", 2, "Floor-length Anarkali with intricate thread work", 3200, 4500, 30, 4.7, 156, 1, 1, 0, 1),
        ("Embroidered Kurti - Mustard", "embroidered-kurti-mustard", "KUR-002", 3, "Beautiful mirror work embroidered kurti in mustard", 1100, 1500, 55, 4.4, 78, 0, 1, 1, 1),
        ("Chanderi Silk Saree - Ivory", "chanderi-silk-saree-ivory", "SAR-003", 1, "Elegant ivory Chanderi silk saree with gold border", 3200, 4500, 20, 4.7, 63, 1, 1, 0, 1),
        ("Georgette Salwar Suit - Peach", "georgette-salwar-suit-peach", "SUIT-003", 2, "Flowy georgette suit with embroidered neckline", 1800, 2500, 35, 4.4, 112, 0, 1, 1, 1)');
    echo "✓ Products table\n";

    // CUSTOMERS
    $db->exec('CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(20) DEFAULT NULL,
        password VARCHAR(255) NOT NULL,
        user_type VARCHAR(20) DEFAULT "customer",
        city VARCHAR(100) DEFAULT NULL,
        state VARCHAR(100) DEFAULT NULL,
        business_name VARCHAR(200) DEFAULT NULL,
        avatar VARCHAR(255) DEFAULT NULL,
        is_verified INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        last_login DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('INSERT OR IGNORE INTO customers (name, email, phone, password, user_type, city, state, is_verified, is_active) VALUES
        ("Priya Sharma", "priya@example.com", "9876543210", "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi", "customer", "Mumbai", "Maharashtra", 1, 1),
        ("Anjali Mehta", "anjali@example.com", "9876543211", "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi", "wholesale", "Delhi", "Delhi", 1, 1),
        ("Sunita Patel", "sunita@example.com", "9876543212", "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi", "retailer", "Ahmedabad", "Gujarat", 1, 1)');
    echo "✓ Customers table\n";

    // ADDRESSES
    $db->exec('CREATE TABLE IF NOT EXISTS addresses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER NOT NULL,
        label VARCHAR(50) DEFAULT "Home",
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address_line1 VARCHAR(255) NOT NULL,
        address_line2 VARCHAR(255) DEFAULT NULL,
        city VARCHAR(100) NOT NULL,
        state VARCHAR(100) NOT NULL,
        pincode VARCHAR(10) NOT NULL,
        is_default INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    echo "✓ Addresses table\n";

    // ORDERS
    $db->exec('CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_number VARCHAR(30) NOT NULL UNIQUE,
        customer_id INTEGER DEFAULT NULL,
        customer_name VARCHAR(100) DEFAULT NULL,
        customer_email VARCHAR(150) DEFAULT NULL,
        customer_phone VARCHAR(20) DEFAULT NULL,
        shipping_address TEXT DEFAULT NULL,
        billing_address TEXT DEFAULT NULL,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        discount DECIMAL(10,2) DEFAULT 0.00,
        coupon_code VARCHAR(50) DEFAULT NULL,
        shipping_cost DECIMAL(10,2) DEFAULT 0.00,
        tax DECIMAL(10,2) DEFAULT 0.00,
        total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        payment_method VARCHAR(50) DEFAULT NULL,
        payment_status VARCHAR(20) DEFAULT "pending",
        transaction_id VARCHAR(100) DEFAULT NULL,
        status VARCHAR(20) DEFAULT "pending",
        notes TEXT DEFAULT NULL,
        admin_notes TEXT DEFAULT NULL,
        shipped_at DATETIME DEFAULT NULL,
        delivered_at DATETIME DEFAULT NULL,
        cancelled_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    // Sample orders
    $db->exec('INSERT OR IGNORE INTO orders (order_number, customer_id, customer_name, customer_email, customer_phone, subtotal, shipping_cost, total, payment_method, payment_status, status, created_at) VALUES
        ("DV-20260507-AAB001", 1, "Priya Sharma", "priya@example.com", "9876543210", 4500, 0, 4500, "cod", "pending", "delivered", datetime("now", "-10 days")),
        ("DV-20260506-AAB002", 2, "Anjali Mehta", "anjali@example.com", "9876543211", 11700, 0, 11700, "online", "paid", "shipped", datetime("now", "-5 days")),
        ("DV-20260505-AAB003", 3, "Sunita Patel", "sunita@example.com", "9876543212", 2200, 99, 2299, "cod", "pending", "processing", datetime("now", "-2 days")),
        ("DV-20260504-AAB004", 1, "Priya Sharma", "priya@example.com", "9876543210", 850, 99, 949, "online", "paid", "confirmed", datetime("now", "-1 days")),
        ("DV-20260503-AAB005", 2, "Anjali Mehta", "anjali@example.com", "9876543211", 18500, 0, 18500, "online", "paid", "pending", datetime("now", "-0 days"))');
    echo "✓ Orders table\n";

    // ORDER ITEMS
    $db->exec('CREATE TABLE IF NOT EXISTS order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        product_id INTEGER DEFAULT NULL,
        product_name VARCHAR(255) NOT NULL,
        product_image VARCHAR(255) DEFAULT NULL,
        sku VARCHAR(50) DEFAULT NULL,
        size VARCHAR(50) DEFAULT NULL,
        color VARCHAR(50) DEFAULT NULL,
        finish VARCHAR(50) DEFAULT NULL,
        price DECIMAL(10,2) NOT NULL,
        quantity INTEGER NOT NULL DEFAULT 1,
        total DECIMAL(10,2) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('INSERT OR IGNORE INTO order_items (order_id, product_id, product_name, sku, price, quantity, total) VALUES
        (1, 1, "Banarasi Silk Saree - Royal Blue", "SAR-001", 4500, 1, 4500),
        (2, 6, "Kanjivaram Silk Saree - Green", "SAR-002", 8500, 1, 8500),
        (2, 2, "Chikankari Cotton Suit", "SUIT-001", 2200, 1, 2200),
        (3, 2, "Chikankari Cotton Suit", "SUIT-001", 2200, 1, 2200),
        (4, 3, "Bandhani Print Kurti - Red", "KUR-001", 850, 1, 850),
        (5, 4, "Bridal Lehenga - Maroon Gold", "LEH-001", 18500, 1, 18500)');
    echo "✓ Order Items table\n";

    // COUPONS
    $db->exec('CREATE TABLE IF NOT EXISTS coupons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(50) NOT NULL UNIQUE,
        type VARCHAR(20) NOT NULL DEFAULT "percentage",
        value DECIMAL(10,2) NOT NULL,
        min_order_amount DECIMAL(10,2) DEFAULT 0.00,
        max_discount DECIMAL(10,2) DEFAULT NULL,
        usage_limit INTEGER DEFAULT NULL,
        usage_count INTEGER DEFAULT 0,
        per_customer_limit INTEGER DEFAULT 1,
        valid_from DATETIME DEFAULT NULL,
        valid_to DATETIME DEFAULT NULL,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('INSERT OR IGNORE INTO coupons (code, type, value, min_order_amount, usage_count, is_active) VALUES
        ("WELCOME10", "percentage", 10, 500, 23, 1),
        ("FLAT200", "fixed", 200, 999, 15, 1),
        ("FESTIVE20", "percentage", 20, 2000, 8, 1)');
    echo "✓ Coupons table\n";

    // REVIEWS
    $db->exec('CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        customer_id INTEGER DEFAULT NULL,
        customer_name VARCHAR(100) NOT NULL,
        rating INTEGER NOT NULL,
        title VARCHAR(200) DEFAULT NULL,
        review TEXT DEFAULT NULL,
        images TEXT DEFAULT NULL,
        is_verified INTEGER DEFAULT 0,
        is_approved INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('INSERT OR IGNORE INTO reviews (product_id, customer_id, customer_name, rating, title, review, is_verified, is_approved) VALUES
        (1, 1, "Priya Sharma", 5, "Absolutely stunning!", "The quality of the Banarasi silk is superb. Received many compliments.", 1, 1),
        (1, 2, "Anjali Mehta", 4, "Beautiful weave", "Lovely saree, colors are vibrant. Shipping was fast.", 1, 1),
        (2, 3, "Sunita Patel", 5, "Perfect for summer", "The chikankari work is so delicate and beautiful.", 1, 1),
        (4, 2, "Anjali Mehta", 5, "Dream bridal wear!", "Wore this for my sister s wedding. Everyone loved it!", 1, 1)');
    echo "✓ Reviews table\n";

    // WISHLIST
    $db->exec('CREATE TABLE IF NOT EXISTS wishlist (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(customer_id, product_id)
    )');
    echo "✓ Wishlist table\n";

    // SETTINGS
    $db->exec('CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT DEFAULT NULL,
        setting_type VARCHAR(20) DEFAULT "text",
        setting_group VARCHAR(50) DEFAULT "general",
        description VARCHAR(255) DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('INSERT OR IGNORE INTO settings (setting_key, setting_value, setting_type, setting_group) VALUES
        ("site_name", "DesiVastra", "text", "general"),
        ("site_tagline", "ARNiya Smart Hub", "text", "general"),
        ("site_email", "admin@desivastra.in", "text", "general"),
        ("site_phone", "+91 98765 43210", "text", "general"),
        ("site_whatsapp", "919876543210", "text", "general"),
        ("currency_symbol", "₹", "text", "general"),
        ("free_shipping_min", "999", "number", "shipping"),
        ("shipping_cost", "99", "number", "shipping"),
        ("tax_rate", "0", "number", "tax"),
        ("cod_enabled", "1", "boolean", "payment"),
        ("online_payment_enabled", "0", "boolean", "payment"),
        ("maintenance_mode", "0", "boolean", "general"),
        ("razorpay_key", "", "text", "payment"),
        ("razorpay_secret", "", "text", "payment"),
        ("smtp_host", "", "text", "email"),
        ("smtp_port", "587", "number", "email"),
        ("smtp_user", "", "text", "email"),
        ("smtp_pass", "", "text", "email"),
        ("social_instagram", "", "text", "social"),
        ("social_facebook", "", "text", "social")');
    echo "✓ Settings table\n";

    // ACTIVITY LOG
    $db->exec('CREATE TABLE IF NOT EXISTS activity_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_id INTEGER DEFAULT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id INTEGER DEFAULT NULL,
        details TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    echo "✓ Activity Log table\n";

    echo "\n✅ SQLite database ready! All tables created with sample data.\n";
    echo "DB Path: $dbPath\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
