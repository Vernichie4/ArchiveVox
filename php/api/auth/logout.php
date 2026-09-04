<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../../auth/session.php';

logoutUser();

echo json_encode(['success' => true, 'message' => 'Logout successful.']);