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
            // Use read/write scope to allow for stock updates and order writing
            $client->setScopes([Sheets::SPREADSHEETS]);
            $client->setAuthConfig(__DIR__ . '/../credentials.json');
            $this->service = new Sheets($client);
        } catch (\Exception $e) {
            $this->service = null;
            error_log('Google Sheets Service Init Error: ' . $e->getMessage());
        }
    }

    /**
     * The final, truly robust function to fetch and correctly parse sheet data.
     * It manually builds an associative array, making it immune to errors
     * from empty cells or column mismatches, which was the final bug.
     */
    public function getSheetData($sheetName) {
        if (!$this->service) {
            error_log("getSheetData failed: Google Service not available.");
            return [];
        }
        if (empty($sheetName) || !is_string($sheetName)) {
            error_log("getSheetData failed: Invalid sheet name provided.");
            return [];
        }

        try {
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $sheetName);
            $allRows = $response->getValues();
            
            if (empty($allRows) || count($allRows) < 2) {
                error_log("Fetched 0 data rows from sheet: {$sheetName}. Check sheet name, permissions, and ensure it has a header and data.");
                return [];
            }

            $header = array_shift($allRows); // Get the header row: ['id', 'name', ...]
            $finalData = [];

            // --- THE DEFINITIVE FIX: Manually build the associative array ---
            foreach ($allRows as $row) {
                $rowData = [];
                // Loop through the header keys, not the row itself
                foreach ($header as $index => $key) {
                    // Get the value from the row at the same index as the header key.
                    // If the cell is missing or empty, default to an empty string.
                    $rowData[$key] = $row[$index] ?? '';
                }
                // Add the newly created associative array to our final data list.
                $finalData[] = $rowData;
            }
            // --- END OF FIX ---
            
            return $finalData;

        } catch (\Exception $e) {
            error_log("API Error fetching from sheet '{$sheetName}': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Finds the row number for a specific variationId in the inventory sheet.
     * This function is now reliable because it uses the fixed getSheetData().
     */
    private function findInventoryRow($variationId) {
        $inventoryData = $this->getSheetData(Config::get('GOOGLE_SHEET_NAME_INVENTORY'));
        foreach ($inventoryData as $index => $item) {
            // Use string casting for a robust comparison
            if (isset($item['variationId']) && (string)$item['variationId'] === (string)$variationId) {
                // Row numbers are 1-based, and we shifted the header, so +2
                return ['rowNumber' => $index + 2, 'stock' => (int)($item['stock'] ?? 0)];
            }
        }
        return null;
    }

    /**
     * Checks stock for all items in a cart and decrements it if available.
     * Note: This function makes live updates to the Google Sheet.
     */
    public function verifyAndUpdateStock(array $items) {
        if (!$this->service) return false;
        $inventorySheetName = Config::get('GOOGLE_SHEET_NAME_INVENTORY');
        $updates = [];

        foreach($items as $item) {
            $inventoryInfo = $this->findInventoryRow($item['variationId']);
            if (!$inventoryInfo || $inventoryInfo['stock'] < $item['quantity']) {
                return false; 
            }
            $updates[] = ['rowNumber' => $inventoryInfo['rowNumber'], 'newStock' => $inventoryInfo['stock'] - $item['quantity']];
        }

        $batchUpdateData = [];
        foreach ($updates as $update) {
            // Assuming 'stock' is the 6th column (F) in your inventory sheet
            $batchUpdateData[] = new ValueRange(['range' => "{$inventorySheetName}!F{$update['rowNumber']}", 'values' => [[$update['newStock']]]]);
        }
        
        try {
            $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateValuesRequest(['valueInputOption' => 'USER_ENTERED', 'data' => $batchUpdateData]);
            $this->service->spreadsheets_values->batchUpdate($this->spreadsheetId, $batchUpdateRequest);
            return true;
        } catch (\Exception $e) {
            error_log("Failed to update stock: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Writes an array of completed orders to the sheet in a single, efficient API call.
     */
    public function writeOrdersBatch(array $orders) {
        if (!$this->service || empty($orders)) { return ['success' => false, 'error' => 'No orders to write.']; }
        
        $range = Config::get('GOOGLE_SHEET_NAME_ORDERS');
        $values = [];
        
        // Your orders sheet header should be:
        // Timestamp, Order ID, Customer Name, Phone, Address, Items Summary, Total Amount, Status, Payment ID, Items (JSON)
        foreach ($orders as $order) {
            $itemsSummary = [];
            if (isset($order['items']) && is_array($order['items'])) {
                foreach ($order['items'] as $item) { $itemsSummary[] = "{$item['name']} ({$item['variationId']}) x {$item['quantity']}"; }
            }
            $values[] = [
                $order['timestamp'] ?? date('Y-m-d H:i:s'),
                $order['order_id'] ?? '',
                $order['customer_info']['name'] ?? '',
                $order['customer_info']['phone'] ?? '',
                $order['customer_info']['address'] ?? '',
                implode("\n", $itemsSummary),
                $order['total'] ?? 0,
                $order['status'] ?? 'Unknown',
                $order['payment_id'] ?? '',
                json_encode($order['items'])
            ];
        }
        try {
            $body = new ValueRange(['values' => $values]);
            $result = $this->service->spreadsheets_values->append($this->spreadsheetId, $range, $body, ['valueInputOption' => 'USER_ENTERED']);
            return ['success' => true, 'updated_rows' => $result->getUpdates()->getUpdatedRows()];
        } catch (\Exception $e) {
            error_log("Google Sheets Batch Write Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Validates the stock for a list of items without changing any data.
     * Returns an array of items with issues. Used by the checkout page.
     */
    public function validateStock(array $items) {
        if (!$this->service) return [['error' => 'Service not available']];
        
        $inventoryData = $this->getSheetData(Config::get('GOOGLE_SHEET_NAME_INVENTORY'));
        $inventoryMap = [];
        foreach ($inventoryData as $invItem) {
            $inventoryMap[$invItem['variationId']] = (int)($invItem['stock'] ?? 0);
        }

        $issues = [];
        foreach ($items as $item) {
            $variationId = $item['variationId'];
            $requiredQty = $item['quantity'];
            $availableStock = $inventoryMap[$variationId] ?? 0;

            if ($availableStock < $requiredQty) {
                $issues[] = ['variationId' => $variationId, 'required' => $requiredQty, 'available' => $availableStock];
            }
        }
        return $issues;
    }
}