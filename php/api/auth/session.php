<?php
// php/api/auth/session.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json; charset=utf-8');

// Check if session exists using the correct structure
if (isset($_SESSION['user']) && isset($_SESSION['user']['user_id']) && isset($_SESSION['user']['role'])) {
    echo json_encode([
        'success' => true,
        'user' => $_SESSION['user']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No active session',
        'session' => $_SESSION // Debug info
    ]);
}
exit;
?>