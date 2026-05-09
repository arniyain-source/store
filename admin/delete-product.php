<?php
require_once __DIR__ . '/../includes/core/app.php';
requireAdminLogin();

if (isset($_GET['id'])) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$_GET['id']]);
}

redirect('products.php');
