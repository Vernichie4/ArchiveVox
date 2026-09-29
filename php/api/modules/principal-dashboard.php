<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $user = getCurrentUser();
    if (!$user) {
        sendJson(['success' => false, 'message' => 'Not authenticated'], 401);
    }

    if (!in_array($user['role'], ['principal', 'admin'])) {
        sendJson(['success' => false, 'message' => 'Access denied'], 403);
    }

    if ($method === 'GET') {
        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM student WHERE is_active = 1');
        $stmt->execute();
        $studentCount = $stmt->fetch()['total'] ?? 0;

                $stmt = $pdo->prepare('
                        SELECT COUNT(*) as total
                        FROM student
                        WHERE is_active = 1
                            AND date_registered >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                ');
                $stmt->execute();
                $studentsThisWeek = (int)($stmt->fetch()['total'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM reading_material WHERE status = "Active"');
        $stmt->execute();
        $materialCount = $stmt->fetch()['total'] ?? 0;

                $stmt = $pdo->prepare('
                        SELECT COUNT(*) as total
                        FROM reading_material
                        WHERE status = "Active"
                            AND upload_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                ');
                $stmt->execute();
                $materialsThisWeek = (int)($stmt->fetch()['total'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM assessment_result');
        $stmt->execute();
        $assessmentCount = $stmt->fetch()['total'] ?? 0;

                $stmt = $pdo->prepare('
                        SELECT COUNT(*) as total
                        FROM assessment_result
                        WHERE assessed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ');
                $stmt->execute();
                $assessmentsThisMonth = (int)($stmt->fetch()['total'] ?? 0);

        $stmt = $pdo->prepare('SELECT AVG(accuracy_percentage) as avg_accuracy FROM assessment_result WHERE accuracy_percentage IS NOT NULL');
        $stmt->execute();
        $avgAccuracy = round((float)($stmt->fetch()['avg_accuracy'] ?? 0), 0);

                $stmt = $pdo->prepare('
                        SELECT AVG(accuracy_percentage) as avg_accuracy
                        FROM assessment_result
                        WHERE accuracy_percentage IS NOT NULL
                            AND assessed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ');
                $stmt->execute();
                $currentAccuracy = (float)($stmt->fetch()['avg_accuracy'] ?? 0);

                $stmt = $pdo->prepare('
                        SELECT AVG(accuracy_percentage) as avg_accuracy
                        FROM assessment_result
                        WHERE accuracy_percentage IS NOT NULL
                            AND assessed_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                            AND assessed_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ');
                $stmt->execute();
                $previousAccuracy = (float)($stmt->fetch()['avg_accuracy'] ?? 0);
                $accuracyTrend = round($currentAccuracy - $previousAccuracy, 1);

        $stmt = $pdo->prepare('
            SELECT 
                CONCAT(s.first_name, " ", s.last_name) as student_name,
                s.first_name,
                s.last_name,
                rm.title as material_title,
                ar.accuracy_percentage,
                ar.fluency_score,
                ar.wcpm,
                ar.assessed_at
            FROM assessment_result ar
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            JOIN student s ON ra.student_id = s.student_id
            JOIN reading_material rm ON ra.material_id = rm.material_id
            ORDER BY ar.assessed_at DESC
            LIMIT 4
        ');
        $stmt->execute();
        $recentAssessments = $stmt->fetchAll();

                $stmt = $pdo->prepare('
            SELECT COUNT(DISTINCT s.student_id) as count
            FROM student s
            JOIN reading_activity ra ON ra.student_id = s.student_id
            JOIN assessment_result ar ON ar.activity_id = ra.activity_id
            WHERE s.is_active = 1
              AND (ar.wcpm < 40 OR ar.accuracy_percentage < 60)
                ');
        $stmt->execute();
        $belowTarget = $stmt->fetch()['count'] ?? 0;

        $stmt = $pdo->prepare('
            SELECT 
                DATE(ar.assessed_at) as activity_date,
                COUNT(*) as count
            FROM assessment_result ar
            WHERE ar.assessed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(ar.assessed_at)
            ORDER BY activity_date ASC
        ');
        $stmt->execute();
        $weeklyRows = $stmt->fetchAll();

        $weeklyMap = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = new DateTime("-{$i} days");
            $key = $date->format('Y-m-d');
            $weeklyMap[$key] = [
                'day' => $date->format('D'),
                'count' => 0
            ];
        }

        foreach ($weeklyRows as $row) {
            $dateKey = $row['activity_date'] ?? null;
            if ($dateKey && isset($weeklyMap[$dateKey])) {
                $weeklyMap[$dateKey]['count'] = (int)($row['count'] ?? 0);
            }
        }

        $weeklyActivity = array_values($weeklyMap);

        $stmt = $pdo->prepare('
            SELECT 
                sc.grade_level,
                ROUND(AVG(ar.accuracy_percentage), 1) as accuracy,
                ROUND(AVG(ar.fluency_score), 1) as fluency,
                ROUND(AVG(ar.wcpm), 1) as rate
            FROM student s
            JOIN student_category sc ON s.category_id = sc.category_id
            JOIN reading_activity ra ON ra.student_id = s.student_id
            JOIN assessment_result ar ON ar.activity_id = ra.activity_id
            WHERE s.is_active = 1
            GROUP BY sc.grade_level
            ORDER BY sc.grade_level
        ');
        $stmt->execute();
        $gradePerformance = $stmt->fetchAll();

        $stmt = $pdo->prepare('
            SELECT 
                s.first_name,
                s.last_name,
                sc.grade_level,
                ROUND(AVG(ar.accuracy_percentage), 1) as avg_accuracy,
                ROUND(AVG(ar.wcpm), 1) as avg_wcpm
            FROM student s
            JOIN student_category sc ON s.category_id = sc.category_id
            JOIN reading_activity ra ON ra.student_id = s.student_id
            JOIN assessment_result ar ON ar.activity_id = ra.activity_id
            WHERE s.is_active = 1
            GROUP BY s.student_id
            HAVING AVG(ar.wcpm) < 40 OR AVG(ar.accuracy_percentage) < 60
            ORDER BY AVG(ar.accuracy_percentage) ASC, AVG(ar.wcpm) ASC
            LIMIT 5
        ');
        $stmt->execute();
        $riskStudents = $stmt->fetchAll();
        
        sendJson([
            'success' => true,
            'dashboard' => [
                'total_students' => (int)$studentCount,
                'total_materials' => (int)$materialCount,
                'total_assessments' => (int)$assessmentCount,
                'avg_accuracy' => (int)$avgAccuracy,
                'students_this_week' => $studentsThisWeek,
                'materials_this_week' => $materialsThisWeek,
                'assessments_this_month' => $assessmentsThisMonth,
                'accuracy_trend' => $accuracyTrend,
                'students_below_target' => (int)$belowTarget,
                'recent_assessments' => $recentAssessments ?: [],
                'weekly_activity' => $weeklyActivity,
                'grade_performance' => $gradePerformance ?: [],
                'risk_students' => $riskStudents ?: []
            ]
        ]);
    } else {
        http_response_code(405);
        sendJson(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log('Principal dashboard error: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Server error'], 500);
}
