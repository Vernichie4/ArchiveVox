<?php
// php/api/teacher/student-import.php

// Set JSON header first
header('Content-Type: application/json; charset=utf-8');

// Enable error logging but hide errors from output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Log the request for debugging
    error_log("student-import.php called with action: " . ($_GET['action'] ?? 'none'));
    error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
    
    // Include the main import module
    require_once __DIR__ . '/../../modules/student_import.php';
    
} catch (Exception $e) {
    error_log("student-import.php error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load import module: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}
?>