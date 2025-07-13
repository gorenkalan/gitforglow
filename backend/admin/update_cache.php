<?php
// Ensure the user is logged in as an admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

// Load dependencies
require_once __DIR__ . '/../vendor/autoload.php';
use App\GoogleSheetsService;
use App\Config;

/**
 * Builds the complete product cache with robust data handling and debugging.
 */
function build_products_cache() {
    // For debugging, create a log file to trace the process
    $log_file = __DIR__ . '/cache_builder.log';
    file_put_contents($log_file, "--- Cache Build Started at " . date('Y-m-d H:i:s') . " ---\n");

    $sheetsService = new GoogleSheetsService();
    
    $productsSheetName = Config::get('GOOGLE_SHEET_NAME_PRODUCTS');
    $inventorySheetName = Config::get('GOOGLE_SHEET_NAME_INVENTORY');

    $productsData = $sheetsService->getSheetData($productsSheetName);
    $inventoryData = $sheetsService->getSheetData($inventorySheetName);

    file_put_contents($log_file, "Fetched " . count($productsData) . " rows from '{$productsSheetName}'.\n", FILE_APPEND);
    file_put_contents($log_file, "Fetched " . count($inventoryData) . " rows from '{$inventorySheetName}'.\n", FILE_APPEND);

    // Create a lookup map for inventory items.
    $inventoryByProductId = [];
    foreach ($inventoryData as $item) {
        $productId = (string)($item['productId'] ?? '');
        if (empty($productId)) continue;

        if (!isset($inventoryByProductId[$productId])) {
            $inventoryByProductId[$productId] = [];
        }

        $inventoryByProductId[$productId][] = [
            'variationId' => $item['variationId'] ?? null,
            'colorName' => $item['colorName'] ?? 'Default',
            'colorHex' => $item['colorHex'] ?? '#FFFFFF',
            'imageUrl' => $item['imageUrl'] ?? '',
            'stock' => isset($item['stock']) ? (int)$item['stock'] : 0,
        ];
    }
    file_put_contents($log_file, "Created inventory map for " . count($inventoryByProductId) . " unique product IDs.\n", FILE_APPEND);

    $combinedProducts = [];
    $products_processed = 0;
    foreach ($productsData as $product) {
        $productId = (string)($product['id'] ?? '');
        if (empty($productId)) continue;
        
        $products_processed++;
        $variations = $inventoryByProductId[$productId] ?? [];
        
        $combinedProducts[] = [
            'id' => $productId,
            'name' => $product['name'] ?? 'No Name',
            'basePrice' => isset($product['basePrice']) ? (float)$product['basePrice'] : 0,
            'description' => $product['description'] ?? '',
            'category' => $product['category'] ?? 'Uncategorized',
            'tags' => isset($product['tags']) ? array_map('trim', explode(',', $product['tags'])) : [],
            'rating' => isset($product['rating']) ? (float)$product['rating'] : 0,
            'reviews' => isset($product['reviews']) ? (int)$product['reviews'] : 0,
            'variations' => $variations,
        ];
    }
    
    file_put_contents($log_file, "Processed {$products_processed} products.\n", FILE_APPEND);
    file_put_contents($log_file, "Total products being written to cache: " . count($combinedProducts) . "\n", FILE_APPEND);

    $cacheDir = __DIR__ . '/../cache/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }
    file_put_contents($cacheDir . 'products.json', json_encode($combinedProducts, JSON_PRETTY_PRINT));
    file_put_contents($log_file, "--- Cache Build Finished ---\n", FILE_APPEND);
}

build_products_cache();

$_SESSION['cache_message'] = "Product cache has been successfully refreshed. Please check 'backend/admin/cache_builder.log' for details.";
header('Location: index.php');
exit;