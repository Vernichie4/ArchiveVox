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
        
        // ==========================================
        // 1. CAPTURE GLOBAL DATE FILTERS
        // ==========================================
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        
        $dateSql = '';
        $params = [];
        $hasDateFilter = false;

        // If dates are provided, build the dynamic WHERE condition
        if (!empty($startDate) && !empty($endDate)) {
            $dateSql = ' AND ar.assessed_at >= :start_date AND ar.assessed_at <= :end_date';
            $params[':start_date'] = $startDate . ' 00:00:00';
            $params[':end_date'] = $endDate . ' 23:59:59';
            $hasDateFilter = true;
        }

        // ==========================================
        // 2. DYNAMIC TOTALS & TREND METRICS
        // ==========================================
        
        if ($hasDateFilter) {
            // Count only students who completed an assessment in the date range
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT ra.student_id) as total 
                FROM assessment_result ar
                JOIN reading_activity ra ON ar.activity_id = ra.activity_id
                WHERE 1=1 $dateSql
            ");
            $stmt->execute($params);
            $studentCount = (int)($stmt->fetch()['total'] ?? 0);

            // Count only materials used for assessments in the date range
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT ra.material_id) as total 
                FROM assessment_result ar
                JOIN reading_activity ra ON ar.activity_id = ra.activity_id
                WHERE 1=1 $dateSql
            ");
            $stmt->execute($params);
            $materialCount = (int)($stmt->fetch()['total'] ?? 0);
        } else {
            // Default: Count all active students and materials
            $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM student WHERE is_active = 1');
            $stmt->execute();
            $studentCount = (int)($stmt->fetch()['total'] ?? 0);

        }

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

        $stmt = $pdo->prepare('
            SELECT COUNT(*) as total
            FROM assessment_result
            WHERE assessed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ');
        $stmt->execute();
        $assessmentsThisMonth = (int)($stmt->fetch()['total'] ?? 0);

        // Accuracy Trends (Last 30 vs Last 60)
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

        // ==========================================
        // 3. FILTERED METRICS (Using $dateSql)
        // ==========================================
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM assessment_result ar WHERE 1=1 $dateSql");
        $stmt->execute($params);
        $assessmentCount = $stmt->fetch()['total'] ?? 0;

        $stmt = $pdo->prepare("SELECT AVG(accuracy_percentage) as avg_accuracy FROM assessment_result ar WHERE ar.accuracy_percentage IS NOT NULL $dateSql");
        $stmt->execute($params);
        $avgAccuracy = round((float)($stmt->fetch()['avg_accuracy'] ?? 0), 0);

        $stmt = $pdo->prepare("
            SELECT
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.first_name,
                s.last_name,
                ra2.title as material_title,
                rm.language as language,
                ar.accuracy_percentage,
                ar.fluency_score,
                ar.wcpm,
                ar.assessed_at
            FROM assessment_result ar
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            JOIN student s ON ra.student_id = s.student_id
            JOIN reading_assignment_material ram ON ra.assignment_material_id = ram.assignment_material_id
            JOIN reading_assignment ra2 ON ram.assignment_id = ra2.assignment_id
            JOIN reading_material rm ON ra.material_id = rm.material_id
            WHERE ra.assignment_material_id IS NOT NULL 
            $dateSql
            ORDER BY ar.assessed_at DESC
            LIMIT 4
        ");
        $stmt->execute($params);
        $recentAssessments = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT s.student_id) as count
            FROM student s
            JOIN reading_activity ra ON ra.student_id = s.student_id
            JOIN assessment_result ar ON ar.activity_id = ra.activity_id
            WHERE s.is_active = 1
              AND (ar.wcpm < 40 OR ar.accuracy_percentage < 60)
              $dateSql
        ");
        $stmt->execute($params);
        $belowTarget = $stmt->fetch()['count'] ?? 0;

        // --- Weekly Activity Chart (Dynamic Date Range handling) ---
        $chartDateCondition = $hasDateFilter ? $dateSql : ' AND ar.assessed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)';
        $stmt = $pdo->prepare("
            SELECT 
                DATE(ar.assessed_at) as activity_date,
                COUNT(*) as count
            FROM assessment_result ar
            WHERE 1=1 $chartDateCondition
            GROUP BY DATE(ar.assessed_at)
            ORDER BY activity_date ASC
            LIMIT 30
        ");
        $stmt->execute($params);
        $weeklyRows = $stmt->fetchAll();

        $weeklyMap = [];
        if ($hasDateFilter) {
            // Plot dates based on selected range (Max 31 days for chart UI)
            $begin = new DateTime($startDate);
            $end = (new DateTime($endDate))->modify('+1 day');
            $period = new DatePeriod($begin, new DateInterval('P1D'), $end);
            $dayCount = 0;
            foreach ($period as $dt) {
                if ($dayCount >= 31) break; 
                $weeklyMap[$dt->format('Y-m-d')] = ['day' => $dt->format('M d'), 'count' => 0];
                $dayCount++;
            }
        } else {
            // Default 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = new DateTime("-{$i} days");
                $weeklyMap[$date->format('Y-m-d')] = ['day' => $date->format('D'), 'count' => 0];
            }
        }

        foreach ($weeklyRows as $row) {
            $dateKey = $row['activity_date'] ?? null;
            if ($dateKey && isset($weeklyMap[$dateKey])) {
                $weeklyMap[$dateKey]['count'] = (int)($row['count'] ?? 0);
            }
        }
        $weeklyActivity = array_values($weeklyMap);

        // --- Grade Performance ---
        $stmt = $pdo->prepare("
            SELECT 
                sc.grade_level,
                ROUND(AVG(ar.accuracy_percentage), 1) as accuracy,
                ROUND(AVG(ar.fluency_score), 1) as fluency,
                ROUND(AVG(ar.wcpm), 1) as rate
            FROM student s
            JOIN student_category sc ON s.category_id = sc.category_id
            JOIN reading_activity ra ON ra.student_id = s.student_id
            JOIN assessment_result ar ON ar.activity_id = ra.activity_id
            WHERE s.is_active = 1 $dateSql
            GROUP BY sc.grade_level
            ORDER BY sc.grade_level
        ");
        $stmt->execute($params);
        $gradePerformance = $stmt->fetchAll();

        // --- Class Performance by Assignment ---
        $stmt = $pdo->prepare("
            SELECT
                ra2.title as assignment,
                sc.grade_level,
                c.section as section,
                AVG(ar.wcpm) as avg_wcpm,
                AVG(ar.accuracy_percentage) as avg_accuracy,
                SUM(ar.substitutions) as total_substitutions,
                SUM(ar.omissions) as total_omissions,
                SUM(ar.insertions) as total_insertions,
                SUM(ar.repetitions) as total_repetitions
            FROM assessment_result ar
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            JOIN reading_assignment_material ram ON ra.assignment_material_id = ram.assignment_material_id
            JOIN reading_assignment ra2 ON ram.assignment_id = ra2.assignment_id
            JOIN student s ON ra.student_id = s.student_id
            LEFT JOIN student_category sc ON s.category_id = sc.category_id
            LEFT JOIN class c ON s.class_id = c.class_id
            WHERE ra2.status = 'assigned'
            AND ra2.title IS NOT NULL
            AND ra2.title != ''
            AND ra.assignment_material_id IS NOT NULL
            $dateSql
            GROUP BY ra2.assignment_id, ra2.title, sc.grade_level, c.section
            ORDER BY ra2.assignment_id DESC
            LIMIT 7
        ");
        $stmt->execute($params);
        $classPerformance = $stmt->fetchAll();

        // --- Risk Students ---
        $stmt = $pdo->prepare("
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
            WHERE s.is_active = 1 $dateSql
            GROUP BY s.student_id
            HAVING AVG(ar.wcpm) < 40 OR AVG(ar.accuracy_percentage) < 60
            ORDER BY AVG(ar.accuracy_percentage) ASC, AVG(ar.wcpm) ASC
            LIMIT 5
        ");
        $stmt->execute($params);
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
                'class_performance' => $classPerformance ?: [],
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