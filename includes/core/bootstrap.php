<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

define('CSRF_TOKEN_LIFETIME', 3600);
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 20MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('UPLOAD_PATH', __DIR__ . '/../../uploads/');
define('ADMIN_PER_PAGE', 10);
define('CURRENCY_SYMBOL', '₹');
