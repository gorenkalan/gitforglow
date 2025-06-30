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

    /**
     * Fetches all rows from a given sheet and returns them as an associative array.
     * This is used by the cache builder.
     */
    public function getSheetData($sheetName) {
        if (!$this->service) return [];
        try {
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $sheetName);
            $values = $response->getValues();
            if (empty($values)) return [];

            $header = array_shift($values);
            $data = [];
            foreach ($values as $row) {
                if (count($row) < count($header)) {
                    // Pad rows that are shorter than the header to prevent errors
                    $row = array_pad($row, count($header), null);
                }
                $data[] = array_combine($header, $row);
            }
            return $data;
        } catch (\Exception $e) {
            error_log("Failed to fetch from sheet '{$sheetName}': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Finds the row number and current stock for a specific variationId.
     * Used for live stock checks.
     */
    private function findInventoryRow($variationId) {
        $inventoryData = $this->getSheetData(Config::get('GOOGLE_SHEET_NAME_INVENTORY'));
        foreach ($inventoryData as $index => $item) {
            if (isset($item['variationId']) && $item['variationId'] === $variationId) {
                // Row numbers are 1-based, and we shifted the header, so +2
                return ['rowNumber' => $index + 2, 'stock' => (int)$item['stock']];
            }
        }
        return null;
    }

    /**
     * Checks stock for all items in a cart and decrements it if available.
     * This is a critical transaction-like operation.
     */
    public function verifyAndUpdateStock(array $items) {
        if (!$this->service) return false;
        $inventorySheetName = Config::get('GOOGLE_SHEET_NAME_INVENTORY');

        $updates = [];
        // First pass: check all stock levels before making any changes
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

        // Second pass: all checks passed, now update the sheet in one batch call
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
            // In a real production app, you might want to implement a rollback logic here
            return false;
        }
    }

    /**
     * Writes an array of completed orders to the sheet in a single, efficient API call.
     */
    public function writeOrdersBatch(array $orders) {
        if (!$this->service || empty($orders)) {
            return ['success' => false, 'error' => 'Service not configured or no orders to write.'];
        }

        $range = Config::get('GOOGLE_SHEET_NAME_ORDERS');
        $values = [];

        foreach ($orders as $order) {
            $itemsSummary = [];
            if (isset($order['items']) && is_array($order['items'])) {
                foreach ($order['items'] as $item) {
                    $itemsSummary[] = "{$item['name']} ({$item['variationId']}) x {$item['quantity']}";
                }
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
            $result = $this->service->spreadsheets_values->append(
                $this->spreadsheetId, $range, $body, ['valueInputOption' => 'USER_ENTERED']
            );
            return ['success' => true, 'updated_rows' => $result->getUpdates()->getUpdatedRows()];
        } catch (\Exception $e) {
            error_log("Google Sheets Batch Write Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}