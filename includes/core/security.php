<?php

function generateCSRF() {
    if (empty($_SESSION['csrf_token']) || time() > ($_SESSION['csrf_token_time'] ?? 0) + CSRF_TOKEN_LIFETIME) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize(mixed $data): mixed {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function clean(mixed $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
