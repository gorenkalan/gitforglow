<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
use App\Config;
use App\OrderService;

// --- Login Logic ---
$password = Config::get('ADMIN_PASSWORD', 'admin');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid password.';
    }
}

// If not logged in, show the simple login form.
if (!isset($_SESSION['admin_logged_in'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f0f2f5; }
        form { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        input { width: 100%; padding: 0.5rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 0.75rem; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .error { color: red; }
    </style>
</head>
<body>
    <form method="POST">
        <h2>Admin Login</h2>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    </form>
</body>
</html>
<?php
    exit;
}
// --- End Login Logic ---


// --- Page Logic (Only runs if logged in) ---
$orderService = new OrderService();
$pendingOrders = $orderService->getPendingOrders();
$paidOrdersCount = count(array_filter($pendingOrders, fn($o) => isset($o['status']) && $o['status'] === 'Paid'));

// Check for flash messages from redirects
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
        body { font-family: sans-serif; padding: 2rem; }
        .container { max-width: 800px; margin: auto; padding: 2rem; border: 1px solid #ccc; border-radius: 8px; }
        .button { display: inline-block; padding: 1rem 2rem; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; margin-right: 1rem;}
        .button-green { background: #28a745; }
        .button-blue { background: #007bff; }
        .button-disabled { background: #ccc; cursor: not-allowed; }
        .message { padding: 1rem; margin-top: 1rem; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; }
        .order-list { margin-top: 2rem; border-top: 1px solid #ccc; padding-top: 1rem; }
        .order { border-bottom: 1px solid #eee; padding: 0.5rem 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>GLOW Admin Panel</h1>
        
        <p>Use these tools to manage your store data.</p>
        
        <!-- Action Buttons -->
        <div>
            <a href="update_cache.php" class="button button-green">Refresh Product Cache</a>
            <a href="sync_orders.php" class="button button-blue <?= $paidOrdersCount === 0 ? 'button-disabled' : '' ?>"
               <?php if ($paidOrdersCount === 0) echo 'onclick="event.preventDefault();"'; ?>>
               Sync <?= $paidOrdersCount ?> Paid Orders
            </a>
        </div>
        
        <!-- Flash Messages -->
        <?php if ($cache_message): ?><p class="message success"><?= htmlspecialchars($cache_message) ?></p><?php endif; ?>
        <?php if ($sync_message): ?><p class="message success"><?= htmlspecialchars($sync_message) ?></p><?php endif; ?>

        <!-- Pending Orders List -->
        <div class="order-list">
            <h2>Pending Orders (Not Synced)</h2>
            <?php if (empty($pendingOrders)): ?>
                <p>No pending orders.</p>
            <?php else: ?>
                <?php foreach ($pendingOrders as $order): ?>
                    <div class="order">
                        <strong><?= htmlspecialchars($order['order_id']) ?>:</strong>
                        <?= htmlspecialchars($order['customer_info']['name']) ?> - 
                        ₹<?= number_format($order['total'] ?? 0, 2) ?>
                        (<span>Status: <?= htmlspecialchars($order['status'] ?? 'Unknown') ?></span>)
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <p style="margin-top: 2rem;"><a href="https://docs.google.com/spreadsheets/d/<?= htmlspecialchars(Config::get('GOOGLE_SHEET_ID')) ?>/edit" target="_blank" rel="noopener noreferrer">Edit Data in Google Sheet</a></p>
    </div>
</body>
</html>