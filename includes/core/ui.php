<?php

function renderPaginationControls(string $baseUrl, int $currentPage, int $totalItems, int $perPage, array $queryParams = []): void
{
    $totalPages = ceil($totalItems / $perPage);
    if ($totalPages <= 1) {
        return;
    }

    echo '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';

    $queryString = http_build_query($queryParams);

    // Previous Button
    $prevPage = $currentPage - 1;
    $prevDisabled = ($currentPage <= 1) ? "disabled" : "";
    echo "<li class=\"page-item {$prevDisabled}\"><a class=\"page-link\" href=\"?page={$prevPage}&{$queryString}\">«</a></li>";

    // Page Numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = ($i == $currentPage) ? "active" : "";
        echo "<li class=\"page-item {$active}\"><a class=\"page-link\" href=\"?page={$i}&{$queryString}\">{$i}</a></li>";
    }

    // Next Button
    $nextPage = $currentPage + 1;
    $nextDisabled = ($currentPage >= $totalPages) ? "disabled" : "";
    echo "<li class=\"page-item {$nextDisabled}\"><a class=\"page-link\" href=\"?page={$nextPage}&{$queryString}\">»</a></li>";

    echo '</ul></nav>';
}

function buildQueryParams(array $newParams): string {
    $params = array_merge($_GET, $newParams);
    return http_build_query($params);
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function getStatusBadge(string $status): string {
    $badges = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info',
        'processing' => 'badge-primary',
        'shipped' => 'badge-purple',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger',
        'returned' => 'badge-dark',
    ];
    return $badges[$status] ?? 'badge-secondary';
}
