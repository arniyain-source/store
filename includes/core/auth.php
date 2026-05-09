<?php

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

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

function getCurrentAdmin() {
    if (!isAdminLoggedIn()) return null;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, role, last_login FROM admins WHERE id = ? AND status = 1");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch();
}

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
        
        $stmt = $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
        
        logActivity('login', 'admin', $admin['id'], ['ip' => getClientIP()]);
        
        return ['success' => true, 'admin' => $admin];
    }
    
    return ['success' => false, 'message' => 'Invalid email or password.'];
}

function adminLogout() {
    if (isAdminLoggedIn()) {
        logActivity('logout', 'admin', $_SESSION['admin_id']);
    }
    session_destroy();
    session_start();
}
