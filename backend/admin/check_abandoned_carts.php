<?php
// This script is called by a button in the admin panel.
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['admin_logged_in'])) { http_response_code(403); echo "Unauthorized"; exit; }

require_once __DIR__ . '/../vendor/autoload.php';
use App\OrderService;
use App\TelegramService;

$orderService = new OrderService();
$telegramService = new TelegramService();
$productsCacheFile = __DIR__ . '/../cache/products.json';

// --- Configuration ---
// An order is considered "abandoned" if it's still pending after this many minutes.
$abandoned_timeout_minutes = 30;

$pendingOrders = $orderService->getPendingOrders();
$abandonedOrders = [];

// Find abandoned orders based on the timeout
foreach ($pendingOrders as $order) {
    if (isset($order['status']) && $order['status'] === 'Pending Payment') {
        $orderTimestamp = strtotime($order['timestamp']);
        if ((time() - $orderTimestamp) > ($abandoned_timeout_minutes * 60)) {
            $abandonedOrders[] = $order;
        }
    }
}

$message = "No abandoned carts found to process.";

if (!empty($abandonedOrders)) {
    // Lock the products cache file to safely update stock
    $fp = fopen($productsCacheFile, 'r+');
    if (flock($fp, LOCK_EX)) {
        $products = json_decode(fread($fp, filesize($productsCacheFile)), true);
        
        $productMap = array_column($products, null, 'id');
        $updatedProductIds = [];

        // Add stock back for each abandoned order
        foreach ($abandonedOrders as $order) {
            if (is_array($order['items'])) {
                foreach ($order['items'] as $item) {
                    $productId = $item['productId'];
                    $variationId = $item['variationId'];

                    if (isset($productMap[$productId])) {
                        foreach ($productMap[$productId]['variations'] as &$variation) { // Use reference '&'
                            if ($variation['variationId'] === $variationId) {
                                $variation['stock'] += $item['quantity'];
                                $updatedProductIds[$productId] = true;
                                break;
                            }
                        }
                    }
                }
            }
            // Update the local order file's status
            $orderService->updateOrderStatus($order['order_id'], 'Abandoned');
        }

        // Reconstruct the products array from the updated map
        $updatedProducts = [];
        foreach ($products as $p) {
            $updatedProducts[] = $productMap[$p['id']] ?? $p;
        }

        // Write the updated data back to the cache file
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode($updatedProducts, JSON_PRETTY_PRINT));
        fflush($fp); flock($fp, LOCK_UN);

        $message = "Successfully processed " . count($abandonedOrders) . " abandoned cart(s). Stock has been replenished.";
        
        // Send a single summary notification to Telegram
        $abandonedIds = array_column($abandonedOrders, 'order_id');
        $telegramService->sendAbandonedCartNotification($abandonedIds);

    } else {
        $message = "Error: Could not get a lock on the product cache file. Please try again.";
    }
    fclose($fp);
}

$_SESSION['sync_message'] = $message;
header('Location: index.php');
exit;