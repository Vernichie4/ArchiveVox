<?php
// php/api/shared/reports.php

// Include your database connection
require_once __DIR__ . '/../../connection.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id']) || !isset($_SESSION['user']['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$userData = $_SESSION['user'];
$userId = $userData['user_id'];
$role = $userData['role'];

try {
    // Use PDO from connection.php
    $pdo = createPdoConnection();
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Only allow teacher role
if ($action === 'teacher' && $role === 'teacher') {
    try {
        // Get teacher_id from teacher table
        $stmt = $pdo->prepare("SELECT teacher_id FROM teacher WHERE user_id = ?");
        $stmt->execute([$userId]);
        $teacher = $stmt->fetch();
        
        if (!$teacher) {
            echo json_encode(['success' => false, 'message' => 'Teacher record not found']);
            exit;
        }
        
        $teacherId = $teacher['teacher_id'];
        
        // Get teacher's active students
        $stmt = $pdo->prepare("SELECT student_id FROM student WHERE teacher_id = ? AND is_active = 1");
        $stmt->execute([$teacherId]);
        $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $studentCount = count($studentIds);
        $assessmentCount = 0;
        $avgWcpm = 0;
        $studentsBelow = 0;
        $recentAssessments = [];
        $classPerformance = [];
        $recentStudents = [];
        
        if (!empty($studentIds)) {
            $ids = implode(',', array_map('intval', $studentIds));
            
            // Get assessment stats from reading_activity and assessment_result
            $statsQuery = "SELECT 
            COUNT(DISTINCT ra.activity_id) as total_assessments,
            AVG(ar.wcpm) as avg_wcpm
            FROM reading_activity ra
            INNER JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            WHERE ra.student_id IN ($ids)
            AND ra.activity_status = 'Completed'";
            $statsResult = $pdo->query($statsQuery);
            $stats = $statsResult->fetch();
            $assessmentCount = $stats['total_assessments'] ?? 0;
            $avgWcpm = $stats['avg_wcpm'] ?? 0;
            
            // Get students below target (WCPM < 60 OR accuracy < 80%)
            $belowQuery = "SELECT COUNT(DISTINCT ra.student_id) as below_count
                FROM reading_activity ra
                LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id
                WHERE ra.student_id IN ($ids)
                AND ra.activity_status = 'Completed'
                AND (ar.wcpm < 60 OR ar.accuracy_percentage < 80)";
            $belowResult = $pdo->query($belowQuery);
            $belowRow = $belowResult->fetch();
            $studentsBelow = $belowRow['below_count'] ?? 0;
            
            // Get recent assessments (last 10)
            $recentQuery = "SELECT 
                ra.*,
                ar.wcpm,
                ar.accuracy_percentage,
                ar.reading_level,
                ar.final_reading_level,
                ar.comprehension_score,
                ar.assessed_at,
                s.first_name,
                s.last_name,
                s.lrn,
                rm.title as material_title
            FROM reading_activity ra
            JOIN student s ON ra.student_id = s.student_id
            LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            LEFT JOIN reading_material rm ON ra.material_id = rm.material_id
            WHERE ra.student_id IN ($ids)
            AND ra.activity_status = 'Completed'
            ORDER BY ra.activity_date DESC
            LIMIT 10";
            $recentResult = $pdo->query($recentQuery);
            while ($row = $recentResult->fetch()) {
                $row['student_name'] = $row['first_name'] . ' ' . $row['last_name'];
                $recentAssessments[] = $row;
            }
            
            // Get class performance (latest assessment per student)
            $perfQuery = "SELECT 
                s.student_id,
                s.first_name,
                s.last_name,
                s.lrn,
                sc.grade_level,
                MAX(ra.activity_date) as last_assessed,
                ar.wcpm,
                ar.accuracy_percentage,
                ar.reading_level,
                ar.final_reading_level
            FROM student s
            LEFT JOIN reading_activity ra ON s.student_id = ra.student_id AND ra.activity_status = 'Completed'
            LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            LEFT JOIN student_category sc ON s.category_id = sc.category_id
            WHERE s.student_id IN ($ids)
            GROUP BY s.student_id
            ORDER BY s.last_name, s.first_name";
            $perfResult = $pdo->query($perfQuery);
            while ($row = $perfResult->fetch()) {
                $row['student_name'] = $row['first_name'] . ' ' . $row['last_name'];
                $classPerformance[] = $row;
            }
            
            // Get recent students for overview table
            $studentQuery2 = "SELECT 
                s.*,
                sc.grade_level,
                COUNT(ra.activity_id) as assessment_count,
                AVG(ar.wcpm) as avg_wcpm
            FROM student s
            LEFT JOIN reading_activity ra ON s.student_id = ra.student_id AND ra.activity_status = 'Completed'
            LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            LEFT JOIN student_category sc ON s.category_id = sc.category_id
            WHERE s.student_id IN ($ids)
            GROUP BY s.student_id
            ORDER BY s.last_name, s.first_name
            LIMIT 10";
            $studentResult2 = $pdo->query($studentQuery2);
            while ($row = $studentResult2->fetch()) {
                // Get latest reading level separately
                $levelStmt = $pdo->prepare("SELECT ar2.reading_level 
                    FROM assessment_result ar2 
                    JOIN reading_activity ra2 ON ar2.activity_id = ra2.activity_id 
                    WHERE ra2.student_id = ? 
                    AND ra2.activity_status = 'Completed'
                    ORDER BY ra2.activity_date DESC LIMIT 1");
                $levelStmt->execute([$row['student_id']]);
                $levelRow = $levelStmt->fetch();
                $row['reading_level'] = $levelRow['reading_level'] ?? 'N/A';
                $recentStudents[] = $row;
            }
        }
        
        // Return complete dashboard data
        echo json_encode([
            'success' => true,
            'dashboard' => [
                'my_students' => (int)$studentCount,
                'class_avg_wcpm' => round((float)$avgWcpm, 1),
                'my_assessments' => (int)$assessmentCount,
                'students_below' => (int)$studentsBelow,
                'recent_assessments' => $recentAssessments,
                'class_performance' => $classPerformance,
                'recent_students' => $recentStudents
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// If action is not recognized
echo json_encode([
    'success' => false, 
    'message' => 'Invalid action or insufficient permissions'
]);
exit;
?>