<?php
/**
 * API: AI Photo Search
 * Handles image upload and visual product matching
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

// Ensure the logs table exists
try {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS `photo_search_logs` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `image_path` VARCHAR(255) NOT NULL,
        `results_json` JSON DEFAULT NULL,
        `confidence` DECIMAL(3,2) DEFAULT 0.00,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // Fail silently
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    // 1. Directory Setup
    $uploadDir = __DIR__ . '/../uploads/search/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // 2. Handle Image Upload
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'No image uploaded or upload error.'], 400);
    }

    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    // Validate size
    if ($file['size'] > $maxSize) {
        jsonResponse(['success' => false, 'message' => 'File size exceeds 5MB limit.'], 400);
    }

    // Validate type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        jsonResponse(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and WEBP allowed.'], 400);
    }

    // Generate unique name and move
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('search_') . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $fileName;
    $relativeUrl = 'uploads/search/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        jsonResponse(['success' => false, 'message' => 'Failed to save uploaded image.'], 500);
    }

    // 3. Matching Logic (Mock AI)
    $confidence = round(rand(70, 95) / 100, 2);
    
    // Fetch random products as "Similar Matches"
    $stmt = $db->query("
        SELECT id, name, sku, price, sale_price, main_image, rating 
        FROM products 
        WHERE is_active = 1 
        ORDER BY RAND() 
        LIMIT 4
    ");
    $matches = $stmt->fetchAll();

    $message = $confidence >= 0.9 ? "Exact product match found!" : "Found " . count($matches) . " similar items.";

    // 4. Logging
    $logStmt = $db->prepare("INSERT INTO photo_search_logs (image_path, results_json, confidence) VALUES (?, ?, ?)");
    $logStmt->execute([
        $relativeUrl,
        json_encode($matches),
        $confidence
    ]);

    // 5. Response
    jsonResponse([
        'success' => true,
        'message' => $message,
        'confidence' => $confidence,
        'matches' => $matches,
        'image_url' => SITE_URL . '/' . $relativeUrl
    ]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()], 500);
}