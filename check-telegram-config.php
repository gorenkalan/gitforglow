<?php
// Save as: D:\xaamp\htdocs\glow_ecommerce\check-telegram.php
// Then open: http://localhost/glow_ecommerce/check-telegram.php

require_once __DIR__ . '/backend/vendor/autoload.php';
use App\Config;

echo "<h1>Telegram Configuration Check</h1><pre>";

// Check if config can be loaded
$token = Config::get('TELEGRAM_BOT_TOKEN');
$chatId = Config::get('TELEGRAM_CHAT_ID');

echo "1. Configuration Status:\n";
echo "   Bot Token: " . (!empty($token) ? "✓ Set (length: " . strlen($token) . ")" : "✗ NOT SET") . "\n";
echo "   Chat ID: " . (!empty($chatId) ? "✓ Set ($chatId)" : "✗ NOT SET") . "\n\n";

if (empty($token) || empty($chatId)) {
    echo "ERROR: Please configure Telegram in your .env file!\n";
    echo "Location: D:\\xaamp\\htdocs\\glow_ecommerce\\backend\\.env\n";
    echo "\nAdd these lines:\n";
    echo "TELEGRAM_BOT_TOKEN=your_bot_token_here\n";
    echo "TELEGRAM_CHAT_ID=your_chat_id_here\n";
} else {
    echo "2. Testing Bot Connection:\n";
    $url = "https://api.telegram.org/bot$token/getMe";
    $response = @file_get_contents($url);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data['ok']) {
            echo "   ✓ Bot is valid!\n";
            echo "   Bot name: @" . $data['result']['username'] . "\n";
            echo "   Bot ID: " . $data['result']['id'] . "\n";
        } else {
            echo "   ✗ Invalid bot token!\n";
        }
    } else {
        echo "   ✗ Could not connect to Telegram API\n";
    }
    
    echo "\n3. Ready to send test message?\n";
    echo "   Click here to test: <a href='?send=true'>Send Test Message</a>\n";
    
    if (isset($_GET['send'])) {
        echo "\n4. Sending test message...\n";
        $testMessage = "🎉 Test message from Glow Ecommerce!\n\n" .
                      "Time: " . date('Y-m-d H:i:s') . "\n" .
                      "If you see this, Telegram integration is working!";
        
        $payload = [
            'chat_id' => $chatId,
            'text' => $testMessage,
            'parse_mode' => 'HTML'
        ];
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($payload)
            ]
        ];
        
        $context = stream_context_create($options);
        $sendUrl = "https://api.telegram.org/bot$token/sendMessage";
        $result = @file_get_contents($sendUrl, false, $context);
        
        if ($result) {
            $resultData = json_decode($result, true);
            if ($resultData['ok']) {
                echo "   ✓ Message sent successfully!\n";
                echo "   Check your Telegram chat/group now!\n";
            } else {
                echo "   ✗ Failed to send: " . $resultData['description'] . "\n";
            }
        } else {
            echo "   ✗ Failed to send message\n";
        }
    }
}

echo "</pre>";