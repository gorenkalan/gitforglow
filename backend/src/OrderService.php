<?php
namespace App;

class OrderService {
    private $pendingDir;
    private $completedDir;

    public function __construct() {
        // Define paths to the order directories
        $this->pendingDir = __DIR__ . '/../orders/pending/';
        $this->completedDir = __DIR__ . '/../orders/completed/';
        // Create directories if they don't exist
        if (!is_dir($this->pendingDir)) mkdir($this->pendingDir, 0775, true);
        if (!is_dir($this->completedDir)) mkdir($this->completedDir, 0775, true);
    }

    private function getFilePath($orderId, $isPending = true) {
        $dir = $isPending ? $this->pendingDir : $this->completedDir;
        // Sanitize order ID to prevent directory traversal issues
        $safeOrderId = basename($orderId);
        return $dir . $safeOrderId . '.json';
    }

    public function createPendingOrder(array $orderData) {
        $filePath = $this->getFilePath($orderData['order_id']);
        $orderData['timestamp'] = date('Y-m-d H:i:s');
        $orderData['status'] = 'Pending Payment';
        $orderData['payment_id'] = ''; // Initially empty
        
        // Save the order data as a JSON file
        return file_put_contents($filePath, json_encode($orderData, JSON_PRETTY_PRINT));
    }

    public function updateOrderToPaid($orderId, $paymentId) {
        $filePath = $this->getFilePath($orderId);
        if (!file_exists($filePath)) {
            return false;
        }

        $orderData = json_decode(file_get_contents($filePath), true);
        if (!$orderData) return false; // Handle JSON decode error

        $orderData['status'] = 'Paid';
        $orderData['payment_id'] = $paymentId;
        
        return file_put_contents($filePath, json_encode($orderData, JSON_PRETTY_PRINT));
    }
    
    public function updateOrderToFailed($orderId) {
        $filePath = $this->getFilePath($orderId);
        if (!file_exists($filePath)) {
            return false;
        }

        $orderData = json_decode(file_get_contents($filePath), true);
        if (!$orderData) return false;

        $orderData['status'] = 'Payment Failed';
        
        return file_put_contents($filePath, json_encode($orderData, JSON_PRETTY_PRINT));
    }
    
    public function getPendingOrders() {
        $files = glob($this->pendingDir . '*.json');
        if ($files === false) return []; // Handle error in glob

        $orders = [];
        foreach ($files as $file) {
            $orders[] = json_decode(file_get_contents($file), true);
        }

        // Sort by timestamp descending so newest orders appear first
        usort($orders, function($a, $b) {
            return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
        });

        return $orders;
    }

    public function moveOrderToCompleted($orderId) {
        $pendingPath = $this->getFilePath($orderId);
        $completedPath = $this->getFilePath($orderId, false);

        if (file_exists($pendingPath)) {
            return rename($pendingPath, $completedPath);
        }
        return false;
    }
}