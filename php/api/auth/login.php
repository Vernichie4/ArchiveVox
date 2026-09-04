<?php
// php/api/auth/login.php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../../auth/authenticate.php';
require_once __DIR__ . '/../../auth/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Accept both JSON and form-encoded payloads.
$json = file_get_contents('php://input');
$data = json_decode($json, true);
if (!is_array($data)) {
    $data = $_POST;
}

// Get username and password from data
$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');
$remember = isset($data['remember']) ? filter_var($data['remember'], FILTER_VALIDATE_BOOLEAN) : false;

// Validate input
if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

// Validate email format (if using email as username)
if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $username)) {
    // Allow non-email usernames too (like 'teacher1')
    // Just log it
    error_log("Login attempt with non-email username: $username");
}

// Authenticate
$user = authenticateUser($username, $password);

// Handle error responses from authenticateUser
if (is_array($user) && isset($user['error'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $user['message'],
        'error_code' => $user['error']
    ]);
    exit;
}

// Check if authentication failed
if ($user === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    exit;
}

// Login success - create session
loginUser($user);

// Handle "Remember Me" - store in session cookie
if ($remember) {
    // Set session cookie to last longer (30 days)
    session_set_cookie_params(60 * 60 * 24 * 30); // 30 days
    session_regenerate_id(true);
}

// Log successful login with details
error_log("User logged in: {$user['username']} (ID: {$user['user_id']}) from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Return success with user data
echo json_encode([
    'success' => true,
    'message' => 'Login successful.',
    'user' => $user
]);