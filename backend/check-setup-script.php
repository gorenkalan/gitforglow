<?php
// check-setup.php - Run this from command line: php check-setup.php

echo "=== Glow Ecommerce Setup Check ===\n\n";

// Check 1: PHP Version
echo "1. PHP Version: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    echo "   ⚠️  Warning: PHP 7.4 or higher recommended\n";
}
echo "\n";

// Check 2: Required Extensions
echo "2. Required Extensions:\n";
$requiredExtensions = ['curl', 'json', 'session'];
foreach ($requiredExtensions as $ext) {
    echo "   - $ext: " . (extension_loaded($ext) ? "✓ Loaded" : "✗ Missing") . "\n";
}
echo "\n";

// Check 3: Directory Structure
echo "3. Directory Structure:\n";
$dirs = [
    'vendor' => __DIR__ . '/vendor',
    'cache' => __DIR__ . '/cache',
    'src' => __DIR__ . '/src',
    'api' => __DIR__ . '/api',
    'orders' => __DIR__ . '/orders',
];
foreach ($dirs as $name => $path) {
    echo "   - $name: " . (is_dir($path) ? "✓ Exists" : "✗ Missing") . " ($path)\n";
}
echo "\n";

// Check 4: Autoloader
echo "4. Composer Autoloader:\n";
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    echo "   ✓ Found at: $autoloadPath\n";
    require_once $autoloadPath;
} else {
    echo "   ✗ Not found! Run 'composer install' in the backend directory\n";
    exit(1);
}
echo "\n";

// Check 5: Configuration
echo "5. Configuration (.env):\n";
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    echo "   ✓ .env file exists\n";
    
    // Try to load config
    if (class_exists('App\\Config')) {
        echo "   ✓ Config class loaded\n";
        
        // Check Telegram config
        $telegramToken = \App\Config::get('TELEGRAM_BOT_TOKEN');
        $telegramChatId = \App\Config::get('TELEGRAM_CHAT_ID');
        
        echo "   - TELEGRAM_BOT_TOKEN: " . 
             (!empty($telegramToken) ? "✓ Set (length: " . strlen($telegramToken) . ")" : "✗ Not set") . "\n";
        echo "   - TELEGRAM_CHAT_ID: " . 
             (!empty($telegramChatId) ? "✓ Set ($telegramChatId)" : "✗ Not set") . "\n";
    } else {
        echo "   ✗ Config class not found\n";
    }
} else {
    echo "   ✗ .env file not found\n";
    echo "   Create .env file with:\n";
    echo "   TELEGRAM_BOT_TOKEN=your_bot_token\n";
    echo "   TELEGRAM_CHAT_ID=your_chat_id\n";
}
echo "\n";

// Check 6: Required Classes
echo "6. Required Classes:\n";
$classes = [
    'App\\Config',
    'App\\GoogleSheetsService',
    'App\\RazorpayService',
    'App\\TelegramService',
    'App\\OrderService'
];
foreach ($classes as $class) {
    echo "   - $class: " . (class_exists($class) ? "✓ Found" : "✗ Missing") . "\n";
}
echo "\n";

// Check 7: Products Cache
echo "7. Products Cache:\n";
$productsFile = __DIR__ . '/cache/products.json';
if (file_exists($productsFile)) {
    echo "   ✓ products.json exists\n";
    $products = json_decode(file_get_contents($productsFile), true);
    echo "   - Products count: " . (is_array($products) ? count($products) : 0) . "\n";
} else {
    echo "   ✗ products.json not found\n";
    echo "   Create it using the admin panel or manually\n";
}
echo "\n";

// Check 8: Write Permissions
echo "8. Write Permissions:\n";
$writableDirs = ['orders', 'cache'];
foreach ($writableDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        echo "   - $dir: " . (is_writable($path) ? "✓ Writable" : "✗ Not writable") . "\n";
    } else {
        echo "   - $dir: ✗ Directory doesn't exist\n";
    }
}
echo "\n";

// Summary
echo "=== Summary ===\n";
echo "If you see any ✗ marks above, those issues need to be fixed.\n";
echo "Most common fixes:\n";
echo "1. Run 'composer install' in the backend directory\n";
echo "2. Create missing directories (vendor, cache, orders)\n";
echo "3. Create .env file with your Telegram credentials\n";
echo "4. Ensure directories have write permissions\n";