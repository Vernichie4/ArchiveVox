<?php
// php/api/auth/session-test.php

// Display all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Simple text output for testing
echo "Session ID: " . session_id() . "\n";
echo "Session Data: " . print_r($_SESSION, true) . "\n";

// Check if session variables are set
echo "user_id set: " . (isset($_SESSION['user_id']) ? 'YES' : 'NO') . "\n";
echo "role set: " . (isset($_SESSION['role']) ? 'YES' : 'NO') . "\n";
?>