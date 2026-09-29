<?php
require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../_helpers.php';

$action = $_GET['action'] ?? '';
$user = getCurrentUser();

if (!$user) {
    sendJson(['success' => false, 'message' => 'Unauthorized']);
}

try {
    $dashboard = [
        'my_students' => 0,
        'class_avg_wcpm' => 0,
        'avg_accuracy' => 0,
        'my_assessments' => 0,
        'students_below' => 0,
        'recent_assessments' => [],
        'class_performance' => [],
        'recent_students' => []
    ];

    if ($action === 'principal' || $action === 'admin') {
        // Stats
        $dashboard['my_students'] = (int) $pdo->query("SELECT COUNT(*) FROM student WHERE is_active = 1")->fetchColumn();
        $dashboard['my_assessments'] = (int) $pdo->query("SELECT COUNT(*) FROM assessment_result ar JOIN reading_activity ra ON ar.activity_id = ra.activity_id")->fetchColumn();
        $dashboard['class_avg_wcpm'] = (float) $pdo->query("SELECT AVG(wcpm) FROM assessment_result WHERE wcpm > 0")->fetchColumn();
        $dashboard['students_below'] = (int) $pdo->query("SELECT COUNT(DISTINCT ra.student_id) FROM assessment_result ar JOIN reading_activity ra ON ar.activity_id = ra.activity_id WHERE ar.reading_level IN ('Low Emerging Reader', 'High Emerging Reader', 'Developing Reader')")->fetchColumn();
        $dashboard['avg_accuracy'] = (int) $pdo->query("SELECT ROUND(AVG(accuracy_percentage)) FROM assessment_result")->fetchColumn();
        
        // Recent Assessments
        $stmt = $pdo->query("
            SELECT 
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                m.title AS material_title,
                ar.accuracy_percentage,
                ar.assessed_at
            FROM assessment_result ar 
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
            JOIN student s ON ra.student_id = s.student_id 
            LEFT JOIN reading_material m ON ra.material_id = m.material_id
            ORDER BY ar.assessed_at DESC LIMIT 5
        ");
        $dashboard['recent_assessments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Class Performance (Time Series for Chart)
        $stmt = $pdo->query("
            SELECT DATE(ar.assessed_at) as date, AVG(ar.wcpm) as avg_wcpm, AVG(ar.accuracy_percentage) as avg_accuracy 
            FROM assessment_result ar 
            GROUP BY DATE(ar.assessed_at) 
            ORDER BY DATE(ar.assessed_at) ASC LIMIT 7
        ");
        $dashboard['class_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Student Overview (Principal)
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
                 ORDER BY ar2.assessed_at DESC LIMIT 1) as reading_level
            FROM student s
            LEFT JOIN class c ON s.class_id = c.class_id
            LEFT JOIN reading_activity ra ON s.student_id = ra.student_id
            LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            WHERE s.is_active = 1
            GROUP BY s.student_id, s.lrn, s.first_name, s.last_name, c.grade_level
            ORDER BY s.first_name ASC
            LIMIT 10
        ");
        $dashboard['recent_students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJson(['success' => true, 'dashboard' => $dashboard]);
    } 
    elseif ($action === 'teacher') {
        $stmt = $pdo->prepare("SELECT teacher_id FROM teacher WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $teacherId = $stmt->fetchColumn();

        if ($teacherId) {
            // Stats
            $dashboard['my_students'] = (int) $pdo->query("SELECT COUNT(*) FROM student WHERE teacher_id = $teacherId AND is_active = 1")->fetchColumn();
            $dashboard['my_assessments'] = (int) $pdo->query("SELECT COUNT(*) FROM assessment_result ar JOIN reading_activity ra ON ar.activity_id = ra.activity_id JOIN student s ON ra.student_id = s.student_id WHERE s.teacher_id = $teacherId")->fetchColumn();
            $dashboard['class_avg_wcpm'] = (float) $pdo->query("SELECT AVG(ar.wcpm) FROM assessment_result ar JOIN reading_activity ra ON ar.activity_id = ra.activity_id JOIN student s ON ra.student_id = s.student_id WHERE s.teacher_id = $teacherId AND ar.wcpm > 0")->fetchColumn();
            $stmt = $pdo->prepare('
                SELECT COUNT(DISTINCT s.student_id) as total
                FROM student s
                JOIN reading_activity ra ON s.student_id = ra.student_id
                JOIN assessment_result ar ON ra.activity_id = ar.activity_id
                WHERE s.teacher_id = :teacher_id
                AND s.is_active = 1
                AND (ar.reading_level LIKE "%Low Emerging%" OR ar.reading_level = "Frustration")
                AND ar.assessed_at = (
                    SELECT MAX(ar2.assessed_at)
                    FROM reading_activity ra2
                    JOIN assessment_result ar2 ON ra2.activity_id = ar2.activity_id
                    WHERE ra2.student_id = s.student_id
                )
            ');
            $stmt->execute([':teacher_id' => $teacherId]);
            $studentsBelow = $stmt->fetch();
            $dashboard['students_below'] = $studentsBelow ? (int) $studentsBelow['total'] : 0;
            $dashboard['avg_accuracy'] = (int) $pdo->query("SELECT ROUND(AVG(ar.accuracy_percentage)) FROM assessment_result ar JOIN reading_activity ra ON ar.activity_id = ra.activity_id JOIN student s ON ra.student_id = s.student_id WHERE s.teacher_id = $teacherId")->fetchColumn();

            // Recent Assessments
            $stmt = $pdo->query("
                SELECT 
                    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    m.title AS material_title,
                    ar.accuracy_percentage,
                    ar.assessed_at
                FROM assessment_result ar 
                JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                JOIN student s ON ra.student_id = s.student_id 
                LEFT JOIN reading_material m ON ra.material_id = m.material_id
                WHERE s.teacher_id = $teacherId 
                ORDER BY ar.assessed_at DESC LIMIT 5
            ");
            $dashboard['recent_assessments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Class Performance (Time Series for Chart)
            $stmt = $pdo->query("
                SELECT DATE(ar.assessed_at) as date, AVG(ar.wcpm) as avg_wcpm, AVG(ar.accuracy_percentage) as avg_accuracy 
                FROM assessment_result ar 
                JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                JOIN student s ON ra.student_id = s.student_id 
                WHERE s.teacher_id = $teacherId 
                GROUP BY DATE(ar.assessed_at) 
                ORDER BY DATE(ar.assessed_at) ASC LIMIT 7
            ");
            $dashboard['class_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Student Overview (Teacher)
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
                     ORDER BY ar2.assessed_at DESC LIMIT 1) as reading_level
                FROM student s
                LEFT JOIN class c ON s.class_id = c.class_id
                LEFT JOIN reading_activity ra ON s.student_id = ra.student_id
                LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id
                WHERE s.teacher_id = $teacherId AND s.is_active = 1
                GROUP BY s.student_id, s.lrn, s.first_name, s.last_name, c.grade_level
                ORDER BY s.first_name ASC
                LIMIT 10
            ");
            $dashboard['recent_students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        sendJson(['success' => true, 'dashboard' => $dashboard]);
    }
    
    sendJson(['success' => false, 'message' => 'Invalid action']);

} catch (Exception $e) {
    sendJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}