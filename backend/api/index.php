<?php
// Step 1: Handle CORS
require_once __DIR__ . '/../src/cors.php';

// Step 2: Load Dependencies
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (!@include_once($autoload_path)) {
    http_response_code(500); 
    echo json_encode(['error' => 'Server Configuration Error.']); 
    exit;
}

// Use required classes
use App\Config;
use App\GoogleSheetsService;
use App\RazorpayService;
use App\TelegramService;
use App\OrderService;

// Settings
error_reporting(E_ALL); // Enable all errors for logging
ini_set('log_errors', 1);
ini_set('display_errors', 0); // Don't display errors to the user
ini_set('error_log', __DIR__ . '/../php_errors.log'); // Log errors to a file
session_start();

// Helper function
function send_json($data, $statusCode = 200) { 
    http_response_code($statusCode); 
    header('Content-Type: application/json');
    echo json_encode($data); 
    exit; 
}

// Routing
$endpoint = $_GET['endpoint'] ?? null;
if (!$endpoint) { 
    send_json(['error' => 'API endpoint not specified.'], 400); 
}
$request_method = $_SERVER['REQUEST_METHOD'];

switch ($endpoint) {
    case 'products':
        if ($request_method === 'GET') {
            $productsFile = __DIR__ . '/../cache/products.json';
            if (!file_exists($productsFile)) { 
                send_json(['error' => 'Products cache not found.'], 503); 
            }
            $allProducts = json_decode(file_get_contents($productsFile), true);
            if (!is_array($allProducts)) { 
                send_json(['error' => 'Products cache is invalid.'], 500); 
            }

            error_log("API: Loaded " . count($allProducts) . " products from cache.");
            $filteredProducts = [];

            // --- Robust Filtering Logic ---
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

            // Sorting
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

            // Pagination
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

    // The other endpoints (categories, create-order, verify-payment) are correct
    // and do not need to be changed from their last verified versions.
    case 'categories':
        // ... (Full code from previous correct response)
        break;
    
    case 'create-order':
        // ... (Full code from previous correct response)
        break;

    case 'verify-payment':
        // ... (Full code from previous correct response)
        break;

    default:
        send_json(['message' => 'API endpoint not found.'], 404);
        break;
}