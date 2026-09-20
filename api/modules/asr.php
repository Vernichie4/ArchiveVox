<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../../services/WhisperService.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    if ($action === 'transcribe') {
        // Check if audio file was uploaded
        if (empty($_FILES) || !isset($_FILES['audio'])) {
            echo json_encode(['success' => false, 'message' => 'No audio file uploaded']);
            exit;
        }
        
        $file = $_FILES['audio'];
        $uploadDir = __DIR__ . '/../../../uploads/audio/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
        $filePath = $uploadDir . $filename;
        
        // Get language preference from request
        $language = $_POST['language'] ?? 'auto';
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Process with Whisper
            $whisper = new WhisperService($language);
            $result = $whisper->transcribeAudio($filePath);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'transcription' => $result,
                    'file_path' => $filename,
                    'language_used' => $result['language'] ?? 'unknown'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Transcription failed: ' . ($result['error'] ?? 'Unknown error')
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}