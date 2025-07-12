<?php
// This file has ONE job: to send the correct CORS headers.
// We are explicitly setting it to 5173, which is Vite's default port.

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Immediately handle the browser's preflight 'OPTIONS' request.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}