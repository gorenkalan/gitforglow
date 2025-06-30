<?php
// This script is called by admin/index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
use App\GoogleSheetsService;
use App\Config;

function build_products_cache() {
    $sheetsService = new GoogleSheetsService();
    // Fetch raw data from both sheets
    $productsData = $sheetsService->getSheetData(Config::get('GOOGLE_SHEET_NAME_PRODUCTS'));
    $inventoryData = $sheetsService->getSheetData(Config::get('GOOGLE_SHEET_NAME_INVENTORY'));

    // Create a lookup map for inventory items for fast access
    $inventoryByProductId = [];
    foreach ($inventoryData as $item) {
        $productId = $item['productId'];
        if (!isset($inventoryByProductId[$productId])) {
            $inventoryByProductId[$productId] = [];
        }
        $inventoryByProductId[$productId][] = [
            'variationId' => $item['variationId'],
            'colorName' => $item['colorName'],
            'colorHex' => $item['colorHex'],
            'imageUrl' => $item['imageUrl'],
        ];
    }

    $combinedProducts = [];
    // Loop through ALL products from the main products sheet
    foreach ($productsData as $product) {
        $productId = $product['id'];
        
        // --- THIS IS THE CORRECTED LOGIC ---
        // Get the variations for this product. If none exist, default to an empty array.
        // This ensures every product from the 'products' sheet is processed.
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
            'variations' => $variations, // This will be an empty array [] if no variations were found
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

// The success message is handled by admin/index.php after including this file.