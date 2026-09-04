<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../../modules/reading_materials.php';
require_once __DIR__ . '/../../backend/ocr.php';

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
                if ($action === 'list') {
                error_log('=== READING MATERIALS LIST REQUEST ===');
                $materials = viewReadingMaterials();
                error_log('Materials found: ' . count($materials));
                echo json_encode(['success' => true, 'materials' => $materials]);
             } else {
                $materialId = $_GET['id'] ?? 0;
                if ($materialId > 0) {
                    $stmt = $pdo->prepare('SELECT * FROM reading_material WHERE material_id = :id');
                    $stmt->execute([':id' => $materialId]);
                    $material = $stmt->fetch();
                    echo json_encode(['success' => true, 'material' => $material]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Material ID required']);
                }
            }
            break;

        case 'preview-ocr':
            if (!empty($_FILES) && isset($_FILES['image'])) {
                $file = $_FILES['image'];
                $uploadDir = __DIR__ . '/../../../uploads/temp/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $filename = time() . '_preview_' . basename($file['name']);
                $filePath = $uploadDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    try {
                        $language = $_POST['language'] ?? 'English';
                        $ocrLanguage = ($language === 'Filipino') ? 'eng+fil' : 'eng';
                        
                        $ocrText = runOcr($filePath, $ocrLanguage);
                        $wordCount = str_word_count($ocrText);
                        
                        unlink($filePath);
                        
                        echo json_encode([
                            'success' => true,
                            'ocr_text' => $ocrText,
                            'word_count' => $wordCount,
                            'ocr_message' => 'OCR extracted ' . $wordCount . ' words successfully!'
                        ]);
                    } catch (Exception $e) {
                        unlink($filePath);
                        echo json_encode([
                            'success' => false,
                            'ocr_text' => '',
                            'message' => $e->getMessage()
                        ]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No image file provided']);
            }
            break;

        case 'POST':
            if (!empty($_FILES) && isset($_FILES['image'])) {
                $file = $_FILES['image'];
                $uploadDir = __DIR__ . '/../../../uploads/materials/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $filename = time() . '_' . basename($file['name']);
                $filePath = $uploadDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $language = $_POST['language'] ?? 'English';
                    $ocrLanguage = ($language === 'Filipino') ? 'fil' : 'eng';
                    
                    try {
                        $ocrText = runOcr($filePath, $ocrLanguage);
                    } catch (Exception $e) {
                        $ocrText = null;
                    }
                    
                    $data = [
                        'teacher_id' => $_POST['teacher_id'] ?? 1,
                        'title' => $_POST['title'] ?? 'Untitled',
                        'description' => $_POST['description'] ?? null,
                        'language' => $_POST['language'] ?? 'English',
                        'material_type' => $_POST['material_type'] ?? 'Custom',
                        'grade_level' => $_POST['grade_level'] ?? 'Grade 2',
                        'difficulty' => $_POST['difficulty'] ?? 'Average',
                        'original_filename' => $file['name'],
                        'file_path' => $filename,
                        'ocr_text' => $ocrText,
                        'total_words' => str_word_count($ocrText ?? ''),
                        'status' => 'Active'
                    ];
                    
                    $result = uploadReadingMaterial($data);
                    
                    if ($ocrText) {
                        $result['ocr_text'] = $ocrText;
                        $result['word_count'] = str_word_count($ocrText);
                        $result['ocr_success'] = true;
                        $result['ocr_message'] = 'OCR extracted ' . str_word_count($ocrText) . ' words successfully!';
                    } else {
                        $result['ocr_success'] = false;
                        $result['ocr_message'] = 'OCR could not extract text from this image.';
                    }
                    
                    echo json_encode($result);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
                }
            } else {
                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data) {
                    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
                    break;
                }
                
                $result = uploadReadingMaterial($data);
                echo json_encode($result);
            }
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
                break;
            }
            
            $materialId = $_GET['id'] ?? 0;
            if ($materialId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Material ID required']);
                break;
            }
            
            $result = editReadingMaterial($materialId, $data);
            echo json_encode($result);
            break;

        case 'DELETE':
            $materialId = $_GET['id'] ?? 0;
            if ($materialId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Material ID required']);
                break;
            }
            
            $result = deleteReadingMaterial($materialId);
            echo json_encode($result);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}