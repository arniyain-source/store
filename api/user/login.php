<?php
/**
 * API: User Login / Logout / Register / Profile / Password Reset
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

$action = $_GET['action'] ?? (($_SERVER['REQUEST_METHOD'] === 'POST') ? 'login' : 'profile');
$data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $db = getDB();

    switch ($action) {

        case 'login':
            $email    = sanitize($data['email'] ?? '');
            $password = $data['password'] ?? '';
            if (!$email || !$password) {
                jsonResponse(['success' => false, 'message' => 'Email and password required'], 400);
            }
            $stmt = $db->prepare("SELECT * FROM customers WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['customer_id']   = $user['id'];
                $_SESSION['customer_name'] = $user['name'];
                $_SESSION['customer_email']= $user['email'];
                $db->prepare("UPDATE customers SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                jsonResponse(['success' => true, 'message' => 'Login successful', 'user' => [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                ]]);
            }
            jsonResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
            break;

        case 'register':
            $name     = sanitize($data['name'] ?? '');
            $email    = sanitize($data['email'] ?? '');
            $phone    = sanitize($data['phone'] ?? '');
            $password = $data['password'] ?? '';
            if (!$name || !$email || !$password) {
                jsonResponse(['success' => false, 'message' => 'Name, email and password are required'], 400);
            }
            // Check duplicate
            $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Email already registered'], 409);
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO customers (name, email, phone, password, user_type, is_active, created_at, updated_at) VALUES (?,?,?,?,'retail',1,NOW(),NOW())");
            $stmt->execute([$name, $email, $phone, $hash]);
            $newId = (int)$db->lastInsertId();
            $_SESSION['customer_id']    = $newId;
            $_SESSION['customer_name']  = $name;
            $_SESSION['customer_email'] = $email;
            jsonResponse(['success' => true, 'message' => 'Account created!', 'user' => ['id' => $newId, 'name' => $name, 'email' => $email]]);
            break;

        case 'logout':
            unset($_SESSION['customer_id'], $_SESSION['customer_name'], $_SESSION['customer_email']);
            jsonResponse(['success' => true, 'message' => 'Logged out']);
            break;

        case 'profile':
            if (empty($_SESSION['customer_id'])) {
                jsonResponse(['success' => false, 'message' => 'Not logged in', 'logged_in' => false], 401);
            }
            $stmt = $db->prepare("SELECT id, name, email, phone, city, state, user_type, avatar FROM customers WHERE id = ?");
            $stmt->execute([$_SESSION['customer_id']]);
            $user = $stmt->fetch();
            // Get addresses
            $stmt2 = $db->prepare("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC");
            $stmt2->execute([$_SESSION['customer_id']]);
            $addresses = $stmt2->fetchAll();
            jsonResponse(['success' => true, 'logged_in' => true, 'user' => $user, 'addresses' => $addresses]);
            break;

        case 'forgot_password':
            $email = sanitize($data['email'] ?? '');
            if (!$email) {
                jsonResponse(['success' => false, 'message' => 'Email is required'], 400);
            }
            $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => 'No account found with that email'], 404);
            }
            $token = bin2hex(random_bytes(32));
            $otp = rand(100000, 999999);
            $expires = new DateTime('+15 minutes');
            $stmt = $db->prepare("INSERT INTO password_resets (email, token, otp, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$email, $token, $otp, $expires->format('Y-m-d H:i:s')]);
            // In a real app, you would email this OTP to the user.
            // For this example, we will return it in the response for simplicity.
            jsonResponse(['success' => true, 'message' => 'An OTP has been sent to your email.', 'otp' => $otp, 'token' => $token]);
            break;

        case 'reset_password':
            $token = sanitize($data['token'] ?? '');
            $otp = sanitize($data['otp'] ?? '');
            $password = $data['password'] ?? '';

            if (!$token || !$otp || !$password) {
                jsonResponse(['success' => false, 'message' => 'Token, OTP, and new password are required'], 400);
            }

            $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND otp = ? AND expires_at > NOW()");
            $stmt->execute([$token, $otp]);
            $reset_request = $stmt->fetch();

            if (!$reset_request) {
                jsonResponse(['success' => false, 'message' => 'Invalid or expired token or OTP'], 400);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE customers SET password = ? WHERE email = ?");
            $stmt->execute([$hash, $reset_request['email']]);

            // Delete the reset token so it can't be used again
            $db->prepare("DELETE FROM password_resets WHERE id = ?")->execute([$reset_request['id']]);

            jsonResponse(['success' => true, 'message' => 'Your password has been reset successfully.']);
            break;

        case 'check_pincode':
            $pincode = sanitize($data['pincode'] ?? $_GET['pincode'] ?? '');
            if (!preg_match('/^\d{6}$/', $pincode)) {
                jsonResponse(['success' => false, 'message' => 'Invalid pincode format'], 400);
            }
            $firstDigit = (int)$pincode[0];
            if ($firstDigit >= 1 && $firstDigit <= 8) {
                $days = rand(3, 7);
                $date = date('D, d M', strtotime("+{$days} days"));
                jsonResponse(['success' => true, 'deliverable' => true, 'message' => "Delivery by <strong>{$date}</strong> — Free Shipping!", 'days' => $days]);
            } else {
                jsonResponse(['success' => true, 'deliverable' => false, 'message' => 'Delivery not available for this pincode.']);
            }
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
