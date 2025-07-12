<?php
// test-telegram.php - Place this in your backend directory and run it to test Telegram integration

require_once __DIR__ . '/vendor/autoload.php';
use App\Config;
use App\TelegramService;

// Test 1: Check configuration
echo "=== Testing Telegram Configuration ===\n";
$botToken = Config::get('TELEGRAM_BOT_TOKEN');
$chatId = Config::get('TELEGRAM_CHAT_ID');

echo "Bot Token: " . (!empty($botToken) ? "Set (length: " . strlen($botToken) . ")" : "NOT SET") . "\n";
echo "Chat ID: " . (!empty($chatId) ? $chatId : "NOT SET") . "\n\n";

if (empty($botToken) || empty($chatId)) {
    echo "ERROR: Telegram configuration is missing!\n";
    echo "Please ensure you have set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID in your .env file\n";
    exit(1);
}

// Test 2: Send a test message
echo "=== Sending Test Message ===\n";
try {
    $telegram = new TelegramService();
    
    // Create a test order
    $testOrder = [
        'order_id' => 'TEST-' . time(),
        'name' => 'Test Customer',
        'phone' => '+1234567890',
        'total' => 1299.99,
        'items' => [
            [
                'name' => 'Test Product 1',
                'variationId' => 'VAR-001',
                'quantity' => 2
            ],
            [
                'name' => 'Test Product 2',
                'variationId' => 'VAR-002',
                'quantity' => 1
            ]
        ]
    ];
    
    echo "Sending order notification...\n";
    $telegram->sendNewOrderAttempt($testOrder);
    echo "✓ Order notification sent successfully!\n\n";
    
    echo "Sending payment confirmation...\n";
    $telegram->sendOrderPaidConfirmation($testOrder['order_id'], 'pay_test_' . uniqid());
    echo "✓ Payment confirmation sent successfully!\n\n";
    
    echo "SUCCESS: Telegram integration is working!\n";
    echo "Check your Telegram chat for the test messages.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Test 3: Direct API test
echo "\n=== Testing Direct Telegram API ===\n";
$testMessage = "🧪 Direct API Test Message\n\nThis is a test message sent directly to the Telegram API.";

$apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
$payload = [
    'chat_id' => $chatId,
    'text' => $testMessage,
    'parse_mode' => 'Markdown'
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "CURL Error: " . $curlError . "\n";
} else {
    echo "HTTP Response Code: " . $httpCode . "\n";
    echo "API Response: " . $response . "\n";
    
    $responseData = json_decode($response, true);
    if ($responseData['ok'] ?? false) {
        echo "✓ Direct API test successful!\n";
    } else {
        echo "✗ API Error: " . ($responseData['description'] ?? 'Unknown error') . "\n";
    }
}