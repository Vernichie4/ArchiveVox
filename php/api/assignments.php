<?php
/**
 * ArchiveVox Assignment API - PHP Conversion
 * Converted from assignment_api.py
 */

// ============================================================
// 1. CONFIGURATION & CORS OVERRIDE
// ============================================================

// Bulletproof CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Accept");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Basic Dotenv Parser (avoids needing Composer for simple setups)
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

define('DEV_MODE', in_array(strtolower(getenv('DEV_MODE') ?: 'true'), ['1', 'true', 'yes']));

// ============================================================
// 2. DATABASE & RESPONSE HELPERS
// ============================================================

function get_db(): PDO {
    // Railway MySQL variables fallback to your local .env/default variables
    $host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306';
    $db   = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'archivevox';
    $user = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '';
    
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}

function success(?array $data = null, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    $payload = ['success' => true];
    if ($data) $payload = array_merge($payload, $data);
    echo json_encode($payload);
    exit;
}

function error(string $message, int $status = 400): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function get_json_payload(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// ============================================================
// 3. SCHEMA & DOMAIN HELPERS
// ============================================================

function table_exists(PDO $pdo, string $table_name): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table_name]);
    return (bool)$stmt->fetchColumn();
}

function get_student(int $student_id): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.lrn, s.first_name, s.middle_name, s.last_name, s.class_id,
               c.teacher_id, c.grade_level, c.section, c.school_year
        FROM student s LEFT JOIN class c ON c.class_id = s.class_id
        WHERE s.student_id = ? AND s.is_active = 1 LIMIT 1
    ");
    $stmt->execute([$student_id]);
    return $stmt->fetch() ?: null;
}

function teacher_owns_class(PDO $pdo, int $teacher_id, int $class_id): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM class WHERE class_id = ? AND teacher_id = ? LIMIT 1");
    $stmt->execute([$class_id, $teacher_id]);
    return (bool)$stmt->fetch();
}

function teacher_owns_material(PDO $pdo, int $teacher_id, int $material_id): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM reading_material WHERE material_id = ? AND status NOT IN ('Archived', 'Deleted', 'archived', 'deleted') LIMIT 1");
    $stmt->execute([$material_id]);
    return (bool)$stmt->fetch();
}

function get_assignment_for_student(PDO $pdo, int $assignment_id, int $student_id): ?array {
    $stmt = $pdo->prepare("
        SELECT ra.*, s.student_id, c.grade_level, c.section, c.school_year
        FROM reading_assignment ra
        INNER JOIN student s ON s.class_id = ra.class_id
        INNER JOIN class c ON c.class_id = ra.class_id
        WHERE ra.assignment_id = ? AND s.student_id = ? AND s.is_active = 1 AND ra.status = 'assigned' LIMIT 1
    ");
    $stmt->execute([$assignment_id, $student_id]);
    return $stmt->fetch() ?: null;
}

function get_assignment_materials(PDO $pdo, int $assignment_id): array {
    $stmt = $pdo->prepare("
        SELECT ram.*, rm.title AS material_title, rm.description, rm.language, rm.material_type, 
               rm.grade_level, rm.difficulty, rm.ocr_text, rm.total_words, rm.file_path,
               q.quiz_id, q.title AS quiz_title, q.instructions AS quiz_instructions, q.total_questions, q.status AS quiz_status
        FROM reading_assignment_material ram
        INNER JOIN reading_material rm ON rm.material_id = ram.material_id
        LEFT JOIN quiz q ON q.material_id = rm.material_id
        WHERE ram.assignment_id = ? ORDER BY ram.assigned_order ASC
    ");
    $stmt->execute([$assignment_id]);
    return $stmt->fetchAll();
}

function get_quiz_questions(PDO $pdo, int $quiz_id): array {
    $stmt = $pdo->prepare("SELECT * FROM quiz_question WHERE quiz_id = ? ORDER BY question_number ASC");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll();
    foreach ($questions as &$question) {
        $cStmt = $pdo->prepare("SELECT * FROM quiz_choice WHERE question_id = ? ORDER BY choice_label ASC");
        $cStmt->execute([$question['question_id']]);
        $question['choices'] = $cStmt->fetchAll();
    }
    return $questions;
}

function column_exists(PDO $pdo, string $table_name, string $column_name): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table_name, $column_name]);
    return (bool)$stmt->fetchColumn();
}

function validate_schema(): void {
    $pdo = get_db();
    try {
        $required_tables = [
            "teacher", "class", "student", "reading_material", "reading_assignment",
            "reading_assignment_material", "quiz", "quiz_question", "quiz_choice",
            "quiz_attempt", "quiz_answer", "reading_activity", "assessment_result"
        ];
        
        foreach ($required_tables as $table) {
            if (!table_exists($pdo, $table)) {
                throw new RuntimeException("Missing required ArchiveVox table: $table");
            }
        }
        
        $required_reading_activity_columns = [
            "student_id", "material_id", "assignment_material_id", "activity_date",
            "started_at", "attempt_number", "activity_status"
        ];
        
        foreach ($required_reading_activity_columns as $column) {
            if (!column_exists($pdo, "reading_activity", $column)) {
                throw new RuntimeException("reading_activity is missing required column: $column");
            }
        }
        
        $required_attempt_columns = [
            "quiz_id", "student_id", "assignment_material_id", "activity_id",
            "score", "total_questions", "percentage", "started_at", "completed_at", "status"
        ];
        
        foreach ($required_attempt_columns as $column) {
            if (!column_exists($pdo, "quiz_attempt", $column)) {
                throw new RuntimeException("quiz_attempt is missing required column: $column");
            }
        }
    } finally {
        $pdo = null;
    }
}

// ============================================================
// 4. ROUTER IMPLEMENTATION
// ============================================================

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Support query parameter routing for easier frontend integration
if (isset($_GET['endpoint'])) {
    $uri = $_GET['endpoint'];
    // Parse the endpoint to extract path and query string
    $parsed = parse_url($uri);
    $uri = $parsed['path'];
    // Merge query parameters from endpoint into $_GET
    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $endpoint_params);
        $_GET = array_merge($_GET, $endpoint_params);
    }
}

// Automatically add /api prefix if missing
if (strpos($uri, '/api') !== 0) {
    $uri = '/api' . $uri;
}

// Safely strip the local XAMPP folder for local testing. 
// On Railway, this does nothing, allowing the router to work perfectly at the root level.
$uri = preg_replace('#^/archivevox copy#', '', $uri);

// Simple routing switch
if ($method === 'GET' && $uri === '/api/health') {
    try {
        $pdo = get_db();
        $stmt = $pdo->query("SELECT 1 AS ok");
        success(['message' => 'ArchiveVox Assignment API is running.', 'database' => (bool)$stmt->fetchColumn()]);
    } catch (Exception $e) {
        error("Database connection failed: " . $e->getMessage(), 500);
    }

} elseif ($method === 'POST' && $uri === '/api/teacher/assignments') {
    $payload = get_json_payload();
    $teacher_id = $_GET['teacher_id'] ?? $payload['teacher_id'] ?? 0;
    $class_id = $payload['class_id'] ?? 0;
    $title = trim($payload['title'] ?? '');
    $materials = $payload['materials'] ?? [];
    
    if (!$teacher_id || !$class_id) error("teacher_id and class_id are required.");
    if (!$title) error("Assignment title is required.");
    if (!is_array($materials) || count($materials) < 1 || count($materials) > 2) error("An assignment must contain 1 to 2 materials.");

    $pdo = get_db();
    try {
        $pdo->beginTransaction();
        if (!teacher_owns_class($pdo, $teacher_id, $class_id)) throw new Exception("This class does not belong to the teacher.", 403);

        $validated_materials = [];
        foreach ($materials as $i => $mat) {
            $mat_id = is_array($mat) ? $mat['material_id'] : $mat;
            if (!teacher_owns_material($pdo, $teacher_id, $mat_id)) throw new Exception("Material $mat_id is unavailable.", 403);
            
            $stmt = $pdo->prepare("SELECT material_id, title FROM reading_material WHERE material_id = ?");
            $stmt->execute([$mat_id]);
            $material = $stmt->fetch();
            
            $stmt = $pdo->prepare("SELECT * FROM quiz WHERE material_id = ? LIMIT 1");
            $stmt->execute([$mat_id]);
            $quiz = $stmt->fetch();
            
            if (!$quiz || $quiz['status'] !== 'active' || $quiz['total_questions'] != 5) {
                throw new Exception("Material '{$material['title']}' must have an active quiz with exactly 5 questions.");
            }
            $validated_materials[] = ['material_id' => $mat_id, 'assigned_order' => $i + 1, 'quiz_id' => $quiz['quiz_id']];
        }

        $stmt = $pdo->prepare("INSERT INTO reading_assignment (teacher_id, class_id, title, instructions, due_date, status) VALUES (?, ?, ?, ?, ?, 'assigned')");
        $stmt->execute([$teacher_id, $class_id, $title, $payload['instructions'] ?? null, $payload['due_date'] ?? null]);
        $assignment_id = $pdo->lastInsertId();

        foreach ($validated_materials as $item) {
            $stmt = $pdo->prepare("INSERT INTO reading_assignment_material (assignment_id, material_id, assigned_order) VALUES (?, ?, ?)");
            $stmt->execute([$assignment_id, $item['material_id'], $item['assigned_order']]);
        }
        $pdo->commit();
        success(['message' => 'Assignment created successfully.', 'assignment_id' => $assignment_id, 'materials' => $validated_materials], 201);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error($e->getMessage(), $e->getCode() ?: 500);
    }

} elseif ($method === 'GET' && $uri === '/api/student/assignments') {
    $student_id = $_GET['student_id'] ?? null;
    if (!$student_id) error("student_id is required.");
    $student = get_student($student_id);
    if (!$student) error("Student not found.", 404);
    if (!$student['class_id']) success(['student' => $student, 'assignments' => []]);

    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT ra.*, c.grade_level, c.section, c.school_year FROM reading_assignment ra INNER JOIN class c ON c.class_id = ra.class_id WHERE ra.class_id = ? AND ra.status IN ('assigned', 'archived') ORDER BY ra.assigned_at DESC");
    $stmt->execute([$student['class_id']]);
    $assignments = $stmt->fetchAll();

    $all_materials = [];
    foreach ($assignments as &$assignment) {
        $materials = get_assignment_materials($pdo, $assignment['assignment_id']);
        $assignment['materials'] = $materials;
        foreach ($materials as $mat) $all_materials[] = $mat;
    }

    $material_ids = array_column($all_materials, 'assignment_material_id');
    if ($material_ids) {
        $placeholders = implode(',', array_fill(0, count($material_ids), '?'));

        $qStmt = $pdo->prepare("SELECT attempt_id, assignment_material_id, status, score, total_questions, percentage FROM quiz_attempt WHERE student_id = ? AND assignment_material_id IN ($placeholders) AND status = 'completed' ORDER BY attempt_id DESC");
        $qStmt->execute(array_merge([$student_id], $material_ids));
        $quiz_dict = [];
        foreach ($qStmt->fetchAll() as $qa) $quiz_dict[$qa['assignment_material_id']] ??= $qa;

        $rStmt = $pdo->prepare("SELECT ra.activity_id, ra.assignment_material_id, ra.activity_status, ar.accuracy_percentage, ar.wcpm FROM reading_activity ra LEFT JOIN assessment_result ar ON ar.activity_id = ra.activity_id WHERE ra.student_id = ? AND ra.assignment_material_id IN ($placeholders) AND ra.activity_status = 'Completed' ORDER BY ra.activity_id DESC");
        $rStmt->execute(array_merge([$student_id], $material_ids));
        $reading_dict = [];
        foreach ($rStmt->fetchAll() as $rr) $reading_dict[$rr['assignment_material_id']] ??= $rr;

        // Attach completion data directly to assignment materials
        foreach ($assignments as &$assignment) {
            foreach ($assignment['materials'] as &$mat) {
                $mat['quiz_attempt'] = $quiz_dict[$mat['assignment_material_id']] ?? null;
                $mat['reading_result'] = $reading_dict[$mat['assignment_material_id']] ?? null;
            }
        }
    }
    success(['student' => $student, 'assignments' => $assignments]);

} elseif ($method === 'POST' && preg_match('#^/api/student/assignments/(\d+)/materials/(\d+)/quiz/submit$#', $uri, $matches)) {
    $assignment_id = (int)$matches[1];
    $assignment_material_id = (int)$matches[2];
    $data = get_json_payload();
    $student_id = $data['student_id'] ?? null;
    $attempt_id = $data['attempt_id'] ?? null;
    $answers = $data['answers'] ?? [];

    if (!$student_id || !$attempt_id) error("student_id and attempt_id are required.");
    if (!is_array($answers)) error("answers must be an array.");

    $pdo = get_db();
    try {
        $pdo->beginTransaction();
        
        if (!get_assignment_for_student($pdo, $assignment_id, $student_id)) throw new Exception("Access denied.", 403);
        
        $stmt = $pdo->prepare("SELECT * FROM quiz_attempt WHERE attempt_id = ? AND student_id = ? AND assignment_material_id = ? LIMIT 1");
        $stmt->execute([$attempt_id, $student_id, $assignment_material_id]);
        $attempt = $stmt->fetch();
        if (!$attempt || $attempt['status'] !== 'in_progress') throw new Exception("Quiz attempt not active.", 400);

        $stmt = $pdo->prepare("SELECT question_id FROM quiz_question WHERE quiz_id = ?");
        $stmt->execute([$attempt['quiz_id']]);
        $valid_q_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $score = 0;
        $answered = [];
        foreach ($answers as $ans) {
            $q_id = $ans['question_id'] ?? null;
            $c_id = $ans['choice_id'] ?? null;
            if (!in_array($q_id, $valid_q_ids) || in_array($q_id, $answered)) continue;
            $answered[] = $q_id;
            
            $is_correct = 0;
            if ($c_id !== null) {
                $cStmt = $pdo->prepare("SELECT is_correct FROM quiz_choice WHERE choice_id = ? AND question_id = ? LIMIT 1");
                $cStmt->execute([$c_id, $q_id]);
                if ($cStmt->fetchColumn()) { $is_correct = 1; $score++; }
            }
            $iStmt = $pdo->prepare("INSERT INTO quiz_answer (attempt_id, question_id, selected_choice_id, is_correct, answered_at) VALUES (?, ?, ?, ?, NOW())");
            $iStmt->execute([$attempt_id, $q_id, $c_id, $is_correct]);
        }

        $total_questions = count($valid_q_ids);
        $percentage = $total_questions ? ($score / $total_questions * 100) : 0;

        $uStmt = $pdo->prepare("UPDATE quiz_attempt SET score = ?, total_questions = ?, percentage = ?, completed_at = NOW(), status = 'completed' WHERE attempt_id = ?");
        $uStmt->execute([$score, $total_questions, $percentage, $attempt_id]);
        $pdo->commit();
        
        success(['attempt_id' => $attempt_id, 'score' => $score, 'total_questions' => $total_questions, 'percentage' => $percentage, 'status' => 'completed']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error($e->getMessage(), $e->getCode() ?: 500);
    }

} elseif ($method === 'POST' && preg_match('#^/api/teacher/materials/(\d+)/quiz$#', $uri, $matches)) {
    $material_id = (int)$matches[1];
    $teacher_id = $_GET['teacher_id'] ?? null;
    $payload = get_json_payload();
    if (!$teacher_id) error("teacher_id is required.");
    
    $title = $payload['title'] ?? 'Comprehension Quiz';
    $questions = $payload['questions'] ?? [];
    if (count($questions) !== 5) error("Exactly 5 questions are required.");

    $pdo = get_db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT quiz_id FROM quiz WHERE material_id = ? LIMIT 1");
        $stmt->execute([$material_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            $quiz_id = $existing['quiz_id'];
            $pdo->prepare("UPDATE quiz SET title = ?, status = 'active' WHERE quiz_id = ?")->execute([$title, $quiz_id]);
            
            $qStmt = $pdo->prepare("SELECT question_id, question_number FROM quiz_question WHERE quiz_id = ?");
            $qStmt->execute([$quiz_id]);
            $q_map = array_column($qStmt->fetchAll(), 'question_id', 'question_number');
            
            foreach ($questions as $q) {
                if (isset($q_map[$q['number']])) {
                    $q_id = $q_map[$q['number']];
                    $pdo->prepare("UPDATE quiz_question SET question_text = ? WHERE question_id = ?")->execute([$q['text'], $q_id]);
                    foreach ($q['choices'] as $choice) {
                        $pdo->prepare("UPDATE quiz_choice SET choice_text = ?, is_correct = ? WHERE question_id = ? AND choice_label = ?")
                            ->execute([$choice['text'], $choice['is_correct'], $q_id, $choice['label']]);
                    }
                } else {
                    $pdo->prepare("INSERT INTO quiz_question (quiz_id, question_number, question_text) VALUES (?, ?, ?)")->execute([$quiz_id, $q['number'], $q['text']]);
                    $q_id = $pdo->lastInsertId();
                    foreach ($q['choices'] as $choice) {
                        $pdo->prepare("INSERT INTO quiz_choice (question_id, choice_label, choice_text, is_correct) VALUES (?, ?, ?, ?)")
                            ->execute([$q_id, $choice['label'], $choice['text'], $choice['is_correct']]);
                    }
                }
            }
        } else {
            $pdo->prepare("INSERT INTO quiz (material_id, title, total_questions, status) VALUES (?, ?, 5, 'active')")->execute([$material_id, $title]);
            $quiz_id = $pdo->lastInsertId();
            foreach ($questions as $q) {
                $pdo->prepare("INSERT INTO quiz_question (quiz_id, question_number, question_text) VALUES (?, ?, ?)")->execute([$quiz_id, $q['number'], $q['text']]);
                $q_id = $pdo->lastInsertId();
                foreach ($q['choices'] as $choice) {
                    $pdo->prepare("INSERT INTO quiz_choice (question_id, choice_label, choice_text, is_correct) VALUES (?, ?, ?, ?)")
                        ->execute([$q_id, $choice['label'], $choice['text'], $choice['is_correct']]);
                }
            }
        }
        $pdo->commit();
        success(['message' => 'Quiz saved successfully', 'quiz_id' => $quiz_id]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error("Failed to save quiz: " . $e->getMessage(), 500);
    }

} elseif ($method === 'POST' && $uri === '/api/setup') {
    if (!DEV_MODE) error("Setup endpoint is disabled.", 403);
    try {
        validate_schema();
        success(['message' => 'Existing ArchiveVox assignment schema validated successfully.']);
    } catch (Exception $e) {
        error("Schema validation failed: " . $e->getMessage(), 500);
    }

} elseif ($method === 'GET' && $uri === '/api/teacher/assignments') {
    $teacher_id = $_GET['teacher_id'] ?? null;
    if (!$teacher_id) error("teacher_id is required.");
    
    $pdo = get_db();
    try {
        $stmt = $pdo->prepare("
            SELECT ra.assignment_id, ra.title, ra.instructions, ra.status, ra.assigned_at, ra.due_date,
                   ra.class_id, c.grade_level, c.section, c.school_year
            FROM reading_assignment ra
            INNER JOIN class c ON c.class_id = ra.class_id
            WHERE ra.teacher_id = ?
            ORDER BY ra.assigned_at DESC
        ");
        $stmt->execute([$teacher_id]);
        $assignments = $stmt->fetchAll();
        
        foreach ($assignments as &$assignment) {
            $materials = get_assignment_materials($pdo, $assignment['assignment_id']);
            $assignment['material_titles'] = implode(', ', array_column($materials, 'material_title'));
            $assignment['materials'] = $materials;
        }
        success(['assignments' => $assignments]);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'GET' && preg_match('#^/api/teacher/assignments/(\d+)$#', $uri, $matches)) {
    $assignment_id = (int)$matches[1];
    $teacher_id = $_GET['teacher_id'] ?? null;
    if (!$teacher_id) error("teacher_id is required.");
    
    $pdo = get_db();
    try {
        $stmt = $pdo->prepare("
            SELECT assignment_id, teacher_id, class_id, title, instructions, assigned_at, due_date, status
            FROM reading_assignment
            WHERE assignment_id = ? AND teacher_id = ?
        ");
        $stmt->execute([$assignment_id, $teacher_id]);
        $assignment = $stmt->fetch();
        if (!$assignment) error("Assignment not found or not owned by this teacher.", 404);
        
        $assignment['materials'] = get_assignment_materials($pdo, $assignment_id);
        success(['assignment' => $assignment]);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'PUT' && preg_match('#^/api/teacher/assignments/(\d+)$#', $uri, $matches)) {
    $assignment_id = (int)$matches[1];
    $teacher_id = $_GET['teacher_id'] ?? null;
    if (!$teacher_id) error("teacher_id is required.");
    
    $payload = get_json_payload();
    $title = $payload['title'] ?? null;
    $instructions = $payload['instructions'] ?? null;
    $due_date = $payload['due_date'] ?? null;
    
    if (!$title) error("Title is required.");
    
    $pdo = get_db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT 1 FROM reading_assignment WHERE assignment_id = ? AND teacher_id = ?");
        $stmt->execute([$assignment_id, $teacher_id]);
        if (!$stmt->fetch()) error("Assignment not found or not owned by this teacher.", 404);
        
        $stmt = $pdo->prepare("UPDATE reading_assignment SET title = ?, instructions = ?, due_date = ? WHERE assignment_id = ? AND teacher_id = ?");
        $stmt->execute([$title, $instructions, $due_date, $assignment_id, $teacher_id]);
        $pdo->commit();
        success(['message' => 'Assignment updated successfully.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error($e->getMessage(), 500);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'DELETE' && preg_match('#^/api/teacher/assignments/(\d+)$#', $uri, $matches)) {
    $assignment_id = (int)$matches[1];
    $teacher_id = $_GET['teacher_id'] ?? null;
    if (!$teacher_id) error("teacher_id is required.");

    $pdo = get_db();
    try {
        $pdo->beginTransaction();

        // Verify ownership
        $stmt = $pdo->prepare("SELECT assignment_id FROM reading_assignment WHERE assignment_id = ? AND teacher_id = ?");
        $stmt->execute([$assignment_id, $teacher_id]);
        if (!$stmt->fetch()) error("Assignment not found or not owned.", 404);

        // Delete assignment materials
        $stmt = $pdo->prepare("DELETE FROM reading_assignment_material WHERE assignment_id = ?");
        $stmt->execute([$assignment_id]);

        // Delete quiz attempts and answers for this assignment
        $stmt = $pdo->prepare("
            DELETE qa FROM quiz_answer qa
            INNER JOIN quiz_attempt q ON qa.attempt_id = q.attempt_id
            INNER JOIN reading_assignment_material ram ON q.assignment_material_id = ram.assignment_material_id
            WHERE ram.assignment_id = ?
        ");
        $stmt->execute([$assignment_id]);

        $stmt = $pdo->prepare("
            DELETE q FROM quiz_attempt q
            INNER JOIN reading_assignment_material ram ON q.assignment_material_id = ram.assignment_material_id
            WHERE ram.assignment_id = ?
        ");
        $stmt->execute([$assignment_id]);

        // Delete reading activities and assessment results for this assignment
        $stmt = $pdo->prepare("
            DELETE ar FROM assessment_result ar
            INNER JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            INNER JOIN reading_assignment_material ram ON ra.assignment_material_id = ram.assignment_material_id
            WHERE ram.assignment_id = ?
        ");
        $stmt->execute([$assignment_id]);

        $stmt = $pdo->prepare("
            DELETE ra FROM reading_activity ra
            INNER JOIN reading_assignment_material ram ON ra.assignment_material_id = ram.assignment_material_id
            WHERE ram.assignment_id = ?
        ");
        $stmt->execute([$assignment_id]);

        // Delete the assignment
        $stmt = $pdo->prepare("DELETE FROM reading_assignment WHERE assignment_id = ?");
        $stmt->execute([$assignment_id]);

        $pdo->commit();
        success(['message' => 'Assignment deleted successfully.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error("Failed to delete assignment: " . $e->getMessage(), 500);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'GET' && preg_match('#^/api/teacher/assignments/(\d+)/results$#', $uri, $matches)) {
    $assignment_id = (int)$matches[1];
    $teacher_id = $_GET['teacher_id'] ?? null;
    if (!$teacher_id) error("teacher_id is required.");
    
    $pdo = get_db();
    try {
        $stmt = $pdo->prepare("SELECT ra.assignment_id, ra.title, ra.class_id FROM reading_assignment ra WHERE ra.assignment_id = ? AND ra.teacher_id = ? LIMIT 1");
        $stmt->execute([$assignment_id, $teacher_id]);
        if (!$stmt->fetch()) error("Assignment not found or not owned by this teacher.", 404);
        
        $stmt = $pdo->prepare("
            SELECT s.student_id, s.lrn, s.first_name, s.last_name, c.grade_level, c.section,
                   ra.assignment_id, ra.title AS assignment_title,
                   (SELECT JSON_OBJECT(
                      'score', qa.score, 'total_questions', qa.total_questions, 'percentage', qa.percentage
                    ) FROM quiz_attempt qa
                    INNER JOIN reading_assignment_material ram ON ram.assignment_material_id = qa.assignment_material_id
                    WHERE qa.student_id = s.student_id AND ram.assignment_id = ? AND qa.status = 'completed'
                    ORDER BY qa.percentage DESC
                    LIMIT 1) AS quiz_result,
                   (SELECT JSON_OBJECT(
                      'accuracy_percentage', ar.accuracy_percentage, 'wcpm', ar.wcpm
                    ) FROM reading_activity ract
                    INNER JOIN reading_assignment_material ram ON ram.assignment_material_id = ract.assignment_material_id
                    LEFT JOIN assessment_result ar ON ar.activity_id = ract.activity_id
                    WHERE ract.student_id = s.student_id AND ram.assignment_id = ? AND ract.activity_status = 'Completed'
                    ORDER BY ar.accuracy_percentage DESC, ar.wcpm DESC
                    LIMIT 1) AS reading_result
            FROM student s
            INNER JOIN class c ON c.class_id = s.class_id
            INNER JOIN reading_assignment ra ON ra.class_id = c.class_id
            WHERE ra.assignment_id = ? AND s.is_active = 1
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute([$assignment_id, $assignment_id, $assignment_id]);
        $results = $stmt->fetchAll();
        success(['assignment_id' => $assignment_id, 'results' => $results]);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'GET' && preg_match('#^/api/student/assignments/(\d+)$#', $uri, $matches)) {
    $assignment_id = (int)$matches[1];
    $student_id = $_GET['student_id'] ?? null;
    if (!$student_id) error("student_id is required.");
    
    $pdo = get_db();
    try {
        $assignment = get_assignment_for_student($pdo, $assignment_id, $student_id);
        if (!$assignment) error("Assignment not found or not assigned to this student's class.", 404);

        $materials = get_assignment_materials($pdo, $assignment_id);

        // Get student's completed reading activities for this assignment
        $material_ids = array_column($materials, 'assignment_material_id');
        if ($material_ids) {
            $placeholders = implode(',', array_fill(0, count($material_ids), '?'));
            $rStmt = $pdo->prepare("
                SELECT ra.assignment_material_id, ra.activity_id, ra.activity_status
                FROM reading_activity ra
                WHERE ra.student_id = ? AND ra.assignment_material_id IN ($placeholders) AND ra.activity_status = 'Completed'
            ");
            $rStmt->execute(array_merge([$student_id], $material_ids));
            $reading_results = [];
            foreach ($rStmt->fetchAll() as $row) {
                $reading_results[$row['assignment_material_id']] = $row;
            }

            // Get student's completed quiz attempts for this assignment
            $qStmt = $pdo->prepare("
                SELECT qa.assignment_material_id, qa.attempt_id, qa.status, qa.score, qa.total_questions, qa.percentage
                FROM quiz_attempt qa
                WHERE qa.student_id = ? AND qa.assignment_material_id IN ($placeholders) AND qa.status = 'completed'
            ");
            $qStmt->execute(array_merge([$student_id], $material_ids));
            $quiz_results = $qStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC); // assignment_material_id => array of attempts
        }

        foreach ($materials as &$mat) {
            if ($mat['quiz_id']) {
                $mat['quiz']['questions'] = get_quiz_questions($pdo, $mat['quiz_id']);
            }

            // Attach reading activity result if exists
            $assignment_material_id = $mat['assignment_material_id'];
            if (isset($reading_results[$assignment_material_id])) {
                $mat['reading_result'] = [
                    'activity_id' => $reading_results[$assignment_material_id],
                    'activity_status' => 'Completed'
                ];
            }

            // Attach quiz attempt if exists
            if (isset($quiz_results[$assignment_material_id])) {
                $mat['quiz_attempt'] = $quiz_results[$assignment_material_id][0]; // Take the most recent
            }
        }
        $assignment['materials'] = $materials;
        success(['assignment' => $assignment]);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'POST' && preg_match('#^/api/student/assignments/(\d+)/materials/(\d+)/start-reading$#', $uri, $matches)) {
    $assignment_id = (int)$matches[1];
    $assignment_material_id = (int)$matches[2];
    $data = get_json_payload();
    $student_id = $data['student_id'] ?? null;
    
    if (!$student_id) error("student_id is required.");
    
    $pdo = get_db();
    try {
        $pdo->beginTransaction();
        
        if (!get_assignment_for_student($pdo, $assignment_id, $student_id)) throw new Exception("Assignment access denied.", 403);
        
        $stmt = $pdo->prepare("SELECT ram.assignment_material_id, ram.material_id, ram.assigned_order FROM reading_assignment_material ram WHERE ram.assignment_material_id = ? AND ram.assignment_id = ? LIMIT 1");
        $stmt->execute([$assignment_material_id, $assignment_id]);
        $assignment_material = $stmt->fetch();
        if (!$assignment_material) throw new Exception("This material is not part of this assignment.", 403);
        
        $material_id = $assignment_material['material_id'];
        
        $stmt = $pdo->prepare("SELECT MAX(attempt_number) AS max_attempt FROM reading_activity WHERE student_id = ? AND material_id = ?");
        $stmt->execute([$student_id, $material_id]);
        $row = $stmt->fetch();
        $next_attempt = ($row['max_attempt'] ?? 0) + 1;
        
        $stmt = $pdo->prepare("INSERT INTO reading_activity (student_id, material_id, assignment_material_id, activity_date, started_at, attempt_number, activity_status) VALUES (?, ?, ?, CURDATE(), NOW(), ?, 'In Progress')");
        $stmt->execute([$student_id, $material_id, $assignment_material_id, $next_attempt]);
        $activity_id = $pdo->lastInsertId();
        
        $pdo->commit();
        success(['activity_id' => $activity_id, 'assignment_id' => $assignment_id, 'material_id' => $material_id, 'attempt_number' => $next_attempt]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error($e->getMessage(), $e->getCode() ?: 500);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'POST' && preg_match('#^/api/student/assignments/(\d+)/materials/(\d+)/quiz/start$#', $uri, $matches)) {
    $assignment_id = (int)$matches[1];
    $assignment_material_id = (int)$matches[2];
    $data = get_json_payload();
    $student_id = $data['student_id'] ?? null;
    
    if (!$student_id) error("student_id is required.");
    
    $pdo = get_db();
    try {
        $pdo->beginTransaction();
        
        if (!get_assignment_for_student($pdo, $assignment_id, $student_id)) throw new Exception("Assignment access denied.", 403);
        
        $stmt = $pdo->prepare("SELECT ram.assignment_material_id, ram.material_id FROM reading_assignment_material ram WHERE ram.assignment_material_id = ? AND ram.assignment_id = ? LIMIT 1");
        $stmt->execute([$assignment_material_id, $assignment_id]);
        $assignment_material = $stmt->fetch();
        if (!$assignment_material) throw new Exception("This material is not part of this assignment.", 403);
        
        $material_id = $assignment_material['material_id'];
        
        $stmt = $pdo->prepare("SELECT quiz_id FROM quiz WHERE material_id = ? LIMIT 1");
        $stmt->execute([$material_id]);
        $quiz = $stmt->fetch();
        if (!$quiz) throw new Exception("No quiz found for this material.", 404);
        
        $stmt = $pdo->prepare("SELECT attempt_id FROM quiz_attempt WHERE student_id = ? AND assignment_material_id = ? AND status = 'in_progress' LIMIT 1");
        $stmt->execute([$student_id, $assignment_material_id]);
        $existing = $stmt->fetch();
        if ($existing) throw new Exception("You already have a quiz in progress for this material.", 400);
        
        $stmt = $pdo->prepare("INSERT INTO quiz_attempt (quiz_id, student_id, assignment_material_id, activity_id, score, total_questions, percentage, started_at, status) VALUES (?, ?, ?, NULL, 0, 0, 0, NOW(), 'in_progress')");
        $stmt->execute([$quiz['quiz_id'], $student_id, $assignment_material_id]);
        $attempt_id = $pdo->lastInsertId();
        
        $questions = get_quiz_questions($pdo, $quiz['quiz_id']);
        
        $pdo->commit();
        success(['attempt_id' => $attempt_id, 'quiz_id' => $quiz['quiz_id'], 'questions' => $questions]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error($e->getMessage(), $e->getCode() ?: 500);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'GET' && preg_match('#^/api/student/assignments/(\d+)/materials/(\d+)/reading-result$#', $uri, $matches)) {
    $assignment_id = (int)$matches[1];
    $assignment_material_id = (int)$matches[2];
    $student_id = $_GET['student_id'] ?? null;
    if (!$student_id) error("student_id is required.");
    
    $pdo = get_db();
    try {
        if (!get_assignment_for_student($pdo, $assignment_id, $student_id)) error("Assignment access denied.", 403);
        
        $stmt = $pdo->prepare("SELECT ram.material_id FROM reading_assignment_material ram WHERE ram.assignment_material_id = ? AND ram.assignment_id = ? LIMIT 1");
        $stmt->execute([$assignment_material_id, $assignment_id]);
        $material = $stmt->fetch();
        if (!$material) error("Invalid assignment material.", 404);
        
        $stmt = $pdo->prepare("
            SELECT ra.activity_id, ra.activity_status, ra.started_at, ra.finished_at,
                   ar.assessment_id, ar.total_words, ar.words_correct, ar.accuracy_percentage,
                   ar.wcpm, ar.reading_time_seconds, ar.part1_total_score, ar.part1_reading_level,
                   ar.comprehension_score, ar.final_reading_level, ar.observation_level, ar.assessed_at
            FROM reading_activity ra
            LEFT JOIN assessment_result ar ON ar.activity_id = ra.activity_id
            WHERE ra.student_id = ? AND ra.assignment_material_id = ?
            ORDER BY ra.activity_id DESC
            LIMIT 1
        ");
        $stmt->execute([$student_id, $assignment_material_id]);
        $result = $stmt->fetch();
        
        if (!$result) error("No reading result found.", 404);
        success(['result' => $result]);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'POST' && preg_match('#^/api/student/activities/(\d+)/save-result$#', $uri, $matches)) {
    $activity_id = (int)$matches[1];
    $metrics = get_json_payload();
    
    $pdo = get_db();
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE reading_activity SET activity_status = 'Completed', finished_at = NOW() WHERE activity_id = ?");
        $stmt->execute([$activity_id]);
        
        $stmt = $pdo->prepare("DELETE FROM assessment_result WHERE activity_id = ?");
        $stmt->execute([$activity_id]);
        
        $stmt = $pdo->prepare("
            INSERT INTO assessment_result (activity_id, total_words, words_correct, accuracy_percentage, wcpm, reading_level, assessed_at, transcript, substitutions, omissions, insertions)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
        ");
        $stmt->execute([
            $activity_id,
            $metrics['total_words'] ?? 0,
            $metrics['words_correct'] ?? 0,
            $metrics['accuracy_percentage'] ?? 0,
            $metrics['wcpm'] ?? 0,
            $metrics['reading_level'] ?? 'Pending',
            $metrics['transcript'] ?? '',
            $metrics['substitutions'] ?? 0,
            $metrics['omissions'] ?? 0,
            $metrics['insertions'] ?? 0
        ]);
        
        $pdo->commit();
        success(['message' => 'Assessment saved successfully!']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error($e->getMessage(), 500);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'GET' && preg_match('#^/api/teacher/materials/(\d+)/quiz$#', $uri, $matches)) {
    $material_id = (int)$matches[1];
    $teacher_id = $_GET['teacher_id'] ?? null;
    if (!$teacher_id) error("teacher_id is required.");
    
    $pdo = get_db();
    try {
        $stmt = $pdo->prepare("SELECT quiz_id, title FROM quiz WHERE material_id = ? LIMIT 1");
        $stmt->execute([$material_id]);
        $quiz = $stmt->fetch();
        
        if (!$quiz) success(['quiz' => null]);
        
        $stmt = $pdo->prepare("SELECT question_id, question_number, question_text FROM quiz_question WHERE quiz_id = ? ORDER BY question_number");
        $stmt->execute([$quiz['quiz_id']]);
        $questions = $stmt->fetchAll();
        
        foreach ($questions as &$q) {
            $cStmt = $pdo->prepare("SELECT choice_label, choice_text, is_correct FROM quiz_choice WHERE question_id = ? ORDER BY choice_label");
            $cStmt->execute([$q['question_id']]);
            $q['choices'] = $cStmt->fetchAll();
        }
        
        $quiz['questions'] = $questions;
        success(['quiz' => $quiz]);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'GET' && $uri === '/api/teacher/materials') {
    $teacher_id = $_GET['teacher_id'] ?? null;
    if (!$teacher_id) error("teacher_id is required.");
    
    $pdo = get_db();
    try {
        $stmt = $pdo->prepare("
            SELECT material_id, title, grade_level, language, material_type
            FROM reading_material
            WHERE status NOT IN ('Archived', 'Deleted', 'archived', 'deleted')
            ORDER BY title ASC
        ");
        $stmt->execute();
        $materials = $stmt->fetchAll();
        success(['materials' => $materials]);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'GET' && $uri === '/api/teacher/classes') {
    $teacher_id = $_GET['teacher_id'] ?? null;
    if (!$teacher_id) error("teacher_id is required.");
    
    $pdo = get_db();
    try {
        $stmt = $pdo->prepare("
            SELECT c.class_id, c.grade_level, c.section, c.school_year, COUNT(s.student_id) AS student_count
            FROM class c
            LEFT JOIN student s ON s.class_id = c.class_id AND s.is_active = 1
            WHERE c.teacher_id = ?
            GROUP BY c.class_id, c.grade_level, c.section, c.school_year
            ORDER BY c.grade_level, c.section
        ");
        $stmt->execute([$teacher_id]);
        $classes = $stmt->fetchAll();
        success(['classes' => $classes]);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'POST' && $uri === '/api/dev/seed-test-assignment') {
    if (!DEV_MODE) error("Development endpoint disabled.", 403);
    
    $pdo = get_db();
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT student_id FROM student WHERE lrn = '2024-0001' LIMIT 1");
        $stmt->execute();
        $student = $stmt->fetch();
        if (!$student) throw new Exception("Test student not found.");
        
        $stmt = $pdo->prepare("SELECT class_id FROM class WHERE grade_level = 4 AND section = 'A' LIMIT 1");
        $stmt->execute();
        $class = $stmt->fetch();
        if (!$class) throw new Exception("Test class not found.");
        
        $stmt = $pdo->prepare("SELECT material_id FROM reading_material WHERE title LIKE '%Ang Pamilya%' LIMIT 1");
        $stmt->execute();
        $material = $stmt->fetch();
        if (!$material) throw new Exception("Test material not found.");
        
        $stmt = $pdo->prepare("SELECT teacher_id FROM class WHERE class_id = ? LIMIT 1");
        $stmt->execute([$class['class_id']]);
        $teacher = $stmt->fetch();
        
        $stmt = $pdo->prepare("INSERT INTO reading_assignment (teacher_id, class_id, title, instructions, status) VALUES (?, ?, 'Reading Practice #1', 'Test assignment', 'assigned')");
        $stmt->execute([$teacher['teacher_id'], $class['class_id']]);
        $assignment_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO reading_assignment_material (assignment_id, material_id, assigned_order) VALUES (?, ?, 1)");
        $stmt->execute([$assignment_id, $material['material_id']]);
        
        $pdo->commit();
        success(['message' => 'Test assignment seeded.', 'assignment_id' => $assignment_id]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error($e->getMessage(), 500);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'GET' && preg_match('#^/api/student/(\d+)/completed$#', $uri, $matches)) {
    $student_id = (int)$matches[1];
    
    $pdo = get_db();
    try {
        $stmt = $pdo->prepare("
            SELECT ra.activity_id, ra.activity_status, ra.started_at, ra.finished_at,
                   rm.title AS material_title, rm.material_type,
                   rtitle.title AS assignment_title,
                   MAX(ar.accuracy_percentage) AS accuracy_percentage,
                   MAX(ar.wcpm) AS wcpm,
                   MAX(ar.reading_time_seconds) AS reading_time_seconds,
                   MAX(ar.final_reading_level) AS final_reading_level,
                   MAX(qa.score) AS score, MAX(qa.total_questions) AS total_questions, MAX(qa.percentage) AS quiz_percentage
            FROM reading_activity ra
            INNER JOIN reading_material rm ON rm.material_id = ra.material_id
            INNER JOIN reading_assignment_material ram ON ram.assignment_material_id = ra.assignment_material_id
            INNER JOIN reading_assignment rtitle ON rtitle.assignment_id = ram.assignment_id
            LEFT JOIN assessment_result ar ON ar.activity_id = ra.activity_id
            LEFT JOIN quiz_attempt qa ON qa.assignment_material_id = ra.assignment_material_id AND qa.student_id = ra.student_id AND LOWER(qa.status) = 'completed'
            WHERE ra.student_id = ? AND ra.activity_status = 'Completed'
            GROUP BY ra.activity_id, ra.activity_status, ra.started_at, ra.finished_at,
                     rm.title, rm.material_type, rtitle.title
            ORDER BY ra.finished_at DESC
        ");
        $stmt->execute([$student_id]);
        $activities = $stmt->fetchAll();
        success(['completed_assignments' => $activities]);
    } finally {
        $pdo = null;
    }

} elseif ($method === 'GET' && preg_match('#^/api/shared/activity/(\d+)/details$#', $uri, $matches)) {
    $activity_id = (int)$matches[1];
    
    $pdo = get_db();
    try {
        $stmt = $pdo->prepare("
            SELECT ra.*, s.student_id, s.first_name, s.last_name, rm.title AS material_title,
                   ar.assessment_id, ar.total_words, ar.words_correct, ar.accuracy_percentage, ar.wcpm
            FROM reading_activity ra
            INNER JOIN student s ON s.student_id = ra.student_id
            INNER JOIN reading_material rm ON rm.material_id = ra.material_id
            LEFT JOIN assessment_result ar ON ar.activity_id = ra.activity_id
            WHERE ra.activity_id = ?
        ");
        $stmt->execute([$activity_id]);
        $activity = $stmt->fetch();
        if (!$activity) error("Activity not found.", 404);
        success(['activity' => $activity]);
    } finally {
        $pdo = null;
    }

} else {
    // Fallback error for unmatched routes
    error("API endpoint not found.", 404);
}