<?php
require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../_helpers.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user || $user['role'] !== 'teacher') {
    sendJson(['success' => false, 'message' => 'Unauthorized'], 401);
}

$stmt = $pdo->prepare('SELECT teacher_id FROM teacher WHERE user_id = :user_id');
$stmt->execute([':user_id' => $user['user_id']]);
$teacher = $stmt->fetch();
if (!$teacher) {
    sendJson(['success' => false, 'message' => 'Teacher record not found'], 404);
}
sendJson(['success' => true, 'teacher_id' => $teacher['teacher_id']]);

