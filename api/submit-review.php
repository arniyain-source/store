<?php

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// 1. INPUT VALIDATION
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
    exit;
}

$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
$name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
$reviewText = trim(filter_input(INPUT_POST, 'review', FILTER_SANITIZE_STRING));

if (!$productId || !$rating || empty($name) || empty($reviewText)) {
    jsonResponse(['success' => false, 'message' => 'Missing required fields.'], 400);
    exit;
}

if ($rating < 1 || $rating > 5) {
    jsonResponse(['success' => false, 'message' => 'Invalid rating value.'], 400);
    exit;
}

$db = getDB();

// 2. CHECK IF PRODUCT EXISTS
$stmt = $db->prepare("SELECT id FROM products WHERE id = ?");
$stmt->execute([$productId]);
if ($stmt->fetch() === false) {
    jsonResponse(['success' => false, 'message' => 'Product not found.'], 404);
    exit;
}

// 3. INSERT REVIEW (as pending approval)
$isApproved = 0; // Default to not approved
$userId = isLoggedIn() ? $_SESSION['user_id'] : null;

try {
    $stmt = $db->prepare(
        "INSERT INTO reviews (product_id, customer_id, customer_name, rating, review, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$productId, $userId, $name, $rating, $reviewText, $isApproved]);
    
    $reviewId = $db->lastInsertId();

    // Log admin activity
    logActivity('new_review_pending', 'review', $reviewId, ['product_id' => $productId, 'rating' => $rating]);

    jsonResponse(['success' => true, 'message' => 'Thank you! Your review has been submitted for approval.']);

} catch (PDOException $e) {
    // In a real app, log this error properly
    error_log("Review submission failed: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Could not submit your review at this time.'], 500);
}

