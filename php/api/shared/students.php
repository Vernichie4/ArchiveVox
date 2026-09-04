<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../../modules/student_management.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                // Get students based on user role
                $user = $_SESSION['user'] ?? null;
                if (!$user) {
                    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
                    break;
                }
                
                $students = [];
                
                // Principals and admins see all students
                if (in_array($user['role'], ['principal', 'admin'])) {
                    $stmt = $pdo->prepare('SELECT * FROM student ORDER BY first_name, last_name');
                    $stmt->execute();
                    $students = $stmt->fetchAll();
                } 
                // Teachers see their own students
                else if ($user['role'] === 'teacher') {
                    $stmt = $pdo->prepare('SELECT teacher_id FROM teacher WHERE user_id = :user_id');
                    $stmt->execute([':user_id' => $user['user_id']]);
                    $teacher = $stmt->fetch();
                    
                    if ($teacher) {
                        $students = getStudentsByTeacher($teacher['teacher_id']);
                    }
                }
                
                echo json_encode(['success' => true, 'students' => $students]);
                
            } elseif ($action === 'search') {
                $term = trim($_GET['term'] ?? '');
                if (strlen($term) < 1) {
                    echo json_encode(['success' => true, 'students' => []]);
                    break;
                }

                $user = $_SESSION['user'] ?? null;
                if (!$user) {
                    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
                    break;
                }

                // Base query – all named placeholders
                $sql = "SELECT * FROM student 
                        WHERE (lrn LIKE :term1 OR CONCAT(first_name, ' ', last_name) LIKE :term2)";
                $params = [
                    ':term1' => "%$term%",
                    ':term2' => "%$term%"
                ];

                // Teachers: restrict to their own students
                if ($user['role'] === 'teacher') {
                    $stmt = $pdo->prepare('SELECT teacher_id FROM teacher WHERE user_id = :user_id');
                    $stmt->execute([':user_id' => $user['user_id']]);
                    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($teacher) {
                        $sql .= " AND teacher_id = :teacher_id";
                        $params[':teacher_id'] = $teacher['teacher_id'];
                    } else {
                        // No teacher record – return empty
                        echo json_encode(['success' => true, 'students' => []]);
                        break;
                    }
                }
                // Principals/admins see all students (no extra filter)

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'students' => $students]);
            } elseif ($action === 'get') {
                $studentId = $_GET['id'] ?? 0;
                if ($studentId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Student ID required']);
                    break;
                }
                $student = getStudentById($studentId);
                if ($student) {
                    echo json_encode(['success' => true, 'student' => $student]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Student not found']);
                }
                
            } elseif ($action === 'get-by-lrn') {
                $lrn = $_GET['lrn'] ?? '';
                if (empty($lrn)) {
                    echo json_encode(['success' => false, 'message' => 'LRN required']);
                    break;
                }
                $student = getStudentByLrn($lrn);
                if ($student) {
                    echo json_encode(['success' => true, 'student' => $student]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Student not found']);
                }
                
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
            break;

        case 'POST':
            // 1. Read JSON input
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
                break;
            }

            // 2. Validate required fields
            if (empty($data['lrn']) || !preg_match('/^[0-9]{6}$/', $data['lrn'])) {
                echo json_encode(['success' => false, 'message' => 'LRN must be exactly 6 digits']);
                break;
            }
            if (empty($data['first_name']) || empty($data['last_name'])) {
                echo json_encode(['success' => false, 'message' => 'First name and last name are required']);
                break;
            }

            // 3. Get teacher_id from session
            $user = $_SESSION['user'] ?? null;
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Not authenticated']);
                break;
            }

            $stmt = $pdo->prepare('SELECT teacher_id FROM teacher WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $user['user_id']]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$teacher) {
                echo json_encode(['success' => false, 'message' => 'Teacher record not found']);
                break;
            }

            // 4. Add teacher_id to data and register
            $data['teacher_id'] = $teacher['teacher_id'];
            $result = registerStudent($data);
            echo json_encode($result);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
                break;
            }
            
            $studentId = $_GET['id'] ?? 0;
            if ($studentId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Student ID required']);
                break;
            }
            
            $result = editStudent($studentId, $data);
            echo json_encode($result);
            break;

        case 'DELETE':
            $studentId = $_GET['id'] ?? 0;
            if ($studentId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Student ID required']);
                break;
            }
            
            $result = archiveStudent($studentId);
            echo json_encode($result);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}