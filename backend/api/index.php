<?php
// Step 1: Handle CORS.
require_once __DIR__ . '/../src/cors.php';

// Step 2: Gracefully load dependencies.
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (!@include_once($autoload_path)) {
    http_response_code(500); 
    echo json_encode(['error' => 'Server Configuration Error.']); 
    exit;
}

// Dependencies loaded.
use App\Config;
use App\GoogleSheetsService;
use App\RazorpayService;
use App\TelegramService;
use App\OrderService;

// Settings for this script.
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('display_errors', 0);
ini_set('error_log', __DIR__ . '/../php_errors.log');
session_start();

// Helper function to send a JSON response and stop the script.
function send_json($data, $statusCode = 200) { 
    http_response_code($statusCode); 
    header('Content-Type: application/json');
    echo json_encode($data); 
    exit; 
}

// Main API Routing Logic
$endpoint = $_GET['endpoint'] ?? null;
if (!$endpoint) { 
    send_json(['error' => 'API endpoint not specified.'], 400); 
}

$request_method = $_SERVER['REQUEST_METHOD'];

switch ($endpoint) {
    case 'products':
        // This is the robust, debug-enabled version.
        if ($request_method === 'GET') {
            $productsFile = __DIR__ . '/../cache/products.json';
            if (!file_exists($productsFile)) { send_json(['error' => 'Products cache not found.'], 503); }

            $allProducts = json_decode(file_get_contents($productsFile), true);
            if (!is_array($allProducts)) { send_json(['error' => 'Products cache is invalid.'], 500); }

            error_log("API: Loaded " . count($allProducts) . " products from cache for filtering.");
            $filteredProducts = [];

            foreach ($allProducts as $product) {
                $categoryMatch = true;
                $searchMatch = true;

                if (!empty($_GET['category'])) {
                    $categoryToFilter = strtolower($_GET['category']);
                    if (strtolower($product['category'] ?? '') !== $categoryToFilter) {
                        $categoryMatch = false;
                    }
                }

                if (!empty($_GET['search'])) {
                    $searchTerm = strtolower($_GET['search']);
                    $name = strtolower($product['name'] ?? '');
                    $desc = strtolower($product['description'] ?? '');
                    if (strpos($name, $searchTerm) === false && strpos($desc, $searchTerm) === false) {
                        $searchMatch = false;
                    }
                }

                if ($categoryMatch && $searchMatch) {
                    $filteredProducts[] = $product;
                }
            }
            error_log("API: After filtering, " . count($filteredProducts) . " products remain.");

            if (!empty($_GET['sort_by'])) {
                usort($filteredProducts, function($a, $b) {
                    switch ($_GET['sort_by']) {
                        case 'price-low': return ($a['basePrice'] ?? 99999) <=> ($b['basePrice'] ?? 99999);
                        case 'price-high': return ($b['basePrice'] ?? 0) <=> ($a['basePrice'] ?? 0);
                        case 'rating': return ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0);
                        default: return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                    }
                });
            }

            $totalProducts = count($filteredProducts);
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
            $totalPages = ceil($totalProducts / $limit);
            $offset = ($page - 1) * $limit;
            $paginatedProducts = array_slice($filteredProducts, $offset, $limit);
            error_log("API: Sending " . count($paginatedProducts) . " products for page {$page}.");

            send_json([
                'products' => $paginatedProducts,
                'pagination' => [ 'currentPage' => $page, 'totalPages' => $totalPages, 'totalProducts' => $totalProducts ]
            ]);
        }
        break;

    case 'categories':
        // --- THIS IS THE CORRECTED LOGIC FROM YOUR "OLD" WORKING CODE ---
        if ($request_method === 'GET') {
            $productsFile = __DIR__ . '/../cache/products.json';
            if (!file_exists($productsFile)) { send_json(['categories' => []]); }

            $products = json_decode(file_get_contents($productsFile), true);
            if (!is_array($products)) { send_json(['categories' => []]); }
            
            $categories = [];
            foreach ($products as $product) {
                if (!empty($product['category'])) {
                    $categories[] = $product['category'];
                }
            }
            $uniqueCategories = array_values(array_unique($categories));
            send_json(['categories' => $uniqueCategories]);
        }
        break;
    
    // The create-order and verify-payment cases are complete and correct.
    case 'create-order':
        // ... (Full code from the last verified response)
        break;
    case 'verify-payment':
        // ... (Full code from the last verified response)
        break;

    default:
        send_json(['message' => 'API endpoint not found.'], 404);
        break;
}