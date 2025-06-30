<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
use App\Config;
use App\OrderService;

// --- Login Logic ---
$password = Config::get('ADMIN_PASSWORD', 'admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = 'Invalid password.';
    }
}
if (!isset($_SESSION['admin_logged_in'])) { /* ... HTML for login form remains the same ... */ exit; }
// --- End Login Logic ---


// --- Page Logic ---
$orderService = new OrderService();
$pendingOrders = $orderService->getPendingOrders();
$paidOrdersCount = count(array_filter($pendingOrders, fn($o) => $o['status'] === 'Paid'));

// Check for messages from redirect
$cache_message = $_SESSION['cache_message'] ?? null;
unset($_SESSION['cache_message']);
$sync_message = $_SESSION['sync_message'] ?? null;
unset($_SESSION['sync_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GLOW Admin</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 2rem; background-color: #f8f9fa; color: #333; }
        .container { max-width: 800px; margin: auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { border-bottom: 2px solid #eee; padding-bottom: 0.5rem; }
        .button { display: inline-block; padding: 0.75rem 1.5rem; font-size: 1rem; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
        .button-green { background: #28a745; } .button-green:hover { background: #218838; }
        .button-blue { background: #007bff; } .button-blue:hover { background: #0069d9; }
        .button-disabled { background: #ccc; cursor: not-allowed; }
        .message { padding: 1rem; margin: 1rem 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .section { margin-bottom: 2.5rem; }
        .order-list { max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 1rem; border-radius: 5px; }
        .order { border-bottom: 1px solid #eee; padding: 1rem 0.5rem; display: flex; justify-content: space-between; align-items: center; }
        .order:last-child { border-bottom: none; }
        .order-details { flex-grow: 1; }
        .order-status { font-weight: bold; padding: 0.25rem 0.5rem; border-radius: 10px; font-size: 0.8rem; }
        .status-paid { background: #28a745; color: white; }
        .status-payment-failed { background: #dc3545; color: white; }
        .status-pending-payment { background: #ffc107; color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>GLOW Admin Panel</h1>

        <!-- Product Cache Section -->
        <div class="section">
            <h2>Product Management</h2>
            <p>If you update products in the sheet, refresh the local cache to show changes on the website.</p>
            <a href="update_cache.php" class="button button-green">Refresh Product Cache</a>
            <?php if ($cache_message): ?><p class="message success"><?= htmlspecialchars($cache_message) ?></p><?php endif; ?>
        </div>

        <!-- Order Sync Section -->
        <div class="section">
            <h2>Order Management</h2>
            <p>New orders are saved locally instantly. Sync them to Google Sheets in batches.</p>
            <a href="sync_orders.php" 
               class="button button-blue <?= $paidOrdersCount === 0 ? 'button-disabled' : '' ?>"
               <?= $paidOrdersCount === 0 ? 'onclick="event.preventDefault();"' : '' ?>>
               Sync <?= $paidOrdersCount ?> Paid Order(s) to Sheet
            </a>
            <?php if ($sync_message): ?><p class="message success"><?= htmlspecialchars($sync_message) ?></p><?php endif; ?>
        </div>

        <!-- Pending Orders List -->
        <div class="section">
            <h2>Pending Orders <small>(not yet synced)</small></h2>
            <div class="order-list">
                <?php if (empty($pendingOrders)): ?>
                    <p>No pending orders to display.</p>
                <?php else: ?>
                    <?php foreach ($pendingOrders as $order): 
                        $statusClass = 'status-' . strtolower(str_replace(' ', '-', $order['status']));
                    ?>
                        <div class="order">
                            <div class="order-details">
                                <strong>ID:</strong> <?= htmlspecialchars($order['order_id']) ?><br>
                                <strong>Customer:</strong> <?= htmlspecialchars($order['customer_info']['name']) ?><br>
                                <strong>Total:</strong> ₹<?= number_format($order['total'], 2) ?>
                            </div>
                            <span class="order-status <?= $statusClass ?>"><?= htmlspecialchars($order['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <p style="margin-top: 2rem;"><a href="https://docs.google.com/spreadsheets/d/<?= Config::get('GOOGLE_SHEET_ID') ?>/edit" target="_blank">Edit Data in Google Sheet</a></p>
    </div>
</body>
</html>