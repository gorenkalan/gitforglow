<?php
namespace App;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

class GoogleSheetsService {
    private $service;
    private $spreadsheetId;

    public function __construct() {
        $this->spreadsheetId = Config::get('GOOGLE_SHEET_ID');
        try {
            $client = new Client();
            $client->setApplicationName('GLOW E-commerce');
            $client->setScopes([Sheets::SPREADSHEETS]);
            $client->setAuthConfig(__DIR__ . '/../credentials.json');
            $this->service = new Sheets($client);
        } catch (\Exception $e) {
            $this->service = null;
            error_log('Google Sheets Service Error: ' . $e->getMessage());
        }
    }

    // Generic function to fetch raw data from any sheet
    public function getSheetData($sheetName) {
        if (!$this->service) return [];
        try {
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $sheetName);
            $values = $response->getValues();
            if (empty($values)) return [];

            $header = array_shift($values);
            $data = [];
            foreach ($values as $row) {
                $row = array_pad($row, count($header), null);
                $data[] = array_combine($header, $row);
            }
            return $data;
        } catch (\Exception $e) {
            error_log("Failed to fetch from sheet '{$sheetName}': " . $e->getMessage());
            return [];
        }
    }

    // Finds the row number for a specific variationId in the inventory sheet
    private function findInventoryRow($variationId) {
        $inventoryData = $this->getSheetData(Config::get('GOOGLE_SHEET_NAME_INVENTORY'));
        foreach ($inventoryData as $index => $item) {
            if (isset($item['variationId']) && $item['variationId'] === $variationId) {
                // Return the row number (1-based index + 1 for header) and current stock
                return ['rowNumber' => $index + 2, 'stock' => (int)$item['stock']];
            }
        }
        return null;
    }

    // Live stock check for multiple items. Returns false if any item is out of stock.
    public function verifyAndUpdateStock(array $items) {
        if (!$this->service) return false;
        $inventorySheetName = Config::get('GOOGLE_SHEET_NAME_INVENTORY');

        $updates = [];
        // First pass: check all stock levels
        foreach($items as $item) {
            $inventoryInfo = $this->findInventoryRow($item['variationId']);
            if (!$inventoryInfo || $inventoryInfo['stock'] < $item['quantity']) {
                // Item not found or not enough stock
                return false; 
            }
            $updates[] = [
                'rowNumber' => $inventoryInfo['rowNumber'],
                'newStock' => $inventoryInfo['stock'] - $item['quantity'],
            ];
        }

        // Second pass: update stock levels if all checks passed
        $batchUpdateData = [];
        foreach ($updates as $update) {
            $batchUpdateData[] = new ValueRange([
                // Assuming 'stock' is the 6th column (F)
                'range' => "{$inventorySheetName}!F{$update['rowNumber']}",
                'values' => [[$update['newStock']]]
            ]);
        }
        
        try {
            $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateValuesRequest([
                'valueInputOption' => 'USER_ENTERED',
                'data' => $batchUpdateData
            ]);
            $this->service->spreadsheets_values->batchUpdate($this->spreadsheetId, $batchUpdateRequest);
            return true;
        } catch (\Exception $e) {
            error_log("Failed to update stock: " . $e->getMessage());
            // In a real production app, you might want to implement a rollback here
            return false;
        }
    }

    // Order writing/updating methods remain the same as the previous correct version
    public function writeInitialOrder(array $orderData) { /* ... same as before ... */ }
    public function updateOrderStatus($orderId, $status, $paymentId) { /* ... same as before ... */ }
}