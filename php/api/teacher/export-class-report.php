<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../_helpers.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="class_report_' . date('Y-m-d') . '.csv"');

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

// Authentication
$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$role = $user['role'];
$teacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$gradeLevel = isset($_GET['grade_level']) ? trim($_GET['grade_level']) : '';
$language = isset($_GET['language']) ? trim($_GET['language']) : '';

// Validate parameters
if (!$classId && !$gradeLevel) {
    http_response_code(400);
    echo 'Please provide class_id or grade_level';
    exit;
}

// If teacher, ensure they have access to the class/grade
if ($role === 'teacher') {
    // Get teacher's own ID if not provided
    if (!$teacherId) {
        $stmt = $pdo->prepare('SELECT teacher_id FROM teacher WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $user['user_id']]);
        $teacher = $stmt->fetch();
        if (!$teacher) {
            http_response_code(403);
            echo 'Teacher record not found';
            exit;
        }
        $teacherId = $teacher['teacher_id'];
    }

    // If class_id is given, verify the teacher owns that class
    if ($classId) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM class WHERE class_id = :class_id AND teacher_id = :teacher_id');
        $stmt->execute([':class_id' => $classId, ':teacher_id' => $teacherId]);
        if ($stmt->fetchColumn() == 0) {
            http_response_code(403);
            echo 'Access denied to this class';
            exit;
        }
    }
    // If grade_level is given, verify teacher has students in that grade
    else if ($gradeLevel) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) 
            FROM student s
            JOIN student_category sc ON s.category_id = sc.category_id
            WHERE s.teacher_id = :teacher_id AND sc.grade_level = :grade_level
        ');
        $stmt->execute([':teacher_id' => $teacherId, ':grade_level' => $gradeLevel]);
        if ($stmt->fetchColumn() == 0) {
            http_response_code(403);
            echo 'No students found in this grade for this teacher';
            exit;
        }
    }
}

// Build base query for students
$studentQuery = "
    SELECT 
        s.student_id,
        s.lrn,
        s.first_name,
        s.last_name,
        s.gender,
        sc.grade_level,
        c.class_name,
        s.date_registered
    FROM student s
    LEFT JOIN student_category sc ON s.category_id = sc.category_id
    LEFT JOIN class c ON s.class_id = c.class_id
    WHERE s.is_active = 1
";

$params = [];
if ($teacherId) {
    $studentQuery .= " AND s.teacher_id = :teacher_id";
    $params[':teacher_id'] = $teacherId;
}
if ($classId) {
    $studentQuery .= " AND s.class_id = :class_id";
    $params[':class_id'] = $classId;
}
if ($gradeLevel) {
    $studentQuery .= " AND sc.grade_level = :grade_level";
    $params[':grade_level'] = $gradeLevel;
}

$studentStmt = $pdo->prepare($studentQuery);
$studentStmt->execute($params);
$students = $studentStmt->fetchAll();

if (empty($students)) {
    http_response_code(404);
    echo 'No students found';
    exit;
}

// Now for each student, get the latest assessment for the chosen language (or any)
$languageCondition = '';
$langParams = [];
if ($language) {
    $languageCondition = " AND rm.language = :language";
    $langParams[':language'] = $language;
}

// Prepare output rows
$rows = [];
$sn = 1;
foreach ($students as $student) {
    // Fetch the most recent completed assessment for this student with the matching language
    $assessStmt = $pdo->prepare("
        SELECT 
            ar.*,
            ra.activity_date,
            rm.title as material_title,
            rm.language,
            rm.grade_level as material_grade
        FROM assessment_result ar
        JOIN reading_activity ra ON ar.activity_id = ra.activity_id
        JOIN reading_material rm ON ra.material_id = rm.material_id
        WHERE ra.student_id = :student_id
          AND ra.activity_status = 'Completed'
          $languageCondition
        ORDER BY ar.assessed_at DESC
        LIMIT 1
    ");
    $combinedParams = array_merge([':student_id' => $student['student_id']], $langParams);
    $assessStmt->execute($combinedParams);
    $assessment = $assessStmt->fetch();

    // Default values if no assessment
    $row = [
        'S/N' => $sn++,
        'LRN' => $student['lrn'] ?? '',
        'Name' => trim($student['first_name'] . ' ' . $student['last_name']),
        'Sex' => $student['gender'] ?? '',
        'Grade' => $student['grade_level'] ?? '',
        'Section' => $student['class_name'] ?? '',
        'Date of Assessment' => $assessment ? date('Y-m-d', strtotime($assessment['assessed_at'])) : '',
        'Task 1 (10)' => $assessment['part1_task1_score'] ?? '',
        'Task 2L Words (10)' => $assessment['part1_words_score'] ?? '',
        'Task 2H Sentences (10)' => $assessment ? ($assessment['part1_total_score'] - $assessment['part1_task1_score'] - $assessment['part1_words_score']) : '',
        'Part 1 Total Score' => $assessment['part1_total_score'] ?? '',
        'Part 1 Reading Level' => $assessment['part1_reading_level'] ?? '',
        'Story Number' => $assessment['story_number'] ?? '',
        'Number of Miscue' => $assessment['miscues'] ?? 0,
        'Words Read' => $assessment['words_read'] ?? 0,
        'Minutes' => $assessment['minutes'] ?? 0,
        'Seconds' => $assessment['seconds'] ?? 0,
        'WPM' => $assessment['wcpm'] ?? 0,
        '% Correct Words' => $assessment['accuracy_percentage'] ?? 0,
        'Comprehension Score' => $assessment['comprehension_score'] ?? 0,
        'Observation Level' => $assessment['observation_level'] ?? '',
        'Reading Profile' => $assessment['final_reading_level'] ?? '',
        'Remarks' => $assessment['teacher_feedback'] ?? '',
    ];
    $rows[] = $row;
}

// Output CSV
$output = fopen('php://output', 'w');
fputcsv($output, array_keys($rows[0])); // headers
foreach ($rows as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit;