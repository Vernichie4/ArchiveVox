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
                    $stmt = $pdo->prepare('
                        SELECT s.*,
                            sc.grade_level,
                            c.section,
                            (SELECT AVG(ar.accuracy_percentage) FROM assessment_result ar JOIN reading_activity ra ON ar.activity_id = ra.activity_id WHERE ra.student_id = s.student_id) AS avg_accuracy,
                            (SELECT MAX(ar.assessed_at) FROM assessment_result ar JOIN reading_activity ra ON ar.activity_id = ra.activity_id WHERE ra.student_id = s.student_id) AS last_assessed,
                            (SELECT ar2.reading_level FROM assessment_result ar2 JOIN reading_activity ra2 ON ar2.activity_id = ra2.activity_id WHERE ra2.student_id = s.student_id AND ar2.reading_level IS NOT NULL ORDER BY ar2.assessed_at DESC LIMIT 1) AS reading_level
                        FROM student s 
                        LEFT JOIN student_category sc ON s.category_id = sc.category_id
                        LEFT JOIN class c ON s.class_id = c.class_id
                        ORDER BY s.first_name, s.last_name
                    ');
                    $stmt->execute();
                    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } 
                // Teachers see their own students
                else if ($user['role'] === 'teacher') {
                    $stmt = $pdo->prepare('SELECT teacher_id FROM teacher WHERE user_id = :user_id');
                    $stmt->execute([':user_id' => $user['user_id']]);
                    $teacher = $stmt->fetch();
                    
                    if ($teacher) {
                        $stmt = $pdo->prepare('
                            SELECT s.*,
                                sc.grade_level,
                                c.section,
                                (SELECT AVG(ar.accuracy_percentage) FROM assessment_result ar JOIN reading_activity ra ON ar.activity_id = ra.activity_id WHERE ra.student_id = s.student_id) AS avg_accuracy,
                                (SELECT MAX(ar.assessed_at) FROM assessment_result ar JOIN reading_activity ra ON ar.activity_id = ra.activity_id WHERE ra.student_id = s.student_id) AS last_assessed,
                                (SELECT ar2.reading_level FROM assessment_result ar2 JOIN reading_activity ra2 ON ar2.activity_id = ra2.activity_id WHERE ra2.student_id = s.student_id AND ar2.reading_level IS NOT NULL ORDER BY ar2.assessed_at DESC LIMIT 1) AS reading_level
                            FROM student s 
                            LEFT JOIN student_category sc ON s.category_id = sc.category_id
                            LEFT JOIN class c ON s.class_id = c.class_id
                            WHERE s.teacher_id = :teacher_id
                            ORDER BY s.first_name, s.last_name
                        ');
                        $stmt->execute([':teacher_id' => $teacher['teacher_id']]);
                        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

                $sql = "SELECT s.*, 
                            sc.grade_level,
                            c.section,
                            (SELECT AVG(ar.accuracy_percentage) FROM assessment_result ar JOIN reading_activity ra ON ar.activity_id = ra.activity_id WHERE ra.student_id = s.student_id) AS avg_accuracy,
                            (SELECT MAX(ar.assessed_at) FROM assessment_result ar JOIN reading_activity ra ON ar.activity_id = ra.activity_id WHERE ra.student_id = s.student_id) AS last_assessed,
                            (SELECT ar2.reading_level FROM assessment_result ar2 JOIN reading_activity ra2 ON ar2.activity_id = ra2.activity_id WHERE ra2.student_id = s.student_id AND ar2.reading_level IS NOT NULL ORDER BY ar2.assessed_at DESC LIMIT 1) AS reading_level
                        FROM student s 
                        LEFT JOIN student_category sc ON s.category_id = sc.category_id
                        LEFT JOIN class c ON s.class_id = c.class_id
                        WHERE (s.lrn LIKE :term1 OR CONCAT(s.first_name, ' ', s.last_name) LIKE :term2)";
                $params = [
                    ':term1' => "%$term%",
                    ':term2' => "%$term%"
                ];

                if ($user['role'] === 'teacher') {
                    $stmt = $pdo->prepare('SELECT teacher_id FROM teacher WHERE user_id = :user_id');
                    $stmt->execute([':user_id' => $user['user_id']]);
                    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($teacher) {
                        $sql .= " AND s.teacher_id = :teacher_id";
                        $params[':teacher_id'] = $teacher['teacher_id'];
                    } else {
                        echo json_encode(['success' => true, 'students' => []]);
                        break;
                    }
                }

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

            // 2. Validate required fields (Grade and Section checks are intentionally gone)
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

            $stmtClass = $pdo->prepare('SELECT class_id FROM class WHERE teacher_id = :teacher_id LIMIT 1');
            $stmtClass->execute([':teacher_id' => $teacher['teacher_id']]);
            $masterClass = $stmtClass->fetch(PDO::FETCH_ASSOC);

            if (!$masterClass) {
                echo json_encode(['success' => false, 'message' => 'System Error: This teacher does not have a class assigned in the database.']);
                break;
            }

            // 4. Force the class_id and teacher_id, completely bypassing frontend inputs
            $data['class_id'] = $masterClass['class_id'];
            $data['teacher_id'] = $teacher['teacher_id'];
            
            // 5. Register the student
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