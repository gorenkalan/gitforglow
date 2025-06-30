<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
use App\Config;

$password = Config::get('ADMIN_PASSWORD', 'admin'); // Fallback password
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['password']) && $_POST['password'] === $password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = 'Invalid password.';
    }
}

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

if (isset($_GET['action']) && $_GET['action'] === 'refresh_cache') {
    // This could be an AJAX call, but for simplicity, we'll use a form
    include 'update_cache.php'; // This script will output the success/failure message
    $success = 'Product cache has been refreshed from Google Sheets.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GLOW Admin</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; }
        .container { max-width: 600px; margin: auto; padding: 2rem; border: 1px solid #ccc; border-radius: 8px; }
        .button { display: inline-block; padding: 1rem 2rem; background: #28a745; color: white; text-decoration: none; border-radius: 4px; }
        .message { padding: 1rem; margin-top: 1rem; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <h1>GLOW Admin Panel</h1>
        <p>Your product data is cached to improve performance and avoid hitting Google Sheets API limits. If you update your products in the Google Sheet, you must refresh the cache here.</p>
        <a href="?action=refresh_cache" class="button">Refresh Product Cache</a>
        <?php if ($success): ?>
            <p class="message success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <p style="margin-top: 2rem;"><a href="https://docs.google.com/spreadsheets/d/<?= Config::get('GOOGLE_SHEET_ID') ?>/edit" target="_blank">Edit Products in Google Sheet</a></p>
    </div>
</body>
</html>