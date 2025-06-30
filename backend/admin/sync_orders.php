<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_logged_in'])) { http_response_code(403); echo "Unauthorized"; exit; }

require_once __DIR__ . '/../vendor/autoload.php';
use App\OrderService;
use App\GoogleSheetsService;

$orderService = new OrderService();
$sheetsService = new GoogleSheetsService();

$pendingOrders = $orderService->getPendingOrders();
// We only want to sync orders that have a status of 'Paid'
$ordersToSync = array_filter($pendingOrders, fn($order) => isset($order['status']) && $order['status'] === 'Paid');

$message = "No new paid orders to sync.";

if (!empty($ordersToSync)) {
    $result = $sheetsService->writeOrdersBatch($ordersToSync);
    
    if (isset($result['success']) && $result['success']) {
        $count = $result['updated_rows'] ?? count($ordersToSync);
        // Move synced files to the completed directory
        foreach ($ordersToSync as $order) {
            $orderService->moveOrderToCompleted($order['order_id']);
        }
        $message = "Successfully synced {$count} new paid order(s) to Google Sheets.";
    } else {
        $message = "Error syncing orders: " . ($result['error'] ?? 'Unknown error');
    }
}

// Store the message in the session to display it on the admin page
$_SESSION['sync_message'] = $message;

// Redirect back to the admin dashboard
header('Location: index.php');
exit;