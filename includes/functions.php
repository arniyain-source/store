<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Dashboard & API ---

function getDashboardStats() {
    global $db;
    $revenue = $db->query("SELECT SUM(total_price) FROM orders WHERE status = 'completed'")->fetchColumn();
    $orders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $customers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $lowStock = $db->query("SELECT COUNT(*) FROM products WHERE stock < 5")->fetchColumn();

    return [
        'total_revenue' => $revenue ?? 0,
        'total_orders' => $orders ?? 0,
        'total_customers' => $customers ?? 0,
        'low_stock' => $lowStock ?? 0,
    ];
}

function getSalesChartData($days = 30) {
    global $db;
    $sales = [];
    $query = "SELECT DATE(created_at) as date, SUM(total_price) as daily_sales 
              FROM orders 
              WHERE created_at >= DATE('now', '-{$days} days') AND status = 'completed'
              GROUP BY DATE(created_at) 
              ORDER BY DATE(created_at) ASC";
    $stmt = $db->query($query);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sales[$row['date']] = $row['daily_sales'];
    }

    return $sales;
}

function getTopSellingProducts($limit = 5) {
    global $db;
    $query = "SELECT p.id, p.name, p.main_image, SUM(oi.quantity) as total_sold 
              FROM products p
              JOIN order_items oi ON p.id = oi.product_id
              GROUP BY p.id
              ORDER BY total_sold DESC
              LIMIT {$limit}";
    return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentOrders($limit = 5) {
    global $db;
    $query = "SELECT * FROM orders ORDER BY created_at DESC LIMIT {$limit}";
    return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
}

function jsonResponse($data, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// --- Auth & Security ---

function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        setFlash('error', 'Please log in to access this page.');
        header('Location: /admin/login.php');
        exit;
    }
}

function generateCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        unset($_SESSION['csrf_token']);
        return true;
    }
    return false;
}

function logActivity($action, $entity_type = null, $entity_id = null, $details = []) {
    try {
        global $db;
        $sql = "INSERT INTO activity_log (admin_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                VALUES (:admin_id, :action, :entity_type, :entity_id, :details, :ip_address, :user_agent)";
        
        $stmt = $db->prepare($sql);

        $admin_id = $_SESSION['admin_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $details_json = json_encode($details);

        $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':entity_type', $entity_type);
        $stmt->bindParam(':entity_id', $entity_id, PDO::PARAM_INT);
        $stmt->bindParam(':details', $details_json);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// --- Flash Messages ---

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// --- Data & String Utilities ---

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return trim($data ?? '');
}

function clean($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function timeAgo($timestamp) {
    if (!$timestamp) return 'never';
    try {
        $datetime = new DateTime($timestamp);
        $now = new DateTime();
        $diff = $now->diff($datetime);

        if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        
        $seconds = $diff->s;
        if ($seconds > 10) return $seconds . ' seconds ago';
    } catch(Exception $e) {
        return 'invalid date';
    }
    
    return 'just now';
}

function buildQueryParams($newParams = []) {
    $queryParams = $_GET;
    // Unset params that we might be replacing, like page
    unset($queryParams['page']);

    $allParams = array_merge($queryParams, $newParams);
    
    if (empty($allParams)) {
        return '';
    }
    
    return '?' . http_build_query($allParams);
}

// --- Pagination ---

function paginate($baseQuery, $params = [], $currentPage = 1, $perPage = 20) {
    global $db;

    $countQuery = 'SELECT COUNT(*) FROM (' . preg_replace('/LIMIT \d+ OFFSET \d+$/i', '', $baseQuery) . ') AS count_alias';
    $countQuery = preg_replace('/ORDER BY .*/i', '', $countQuery);

    try {
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $totalRecords = (int) $countStmt->fetchColumn();
    } catch(Exception $e) {
        $countQuerySimple = preg_replace('/SELECT .*? FROM/i', 'SELECT COUNT(*) FROM', $baseQuery);
        $countQuerySimple = preg_replace('/ORDER BY .*/i', '', $countQuerySimple);
        $countStmt = $db->prepare($countQuerySimple);
        $countStmt->execute($params);
        $totalRecords = (int) $countStmt->fetchColumn();
    }
    
    $totalPages = (int) ceil($totalRecords / $perPage);
    $currentPage = max(1, min((int)$currentPage, $totalPages > 0 ? $totalPages : 1));
    
    $offset = ($currentPage - 1) * $perPage;
    $dataQuery = $baseQuery . " LIMIT :limit OFFSET :offset";
    
    $dataStmt = $db->prepare($dataQuery);
    
    if (!empty($params)) {
        foreach ($params as $key => $val) {
             $dataStmt->bindValue($key, $val);
        }
    }
    
    $dataStmt->bindValue(':limit', (int) $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
    
    $dataStmt->execute();
    $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'data' => $data,
        'total' => $totalRecords,
        'total_pages' => $totalPages,
        'page' => $currentPage,
        'per_page' => $perPage,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
    ];
}

function getActivityLogs($filters = [], $page = 1, $limit = 20) {
    global $db;

    $whereClauses = [];
    $params = [];

    if (!empty($filters['user_id'])) {
        $whereClauses[] = 'user_id = :user_id';
        $params[':user_id'] = $filters['user_id'];
    }
    if (!empty($filters['action_type'])) {
        $whereClauses[] = 'action_type = :action_type';
        $params[':action_type'] = $filters['action_type'];
    }

    $whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $totalQuery = "SELECT COUNT(*) FROM activity_logs {$whereSql}";
    $totalStmt = $db->prepare($totalQuery);
    $totalStmt->execute($params);
    $totalRecords = (int)$totalStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    $offset = ($page - 1) * $limit;
    $logsQuery = "SELECT * FROM activity_logs {$whereSql} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $logsStmt = $db->prepare($logsQuery);

    foreach ($params as $key => &$val) {
        $logsStmt->bindParam($key, $val);
    }

    $logsStmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $logsStmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    $logsStmt->execute();
    $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'logs' => $logs,
        'pagination' => [
            'total' => $totalRecords,
            'pages' => $totalPages,
            'current' => $page,
            'limit' => $limit
        ]
    ];
}

?>