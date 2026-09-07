<?php
// php/api/auth/logout.php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../../auth/session.php';

// UPDATE 1: Enforce POST method to prevent accidental or malicious logouts via GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Execute your core logout logic
logoutUser();

// UPDATE 2: Explicitly destroy the session cookie in the user's browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Log the event for debugging
error_log("User logged out successfully from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

echo json_encode(['success' => true, 'message' => 'Logout successful.']);