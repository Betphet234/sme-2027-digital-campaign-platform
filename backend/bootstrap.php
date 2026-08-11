<?php
session_start();
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
require_once __DIR__ . '/config.php';
// Optional payment and SMS keys. Keep real API keys out of public HTML/JavaScript.
if (file_exists(__DIR__ . '/payment_sms_config.php')) {
    require_once __DIR__ . '/payment_sms_config.php';
}
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
} else {
    ini_set('display_errors', '1');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
