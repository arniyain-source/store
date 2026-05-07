<?php
/**
 * DesiVastra Installer - /install
 * Handles: DB setup, admin creation, .env writing, permission check
 */

// Block if already installed
$installedFlag = __DIR__ . '/.installed';
$step = $_GET['step'] ?? 'form';

if (file_exists($installedFlag) && $step !== 'done') {
    header('Location: /admin/login.php');
    exit;
}

$errors = [];
$success = false;
$logs = [];
$adminEmail = '';
$adminPass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_install'])) {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $adminName  = trim($_POST['admin_name'] ?? 'Super Admin');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_password'] ?? '';
    $siteUrl    = rtrim(trim($_POST['site_url'] ?? 'https://desivastra.in'), '/');

    // Validate
    if (!$dbName) $errors[] = 'Database name is required.';
    if (!$dbUser) $errors[] = 'Database username is required.';
    if (!$adminEmail || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid admin email required.';
    if (strlen($adminPass) < 6) $errors[] = 'Admin password must be at least 6 characters.';

    if (empty($errors)) {
        try {
            // 1. Test DB connection
            $pdo = new PDO(
                "mysql:host={$dbHost};charset=utf8mb4",
                $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $logs[] = ['ok', 'Database connection successful'];

            // 2. Create DB if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
            $logs[] = ['ok', "Database '{$dbName}' ready"];

            // 3. Create all tables
            $tables = [
                "CREATE TABLE IF NOT EXISTS `admins` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `email` VARCHAR(150) NOT NULL UNIQUE,
                    `password` VARCHAR(255) NOT NULL,
                    `role` ENUM('super_admin','admin','editor') DEFAULT 'admin',
                    `status` TINYINT(1) DEFAULT 1,
                    `last_login` DATETIME DEFAULT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `categories` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `slug` VARCHAR(120) NOT NULL UNIQUE,
                    `description` TEXT DEFAULT NULL,
                    `image` VARCHAR(255) DEFAULT NULL,
                    `icon` VARCHAR(50) DEFAULT NULL,
                    `sort_order` INT DEFAULT 0,
                    `status` TINYINT(1) DEFAULT 1,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `products` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(255) NOT NULL,
                    `slug` VARCHAR(280) NOT NULL UNIQUE,
                    `sku` VARCHAR(50) DEFAULT NULL UNIQUE,
                    `category_id` INT UNSIGNED DEFAULT NULL,
                    `short_description` VARCHAR(500) DEFAULT NULL,
                    `description` TEXT DEFAULT NULL,
                    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `old_price` DECIMAL(10,2) DEFAULT NULL,
                    `stock` INT UNSIGNED DEFAULT 0,
                    `low_stock_threshold` INT UNSIGNED DEFAULT 5,
                    `main_image` VARCHAR(255) DEFAULT NULL,
                    `images` JSON DEFAULT NULL,
                    `sizes` JSON DEFAULT NULL,
                    `colors` JSON DEFAULT NULL,
                    `rating` DECIMAL(2,1) DEFAULT 0.0,
                    `reviews_count` INT UNSIGNED DEFAULT 0,
                    `is_featured` TINYINT(1) DEFAULT 0,
                    `is_new_arrival` TINYINT(1) DEFAULT 0,
                    `is_top_selling` TINYINT(1) DEFAULT 0,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `customers` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `email` VARCHAR(150) NOT NULL UNIQUE,
                    `phone` VARCHAR(20) DEFAULT NULL,
                    `password` VARCHAR(255) NOT NULL,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `last_login` DATETIME DEFAULT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `orders` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `order_number` VARCHAR(30) NOT NULL UNIQUE,
                    `customer_id` INT UNSIGNED DEFAULT NULL,
                    `customer_name` VARCHAR(100) DEFAULT NULL,
                    `customer_email` VARCHAR(150) DEFAULT NULL,
                    `customer_phone` VARCHAR(20) DEFAULT NULL,
                    `shipping_address` JSON DEFAULT NULL,
                    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `discount` DECIMAL(10,2) DEFAULT 0.00,
                    `shipping_cost` DECIMAL(10,2) DEFAULT 0.00,
                    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `payment_method` VARCHAR(50) DEFAULT NULL,
                    `payment_status` ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
                    `status` ENUM('pending','confirmed','processing','shipped','delivered','cancelled','returned') DEFAULT 'pending',
                    `notes` TEXT DEFAULT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `order_items` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `order_id` INT UNSIGNED NOT NULL,
                    `product_id` INT UNSIGNED DEFAULT NULL,
                    `product_name` VARCHAR(255) NOT NULL,
                    `price` DECIMAL(10,2) NOT NULL,
                    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
                    `total` DECIMAL(10,2) NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `coupons` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `code` VARCHAR(50) NOT NULL UNIQUE,
                    `type` ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
                    `value` DECIMAL(10,2) NOT NULL,
                    `min_order_amount` DECIMAL(10,2) DEFAULT 0.00,
                    `usage_limit` INT UNSIGNED DEFAULT NULL,
                    `usage_count` INT UNSIGNED DEFAULT 0,
                    `valid_from` DATETIME DEFAULT NULL,
                    `valid_to` DATETIME DEFAULT NULL,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `reviews` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `product_id` INT UNSIGNED NOT NULL,
                    `customer_id` INT UNSIGNED DEFAULT NULL,
                    `customer_name` VARCHAR(100) NOT NULL,
                    `rating` TINYINT UNSIGNED NOT NULL,
                    `review` TEXT DEFAULT NULL,
                    `is_approved` TINYINT(1) DEFAULT 0,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `settings` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
                    `setting_value` TEXT DEFAULT NULL,
                    `setting_group` VARCHAR(50) DEFAULT 'general',
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `activity_log` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `admin_id` INT UNSIGNED DEFAULT NULL,
                    `action` VARCHAR(100) NOT NULL,
                    `entity_type` VARCHAR(50) DEFAULT NULL,
                    `entity_id` INT UNSIGNED DEFAULT NULL,
                    `details` JSON DEFAULT NULL,
                    `ip_address` VARCHAR(45) DEFAULT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `wishlist` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `customer_id` INT UNSIGNED NOT NULL,
                    `product_id` INT UNSIGNED NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `unique_wishlist` (`customer_id`,`product_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ];

            foreach ($tables as $sql) {
                $pdo->exec($sql);
                if (preg_match('/CREATE TABLE.*?`(\w+)`/i', $sql, $m)) {
                    $logs[] = ['ok', "Table '{$m[1]}' ready"];
                }
            }

            // 4. Seed default categories
            $pdo->exec("INSERT IGNORE INTO `categories` (`name`,`slug`,`icon`,`sort_order`) VALUES
                ('Watches','watches','fa-clock',1),
                ('Jewelry','jewelry','fa-gem',2),
                ('Accessories','accessories','fa-glasses',3),
                ('Perfumes','perfumes','fa-flask',4),
                ('Bags','bags','fa-bag-shopping',5)");
            $logs[] = ['ok', 'Default categories seeded'];

            // 5. Seed default settings (use prepared statements to safely handle any URL/value)
            $settingsStmt = $pdo->prepare(
                "INSERT IGNORE INTO `settings` (`setting_key`,`setting_value`,`setting_group`) VALUES (?,?,?)"
            );
            $defaultSettings = [
                ['site_name',              'DesiVastra',   'general'],
                ['site_url',               $siteUrl,       'general'],
                ['currency_symbol',        '₹',            'general'],
                ['free_shipping_min',      '999',          'shipping'],
                ['shipping_cost',          '99',           'shipping'],
                ['cod_enabled',            '1',            'payment'],
                ['online_payment_enabled', '0',            'payment'],
                ['site_phone',             '+91 9876543210','general'],
                ['site_whatsapp',          '919876543210', 'general'],
                ['site_email',             $adminEmail,    'general'],
                ['tax_rate',               '0',            'tax'],
                ['razorpay_key',           '',             'payment'],
                ['razorpay_secret',        '',             'payment'],
                ['maintenance_mode',       '0',            'general'],
            ];
            foreach ($defaultSettings as $row) {
                $settingsStmt->execute($row);
            }
            $logs[] = ['ok', 'Default settings seeded (' . count($defaultSettings) . ' entries)'];

            // 6. Create admin user
            $hashedPw = password_hash($adminPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO `admins` (`name`,`email`,`password`,`role`) VALUES (?,?,?,'super_admin')
                ON DUPLICATE KEY UPDATE `password`=VALUES(`password`), `name`=VALUES(`name`)");
            $stmt->execute([$adminName, $adminEmail, $hashedPw]);
            $logs[] = ['ok', "Admin user '{$adminEmail}' created/updated"];

            // 7. Update config/database.php
            $configPath = __DIR__ . '/config/database.php';
            if (file_exists($configPath)) {
                $cfg = file_get_contents($configPath);
                $cfg = preg_replace("/define\('DB_HOST',\s*'.*?'\)/", "define('DB_HOST', '{$dbHost}')", $cfg);
                $cfg = preg_replace("/define\('DB_NAME',\s*'.*?'\)/", "define('DB_NAME', '{$dbName}')", $cfg);
                $cfg = preg_replace("/define\('DB_USER',\s*'.*?'\)/", "define('DB_USER', '{$dbUser}')", $cfg);
                $cfg = preg_replace("/define\('DB_PASS',\s*'.*?'\)/", "define('DB_PASS', '{$dbPass}')", $cfg);
                $cfg = preg_replace("/define\('SITE_URL',\s*'.*?'\)/", "define('SITE_URL', '{$siteUrl}')", $cfg);
                file_put_contents($configPath, $cfg);
                $logs[] = ['ok', 'config/database.php updated'];
            }

            // 8. Create uploads directory
            $uploadsDir = __DIR__ . '/uploads/products';
            if (!is_dir($uploadsDir)) { mkdir($uploadsDir, 0755, true); }
            $logs[] = ['ok', 'Upload folders ready'];

            // 9. Write .installed flag
            file_put_contents($installedFlag, date('Y-m-d H:i:s') . "\n{$adminEmail}\n{$siteUrl}");
            $logs[] = ['ok', 'Installation complete!'];

            $success = true;

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
            $logs[] = ['err', $e->getMessage()];
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

$siteUrl = $_POST['site_url'] ?? 'https://desivastra.in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DesiVastra Installer</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',-apple-system,sans-serif;background:#0a0a0f;color:#f0f0f5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.wrap{max-width:680px;width:100%}
.card{background:#1a1a2e;border:1px solid #2a2a4a;border-radius:16px;padding:40px}
.logo{font-size:28px;font-weight:700;background:linear-gradient(135deg,#d4a853,#f0d78c);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:4px}
.subtitle{color:#9a9ab0;font-size:14px;margin-bottom:30px}
.step-bar{display:flex;gap:8px;margin-bottom:28px}
.step-dot{flex:1;height:4px;border-radius:2px;background:#2a2a4a}
.step-dot.done{background:#d4a853}
.step-dot.active{background:linear-gradient(90deg,#d4a853,#f0d78c)}
h3{font-size:13px;font-weight:600;color:#d4a853;text-transform:uppercase;letter-spacing:.5px;margin:20px 0 12px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:500px){.row{grid-template-columns:1fr}}
.fg{margin-bottom:14px}
label{display:block;font-size:11px;font-weight:600;color:#9a9ab0;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px}
input{width:100%;padding:10px 14px;background:#16162b;border:1px solid #2a2a4a;border-radius:8px;color:#f0f0f5;font-size:13px;font-family:inherit;transition:.2s}
input:focus{outline:none;border-color:#b8922e;background:#1e1e3a}
.btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;background:linear-gradient(135deg,#d4a853,#f0d78c,#d4a853);color:#0a0a0f;transition:.3s;margin-top:20px}
.btn:hover{box-shadow:0 4px 20px rgba(212,168,83,.35);transform:translateY(-1px)}
.err{background:rgba(231,76,60,.12);border:1px solid rgba(231,76,60,.3);border-radius:8px;padding:12px 16px;font-size:13px;color:#e74c3c;margin-bottom:16px}
.log-box{background:#0a0a0f;border:1px solid #2a2a4a;border-radius:8px;padding:14px;font-family:monospace;font-size:12px;max-height:300px;overflow-y:auto;margin:16px 0;line-height:1.9}
.log-ok{color:#2ecc71}.log-err{color:#e74c3c}.log-info{color:#3498db}
.success-box{text-align:center;padding:30px 20px;background:rgba(46,204,113,.08);border:1px solid rgba(46,204,113,.2);border-radius:12px}
.success-box h2{color:#2ecc71;font-size:22px;margin-bottom:10px}
.success-box p{color:#9a9ab0;font-size:13px;margin-bottom:6px}
.creds{background:rgba(0,0,0,.3);border:1px solid #2a2a4a;border-radius:8px;padding:14px;margin:14px auto;max-width:340px;text-align:left}
.creds p{font-size:13px;color:#9a9ab0;margin-bottom:4px}
.creds strong{color:#d4a853}
.links{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:16px}
.link-btn{padding:12px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;transition:.2s}
.link-gold{background:linear-gradient(135deg,#d4a853,#f0d78c);color:#0a0a0f}
.link-ghost{background:rgba(255,255,255,.07);color:#f0f0f5;border:1px solid #2a2a4a}
.note{font-size:12px;color:#e74c3c;font-weight:600;margin-top:12px}
.already{text-align:center;padding:40px}
.already h2{color:#d4a853;font-size:22px;margin-bottom:10px}
.already p{color:#9a9ab0;margin-bottom:20px}
</style>
</head>
<body>
<div class="wrap">
<div class="card">
<?php if ($success): ?>
    <div class="success-box">
        <div style="font-size:48px;margin-bottom:12px">✅</div>
        <h2>Installation Complete!</h2>
        <p>DesiVastra has been set up successfully.</p>
        <div class="creds">
            <p><strong>Admin Login:</strong></p>
            <p>Email: <strong><?= htmlspecialchars($adminEmail) ?></strong></p>
            <p>Password: <strong><?= htmlspecialchars($_POST['admin_password'] ?? '') ?></strong></p>
            <p style="color:#f1c40f;margin-top:8px">⚠️ Save these credentials now!</p>
        </div>
        <div class="log-box">
        <?php foreach ($logs as $l): ?>
            <div class="log-<?= $l[0] ?>"><?= $l[0]==='ok'?'✓':($l[0]==='err'?'✗':'→') ?> <?= htmlspecialchars($l[1]) ?></div>
        <?php endforeach; ?>
        </div>
        <div class="links">
            <a href="/admin-login" class="link-btn link-gold">Open Admin Login →</a>
            <a href="/" class="link-btn link-ghost">View Website</a>
        </div>
        <p class="note">⚠️ For security: rename or delete <code>install.php</code> after setup.</p>
    </div>
<?php else: ?>
    <div class="logo">DesiVastra</div>
    <p class="subtitle">Installation Wizard — Set up your store in seconds</p>
    <div class="step-bar">
        <div class="step-dot done"></div>
        <div class="step-dot active"></div>
        <div class="step-dot"></div>
    </div>
    <?php if (!empty($errors)): ?>
        <div class="err">
            <?php foreach ($errors as $e): ?><div>⚠ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($logs)): ?>
        <div class="log-box">
        <?php foreach ($logs as $l): ?>
            <div class="log-<?= $l[0] ?>"><?= $l[0]==='ok'?'✓':($l[0]==='err'?'✗':'→') ?> <?= htmlspecialchars($l[1]) ?></div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="POST">
        <h3>🗄️ Database Configuration</h3>
        <div class="row">
            <div class="fg"><label>DB Host</label><input name="db_host" value="<?= htmlspecialchars($_POST['db_host']??'localhost') ?>" placeholder="localhost" required></div>
            <div class="fg"><label>DB Name</label><input name="db_name" value="<?= htmlspecialchars($_POST['db_name']??'u602484543_desivastra') ?>" placeholder="database_name" required></div>
        </div>
        <div class="row">
            <div class="fg"><label>DB Username</label><input name="db_user" value="<?= htmlspecialchars($_POST['db_user']??'u602484543_desivastra') ?>" placeholder="db_user" required></div>
            <div class="fg"><label>DB Password</label><input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass']??'') ?>" placeholder="••••••••"></div>
        </div>
        <h3>👤 Admin Account</h3>
        <div class="row">
            <div class="fg"><label>Admin Name</label><input name="admin_name" value="<?= htmlspecialchars($_POST['admin_name']??'Super Admin') ?>" placeholder="Super Admin" required></div>
            <div class="fg"><label>Admin Email</label><input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email']??'admin@desivastra.in') ?>" placeholder="admin@desivastra.in" required></div>
        </div>
        <div class="fg"><label>Admin Password (min 6 chars)</label><input type="password" name="admin_password" placeholder="Create a strong password" required minlength="6"></div>
        <h3>🌐 Site Configuration</h3>
        <div class="fg"><label>Site URL</label><input name="site_url" value="<?= htmlspecialchars($siteUrl) ?>" placeholder="https://desivastra.in" required></div>
        <button type="submit" name="run_install" value="1" class="btn">⚡ Install DesiVastra Now</button>
    </form>
    <div style="text-align:center;margin-top:20px;font-size:12px;color:#6b6b85">
        <a href="/admin-login" style="color:#d4a853">Already installed? → Admin Login</a>
    </div>
<?php endif; ?>
</div>
</div>
</body>
</html>
