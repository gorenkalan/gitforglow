<?php
// Step 1: Set up basic error reporting and handle CORS.
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once __DIR__ . '/../src/cors.php';

// Step 2: NOW we load the autoloader. This is the critical change.
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (!@include_once($autoload_path)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server Configuration Error: The backend dependencies are missing.']);
    exit;
}

// Step 3: Now that the autoloader is loaded, we can safely use all our classes.
use App\Config;
use App\GoogleSheetsService;
use App\RazorpayService;
use App\TelegramService;

session_start();

// The rest of the file is IDENTICAL to the last complete version.
// The code from here down is correct.

function send_json($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

$endpoint = $_GET['endpoint'] ?? null;
if (!$endpoint) { send_json(['error' => 'API endpoint not specified.'], 400); }

$request_method = $_SERVER['REQUEST_METHOD'];

switch ($endpoint) {
    case 'products':
        if ($request_method === 'GET') {
            $productsFile = __DIR__ . '/../cache/products.json';
            if (!file_exists($productsFile)) { send_json(['error' => 'Products cache not found.'], 503); }
            $products = json_decode(file_get_contents($productsFile), true);
            if (!is_array($products)) { send_json(['error' => 'Products cache is invalid.'], 500); }
            if (!empty($_GET['category'])) { $categoryToFilter = strtolower($_GET['category']); $products = array_filter($products, function($p) use ($categoryToFilter) { return isset($p['category']) && strtolower($p['category']) === $categoryToFilter; }); }
            if (!empty($_GET['search'])) { $searchTerm = strtolower($_GET['search']); $products = array_filter($products, function($p) use ($searchTerm) { $nameMatch = isset($p['name']) && str_contains(strtolower($p['name']), $searchTerm); $descMatch = isset($p['description']) && str_contains(strtolower($p['description']), $searchTerm); return $nameMatch || $descMatch; }); }
            if (!empty($_GET['sort_by'])) { usort($products, function($a, $b) { switch ($_GET['sort_by']) { case 'price-low': return ($a['basePrice'] ?? 0) <=> ($b['basePrice'] ?? 0); case 'price-high': return ($b['basePrice'] ?? 0) <=> ($a['basePrice'] ?? 0); case 'rating': return ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0); default: return strcasecmp($a['name'] ?? '', $b['name'] ?? ''); } }); }
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
            $totalProducts = count($products);
            $totalPages = ceil($totalProducts / $limit);
            $offset = ($page - 1) * $limit;
            $paginatedProducts = array_slice($products, $offset, $limit);
            send_json(['products' => array_values($paginatedProducts), 'pagination' => [ 'currentPage' => $page, 'totalPages' => $totalPages, 'totalProducts' => $totalProducts ]]);
        }
        break;

    case 'categories':
        if ($request_method === 'GET') {
            $productsFile = __DIR__ . '/../cache/products.json';
            if (!file_exists($productsFile)) { send_json(['categories' => []]); }
            $products = json_decode(file_get_contents($productsFile), true);
            if (!is_array($products)) { send_json(['categories' => []]); }
            $categories = array_unique(array_column($products, 'category'));
            send_json(['categories' => array_values(array_filter($categories))]);
        }
        break;
    
    case 'create-order':
        if ($request_method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!empty($input['hp_email'])) { error_log("Spam detected: Honeypot"); send_json(['status' => 'success']); }
            $minTimeSeconds = 3; $formTimestamp = $input['form_timestamp'] ?? 0;
            if (empty($formTimestamp)) { error_log("Spam detected: Timestamp missing."); send_json(['status' => 'success']); }
            $submissionTime = (int)($formTimestamp / 1000); $timeElapsed = time() - $submissionTime;
            if ($timeElapsed < $minTimeSeconds) { error_log("Spam detected: Form submitted too quickly ($timeElapsed seconds)."); send_json(['status' => 'success']); }
            $timeBetweenSubmissions = 60;
            if (isset($_SESSION['last_submission_time']) && (time() - $_SESSION['last_submission_time']) < $timeBetweenSubmissions) { send_json(['error' => 'You are submitting too frequently.'], 429); }
            if (!$input || empty($input['total']) || empty($input['items'])) { send_json(['error' => 'Invalid input'], 400); }
            $sheetsService = new GoogleSheetsService();
            $stockAvailable = $sheetsService->verifyAndUpdateStock($input['items']);
            if (!$stockAvailable) { send_json(['error' => 'One or more items are out of stock.'], 409); }
            $internalOrderId = 'GLOW-' . time();
            $orderDataForSheet = ['order_id' => $internalOrderId, 'name' => $input['customer_info']['name'], 'phone' => $input['customer_info']['phone'], 'address' => $input['customer_info']['address'], 'total' => $input['total'], 'items' => $input['items']];
            $sheetsService->writeInitialOrder($orderDataForSheet);
            $telegramService = new TelegramService();
            $telegramService->sendNewOrderAttempt($orderDataForSheet);
            $razorpayService = new RazorpayService();
            $razorpayOrder = $razorpayService->createOrder($input['total'], $internalOrderId);
            if (isset($razorpayOrder['error'])) { send_json(['error' => 'Failed to create Razorpay order', 'details' => $razorpayOrder['error']], 500); }
            $_SESSION['last_submission_time'] = time();
            send_json(['razorpay_order_id' => $razorpayOrder['id'], 'internal_order_id' => $internalOrderId, 'razorpay_key_id' => $razorpayService->getKeyId(), 'amount' => $razorpayOrder['amount'], 'currency' => $razorpayOrder['currency']]);
        }
        break;

    case 'verify-payment':
        if ($request_method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['razorpay_payment_id']) || empty($input['razorpay_order_id']) || empty($input['internal_order_id'])) { send_json(['error' => 'Verification data missing'], 400); }
            $razorpayService = new RazorpayService();
            $isSignatureValid = $razorpayService->verifySignature($input);
            $internalOrderId = $input['internal_order_id'];
            $paymentId = $input['razorpay_payment_id'];
            if ($isSignatureValid) {
                $sheetsService = new GoogleSheetsService();
                $sheetsService->updateOrderStatus($internalOrderId, 'Paid', $paymentId);
                $telegramService = new TelegramService();
                $telegramService->sendOrderPaidConfirmation($internalOrderId, $paymentId);
                send_json(['status' => 'success', 'orderId' => $internalOrderId]);
            } else {
                $sheetsService = new GoogleSheetsService();
                $sheetsService->updateOrderStatus($internalOrderId, 'Payment Failed', $paymentId);
                send_json(['error' => 'Invalid payment signature'], 400);
            }
        }
        break;

    default:
        send_json(['message' => 'API endpoint not found.'], 404);
        break;
}