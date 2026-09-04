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
        $stmt = $pdo->prepare('
            SELECT DISTINCT c.class_id, c.class_name 
            FROM class c
            JOIN student s ON s.class_id = c.class_id
            WHERE s.teacher_id = :teacher_id
            ORDER BY c.class_name
        ');
        $stmt->execute([':teacher_id' => $teacherId]);
    } else if (in_array($user['role'], ['principal', 'admin'])) {
        // For principal/admin: get all classes
        $stmt = $pdo->query('SELECT class_id, class_name FROM class ORDER BY class_name');
    } else {
        sendJson(['success' => false, 'message' => 'Access denied'], 403);
    }

    $classes = $stmt->fetchAll();
    sendJson(['success' => true, 'classes' => $classes]);
}