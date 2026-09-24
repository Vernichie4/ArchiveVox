<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // Verify user is logged in
    $user = getCurrentUser();
    if (!$user) {
        sendJson(['success' => false, 'message' => 'Not authenticated'], 401);
    }

    if ($method !== 'GET') {
        http_response_code(405);
        sendJson(['success' => false, 'message' => 'Method not allowed']);
    }

    // ============================================================
    // SCHOOL-WIDE REPORT
    // ============================================================
    if ($action === 'school-wide') {
        // Only principals and admins can access
        if (!in_array($user['role'], ['principal', 'admin'])) {
            sendJson(['success' => false, 'message' => 'Access denied'], 403);
        }

        $stmt = $pdo->prepare('
            SELECT 
                c.grade_level,
                COUNT(DISTINCT s.student_id) as student_count,
                COUNT(DISTINCT ar.assessment_id) as assessment_count,
                AVG(ar.wcpm) as avg_wcpm,
                AVG(ar.accuracy_percentage) as avg_accuracy,
                MIN(ar.wcpm) as min_wcpm,
                MAX(ar.wcpm) as max_wcpm,
                AVG(ar.fluency_score) as avg_fluency
            FROM student s
            LEFT JOIN class c ON s.class_id = c.class_id
            LEFT JOIN reading_activity ra ON s.student_id = ra.student_id
            LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            WHERE s.is_active = 1
            GROUP BY c.grade_level
            ORDER BY c.grade_level
        ');

        $stmt->execute();
        $gradeStats = $stmt->fetchAll();

        // Get top performers (students with at least 2 assessments)
        $stmt = $pdo->prepare('
            SELECT 
                s.lrn,
                s.first_name,
                s.last_name,
                c.grade_level,
                AVG(ar.wcpm) as avg_wcpm,
                AVG(ar.accuracy_percentage) as avg_accuracy,
                COUNT(ar.assessment_id) as assessment_count
            FROM student s
            LEFT JOIN class c ON s.class_id = c.class_id
            JOIN reading_activity ra ON s.student_id = ra.student_id
            JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            WHERE s.is_active = 1
            GROUP BY s.student_id
            HAVING COUNT(ar.assessment_id) >= 2
            ORDER BY avg_wcpm DESC
            LIMIT 10
        ');
        $stmt->execute();
        $topPerformers = $stmt->fetchAll();

        // Get students needing intervention (WCPM < 40)
        $stmt = $pdo->prepare('
            SELECT 
                s.student_id,
                s.first_name,
                s.last_name,
                c.section,
                sc.grade_level,
                AVG(ar.wcpm) as average_wcpm,
                AVG(ar.accuracy_percentage) as average_accuracy,
                COUNT(ar.assessment_id) as assessments_taken
            FROM student s
            JOIN student_category sc ON s.category_id = sc.category_id
            JOIN class c ON s.class_id = c.class_id
            JOIN reading_activity ra ON s.student_id = ra.student_id
            JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            WHERE s.is_active = 1
            GROUP BY s.student_id
            HAVING (AVG(ar.wcpm) < 60.0 OR AVG(ar.accuracy_percentage) < 80.0) 
            AND COUNT(ar.assessment_id) >= 1
            ORDER BY average_wcpm ASC
        ');

        $stmt->execute();
        $intervention = $stmt->fetchAll();

        // Get overall stats
        $stmt = $pdo->query('
            SELECT 
                COUNT(DISTINCT s.student_id) as total_students,
                COUNT(DISTINCT rm.material_id) as total_materials,
                COUNT(ar.assessment_id) as total_assessments,
                AVG(ar.wcpm) as overall_wcpm,
                AVG(ar.accuracy_percentage) as overall_accuracy
            FROM student s
            LEFT JOIN reading_material rm ON rm.status = "Active"
            LEFT JOIN reading_activity ra ON s.student_id = ra.student_id
            LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            WHERE s.is_active = 1
        ');
        $overall = $stmt->fetch();

        sendJson([
            'success' => true,
            'overall' => $overall,
            'grade_statistics' => $gradeStats,
            'top_performers' => $topPerformers,
            'intervention_needed' => $intervention,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
    }

    // ============================================================
    // MATERIAL USAGE REPORT
    // ============================================================
    elseif ($action === 'material-usage') {
        // Only principals and admins can access
        if (!in_array($user['role'], ['principal', 'admin'])) {
            sendJson(['success' => false, 'message' => 'Access denied'], 403);
        }

        $stmt = $pdo->prepare('
            SELECT 
                rm.material_id,
                rm.title,
                rm.grade_level,
                rm.language,
                rm.difficulty,
                rm.material_type,
                COUNT(DISTINCT ar.assessment_id) as usage_count,
                COUNT(DISTINCT ra.student_id) as student_count,
                AVG(ar.wcpm) as avg_wcpm,
                AVG(ar.accuracy_percentage) as avg_accuracy,
                AVG(ar.fluency_score) as avg_fluency,
                MAX(ar.assessed_at) as last_used
            FROM reading_material rm
            LEFT JOIN reading_activity ra ON ra.material_id = rm.material_id
            LEFT JOIN assessment_result ar ON ar.activity_id = ra.activity_id
            WHERE rm.status = "Active"
            GROUP BY rm.material_id
            ORDER BY usage_count DESC
        ');
        $stmt->execute();
        $materials = $stmt->fetchAll();

        sendJson([
            'success' => true,
            'materials' => $materials,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
    }

    // ============================================================
    // CLASS PERFORMANCE REPORT
    // ============================================================
    elseif ($action === 'class-performance') {
        $teacherId = $_GET['teacher_id'] ?? 0;

        // 1. SECURITY FIX: Enforce strict authorization for teachers
        // Ignore any GET parameter they pass and force their own teacher_id.
        if ($user['role'] === 'teacher') {
            $stmt = $pdo->prepare('SELECT teacher_id FROM teacher WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $user['user_id']]);
            $teacherId = (int) $stmt->fetchColumn() ?: 0;
        }

        // 2. PRINCIPAL/ADMIN VIEW: Allow viewing all students across the school if no specific teacher is selected
        if (in_array($user['role'], ['principal', 'admin']) && !$teacherId) {
            $stmt = $pdo->prepare('
                SELECT 
                    s.student_id,
                    s.lrn,
                    s.first_name,
                    s.last_name,
                    c.grade_level,
                    c.section,
                    t.first_name as teacher_first,
                    t.last_name as teacher_last,
                    COUNT(DISTINCT ar.assessment_id) as assessment_count,
                    ROUND(AVG(ar.wcpm), 1) as avg_wcpm,
                    ROUND(AVG(ar.accuracy_percentage), 1) as avg_accuracy,
                    (
                        SELECT ar2.reading_level 
                        FROM assessment_result ar2 
                        JOIN reading_activity ra2 ON ar2.activity_id = ra2.activity_id 
                        WHERE ra2.student_id = s.student_id AND ar2.reading_level IS NOT NULL
                        ORDER BY ar2.assessed_at DESC LIMIT 1
                    ) as latest_reading_level
                FROM student s
                LEFT JOIN class c ON s.class_id = c.class_id
                LEFT JOIN teacher t ON s.teacher_id = t.teacher_id
                LEFT JOIN reading_activity ra ON s.student_id = ra.student_id
                LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id
                WHERE s.is_active = 1
                GROUP BY s.student_id, t.first_name, t.last_name
                ORDER BY s.last_name, s.first_name
                LIMIT 50
            ');
            $stmt->execute();
            $students = $stmt->fetchAll();
            
        // 3. TEACHER/CLASS VIEW: For Principal drill-downs or Teacher's own dashboard
        } elseif ($teacherId) {
            $stmt = $pdo->prepare('
                SELECT 
                    s.student_id, 
                    s.lrn,
                    s.first_name,
                    s.last_name,
                    c.grade_level,
                    c.section,
                    COUNT(DISTINCT ar.assessment_id) as assessment_count,
                    ROUND(AVG(ar.wcpm), 1) as avg_wcpm,
                    ROUND(AVG(ar.accuracy_percentage), 1) as avg_accuracy,
                    MAX(ar.assessed_at) as last_assessment_date,
                    (
                        SELECT ar2.reading_level 
                        FROM assessment_result ar2 
                        JOIN reading_activity ra2 ON ar2.activity_id = ra2.activity_id 
                        WHERE ra2.student_id = s.student_id AND ar2.reading_level IS NOT NULL
                        ORDER BY ar2.assessed_at DESC LIMIT 1
                    ) as latest_reading_level
                FROM student s
                LEFT JOIN class c ON s.class_id = c.class_id
                LEFT JOIN reading_activity ra ON s.student_id = ra.student_id
                LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id
                WHERE s.teacher_id = :teacher_id AND s.is_active = 1
                GROUP BY s.student_id
                ORDER BY s.last_name, s.first_name
            ');
            $stmt->execute([':teacher_id' => $teacherId]);
            $students = $stmt->fetchAll();
            
        } else {
            sendJson(['success' => false, 'message' => 'Teacher ID required or not found.'], 400);
            exit;
        }

        sendJson([
            'success' => true,
            'students' => $students,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
    }

    else {
        sendJson(['success' => false, 'message' => 'Invalid action. Available actions: school-wide, material-usage, class-performance'], 400);
    }

} catch (Exception $e) {
    error_log('Report analytics error: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}