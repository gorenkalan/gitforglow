<?php
// Step 1: Handle CORS.
require_once __DIR__ . '/../src/cors.php';

// Step 2: Gracefully load dependencies.
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (!@include_once($autoload_path)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server Configuration Error']);
    exit;
}

// Dependencies loaded.
use App\Config;
use App\GoogleSheetsService;
use App\RazorpayService;
use App\TelegramService;
use App\OrderService; // <-- Include the new OrderService

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
session_start();

function send_json($data, $statusCode = 200) { /* ... same as before ... */ }

$endpoint = $_GET['endpoint'] ?? null;
if (!$endpoint) { send_json(['error' => 'API endpoint not specified.'], 400); }

$request_method = $_SERVER['REQUEST_METHOD'];

switch ($endpoint) {
    case 'products':
        // ... (this case is complete and correct from the last response)
        break;

    case 'categories':
        // ... (this case is complete and correct from the last response)
        break;
    
    case 'create-order':
        if ($request_method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            // ... (Spam protection block is the same) ...
            if (!$input || empty($input['total']) || empty($input['items'])) { send_json(['error' => 'Invalid input'], 400); }
            
            $orderService = new OrderService();
            $sheetsService = new GoogleSheetsService();
            $telegramService = new TelegramService();
            
            $stockAvailable = $sheetsService->verifyAndUpdateStock($input['items']);
            if (!$stockAvailable) {
                $telegramService->sendCriticalError("Stock Verification Failed", ['customer' => $input['customer_info']['name'], 'items' => json_encode($input['items'])]);
                send_json(['error' => 'One or more items are out of stock. Please update your cart.'], 409);
            }

            $internalOrderId = 'GLOW-' . time();
            
            $orderDataForFile = [
                'order_id' => $internalOrderId,
                'customer_info' => $input['customer_info'],
                'items' => $input['items'],
                'total' => $input['total']
            ];
            $orderService->createPendingOrder($orderDataForFile);
            $telegramService->sendNewOrderAttempt($orderDataForFile);

            $razorpayService = new RazorpayService();
            $razorpayOrder = $razorpayService->createOrder($input['total'], $internalOrderId);
            if (isset($razorpayOrder['error'])) {
                 $telegramService->sendCriticalError("Razorpay Order Creation Failed", ['customer' => $input['customer_info']['name'], 'error' => $razorpayOrder['error']]);
                 send_json(['error' => 'Failed to create Razorpay order', 'details' => $razorpayOrder['error']], 500);
            }
            
            $_SESSION['last_submission_time'] = time();
            send_json([
                'razorpay_order_id' => $razorpayOrder['id'], 'internal_order_id' => $internalOrderId,
                'razorpay_key_id' => $razorpayService->getKeyId(), 'amount' => $razorpayOrder['amount'],
                'currency' => $razorpayOrder['currency']
            ]);
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
            
            $orderService = new OrderService();
            $telegramService = new TelegramService();

            if ($isSignatureValid) {
                $orderService->updateOrderToPaid($internalOrderId, $paymentId);
                $telegramService->sendOrderPaidConfirmation($internalOrderId, $paymentId);
                send_json(['status' => 'success', 'orderId' => $internalOrderId]);
            } else {
                $orderService->updateOrderToFailed($internalOrderId);
                // No need to send a telegram message for invalid signature, as it could be malicious
                send_json(['error' => 'Invalid payment signature'], 400);
            }
        }
        break;

    default:
        send_json(['message' => 'API endpoint not found.'], 404);
        break;
}