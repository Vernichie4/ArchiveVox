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

    if ($method === 'GET') {
        
        if ($action === 'student-progress') {
            // Get a specific student's reading progress
            $studentId = $_GET['id'] ?? 0;
            
            // Check if principal can access this student
            if ($user['role'] !== 'principal' && $user['role'] !== 'admin') {
                $stmt = $pdo->prepare('
                    SELECT teacher_id FROM teacher WHERE user_id = :user_id
                ');
                $stmt->execute([':user_id' => $user['user_id']]);
                $teacher = $stmt->fetch();
                
                $stmt = $pdo->prepare('
                    SELECT COUNT(*) as count FROM teacher_student 
                    WHERE teacher_id = :teacher_id AND student_id = :student_id
                ');
                $stmt->execute([':teacher_id' => $teacher['teacher_id'] ?? 0, ':student_id' => $studentId]);
                
                if (!$stmt->fetch()['count']) {
                    sendJson(['success' => false, 'message' => 'Access denied'], 403);
                }
            }
            
            // Get student info
            $stmt = $pdo->prepare('SELECT * FROM student WHERE student_id = :student_id');
            $stmt->execute([':student_id' => $studentId]);
            $student = $stmt->fetch();
            
            if (!$student) {
                sendJson(['success' => false, 'message' => 'Student not found'], 404);
            }
            
            // Get assessment history
            $stmt = $pdo->prepare('
                SELECT 
                    ar.assessment_id,
                    ar.assessment_date,
                    rm.title,
                    ar.wcpm,
                    ar.accuracy_percentage,
                    ar.errors_count,
                    t.first_name as teacher_first_name,
                    t.last_name as teacher_last_name
                FROM assessment_result ar
                JOIN reading_material rm ON ar.material_id = rm.material_id
                LEFT JOIN teacher t ON ar.recorded_by = t.teacher_id
                WHERE ar.student_id = :student_id
                ORDER BY ar.assessment_date DESC
            ');
            $stmt->execute([':student_id' => $studentId]);
            $assessments = $stmt->fetchAll();
            
            sendJson([
                'success' => true,
                'student' => $student,
                'assessments' => $assessments,
                'statistics' => calculateStudentStats($assessments)
            ]);
        }
        
        elseif ($action === 'class-performance') {
            // Get performance report for a specific teacher's class
            if ($user['role'] === 'teacher') {
                $stmt = $pdo->prepare('SELECT teacher_id FROM teacher WHERE user_id = :user_id');
                $stmt->execute([':user_id' => $user['user_id']]);
                $teacher = $stmt->fetch();
                $teacherId = $teacher['teacher_id'] ?? 0;
            } else {
                $teacherId = $_GET['teacher_id'] ?? 0;
            }
            
            if (!$teacherId) {
                sendJson(['success' => false, 'message' => 'Teacher ID required'], 400);
            }
            
            $stmt = $pdo->prepare('
                SELECT 
                    s.student_id,
                    s.first_name,
                    s.last_name,
                    s.grade_level,
                    COUNT(ar.assessment_id) as assessment_count,
                    AVG(ar.wcpm) as avg_wcpm,
                    AVG(ar.accuracy_percentage) as avg_accuracy,
                    MAX(ar.assessment_date) as last_assessment_date,
                    MAX(ar.wcpm) as max_wcpm,
                    MIN(ar.wcpm) as min_wcpm
                FROM student s
                JOIN teacher_student ts ON s.student_id = ts.student_id
                LEFT JOIN assessment_result ar ON ar.student_id = s.student_id
                WHERE ts.teacher_id = :teacher_id
                GROUP BY s.student_id
                ORDER BY s.first_name, s.last_name
            ');
            $stmt->execute([':teacher_id' => $teacherId]);
            $students = $stmt->fetchAll();
            
            sendJson(['success' => true, 'students' => $students]);
        }
        
        elseif ($action === 'school-wide') {
            // Only principals and admins can access
            if (!in_array($user['role'], ['principal', 'admin'])) {
                sendJson(['success' => false, 'message' => 'Access denied'], 403);
            }
            
            // Get statistics by grade level
            $stmt = $pdo->prepare('
                SELECT 
                    s.grade_level,
                    COUNT(DISTINCT s.student_id) as student_count,
                    COUNT(DISTINCT ar.assessment_id) as assessment_count,
                    AVG(ar.wcpm) as avg_wcpm,
                    AVG(ar.accuracy_percentage) as avg_accuracy,
                    MIN(ar.wcpm) as min_wcpm,
                    MAX(ar.wcpm) as max_wcpm
                FROM student s
                LEFT JOIN assessment_result ar ON ar.student_id = s.student_id
                GROUP BY s.grade_level
                ORDER BY s.grade_level
            ');
            $stmt->execute();
            $gradeStats = $stmt->fetchAll();
            
            // Get top performers
            $stmt = $pdo->prepare('
                SELECT 
                    s.student_id,
                    s.first_name,
                    s.last_name,
                    s.grade_level,
                    AVG(ar.wcpm) as avg_wcpm,
                    COUNT(ar.assessment_id) as assessment_count
                FROM student s
                JOIN assessment_result ar ON ar.student_id = s.student_id
                GROUP BY s.student_id
                HAVING COUNT(ar.assessment_id) >= 2
                ORDER BY avg_wcpm DESC
                LIMIT 10
            ');
            $stmt->execute();
            $topPerformers = $stmt->fetchAll();
            
            // Get students needing intervention
            $stmt = $pdo->prepare('
                SELECT 
                    s.student_id,
                    s.first_name,
                    s.last_name,
                    s.grade_level,
                    AVG(ar.wcpm) as avg_wcpm,
                    COUNT(ar.assessment_id) as assessment_count
                FROM student s
                JOIN assessment_result ar ON ar.student_id = s.student_id
                GROUP BY s.student_id
                HAVING COUNT(ar.assessment_id) >= 1 AND AVG(ar.wcpm) < 40
                ORDER BY avg_wcpm ASC
                LIMIT 10
            ');
            $stmt->execute();
            $intervention = $stmt->fetchAll();
            
            sendJson([
                'success' => true,
                'grade_statistics' => $gradeStats,
                'top_performers' => $topPerformers,
                'intervention_needed' => $intervention
            ]);
        }
        
        elseif ($action === 'material-usage') {
            // Get which materials are being used most
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
                    COUNT(ar.assessment_id) as usage_count,
                    AVG(ar.wcpm) as avg_wcpm,
                    AVG(ar.accuracy_percentage) as avg_accuracy
                FROM reading_material rm
                LEFT JOIN assessment_result ar ON ar.material_id = rm.material_id
                WHERE rm.status = "Active"
                GROUP BY rm.material_id
                ORDER BY usage_count DESC
            ');
            $stmt->execute();
            $materials = $stmt->fetchAll();
            
            sendJson(['success' => true, 'materials' => $materials]);
        }
        
        else {
            sendJson(['success' => false, 'message' => 'Invalid action'], 400);
        }
    } 
    else {
        http_response_code(405);
        sendJson(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log('Reports API error: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Server error'], 500);
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function calculateStudentStats($assessments) {
    if (empty($assessments)) {
        return [
            'total_assessments' => 0,
            'avg_wcpm' => 0,
            'avg_accuracy' => 0,
            'max_wcpm' => 0,
            'min_wcpm' => 0,
            'trend' => 'no_data'
        ];
    }
    
    $wcpms = array_filter(array_map(fn($a) => $a['wcpm'], $assessments));
    $accuracies = array_filter(array_map(fn($a) => $a['accuracy_percentage'], $assessments));
    
    $stats = [
        'total_assessments' => count($assessments),
        'avg_wcpm' => count($wcpms) > 0 ? array_sum($wcpms) / count($wcpms) : 0,
        'avg_accuracy' => count($accuracies) > 0 ? array_sum($accuracies) / count($accuracies) : 0,
        'max_wcpm' => count($wcpms) > 0 ? max($wcpms) : 0,
        'min_wcpm' => count($wcpms) > 0 ? min($wcpms) : 0
    ];
    
    // Calculate trend
    if (count($wcpms) >= 2) {
        $first = array_pop($wcpms);
        $last = $wcpms[0] ?? $first;
        $stats['trend'] = $last > $first ? 'improving' : ($last < $first ? 'declining' : 'stable');
    } else {
        $stats['trend'] = 'insufficient_data';
    }
    
    return $stats;
}
