<?php
/**
 * Common Functions - DesiVastra E-Commerce
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// ============================================
// CONFIGURATION & CONSTANTS
// ============================================
define('CSRF_TOKEN_LIFETIME', 3600);
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('ADMIN_PER_PAGE', 10);
define('CURRENCY_SYMBOL', '₹');

/**
 * Build URL query parameters
 */
function buildQueryParams(array $newParams): string {
    $params = array_merge($_GET, $newParams);
    $query = http_build_query($params);
    return $query ? '?' . $query : '';
}

// ============================================
// DATABASE CONNECTION
// ============================================

/**
 * Get Database Connection
 * Supports SQLite (local dev) and MySQL (production) via DB_DRIVER constant
 */
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';

            if ($driver === 'sqlite') {
                $db = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $db->exec('PRAGMA foreign_keys = ON');
                $db->exec('PRAGMA journal_mode = WAL');
                // Use Pdo\Sqlite::createFunction() (PHP 8.5+) or fall back silently
                $sqFn = function(string $name, callable $fn, int $argc = -1) use ($db): void {
                    if ($db instanceof Pdo\Sqlite) {
                        $db->createFunction($name, $fn, $argc);
                    } elseif (method_exists($db, 'sqliteCreateFunction')) {
                        // Suppress deprecation on PHP < 8.5 where this is the only option
                        @$db->sqliteCreateFunction($name, $fn, $argc);
                    }
                };
                $sqFn('NOW', function() { return date('Y-m-d H:i:s'); }, 0);
                $sqFn('CURDATE', function() { return date('Y-m-d'); }, 0);
                $sqFn('DATE_FORMAT', function($date, $format) {
                    $map = ['%Y'=>'Y','%m'=>'m','%d'=>'d','%H'=>'H','%i'=>'i','%s'=>'s','%M'=>'F','%b'=>'M'];
                    $phpFmt = str_replace(array_keys($map), array_values($map), $format);
                    return $date ? date($phpFmt, strtotime($date)) : null;
                }, 2);
                $sqFn('DATE_SUB', function($date, $interval) {
                    if (preg_match('/(\d+)\s+(DAY|MONTH|YEAR|HOUR|MINUTE)/i', $interval, $m)) {
                        return date('Y-m-d H:i:s', strtotime("-{$m[1]} {$m[2]}", strtotime($date)));
                    }
                    return $date;
                }, 2);
                $sqFn('DATE_ADD', function($date, $interval) {
                    if (preg_match('/(\d+)\s+(DAY|MONTH|YEAR|HOUR|MINUTE)/i', $interval, $m)) {
                        return date('Y-m-d H:i:s', strtotime("+{$m[1]} {$m[2]}", strtotime($date)));
                    }
                    return $date;
                }, 2);
                $sqFn('DATEDIFF', function($d1, $d2) {
                    return round((strtotime($d1) - strtotime($d2)) / 86400);
                }, 2);
                $sqFn('TIMESTAMPDIFF', function($unit, $d1, $d2) {
                    $diff = strtotime($d2) - strtotime($d1);
                    switch (strtoupper($unit)) {
                        case 'DAY':    return (int)($diff / 86400);
                        case 'HOUR':   return (int)($diff / 3600);
                        case 'MINUTE': return (int)($diff / 60);
                        case 'MONTH':  return (int)($diff / 2592000);
                        case 'YEAR':   return (int)($diff / 31536000);
                        default: return $diff;
                    }
                }, 3);
                $sqFn('CONCAT', function() { return implode('', func_get_args()); });
                $sqFn('IF', function($cond, $true, $false) { return $cond ? $true : $false; }, 3);
            } else {
                $dsn = "mysql:charset=utf8mb4";
                if (defined('DB_SOCKET') && !empty(DB_SOCKET)) {
                    $dsn .= ";unix_socket=" . DB_SOCKET;
                } else {
                    $dsn .= ";host=" . DB_HOST;
                }
                $dsn .= ";dbname=" . DB_NAME;
                $db = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            }
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    return $db;
}

// ============================================
// SECURITY FUNCTIONS
// ============================================

/**
 * Generate CSRF Token
 */
function generateCSRF() {
    if (empty($_SESSION['csrf_token']) || time() > ($_SESSION['csrf_token_time'] ?? 0) + CSRF_TOKEN_LIFETIME) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCSRF(string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input
 */
function sanitize(mixed $data): mixed {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Clean string for output
 */
function clean(mixed $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// ============================================
// AUTH FUNCTIONS
// ============================================

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Require admin login (redirect if not)
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        if (isAjaxRequest()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
            exit;
        }
        header('Location: ' . getAdminUrl('login.php'));
        exit;
    }
}

/**
 * Get current admin data
 */
function getCurrentAdmin() {
    if (!isAdminLoggedIn()) return null;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, role, last_login FROM admins WHERE id = ? AND status = 1");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch();
}

/**
 * Admin login
 */
function adminLogin(string $email, string $password): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, password, role FROM admins WHERE email = ? AND status = 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_role'] = $admin['role'];
        
        // Update last login
        $stmt = $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
        
        // Log activity
        logActivity('login', 'admin', $admin['id'], ['ip' => getClientIP()]);
        
        return ['success' => true, 'admin' => $admin];
    }
    
    return ['success' => false, 'message' => 'Invalid email or password.'];
}

/**
 * Admin logout
 */
function adminLogout() {
    if (isAdminLoggedIn()) {
        logActivity('logout', 'admin', $_SESSION['admin_id']);
    }
    session_destroy();
    session_start();
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Check if AJAX request
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get client IP
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get admin URL
 */
function getAdminUrl($path = '') {
    return SITE_URL . '/admin/' . ltrim($path, '/');
}

/**
 * Format price with currency
 */
function formatPrice(float|int $amount): string {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

/**
 * Format Indian price (e.g., ₹1,23,456.00)
 */
function formatIndianPrice(float|int $amount): string {
    $amount = (float)$amount;
    $decimals = ($amount == floor($amount)) ? 0 : 2;
    $formatted = number_format($amount, $decimals, '.', '');
    $parts = explode('.', $formatted);
    $intPart = $parts[0];
    
    if (strlen($intPart) > 3) {
        $last3 = substr($intPart, -3);
        $restUnits = substr($intPart, 0, -3);
        $restUnits = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $restUnits);
        $intPart = $restUnits . ',' . $last3;
    }
    
    return CURRENCY_SYMBOL . $intPart . (isset($parts[1]) ? '.' . $parts[1] : '');
}

/**
 * Generate unique order number
 */
function generateOrderNumber() {
    return 'DV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Generate slug from string
 */
function generateSlug(string $string): string {
    $slug = preg_replace('/[^a-zA-Z0-9\s-]/', '', strtolower($string));
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Generate unique slug
 */
function generateUniqueSlug(string $string, string $table, ?int $excludeId = null): string {
    $db = getDB();
    $slug = generateSlug($string);
    $originalSlug = $slug;
    $counter = 1;
    
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        $params = [$slug];
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) break;
        $slug = $originalSlug . '-' . $counter++;
    }
    
    return $slug;
}

/**
 * Upload file with validation
 */
function uploadFile(array $file, string $subfolder = 'products', ?array $allowedTypes = null): array {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error.'];
    }
    
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds maximum allowed size (5MB).'];
    }
    
    $types = $allowedTypes ?? ALLOWED_IMAGE_TYPES;
    $finfo    = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    unset($finfo); // explicit release (no finfo_close needed)
    
    if (!in_array($mimeType, $types)) {
        return ['success' => false, 'message' => 'Invalid file type.'];
    }
    
    $uploadDir = UPLOAD_PATH . $subfolder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '-' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'path' => 'uploads/' . $subfolder . '/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to save file.'];
}

/**
 * Delete uploaded file
 */
function deleteUploadedFile(?string $path): void {
    if ($path && file_exists(dirname(__DIR__) . '/' . $path)) {
        unlink(dirname(__DIR__) . '/' . $path);
    }
}

/**
 * Paginate results
 */
function paginate(string $query, array $params, int $page = 1, int $perPage = ADMIN_PER_PAGE): array {
    $db = getDB();
    $page = max(1, (int)$page);
    $offset = ($page - 1) * $perPage;
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM ({$query}) as count_table";
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $total = (int)$stmt->fetch()['total'];
    
    // Get paginated results
    $dataQuery = $query . " LIMIT {$perPage} OFFSET {$offset}";
    $stmt = $db->prepare($dataQuery);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage),
        'has_prev' => $page > 1,
        'has_next' => $page < ceil($total / $perPage)
    ];
}

/**
 * Log admin activity
 */
function logActivity(string $action, ?string $entityType = null, int|string|null $entityId = null, ?array $details = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_log (admin_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['admin_id'] ?? null,
            $action,
            $entityType,
            $entityId,
            $details ? json_encode($details) : null,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

/**
 * Get settings value
 */
function getSetting(string $key, mixed $default = null): mixed {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

/**
 * Update setting value
 */
function updateSetting(string $key, mixed $value): bool {
    $db = getDB();
    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    return $stmt->execute([$value, $key]);
}

/**
 * JSON response
 */
function jsonResponse(mixed $data, int $statusCode = 200): never {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Redirect
 */
function redirect(string $url): never {
    header("Location: {$url}");
    exit;
}

/**
 * Flash message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get flash message
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Time ago
 */
function timeAgo(string $datetime): string {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Get order status badge class
 */
function getStatusBadge(string $status): string {
    $badges = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info',
        'processing' => 'badge-primary',
        'shipped' => 'badge-purple',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger',
        'returned' => 'badge-dark',
        'paid' => 'badge-success',
        'failed' => 'badge-danger',
        'refunded' => 'badge-info'
    ];
    return $badges[$status] ?? 'badge-secondary';
}

/**
 * Calculate dashboard stats
 */
function getDashboardStats() {
    $db = getDB();
    
    $stats = [];
    
    // Total revenue
    $stmt = $db->query("SELECT COALESCE(SUM(total), 0) as total_revenue FROM orders WHERE payment_status = 'paid'");
    $stats['total_revenue'] = (float)$stmt->fetch()['total_revenue'];
    
    // Today's revenue
    $stmt = $db->query("SELECT COALESCE(SUM(total), 0) as today_revenue FROM orders WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()");
    $stats['today_revenue'] = (float)$stmt->fetch()['today_revenue'];
    
    // Total orders
    $stmt = $db->query("SELECT COUNT(*) as total_orders FROM orders");
    $stats['total_orders'] = (int)$stmt->fetch()['total_orders'];
    
    // Pending orders
    $stmt = $db->query("SELECT COUNT(*) as pending_orders FROM orders WHERE status = 'pending'");
    $stats['pending_orders'] = (int)$stmt->fetch()['pending_orders'];
    
    // Total products
    $stmt = $db->query("SELECT COUNT(*) as total_products FROM products WHERE is_active = 1");
    $stats['total_products'] = (int)$stmt->fetch()['total_products'];
    
    // Total customers
    $stmt = $db->query("SELECT COUNT(*) as total_customers FROM customers WHERE is_active = 1");
    $stats['total_customers'] = (int)$stmt->fetch()['total_customers'];
    
    // Low stock products
    $stmt = $db->query("SELECT COUNT(*) as low_stock FROM products WHERE stock <= low_stock_threshold AND is_active = 1");
    $stats['low_stock'] = (int)$stmt->fetch()['low_stock'];
    
    // Total coupons
    $stmt = $db->query("SELECT COUNT(*) as total_coupons FROM coupons WHERE is_active = 1");
    $stats['total_coupons'] = (int)$stmt->fetch()['total_coupons'];
    
    return $stats;
}

/**
 * Get recent orders
 */
function getRecentOrders($limit = 10) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT o.id, o.order_number, o.customer_name, o.total, o.status, o.payment_status, o.created_at 
        FROM orders o 
        ORDER BY o.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get sales chart data (last 30 days)
 */
function getSalesChartData($days = 30) {
    $db = getDB();
    $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';
    if ($driver === 'sqlite') {
        $stmt = $db->prepare("
            SELECT DATE(created_at) as date, COALESCE(SUM(total), 0) as revenue, COUNT(*) as orders
            FROM orders
            WHERE DATE(created_at) >= DATE('now', '-' || ? || ' days')
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
    } else {
        $stmt = $db->prepare("
            SELECT DATE(created_at) as date, COALESCE(SUM(total), 0) as revenue, COUNT(*) as orders
            FROM orders
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
    }
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

/**
 * Get top selling products
 */
function getTopSellingProducts($limit = 5) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.main_image, p.price, SUM(oi.quantity) as total_sold, SUM(oi.total) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled'
        GROUP BY oi.product_id
        ORDER BY total_sold DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get order status counts
 */
function getOrderStatusCounts() {
    $db = getDB();
    $stmt = $db->query("
        SELECT status, COUNT(*) as count 
        FROM orders 
        GROUP BY status
    ");
    $counts = [];
    while ($row = $stmt->fetch()) {
        $counts[$row['status']] = (int)$row['count'];
    }
    return $counts;
}