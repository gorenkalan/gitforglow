<?php
// Step 1: Handle CORS. This is the absolute first thing we do.
require_once __DIR__ . '/../src/cors.php';

// Step 2: Gracefully load all dependencies.
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (!@include_once($autoload_path)) {
    http_response_code(500); 
    echo json_encode(['error' => 'Server Configuration Error: Dependencies are missing. Please run "composer install".']); 
    exit;
}

// Dependencies loaded. Now we can use our classes.
use App\Config;
use App\GoogleSheetsService;
use App\RazorpayService;
use App\TelegramService;
use App\OrderService;

// Settings for this script.
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
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
    send_json(['error' => 'API endpoint not specified. Use ?endpoint=...'], 400); 
}

$request_method = $_SERVER['REQUEST_METHOD'];

switch ($endpoint) {
    case 'products':
        // This block is from your working version and is correct.
        if ($request_method === 'GET') {
            $productsFile = __DIR__ . '/../cache/products.json';
            if (!file_exists($productsFile)) { 
                send_json(['error' => 'Products cache not found. Please use the admin panel to refresh it.'], 503); 
            }
            $allProducts = json_decode(file_get_contents($productsFile), true);
            if (!is_array($allProducts)) { 
                send_json(['error' => 'Products cache file is invalid or corrupted.'], 500); 
            }
            $filteredProducts = $allProducts;
            if (!empty($_GET['category'])) {
                $categoryToFilter = strtolower($_GET['category']);
                $filteredProducts = array_filter($filteredProducts, function($p) use ($categoryToFilter) {
                    return isset($p['category']) && strtolower($p['category']) === $categoryToFilter;
                });
            }
            if (!empty($_GET['search'])) {
                $searchTerm = strtolower($_GET['search']);
                $filteredProducts = array_filter($filteredProducts, function($p) use ($searchTerm) {
                    $nameMatch = isset($p['name']) && stripos($p['name'], $searchTerm) !== false;
                    $descMatch = isset($p['description']) && stripos($p['description'], $searchTerm) !== false;
                    return $nameMatch || $descMatch;
                });
            }
            if (!empty($_GET['sort_by'])) {
                usort($filteredProducts, function($a, $b) {
                    switch ($_GET['sort_by']) {
                        case 'price-low': return ($a['basePrice'] ?? 0) <=> ($b['basePrice'] ?? 0);
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
            send_json([
                'products' => array_values($paginatedProducts),
                'pagination' => [ 'currentPage' => $page, 'totalPages' => $totalPages, 'totalProducts' => $totalProducts ]
            ]);
        }
        break;

    case 'categories':
        // This block is from your working version and is correct.
        if ($request_method === 'GET') {
            $productsFile = __DIR__ . '/../cache/products.json';
            if (!file_exists($productsFile)) { send_json(['categories' => []]); }
            $products = json_decode(file_get_contents($productsFile), true);
            if (!is_array($products)) { send_json(['categories' => []]); }
            $categories = [];
            foreach ($products as $product) { if (!empty($product['category'])) { $categories[] = $product['category']; } }
            $uniqueCategories = array_values(array_unique($categories));
            send_json(['categories' => $uniqueCategories]);
        }
        break;
    
    case 'create-order':
        if ($request_method === 'POST') {
            $telegramService = new TelegramService();
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true);

            // --- THE FINAL FIX: VALIDATION ORDER ---

            // 1. Check if the JSON is valid before trying to access its keys.
            if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
                $telegramService->sendCriticalError("Checkout Failed: Malformed JSON", ['raw_payload' => $rawInput]);
                send_json(['error' => 'There was a problem with the data sent. Please try again.'], 400);
            }

            // 2. Now that we know $input is an array, we can safely run spam checks.
            if (!empty($input['field_9a3b'])) { // The correct honeypot field name
                error_log("Spam detected: Honeypot filled.");
                send_json(['error' => 'Automated submission detected. Please disable autofill and try again.'], 400);
            }
            $minTimeSeconds = 3;
            if (empty($input['form_timestamp']) || (time() - ($input['form_timestamp'] / 1000)) < $minTimeSeconds) {
                error_log("Spam detected: Form submitted too quickly.");
                send_json(['error' => 'Invalid request. Please try again.'], 400);
            }
            $timeBetweenSubmissions = 60;
            if (isset($_SESSION['last_submission_time']) && (time() - $_SESSION['last_submission_time']) < $timeBetweenSubmissions) {
                send_json(['error' => 'You are submitting too frequently. Please wait a moment.'], 429);
            }
            
            // 3. Now validate the contents of the valid, non-spam payload.
            if (empty($input['total_in_paise']) || !isset($input['items']) || !is_array($input['items'])) {
                send_json(['error' => 'Invalid input. Required fields are missing.'], 400);
            }
            
            // All checks passed, proceed with the order logic.
            $sheetsService = new GoogleSheetsService();
            $stockAvailable = $sheetsService->verifyAndUpdateStock($input['items']);
            if (!$stockAvailable) {
                $telegramService->sendCriticalError("Stock Verification Failed", ['customer' => $input['customer_info']['name'] ?? 'N/A', 'items' => json_encode($input['items'])]);
                send_json(['error' => 'An item in your cart is now out of stock.'], 409); 
            }

            $orderService = new OrderService();
            $internalOrderId = 'GLOW-' . time();
            $orderDataForFile = ['order_id' => $internalOrderId, 'customer_info' => $input['customer_info'], 'items' => $input['items'], 'total' => $input['total_display']];
            $orderService->createPendingOrder($orderDataForFile);
            $telegramService->sendNewOrderAttempt($orderDataForFile);

            $razorpayService = new RazorpayService();
            $razorpayOrder = $razorpayService->createOrder($input['total_in_paise'], $internalOrderId);
            if (isset($razorpayOrder['error'])) {
                 $telegramService->sendCriticalError("Razorpay Order Creation Failed", ['customer' => $input['customer_info']['name'], 'error' => $razorpayOrder['error']]);
                 send_json(['error' => 'Failed to create payment order. Please try again.', 'details' => $razorpayOrder['error']], 500);
            }
            
            $_SESSION['last_submission_time'] = time();
            send_json([
                'razorpay_order_id' => $razorpayOrder['id'],
                'internal_order_id' => $internalOrderId,
                'razorpay_key_id' => $razorpayService->getKeyId(),
                'amount' => $razorpayOrder['amount'],
                'currency' => $razorpayOrder['currency']
            ]);
        }
        break;

    case 'verify-payment':
        // This block is correct and remains unchanged.
        if ($request_method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['razorpay_payment_id']) || empty($input['razorpay_order_id']) || empty($input['internal_order_id'])) { 
                send_json(['error' => 'Verification data missing'], 400); 
            }
            
            $razorpayService = new RazorpayService();
            $isSignatureValid = $razorpayService->verifySignature($input);
            
            $internalOrderId = $input['internal_order_id'];
            $paymentId = $input['razorpay_payment_id'];
            $orderService = new OrderService();
            $telegramService = new TelegramService();

            if ($isSignatureValid) {
                $orderService->updateOrderToPaid($internalOrderId, $paymentId);
                $telegramService->sendOrderPaidConfirmation($internalOrderId, $paymentId);
                send_json(['status' => 'success', 'orderId' => $internalOrderId]);
            } else {
                $orderService->updateOrderToFailed($internalOrderId);
                $telegramService->sendCriticalError("Razorpay Signature Verification Failed", ['internal_order_id' => $internalOrderId, 'razorpay_order_id' => $input['razorpay_order_id']]);
                send_json(['error' => 'Invalid payment signature'], 400);
            }
        }
        break;

    default:
        send_json(['message' => 'API endpoint not found.'], 404);
        break;
}