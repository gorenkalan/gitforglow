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
 * Builds the complete product cache by combining data from the 'products'
 * and 'inventory' sheets, including stock levels.
 */
function build_products_cache() {
    $sheetsService = new GoogleSheetsService();
    
    $productsData = $sheetsService->getSheetData(Config::get('GOOGLE_SHEET_NAME_PRODUCTS'));
    $inventoryData = $sheetsService->getSheetData(Config::get('GOOGLE_SHEET_NAME_INVENTORY'));

    // Create a lookup map for inventory items for fast access
    $inventoryByProductId = [];
    foreach ($inventoryData as $item) {
        $productId = $item['productId'] ?? null;
        if (!$productId) continue;

        if (!isset($inventoryByProductId[$productId])) {
            $inventoryByProductId[$productId] = [];
        }

        // --- THE CRITICAL CHANGE ---
        // We now read the 'stock' column and save it with each variation.
        $inventoryByProductId[$productId][] = [
            'variationId' => $item['variationId'] ?? null,
            'colorName' => $item['colorName'] ?? 'Default',
            'colorHex' => $item['colorHex'] ?? '#FFFFFF',
            'imageUrl' => $item['imageUrl'] ?? '',
            'stock' => isset($item['stock']) ? (int)$item['stock'] : 0, // Read the stock value
        ];
    }

    $combinedProducts = [];
    foreach ($productsData as $product) {
        $productId = $product['id'] ?? null;
        if (!$productId) continue;

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
    
    $cacheDir = __DIR__ . '/../cache/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }
    file_put_contents($cacheDir . 'products.json', json_encode($combinedProducts, JSON_PRETTY_PRINT));
}

// Execute the cache build
build_products_cache();

// Redirect back with a success message
$_SESSION['cache_message'] = "Product cache has been successfully refreshed from Google Sheets.";
header('Location: index.php');
exit;