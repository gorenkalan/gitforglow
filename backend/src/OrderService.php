<?php
namespace App;

class OrderService {
    private $pendingDir;
    private $completedDir;

    public function __construct() {
        $this->pendingDir = __DIR__ . '/../orders/pending/';
        $this->completedDir = __DIR__ . '/../orders/completed/';
        if (!is_dir($this->pendingDir)) mkdir($this->pendingDir, 0775, true);
        if (!is_dir($this->completedDir)) mkdir($this->completedDir, 0775, true);
    }

    private function getFilePath($orderId, $isPending = true) {
        $dir = $isPending ? $this->pendingDir : $this->completedDir;
        $safeOrderId = basename($orderId);
        return $dir . $safeOrderId . '.json';
    }

    public function createPendingOrder(array $orderData) {
        $filePath = $this->getFilePath($orderData['order_id']);
        $orderData['timestamp'] = date('Y-m-d H:i:s');
        $orderData['status'] = 'Pending Payment';
        $orderData['payment_id'] = '';
        return file_put_contents($filePath, json_encode($orderData, JSON_PRETTY_PRINT));
    }

    // --- NEW FLEXIBLE FUNCTION ---
    public function updateOrderStatus($orderId, $newStatus, $paymentId = null) {
        $filePath = $this->getFilePath($orderId);
        if (!file_exists($filePath)) {
            return false;
        }
        $orderData = json_decode(file_get_contents($filePath), true);
        if (!$orderData) return false;

        $orderData['status'] = $newStatus;
        if ($paymentId !== null) {
            $orderData['payment_id'] = $paymentId;
        }
        
        return file_put_contents($filePath, json_encode($orderData, JSON_PRETTY_PRINT));
    }
    
    public function getPendingOrders() {
        $files = glob($this->pendingDir . '*.json');
        if ($files === false) return [];
        $orders = [];
        foreach ($files as $file) {
            $orders[] = json_decode(file_get_contents($file), true);
        }
        usort($orders, fn($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));
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