<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../_helpers.php';

// IMPORTANT: Requires PhpSpreadsheet via Composer
require_once __DIR__ . '/../../../vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

// The frontend modal passes class_id and assignment_id
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if (!$classId || !$assignmentId) {
    http_response_code(400);
    echo 'Class ID and Assignment ID are required.';
    exit;
}

// If teacher, verify they have access to this specific class
if ($user['role'] === 'teacher') {
    $stmt = $pdo->prepare('SELECT teacher_id FROM teacher WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $user['user_id']]);
    $teacher = $stmt->fetch();
    
    if (!$teacher) {
        http_response_code(403);
        echo 'Teacher record not found';
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM class WHERE class_id = :class_id AND teacher_id = :teacher_id');
    $stmt->execute([':class_id' => $classId, ':teacher_id' => $teacher['teacher_id']]);
    if ($stmt->fetchColumn() == 0) {
        http_response_code(403);
        echo 'Access denied to this class';
        exit;
    }
}

// Fetch all active students in the class with their Grade and Section
$studentStmt = $pdo->prepare("
    SELECT 
        s.student_id,
        s.lrn,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.gender,
        c.grade_level,
        c.section
    FROM student s
    JOIN class c ON s.class_id = c.class_id
    WHERE s.is_active = 1 AND s.class_id = :class_id
    ORDER BY s.gender DESC, s.last_name ASC, s.first_name ASC
");
$studentStmt->execute([':class_id' => $classId]);
$students = $studentStmt->fetchAll();

if (empty($students)) {
    http_response_code(404);
    echo 'No students found in this class.';
    exit;
}

// Fetch Materials assigned to this Assignment (So we get the Story Title)
$matStmt = $pdo->prepare("
    SELECT ram.assignment_material_id, rm.title AS story_title
    FROM reading_assignment_material ram
    JOIN reading_material rm ON rm.material_id = ram.material_id
    WHERE ram.assignment_id = :assignment_id
    ORDER BY ram.assigned_order ASC
");
$matStmt->execute([':assignment_id' => $assignmentId]);
$materials = $matStmt->fetchAll();

// Create a new Spreadsheet from scratch
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('ArchiveVox Reading Scoresheet');

// Define Column Headers (Row 1) exactly as requested
$headers = [
    'A1' => 'S/N',
    'B1' => 'LRN',
    'C1' => 'Name',
    'D1' => 'Sex',
    'E1' => 'Grade',
    'F1' => 'Section',
    'G1' => 'Date of Assessment',
    'H1' => 'Practice Attempt 1',
    'I1' => 'Practice Attempt 2',
    'J1' => 'Final Attempt',
    'K1' => 'Part 1 Total Score (Reading Assesment)',
    'L1' => 'Part 2 Total Score (Quiz)',
    'M1' => 'Story Title',
    'N1' => 'Number of Miscue',
    'O1' => 'Words Read',
    'P1' => 'Minutes',
    'Q1' => 'Seconds',
    'R1' => 'WPM',
    'S1' => '% Correct Words',
    'T1' => 'Comprehension Score',
    'U1' => 'Observation Level',
    'V1' => 'Reading Profile'
];

// Set headers and make them bold
foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
    $sheet->getStyle($cell)->getFont()->setBold(true);
}

// Auto-size columns for better readability
foreach (range('A', 'V') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Data injection starts at Row 2
$row = 2;
$sn = 1;

foreach ($students as $student) {
    // Format Learner Name (Last Name, First Name M.)
    $middleInitial = !empty($student['middle_name']) ? ' ' . strtoupper(substr(trim($student['middle_name']), 0, 1)) . '.' : '';
    $learnerName = trim($student['last_name']) . ', ' . trim($student['first_name']) . $middleInitial;

    // A student might have multiple materials in one assignment. 
    // We will generate a row for each material assigned to them.
    foreach ($materials as $mat) {
        
        // 1. Fetch all reading attempts for this specific assignment material to map out Attempts 1, 2, and 3
        $readStmt = $pdo->prepare("
            SELECT ra.attempt_number, ra.activity_date, ra.duration_seconds, ar.*
            FROM reading_activity ra
            LEFT JOIN assessment_result ar ON ar.activity_id = ra.activity_id
            WHERE ra.student_id = :student_id 
              AND ra.assignment_material_id = :am_id
              AND ra.activity_status = 'Completed'
            ORDER BY ra.attempt_number ASC
        ");
        $readStmt->execute([
            ':student_id' => $student['student_id'],
            ':am_id' => $mat['assignment_material_id']
        ]);
        $attempts = $readStmt->fetchAll();

        // 2. Fetch the highest/latest Quiz attempt
        $quizStmt = $pdo->prepare("
            SELECT score, total_questions, percentage 
            FROM quiz_attempt 
            WHERE student_id = :student_id 
              AND assignment_material_id = :am_id 
              AND status = 'completed'
            ORDER BY percentage DESC LIMIT 1
        ");
        $quizStmt->execute([
            ':student_id' => $student['student_id'],
            ':am_id' => $mat['assignment_material_id']
        ]);
        $quiz = $quizStmt->fetch();

        // Map out the attempt numbers (we'll display their WCPM for the attempt)
        $attempt1 = '-';
        $attempt2 = '-';
        $attempt3 = '-';
        $finalAssessment = null;

        foreach ($attempts as $att) {
            $wcpmScore = isset($att['wcpm']) ? $att['wcpm'] . ' WCPM' : 'Done';
            if ($att['attempt_number'] == 1) $attempt1 = $wcpmScore;
            if ($att['attempt_number'] == 2) $attempt2 = $wcpmScore;
            if ($att['attempt_number'] == 3) $attempt3 = $wcpmScore;
            
            // The last recorded completed attempt will serve as their final assessment data
            $finalAssessment = $att; 
        }

        // --- Begin Writing to Excel ---
        $sheet->setCellValue('A' . $row, $sn);
        $sheet->setCellValue('B' . $row, $student['lrn'] ?? '');
        $sheet->setCellValue('C' . $row, $learnerName);
        $sheet->setCellValue('D' . $row, $student['gender'] ?? '');
        $sheet->setCellValue('E' . $row, $student['grade_level'] ?? '');
        $sheet->setCellValue('F' . $row, $student['section'] ?? '');
        
        $sheet->setCellValue('H' . $row, $attempt1);
        $sheet->setCellValue('I' . $row, $attempt2);
        $sheet->setCellValue('J' . $row, $attempt3);
        
        $sheet->setCellValue('M' . $row, $mat['story_title']); // Story Title

        // If they have completed at least one reading attempt, map the detailed stats
        if ($finalAssessment) {
            $sheet->setCellValue('G' . $row, date('Y-m-d', strtotime($finalAssessment['assessed_at'] ?? $finalAssessment['activity_date'])));
            
            // Reading Total Score (Words Correct)
            $sheet->setCellValue('K' . $row, $finalAssessment['words_correct'] ?? 0);
            
            // Compute Miscues safely (Substitutions + Omissions + Insertions)
            $sub = $finalAssessment['substitutions'] ?? 0;
            $om = $finalAssessment['omissions'] ?? 0;
            $ins = $finalAssessment['insertions'] ?? 0;
            $miscues = isset($finalAssessment['miscues']) ? $finalAssessment['miscues'] : ($sub + $om + $ins);
            $sheet->setCellValue('N' . $row, $miscues);
            
            // Words Read
            $sheet->setCellValue('O' . $row, $finalAssessment['total_words'] ?? 0);
            
            // Time logic (Convert duration_seconds to Min/Sec)
            $duration = $finalAssessment['duration_seconds'] ?? 0;
            $sheet->setCellValue('P' . $row, floor($duration / 60)); // Minutes
            $sheet->setCellValue('Q' . $row, $duration % 60);        // Seconds
            
            $sheet->setCellValue('R' . $row, $finalAssessment['wcpm'] ?? 0); // WPM
            $sheet->setCellValue('S' . $row, $finalAssessment['accuracy_percentage'] ?? 0);
            
            $sheet->setCellValue('U' . $row, $finalAssessment['observation_level'] ?? '');
            $sheet->setCellValue('V' . $row, $finalAssessment['reading_level'] ?? '');
        }

        // Quiz score mapping
        if ($quiz) {
            $quizScore = $quiz['score'] . ' / ' . $quiz['total_questions'];
            $sheet->setCellValue('L' . $row, $quizScore);
            $sheet->setCellValue('T' . $row, $quiz['percentage'] ?? 0); // Comprehension Score %
        }

        $row++;
        $sn++;
    }
}

// Clear any accidental whitespace/errors to prevent file corruption
if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'ArchiveVox_Class_' . $classId . '_Scoresheet_' . date('Y-m-d') . '.xlsx';

// Set headers for Excel download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit;