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

// ==========================================
// 1. PROCESS ROLE & FETCH STUDENT ID FIRST
// ==========================================
$role = $user['role'] ?? 'unknown_role';
$user['role'] = strtolower($role);

if ($user['role'] === 'student') {
    try {
        // Fetch the student ID using the user ID
        $stmt = $pdo->prepare("SELECT student_id FROM student WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($studentRow) {
            // Attach it to the $user array
            $user['student_id'] = $studentRow['student_id'];
        }
    } catch (Exception $e) {
        error_log("Database error fetching student_id: " . $e->getMessage());
    }
}

// ==========================================
// 2. NOW CREATE THE SESSION WITH FULL DATA
// ==========================================
loginUser($user);

// ALWAYS regenerate session ID on login to prevent session fixation attacks
session_regenerate_id(true);

// Handle "Remember Me" - store in session cookie
if ($remember) {
    // Set secure session cookie parameters (30 days)
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params(
        60 * 60 * 24 * 30, // 30 days
        $cookieParams["path"],
        $cookieParams["domain"],
        isset($_SERVER['HTTPS']), // Secure flag (only send over HTTPS if active)
        true // HttpOnly flag (prevents XSS Javascript theft)
    );
}

// Log successful login with the user's role for better debugging
error_log("User logged in: {$user['username']} (Role: {$user['role']}, ID: {$user['user_id']}) from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Return success with user data
echo json_encode([
    'success' => true,
    'message' => 'Login successful.',
    'user' => $user
]);