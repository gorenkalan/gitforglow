<?php
// Save this as: C:\xampp\htdocs\glow_ecommerce\quick-test.php
// Then open: http://localhost/glow_ecommerce/quick-test.php

// Enable all error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Glow Ecommerce Quick Test</h1>";
echo "<pre>";

// Test 1: Check if we can access the API
echo "1. Testing API Access:\n";
$api_path = __DIR__ . '/backend/api/index.php';
if (file_exists($api_path)) {
    echo "   ✓ API file exists\n";
} else {
    echo "   ✗ API file not found at: $api_path\n";
}

// Test 2: Check autoloader
echo "\n2. Testing Autoloader:\n";
$autoload_path = __DIR__ . '/backend/vendor/autoload.php';
if (file_exists($autoload_path)) {
    echo "   ✓ Autoloader found\n";
    require_once $autoload_path;
} else {
    echo "   ✗ Autoloader missing - Run 'composer install' in backend directory\n";
}

// Test 3: Make a simple API call
echo "\n3. Testing API Call:\n";
$test_url = "http://localhost/glow_ecommerce/backend/api/index.php?endpoint=categories";
$response = @file_get_contents($test_url);
if ($response !== false) {
    echo "   ✓ API responded\n";
    echo "   Response: " . substr($response, 0, 100) . "...\n";
} else {
    echo "   ✗ API call failed\n";
}

// Test 4: Check for .env file
echo "\n4. Testing Configuration:\n";
$env_path = __DIR__ . '/backend/.env';
if (file_exists($env_path)) {
    echo "   ✓ .env file exists\n";
} else {
    echo "   ✗ .env file missing\n";
    echo "   Create it in: $env_path\n";
}

// Test 5: Try to create an order
echo "\n5. Testing Order Creation:\n";
$order_data = [
    'total' => 100,
    'form_timestamp' => time() * 1000,
    'hp_email' => '',
    'customer_info' => [
        'name' => 'Test User',
        'phone' => '1234567890',
        'email' => 'test@test.com'
    ],
    'items' => [
        ['id' => '1', 'name' => 'Test', 'variationId' => 'test', 'quantity' => 1, 'price' => 100]
    ]
];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($order_data)
    ]
];
$context = stream_context_create($options);
$create_url = "http://localhost/glow_ecommerce/backend/api/index.php?endpoint=create-order";
$result = @file_get_contents($create_url, false, $context);

if ($result !== false) {
    echo "   Response: " . $result . "\n";
} else {
    echo "   ✗ Order creation failed\n";
    echo "   Check PHP error log for details\n";
}

echo "\n6. PHP Error Log Location:\n";
echo "   Windows XAMPP: C:\\xampp\\php\\logs\\php_error_log\n";
echo "   Check this file for detailed error messages!\n";

echo "</pre>";

// Show any PHP errors that occurred
if (function_exists('error_get_last')) {
    $error = error_get_last();
    if ($error !== null) {
        echo "<h2>Last PHP Error:</h2><pre>";
        print_r($error);
        echo "</pre>";
    }
}
?>