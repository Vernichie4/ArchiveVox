<?php
require_once __DIR__ . '/../auth/config.php';

function getTeacherDashboard(int $teacherId): array {
    global $pdo;
    
    try {
        // 1. Get total students for this teacher
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as total 
            FROM student 
            WHERE teacher_id = :teacher_id AND is_active = 1
        ');
        $stmt->execute([':teacher_id' => $teacherId]);
        $studentCount = $stmt->fetch();
        $myStudents = $studentCount ? (int)$studentCount['total'] : 0;
        
        // 2. Get class average WCPM
        $stmt = $pdo->prepare('
            SELECT AVG(ar.wcpm) as avg_wcpm
            FROM assessment_result ar
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            JOIN student s ON ra.student_id = s.student_id
            WHERE s.teacher_id = :teacher_id
            AND ar.wcpm > 0
        ');
        $stmt->execute([':teacher_id' => $teacherId]);
        $avgWcpm = $stmt->fetch();
        $classAvgWcpm = $avgWcpm && $avgWcpm['avg_wcpm'] ? (float)$avgWcpm['avg_wcpm'] : 0;
        
        // 3. Get total assessments this month for this teacher's students
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as total
            FROM assessment_result ar
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            JOIN student s ON ra.student_id = s.student_id
            WHERE s.teacher_id = :teacher_id
            AND ar.assessed_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")
        ');
        $stmt->execute([':teacher_id' => $teacherId]);
        $assessments = $stmt->fetch();
        $myAssessments = $assessments ? (int)$assessments['total'] : 0;
        
        // 4. Get students below target (WCPM < 40)
        $stmt = $pdo->prepare('
            SELECT COUNT(DISTINCT s.student_id) as total
            FROM student s
            JOIN reading_activity ra ON s.student_id = ra.student_id
            JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            WHERE s.teacher_id = :teacher_id
            AND ar.wcpm < 40
            AND ar.assessed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ');
        $stmt->execute([':teacher_id' => $teacherId]);
        $studentsBelow = $stmt->fetch();
        $studentsBelowTarget = $studentsBelow ? (int)$studentsBelow['total'] : 0;
        
        // 5. Get recent assessments (last 10)
        $stmt = $pdo->prepare('
            SELECT 
                CONCAT(s.first_name, " ", s.last_name) as student_name,
                rm.title as material_title,
                ar.accuracy_percentage,
                ar.fluency_score,
                ar.wcpm,
                ar.miscues,
                ar.comprehension_score,
                ar.assessed_at,
                ar.reading_level
            FROM assessment_result ar
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            JOIN student s ON ra.student_id = s.student_id
            LEFT JOIN reading_material rm ON ra.material_id = rm.material_id
            WHERE s.teacher_id = :teacher_id
            ORDER BY ar.assessed_at DESC
            LIMIT 10
        ');
        $stmt->execute([':teacher_id' => $teacherId]);
        $recentAssessments = $stmt->fetchAll();
        
        // 6. Get class performance data for charts (by student)
        $stmt = $pdo->prepare('
            SELECT 
                s.student_id,
                CONCAT(s.first_name, " ", s.last_name) as student_name,
                AVG(ar.wcpm) as wcpm,
                AVG(ar.accuracy_percentage) as accuracy_percentage,
                MAX(ar.reading_level) as reading_level
            FROM assessment_result ar
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            JOIN student s ON ra.student_id = s.student_id
            WHERE s.teacher_id = :teacher_id
            AND ar.wcpm > 0
            GROUP BY s.student_id
            ORDER BY wcpm DESC
            LIMIT 20
        ');
        $stmt->execute([':teacher_id' => $teacherId]);
        $classPerformance = $stmt->fetchAll();
        
        // 7. Get recent students with assessment stats
        $stmt = $pdo->prepare('
            SELECT 
                s.student_id,
                s.first_name,
                s.last_name,
                s.lrn,
                s.grade_level,
                s.section,
                COUNT(DISTINCT ra.activity_id) as assessment_count,
                AVG(ar.wcpm) as avg_wcpm,
                MAX(ar.reading_level) as reading_level
            FROM student s
            LEFT JOIN reading_activity ra ON s.student_id = ra.student_id
            LEFT JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            WHERE s.teacher_id = :teacher_id
            AND s.is_active = 1
            GROUP BY s.student_id
            ORDER BY s.last_name ASC
            LIMIT 15
        ');
        $stmt->execute([':teacher_id' => $teacherId]);
        $recentStudents = $stmt->fetchAll();
        
        return [
            'my_students' => $myStudents,
            'class_avg_wcpm' => $classAvgWcpm,
            'my_assessments' => $myAssessments,
            'students_below' => $studentsBelowTarget,
            'recent_assessments' => $recentAssessments,
            'class_performance' => $classPerformance,
            'recent_students' => $recentStudents
        ];
        
    } catch (PDOException $e) {
        error_log('Teacher dashboard error: ' . $e->getMessage());
        return [
            'my_students' => 0,
            'class_avg_wcpm' => 0,
            'my_assessments' => 0,
            'students_below' => 0,
            'recent_assessments' => [],
            'class_performance' => [],
            'recent_students' => [],
            'error' => $e->getMessage()
        ];
    }
}

function principalDashboard(): array {
    global $pdo;
    
    try {
        // Get total students
        $stmt = $pdo->query('SELECT COUNT(*) as total FROM student WHERE is_active = 1');
        $totalStudents = (int)$stmt->fetch()['total'];
        
        // Get total materials
        $stmt = $pdo->query('SELECT COUNT(*) as total FROM reading_material WHERE status = "Active"');
        $totalMaterials = (int)$stmt->fetch()['total'];
        
        // Get total assessments
        $stmt = $pdo->query('SELECT COUNT(*) as total FROM assessment_result');
        $totalAssessments = (int)$stmt->fetch()['total'];
        
        // Get average accuracy
        $stmt = $pdo->query('SELECT AVG(accuracy_percentage) as avg FROM assessment_result WHERE accuracy_percentage > 0');
        $avgAccuracy = (float)($stmt->fetch()['avg'] ?? 0);
        
        // Get students this week
        $stmt = $pdo->query('
            SELECT COUNT(*) as total 
            FROM student 
            WHERE is_active = 1 
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ');
        $studentsThisWeek = (int)$stmt->fetch()['total'];
        
        // Get materials this week
        $stmt = $pdo->query('
            SELECT COUNT(*) as total 
            FROM reading_material 
            WHERE upload_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ');
        $materialsThisWeek = (int)$stmt->fetch()['total'];
        
        // Get assessments this month
        $stmt = $pdo->query('
            SELECT COUNT(*) as total 
            FROM assessment_result 
            WHERE assessed_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")
        ');
        $assessmentsThisMonth = (int)$stmt->fetch()['total'];
        
        // Get accuracy trend (compare this month vs last month)
        $stmt = $pdo->query('
            SELECT 
                AVG(CASE 
                    WHEN assessed_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01") 
                    THEN accuracy_percentage 
                END) as this_month,
                AVG(CASE 
                    WHEN assessed_at >= DATE_SUB(DATE_FORMAT(CURDATE(), "%Y-%m-01"), INTERVAL 1 MONTH)
                    AND assessed_at < DATE_FORMAT(CURDATE(), "%Y-%m-01")
                    THEN accuracy_percentage 
                END) as last_month
            FROM assessment_result
            WHERE accuracy_percentage > 0
        ');
        $trend = $stmt->fetch();
        $accuracyTrend = 0;
        if ($trend && $trend['last_month'] && $trend['last_month'] > 0) {
            $accuracyTrend = (float)$trend['this_month'] - (float)$trend['last_month'];
        }
        
        // Get students below target
        $stmt = $pdo->query('
            SELECT COUNT(DISTINCT s.student_id) as total
            FROM student s
            JOIN reading_activity ra ON s.student_id = ra.student_id
            JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            WHERE ar.wcpm < 40
            AND ar.assessed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ');
        $studentsBelowTarget = (int)$stmt->fetch()['total'];
        
        // Get recent assessments
        $stmt = $pdo->query('
            SELECT 
                CONCAT(s.first_name, " ", s.last_name) as student_name,
                rm.title as material_title,
                ar.accuracy_percentage,
                ar.fluency_score,
                ar.wcpm,
                ar.assessed_at,
                ar.reading_level
            FROM assessment_result ar
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            JOIN student s ON ra.student_id = s.student_id
            LEFT JOIN reading_material rm ON ra.material_id = rm.material_id
            ORDER BY ar.assessed_at DESC
            LIMIT 10
        ');
        $recentAssessments = $stmt->fetchAll();
        
        // Get weekly activity (last 7 days)
        $stmt = $pdo->query('
            SELECT 
                DAYNAME(assessed_at) as day,
                COUNT(*) as count
            FROM assessment_result
            WHERE assessed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DAYNAME(assessed_at)
            ORDER BY assessed_at ASC
        ');
        $weeklyActivity = $stmt->fetchAll();
        
        // Fill in missing days
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $weeklyMap = [];
        foreach ($weeklyActivity as $row) {
            $weeklyMap[$row['day']] = (int)$row['count'];
        }
        $weeklyData = [];
        foreach ($days as $day) {
            $weeklyData[] = [
                'day' => $day,
                'count' => $weeklyMap[$day] ?? 0
            ];
        }
        
        // Get grade performance
        $stmt = $pdo->query('
            SELECT 
                s.grade_level,
                AVG(ar.accuracy_percentage) as accuracy,
                AVG(ar.fluency_score) as fluency,
                AVG(ar.wcpm) as rate
            FROM assessment_result ar
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            JOIN student s ON ra.student_id = s.student_id
            WHERE ar.accuracy_percentage > 0
            GROUP BY s.grade_level
            ORDER BY s.grade_level
        ');
        $gradePerformance = $stmt->fetchAll();
        
        // Get students at risk
        $stmt = $pdo->query('
            SELECT DISTINCT
                s.first_name,
                s.last_name,
                s.lrn,
                s.grade_level,
                ar.reading_level
            FROM student s
            JOIN reading_activity ra ON s.student_id = ra.student_id
            JOIN assessment_result ar ON ra.activity_id = ar.activity_id
            WHERE ar.reading_level = "Frustration"
            AND ar.assessed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY s.last_name ASC
            LIMIT 10
        ');
        $riskStudents = $stmt->fetchAll();
        
        return [
            'total_students' => $totalStudents,
            'total_materials' => $totalMaterials,
            'total_assessments' => $totalAssessments,
            'avg_accuracy' => $avgAccuracy,
            'students_this_week' => $studentsThisWeek,
            'materials_this_week' => $materialsThisWeek,
            'assessments_this_month' => $assessmentsThisMonth,
            'accuracy_trend' => $accuracyTrend,
            'students_below_target' => $studentsBelowTarget,
            'recent_assessments' => $recentAssessments,
            'weekly_activity' => $weeklyData,
            'grade_performance' => $gradePerformance,
            'risk_students' => $riskStudents
        ];
        
    } catch (PDOException $e) {
        error_log('Principal dashboard error: ' . $e->getMessage());
        return [
            'total_students' => 0,
            'total_materials' => 0,
            'total_assessments' => 0,
            'avg_accuracy' => 0,
            'students_this_week' => 0,
            'materials_this_week' => 0,
            'assessments_this_month' => 0,
            'accuracy_trend' => 0,
            'students_below_target' => 0,
            'recent_assessments' => [],
            'weekly_activity' => [],
            'grade_performance' => [],
            'risk_students' => []
        ];
    }
}

// Legacy function for backward compatibility
function teacherDashboard(): array {
    global $pdo;

    $studentCount = (int) $pdo->query('SELECT COUNT(*) FROM student')->fetchColumn();
    $recentAssessments = $pdo->query('SELECT assessment_id, wcpm, assessed_at FROM assessment_result ORDER BY assessed_at DESC LIMIT 5')->fetchAll();
    $avgWcpm = (float) $pdo->query('SELECT AVG(wcpm) FROM assessment_result')->fetchColumn();

    return [
        'student_count' => $studentCount,
        'recent_assessments' => $recentAssessments,
        'average_wcpm' => $avgWcpm,
    ];
}