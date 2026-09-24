<?php
require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../_helpers.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    sendJson(['success' => false, 'message' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $teacherId = null;
    if ($user['role'] === 'teacher') {
        $stmt = $pdo->prepare('SELECT teacher_id FROM teacher WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $user['user_id']]);
        $teacher = $stmt->fetch();
        if ($teacher) {
            $teacherId = $teacher['teacher_id'];
        }
    }

    if ($teacherId) {
        // For teacher: get classes they teach
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.class_id, CONCAT(c.grade_level, ' - ', c.section) AS class_name 
            FROM class c
            JOIN student s ON s.class_id = c.class_id
            WHERE s.teacher_id = :teacher_id
            ORDER BY c.grade_level, c.section
        ");
        $stmt->execute([':teacher_id' => $teacherId]);
    } else if (in_array(strtolower($user['role']), ['principal', 'admin'])) {
        // For principal/admin: get all classes
        $stmt = $pdo->query("
            SELECT class_id, CONCAT(grade_level, ' - ', section) AS class_name 
            FROM class 
            ORDER BY grade_level, section
        ");
    } else {
        sendJson(['success' => false, 'message' => 'Access denied'], 403);
    }
}