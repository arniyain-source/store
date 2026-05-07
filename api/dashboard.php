<?php
/**
 * API: Get Dashboard Stats
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../includes/functions.php';

try {
    $stats = getDashboardStats();
    $recentOrders = getRecentOrders(5);
    $salesData = getSalesChartData(30);
    $topProducts = getTopSellingProducts(5);
    
    jsonResponse([
        'success' => true,
        'stats' => $stats,
        'recent_orders' => $recentOrders,
        'sales_data' => $salesData,
        'top_products' => $topProducts
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error loading dashboard data'], 500);
}
