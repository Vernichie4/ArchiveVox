<?php
require_once __DIR__ . '/../auth/config.php';

function addUser(array $data): array {
    global $pdo;

    $stmt = $pdo->prepare(
        'INSERT INTO user (username, password, role, status) VALUES (:username, :password, :role, :status)'
    );

    $stmt->execute([
        ':username' => $data['username'],
        ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
        ':role' => $data['role'],
        ':status' => $data['status'] ?? 'Active',
    ]);

    return ['success' => true, 'user_id' => (int) $pdo->lastInsertId()];
}

function editUser(int $userId, array $data): array {
    global $pdo;

    $fields = [];
    $values = [':user_id' => $userId];

    if (!empty($data['username'])) {
        $fields[] = 'username = :username';
        $values[':username'] = $data['username'];
    }

    if (!empty($data['role'])) {
        $fields[] = 'role = :role';
        $values[':role'] = $data['role'];
    }

    if (!empty($data['status'])) {
        $fields[] = 'status = :status';
        $values[':status'] = $data['status'];
    }

    if (!empty($data['password'])) {
        $fields[] = 'password = :password';
        $values[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    if (!$fields) {
        return ['success' => false, 'message' => 'No changes provided.'];
    }

    $stmt = $pdo->prepare('UPDATE user SET ' . implode(', ', $fields) . ' WHERE user_id = :user_id');
    $stmt->execute($values);

    return ['success' => true];
}

function deleteUser(int $userId): array {
    global $pdo;
    $stmt = $pdo->prepare('DELETE FROM user WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
    return ['success' => true];
}

function viewUsers(): array {
    global $pdo;
    $stmt = $pdo->query('SELECT user_id, username, role, status, created_at FROM user ORDER BY user_id DESC');
    return $stmt->fetchAll();
}
