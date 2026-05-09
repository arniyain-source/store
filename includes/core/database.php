<?php

function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            // Database connection logic from your provided code
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    return $db;
}

function paginate(string $query, array $params, int $page = 1, int $perPage = ADMIN_PER_PAGE): array {
    // Pagination data logic
}

function logActivity(string $action, ?string $entityType = null, int|string|null $entityId = null, ?array $details = null): void {
    // Activity logging logic
}

function getSetting(string $key, mixed $default = null): mixed {
    // Get setting logic
}

function updateSetting(string $key, mixed $value): bool {
    // Update setting logic
}
