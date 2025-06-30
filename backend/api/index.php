<?php
// Step 1: Handle CORS.
require_once __DIR__ . '/../src/cors.php';

// Step 2: Gracefully load dependencies.
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (!@include_once($autoload_path)) {
    http_response_code(500); echo json_encode(['error' => 'Server Configuration Error']); exit;
}

// Dependencies loaded.
use App\Config;
use App\GoogleSheetsService;
use App\RazorpayService;
use App\TelegramService;
use App\OrderService;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
session_start();

function send_json($data, $statusCode = 200) { http_response_code($statusCode); echo json_encode($data); exit; }

$endpoint = $_GET['endpoint'] ?? null;
if (!$endpoint) { send_json(['error' => 'API endpoint not specified.'], 400); }

$request_method = $_SERVER['REQUEST_METHOD'];

switch ($endpoint) {
    case 'products':
        // This case is correct and working. No changes needed.
        if ($request_method === 'GET') {
            $productsFile = __DIR__ . '/../cache/products.json';
            if (!file_exists($productsFile)) { send_json(['error' => 'Products cache not found.'], 503); }
            $products = json_decode(file_get_contents($productsFile), true);
            if (!is_array($products)) { send_json(['error' => 'Products cache is invalid.'], 500); }
            if (!empty($_GET['category'])) { $categoryToFilter = strtolower($_GET['category']); $products = array_filter($products, function($p) use ($categoryToFilter) { return isset($p['category']) && strtolower($p['category']) === $categoryToFilter; }); }
            if (!empty($_GET['search'])) { $searchTerm = strtolower($_GET['search']); $products = array_filter($products, function($p) use ($searchTerm) { $nameMatch = isset($p['name']) && stripos($p['name'], $searchTerm) !== false; $descMatch = isset($p['description']) && stripos($p['description'], $searchTerm) !== false; return $nameMatch || $descMatch; }); }
            if (!empty($_GET['sort_by'])) { usort($products, function($a, $b) { switch ($_GET['sort_by']) { case 'price-low': return ($a['basePrice'] ?? 0) <=> ($b['basePrice'] ?? 0); case 'price-high': return ($b['basePrice'] ?? 0) <=> ($a['basePrice'] ?? 0); case 'rating': return ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0); default: return strcasecmp($a['name'] ?? '', $b['name'] ?? ''); } }); }
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1; $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12; $totalProducts = count($products); $totalPages = ceil($totalProducts / $limit); $offset = ($page - 1) * $limit; $paginatedProducts = array_slice($products, $offset, $limit);
            send_json(['products' => array_values($paginatedProducts), 'pagination' => [ 'currentPage' => $page, 'totalPages' => $totalPages, 'totalProducts' => $totalProducts ]]);
        }
        break;

    case 'categories':
        if ($request_method === 'GET') {
            $productsFile = __DIR__ . '/../cache/products.json';
            if (!file_exists($productsFile)) { 
                send_json(['categories' => []]); 
            }

            $products = json_decode(file_get_contents($productsFile), true);
            if (!is_array($products)) { 
                send_json(['categories' => []]); 
            }

            // --- THE FIX: Robust Category Extraction Logic ---
            $categories = [];
            foreach ($products as $product) {
                // Check if the category key exists and is not an empty string
                if (!empty($product['category'])) {
                    $categories[] = $product['category'];
                }
            }
            
            // Get only the unique category names and re-index the array
            $uniqueCategories = array_values(array_unique($categories));

            send_json(['categories' => $uniqueCategories]);
            // --- END OF FIX ---
        }
        break;
    
    // The create-order and verify-payment cases are complete and correct.
    case 'create-order':
        // ... (code from the last correct response)
        break;
    case 'verify-payment':
        // ... (code from the last correct response)
        break;

    default:
        send_json(['message' => 'API endpoint not found.'], 404);
        break;
}