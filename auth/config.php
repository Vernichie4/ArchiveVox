<?php
// php/api/auth/config.php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('default_charset', 'UTF-8');

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../connection.php';

// Create PDO instance (available globally)
$pdo = createPdoConnection();

// Optional: set timezone
date_default_timezone_set('Asia/Manila');

$dbConfig = getDbConfig();
if (strtolower((string)($dbConfig['charset'] ?? '')) !== 'utf8mb4') {
    putenv('DB_CHARSET=utf8mb4');
}

$pdo = createPdoConnection();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session security settings
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    // Only send cookies over HTTPS
    session_set_cookie_params([
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Regenerate session ID periodically (every 10 minutes)
if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at'] > 600)) {
    session_regenerate_id(true);
    $_SESSION['created_at'] = time();
} elseif (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
}