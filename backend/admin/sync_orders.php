<?php
// This script is called by admin/index.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_logged_in'])) { http_response_code(403); echo "Unauthorized"; exit; }

require_once __DIR__ . '/../vendor/autoload.php';
use App\OrderService;
use App\GoogleSheetsService;

$orderService = new OrderService();
$sheetsService = new GoogleSheetsService();

$pendingOrders = $orderService->getPendingOrders();
$ordersToSync = array_filter($pendingOrders, fn($order) => $order['status'] === 'Paid');

$message = "No new paid orders to sync.";

if (!empty($ordersToSync)) {
    $result = $sheetsService->writeOrdersBatch($ordersToSync);
    
    if ($result['success']) {
        $count = $result['updated_rows'];
        // Move synced files to the completed directory
        foreach ($ordersToSync as $order) {
            $orderService->moveOrderToCompleted($order['order_id']);
        }
        $message = "Successfully synced {$count} new paid orders to Google Sheets.";
    } else {
        $message = "Error syncing orders: " . $result['error'];
    }
}

// Store the message in the session to display it on the admin page
$_SESSION['sync_message'] = $message;

// Redirect back to the admin dashboard
header('Location: index.php');
exit;