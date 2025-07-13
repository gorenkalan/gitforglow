<?php
// A simple script to test the Google Sheets connection in isolation.

// Load only what we need.
require_once __DIR__ . '/vendor/autoload.php';
use App\Config;
use Google\Client;
use Google\Service\Sheets;

echo "<pre>"; // Use <pre> for easy-to-read output in the browser

// 1. Load Configuration
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    echo "SUCCESS: .env file loaded.\n";
} catch (\Exception $e) {
    echo "ERROR: Could not load .env file. " . $e->getMessage() . "\n";
    exit;
}

// 2. Get Credentials from Config
$spreadsheetId = Config::get('GOOGLE_SHEET_ID');
$productsSheet = Config::get('GOOGLE_SHEET_NAME_PRODUCTS');
$credentialsFile = __DIR__ . '/credentials.json';

echo "Spreadsheet ID: " . ($spreadsheetId ?: 'NOT FOUND') . "\n";
echo "Products Sheet Name: " . ($productsSheet ?: 'NOT FOUND') . "\n";
echo "Credentials File Path: " . $credentialsFile . "\n";

// 3. Check if Credentials File Exists and is Readable
if (!file_exists($credentialsFile)) {
    echo "ERROR: credentials.json file not found at the specified path.\n";
    exit;
}
if (!is_readable($credentialsFile)) {
    echo "ERROR: credentials.json file exists but is not readable. Check file permissions.\n";
    exit;
}
echo "SUCCESS: credentials.json file found and is readable.\n";

// 4. Attempt to Authenticate and Connect
try {
    $client = new Client();
    $client->setApplicationName('GLOW E-commerce Test');
    $client->setScopes([Sheets::SPREADSHEETS_READONLY]); // Use READONLY scope for safety
    $client->setAuthConfig($credentialsFile);
    
    $service = new Sheets($client);
    echo "SUCCESS: Google API client initialized.\n";
    
    // 5. Attempt to Fetch Data
    echo "Attempting to fetch data from range: {$productsSheet}\n";
    $response = $service->spreadsheets_values->get($spreadsheetId, $productsSheet);
    $values = $response->getValues();
    
    if (empty($values)) {
        echo "RESULT: The API call succeeded but returned 0 rows. This points to a permissions or sheet name issue.\n";
    } else {
        $rowCount = count($values);
        echo "SUCCESS! Fetched {$rowCount} rows from the sheet.\n\n";
        echo "First row data:\n";
        print_r($values[0]);
    }

} catch (\Exception $e) {
    echo "\n--- AN ERROR OCCURRED ---\n";
    echo "Error Type: " . get_class($e) . "\n";
    echo "Error Message: " . $e->getMessage() . "\n";
    echo "\n--- STACK TRACE ---\n";
    echo $e->getTraceAsString();
}

echo "</pre>";