<?php
// Placeholder for AI-powered visual search API
require_once __DIR__ . '/../includes/functions.php';

// In a real implementation, you would use a library to handle the file upload
// and then send the image to a service like Google Vision AI.

// For now, we'll simulate a successful response with mock data.

header('Content-Type: application/json');

// Simulate AI processing delay
sleep(2);

// Mock AI-generated tags based on image analysis
$mockTags = ['Fashion', 'Style', 'Ethnic Wear', 'Saree'];

// Mock finding a best match and similar products
$db = getDB();

// Fetch one random product as the "best match"
$bestMatch = $db->query("SELECT id, name, price, old_price, main_image as img FROM products WHERE is_active = 1 ORDER BY RANDOM() LIMIT 1")->fetch();

// Fetch a few other random products as "similar"
if ($bestMatch) {
    $similar = $db->query("SELECT id, name, price, old_price, main_image as img FROM products WHERE is_active = 1 AND id != ''' . $bestMatch['id'] . ''' ORDER BY RANDOM() LIMIT 6")->fetchAll();
} else {
    $similar = $db->query("SELECT id, name, price, old_price, main_image as img FROM products WHERE is_active = 1 ORDER BY RANDOM() LIMIT 6")->fetchAll();
}

if (!$bestMatch && empty($similar)) {
    echo json_encode([
        'success' => false,
        'message' => 'Could not find any products to display.'
    ]);
    exit;
}

// Ensure the paths for images are correct, using a placeholder if needed.
$placeholder = 'https://images.unsplash.com/photo-1585487000160-6ebcfceb0d03?w=540&h=960&fit=crop&q=80';

if ($bestMatch && empty($bestMatch['img'])) {
    $bestMatch['img'] = $placeholder;
}
foreach ($similar as &$p) {
    if (empty($p['img'])) {
        $p['img'] = $placeholder;
    }
}
unset($p);

echo json_encode([
    'success' => true,
    'tags' => $mockTags,
    'exactMatch' => $bestMatch,
    'similar' => $similar
]);
