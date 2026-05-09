<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Basic input validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review_text = isset($_POST['review']) ? trim($_POST['review']) : '';

if (empty($product_id) || empty($name) || empty($rating) || empty($review_text)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid rating value.']);
    exit;
}

try {
    $db = getDB();

    // Check if the product exists and is active
    $stmt = $db->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    // Insert the new review with 'is_approved' set to 0 (pending)
    $stmt = $db->prepare("
        INSERT INTO reviews (product_id, customer_name, rating, review, is_approved, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");

    $stmt->execute([
        $product_id,
        htmlspecialchars($name), // Sanitize name
        $rating,
        htmlspecialchars($review_text) // Sanitize review text
    ]);

    echo json_encode(['success' => true, 'message' => 'Thank you! Your review has been submitted and is pending approval.']);

} catch (PDOException $e) {
    http_response_code(500);
    // In a real app, log this error instead of echoing it
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}
