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
 * and 'inventory' sheets in Google Sheets.
 */
function build_products_cache() {
    $sheetsService = new GoogleSheetsService();
    
    // Fetch raw data from both sheets
    $productsData = $sheetsService->getSheetData(Config::get('GOOGLE_SHEET_NAME_PRODUCTS'));
    $inventoryData = $sheetsService->getSheetData(Config::get('GOOGLE_SHEET_NAME_INVENTORY'));

    // Create a lookup map for inventory items for fast access
    $inventoryByProductId = [];
    foreach ($inventoryData as $item) {
        $productId = $item['productId'] ?? null;
        if (!$productId) continue; // Skip inventory items with no productId

        if (!isset($inventoryByProductId[$productId])) {
            $inventoryByProductId[$productId] = [];
        }

        $inventoryByProductId[$productId][] = [
            'variationId' => $item['variationId'] ?? null,
            'colorName' => $item['colorName'] ?? 'Default',
            'colorHex' => $item['colorHex'] ?? '#FFFFFF',
            'imageUrl' => $item['imageUrl'] ?? '',
        ];
    }

    $combinedProducts = [];
    // Loop through ALL products from the main products sheet
    foreach ($productsData as $product) {
        $productId = $product['id'] ?? null;
        if (!$productId) continue; // Skip products with no ID

        // Get the variations for this product. If none exist, default to an empty array.
        $variations = $inventoryByProductId[$productId] ?? [];
        
        // Add the product to our cache array
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
    
    // Write the complete, combined list to the cache file
    $cacheDir = __DIR__ . '/../cache/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }
    file_put_contents($cacheDir . 'products.json', json_encode($combinedProducts, JSON_PRETTY_PRINT));
}

// Execute the cache build
build_products_cache();

// Set a success message in the session and redirect back to the admin dashboard
$_SESSION['cache_message'] = "Product cache has been successfully refreshed from Google Sheets.";
header('Location: index.php');
exit;