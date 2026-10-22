<?php
require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../_helpers.php';

$action = $_GET['action'] ?? '';

// 1. CAPTURE ALL PARAMETERS
$language   = strtolower($_GET['language'] ?? 'all');
$dateFilter = strtolower($_GET['dateFilter'] ?? 'custom');
$startDate  = $_GET['start_date'] ?? null;
$endDate    = $_GET['end_date'] ?? null;

$user = getCurrentUser();

if (!$user) {
    sendJson(['success' => false, 'message' => 'Unauthorized']);
}

try {
    $dashboard = [
        'my_students' => 0, 'class_avg_wcpm' => 0, 'avg_accuracy' => 0,
        'my_assessments' => 0, 'students_below' => 0, 'recent_assessments' => [],
        'class_performance' => [], 'recent_students' => []
    ];

    // 2. BUILD DYNAMIC LANGUAGE FILTERS
    $langFilterRa  = ""; 
    $langFilterRa2 = ""; 
    $langFilterM   = "";

    if ($language === 'english') {
        $langFilterRa  = " AND ra.material_id IN (SELECT material_id FROM reading_material WHERE LOWER(language) = 'english') ";
        $langFilterRa2 = " AND ra2.material_id IN (SELECT material_id FROM reading_material WHERE LOWER(language) = 'english') ";
        $langFilterM   = " AND LOWER(m.language) = 'english' ";
    } elseif ($language === 'filipino') {
        $langFilterRa  = " AND ra.material_id IN (SELECT material_id FROM reading_material WHERE LOWER(language) = 'filipino') ";
        $langFilterRa2 = " AND ra2.material_id IN (SELECT material_id FROM reading_material WHERE LOWER(language) = 'filipino') ";
        $langFilterM   = " AND LOWER(m.language) = 'filipino' ";
    }

    // 3. BUILD DYNAMIC DATE FILTERS (Index-friendly timestamp ranges)
    $dateFilterSql  = ""; 
    $dateFilterSql2 = "";

    if ($dateFilter === 'day') {
        $dateFilterSql  = " AND ar.assessed_at >= CURDATE() AND ar.assessed_at < CURDATE() + INTERVAL 1 DAY ";
        $dateFilterSql2 = " AND ar2.assessed_at >= CURDATE() AND ar2.assessed_at < CURDATE() + INTERVAL 1 DAY ";
    } elseif ($dateFilter === 'week') {
        $dateFilterSql  = " AND ar.assessed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ";
        $dateFilterSql2 = " AND ar2.assessed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ";
    } elseif ($dateFilter === 'month') {
        $dateFilterSql  = " AND ar.assessed_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00') ";
        $dateFilterSql2 = " AND ar2.assessed_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00') ";
    } elseif ($dateFilter === 'custom' && !empty($startDate) && !empty($endDate)) {
        $startFormatted = date('Y-m-d 00:00:00', strtotime($startDate));
        $endFormatted   = date('Y-m-d 23:59:59', strtotime($endDate));
        
        $dateFilterSql  = " AND ar.assessed_at BETWEEN '$startFormatted' AND '$endFormatted' ";
        $dateFilterSql2 = " AND ar2.assessed_at BETWEEN '$startFormatted' AND '$endFormatted' ";
    }

    // --- PRINCIPAL / ADMIN ROLE ---
    if ($action === 'principal' || $action === 'admin') {
        $dashboard['my_students'] = (int) $pdo->query("
            SELECT COUNT(DISTINCT ra.student_id) 
            FROM reading_activity ra 
            JOIN student s ON ra.student_id = s.student_id 
            JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            WHERE s.is_active = 1 $langFilterRa $dateFilterSql
        ")->fetchColumn();

        $dashboard['my_assessments'] = (int) $pdo->query("
            SELECT COUNT(*) 
            FROM assessment_result ar 
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
            WHERE 1=1 $langFilterRa $dateFilterSql
        ")->fetchColumn();

        $dashboard['class_avg_wcpm'] = (float) $pdo->query("
            SELECT AVG(ar.wcpm) 
            FROM assessment_result ar 
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
            WHERE ar.wcpm > 0 $langFilterRa $dateFilterSql
        ")->fetchColumn();

        $dashboard['students_below'] = (int) $pdo->query("
            SELECT COUNT(DISTINCT ra.student_id) 
            FROM assessment_result ar 
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
            WHERE ar.reading_level IN ('Low Emerging Reader', 'High Emerging Reader', 'Developing Reader', 'Frustration') 
            $langFilterRa $dateFilterSql
        ")->fetchColumn();

        $dashboard['avg_accuracy'] = (int) $pdo->query("
            SELECT ROUND(AVG(ar.accuracy_percentage)) 
            FROM assessment_result ar 
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
            WHERE 1=1 $langFilterRa $dateFilterSql
        ")->fetchColumn();

        // Recent Assessments
        $stmt = $pdo->query("
            SELECT
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                ra2.title AS material_title,
                m.language AS language,
                ar.accuracy_percentage,
                ar.assessed_at
            FROM assessment_result ar
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            JOIN student s ON ra.student_id = s.student_id
            JOIN reading_assignment_material ram ON ra.assignment_material_id = ram.assignment_material_id
            JOIN reading_assignment ra2 ON ram.assignment_id = ra2.assignment_id
            JOIN reading_material m ON ra.material_id = m.material_id
            WHERE ra2.status = 'assigned'
            AND ra2.title IS NOT NULL
            AND ra2.title != ''
            AND ra.assignment_material_id IS NOT NULL
            $langFilterM
            $dateFilterSql
            ORDER BY ar.assessed_at DESC LIMIT 5
        ");
        $dashboard['recent_assessments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Class Performance Time Series
        $stmt = $pdo->query("
            SELECT DATE(ar.assessed_at) as date, AVG(ar.wcpm) as avg_wcpm, AVG(ar.accuracy_percentage) as avg_accuracy 
            FROM assessment_result ar 
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            WHERE 1=1 $langFilterRa $dateFilterSql
            GROUP BY DATE(ar.assessed_at) 
            ORDER BY DATE(ar.assessed_at) ASC LIMIT 7
        ");
        $dashboard['class_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Student Overview
        $stmt = $pdo->query("
            SELECT 
                s.lrn, 
                s.first_name, 
                s.last_name, 
                c.grade_level,
                COUNT(ar.assessment_id) as assessment_count,
                AVG(ar.wcpm) as avg_wcpm,
                AVG(ar.accuracy_percentage) as avg_accuracy,
                MAX(ar.assessed_at) as last_assessed,
                (SELECT ar2.reading_level 
                 FROM assessment_result ar2 
                 JOIN reading_activity ra2 ON ar2.activity_id = ra2.activity_id 
                 WHERE ra2.student_id = s.student_id AND ar2.reading_level IS NOT NULL $langFilterRa2 $dateFilterSql2
                 ORDER BY ar2.assessed_at DESC LIMIT 1) as reading_level
            FROM student s
            LEFT JOIN class c ON s.class_id = c.class_id
            LEFT JOIN reading_activity ra ON s.student_id = ra.student_id $langFilterRa
            LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id $dateFilterSql
            WHERE s.is_active = 1
            GROUP BY s.student_id, s.lrn, s.first_name, s.last_name, c.grade_level
            ORDER BY s.first_name ASC
            LIMIT 10
        ");
        $dashboard['recent_students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJson(['success' => true, 'dashboard' => $dashboard]);
    } 

    // --- TEACHER ROLE ---
    elseif ($action === 'teacher') {
        $stmt = $pdo->prepare("SELECT teacher_id FROM teacher WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $teacherId = $stmt->fetchColumn();

        if ($teacherId) {
            $dashboard['my_students'] = (int) $pdo->query("
                SELECT COUNT(DISTINCT s.student_id) 
                FROM student s 
                JOIN reading_activity ra ON s.student_id = ra.student_id 
                JOIN assessment_result ar ON ra.activity_id = ar.activity_id 
                WHERE s.teacher_id = $teacherId AND s.is_active = 1 $langFilterRa $dateFilterSql
            ")->fetchColumn();
            
            $dashboard['my_assessments'] = (int) $pdo->query("
                SELECT COUNT(*) 
                FROM assessment_result ar 
                JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                JOIN student s ON ra.student_id = s.student_id 
                WHERE s.teacher_id = $teacherId $langFilterRa $dateFilterSql
            ")->fetchColumn();            
            
            $dashboard['avg_comprehension'] = (float) $pdo->query("
                SELECT AVG(ar.comprehension_score) 
                FROM assessment_result ar 
                JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                JOIN student s ON ra.student_id = s.student_id 
                WHERE s.teacher_id = $teacherId $langFilterRa $dateFilterSql
            ")->fetchColumn();

            $dashboard['class_avg_wcpm'] = (float) $pdo->query("
                SELECT AVG(ar.wcpm) 
                FROM assessment_result ar 
                JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                JOIN student s ON ra.student_id = s.student_id 
                WHERE s.teacher_id = $teacherId AND ar.wcpm > 0 $langFilterRa $dateFilterSql
            ")->fetchColumn();
            
            $classStmt = $pdo->query("
                SELECT grade_level, section 
                FROM class 
                WHERE teacher_id = $teacherId 
                ORDER BY school_year DESC 
                LIMIT 1
            ");
            
            if ($classStmt && $classData = $classStmt->fetch(PDO::FETCH_ASSOC)) {
                $dashboard['grade'] = $classData['grade_level'] ?? 'N/A';
                $dashboard['section'] = $classData['section'] ?? 'N/A';
            } else {
                $dashboard['grade'] = 'N/A';
                $dashboard['section'] = 'N/A';
            }

            $stmt = $pdo->prepare('
                SELECT COUNT(DISTINCT s.student_id) as total
                FROM student s
                JOIN reading_activity ra ON s.student_id = ra.student_id
                JOIN assessment_result ar ON ra.activity_id = ar.activity_id
                WHERE s.teacher_id = :teacher_id
                AND s.is_active = 1
                AND (ar.reading_level LIKE "%Low Emerging%" OR ar.reading_level = "Frustration")
                ' . $langFilterRa . '
                ' . $dateFilterSql . '
                AND ar.assessed_at = (
                    SELECT MAX(ar2.assessed_at)
                    FROM reading_activity ra2
                    JOIN assessment_result ar2 ON ra2.activity_id = ar2.activity_id
                    WHERE ra2.student_id = s.student_id
                    ' . $langFilterRa2 . '
                    ' . $dateFilterSql2 . '
                )
            ');
            $stmt->execute([':teacher_id' => $teacherId]);
            $studentsBelow = $stmt->fetch();
            $dashboard['students_below'] = $studentsBelow ? (int) $studentsBelow['total'] : 0;
            
            $dashboard['avg_accuracy'] = (int) $pdo->query("
                SELECT ROUND(AVG(ar.accuracy_percentage)) 
                FROM assessment_result ar 
                JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                JOIN student s ON ra.student_id = s.student_id 
                WHERE s.teacher_id = $teacherId $langFilterRa $dateFilterSql
            ")->fetchColumn();

            // Recent Assessments
            $stmt = $pdo->query("
                SELECT
                    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    ra2.title AS material_title,
                    m.language AS language,
                    ar.accuracy_percentage,
                    ar.assessed_at
                FROM assessment_result ar
                JOIN reading_activity ra ON ar.activity_id = ra.activity_id
                JOIN student s ON ra.student_id = s.student_id
                JOIN reading_assignment_material ram ON ra.assignment_material_id = ram.assignment_material_id
                JOIN reading_assignment ra2 ON ram.assignment_id = ra2.assignment_id
                JOIN reading_material m ON ra.material_id = m.material_id
                WHERE s.teacher_id = $teacherId
                AND ra2.status = 'assigned'
                AND ra.assignment_material_id IS NOT NULL
                $langFilterM
                $dateFilterSql
                ORDER BY ar.assessed_at DESC LIMIT 5
            ");
            $dashboard['recent_assessments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Class Performance
            $stmt = $pdo->query("
                SELECT DATE(ar.assessed_at) as date, AVG(ar.wcpm) as avg_wcpm, AVG(ar.accuracy_percentage) as avg_accuracy 
                FROM assessment_result ar 
                JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                JOIN student s ON ra.student_id = s.student_id 
                WHERE s.teacher_id = $teacherId 
                $langFilterRa
                $dateFilterSql
                GROUP BY DATE(ar.assessed_at) 
                ORDER BY DATE(ar.assessed_at) ASC LIMIT 7
            ");
            $dashboard['class_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Student Overview
            $stmt = $pdo->query("
                SELECT 
                    s.lrn, 
                    s.first_name, 
                    s.last_name, 
                    c.grade_level,
                    COUNT(ar.assessment_id) as assessment_count,
                    AVG(ar.wcpm) as avg_wcpm,
                    AVG(ar.accuracy_percentage) as avg_accuracy,
                    MAX(ar.assessed_at) as last_assessed,
                    (SELECT ar2.reading_level 
                     FROM assessment_result ar2 
                     JOIN reading_activity ra2 ON ar2.activity_id = ra2.activity_id 
                     WHERE ra2.student_id = s.student_id AND ar2.reading_level IS NOT NULL
                     $langFilterRa2
                     $dateFilterSql2
                     ORDER BY ar2.assessed_at DESC LIMIT 1) as reading_level
                FROM student s
                LEFT JOIN class c ON s.class_id = c.class_id
                LEFT JOIN reading_activity ra ON s.student_id = ra.student_id $langFilterRa
                LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id $dateFilterSql
                WHERE s.teacher_id = $teacherId AND s.is_active = 1
                GROUP BY s.student_id, s.lrn, s.first_name, s.last_name, c.grade_level
                ORDER BY s.first_name ASC
                LIMIT 10
            ");
            $dashboard['recent_students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group Results by Reading Profile
            $stmt = $pdo->query("
                SELECT 
                    ar.final_reading_level as profile, 
                    COUNT(DISTINCT s.student_id) as students, 
                    AVG(ar.wcpm) as avg_wcpm, 
                    AVG(ar.accuracy_percentage) as avg_accuracy 
                FROM assessment_result ar 
                JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                JOIN student s ON ra.student_id = s.student_id 
                WHERE s.teacher_id = $teacherId AND ar.final_reading_level IS NOT NULL 
                $langFilterRa $dateFilterSql 
                GROUP BY ar.final_reading_level
                ORDER BY avg_accuracy ASC
            ");
            $dashboard['profile_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group Results by Part 1 Level
            $stmt = $pdo->query("
                SELECT 
                    ar.part1_reading_level as level, 
                    COUNT(DISTINCT s.student_id) as students, 
                    AVG(ar.wcpm) as avg_wcpm, 
                    AVG(ar.accuracy_percentage) as avg_accuracy 
                FROM assessment_result ar 
                JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                JOIN student s ON ra.student_id = s.student_id 
                WHERE s.teacher_id = $teacherId AND ar.part1_reading_level IS NOT NULL 
                $langFilterRa $dateFilterSql 
                GROUP BY ar.part1_reading_level
            ");
            $dashboard['part1_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        sendJson(['success' => true, 'dashboard' => $dashboard]);
    }

    sendJson(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    sendJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}