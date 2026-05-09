<?php
/**
 * API: Customers - Update Role (reseller / wholesale / retail)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';

if (!isAdminLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'POST required'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$customerId = (int)($data['customer_id'] ?? 0);
$newRole    = sanitize($data['role'] ?? '');

$validRoles = ['retail', 'reseller', 'wholesale', 'vip'];
if (!$customerId || !in_array($newRole, $validRoles)) {
    jsonResponse(['success' => false, 'message' => 'Invalid customer or role'], 400);
}

try {
    $db   = getDB();
    $stmt = $db->prepare("UPDATE customers SET user_type = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newRole, $customerId]);
    logActivity('update_customer_role', 'customer', $customerId, ['role' => $newRole]);
    jsonResponse(['success' => true, 'message' => 'Customer role updated to ' . $newRole]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
