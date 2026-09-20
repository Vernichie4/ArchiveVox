<?php

function readJsonInput(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function sendJson(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function loadAppBackend(): array {
    try {
        // FIXED PATHS - These files are in the correct locations now
        require_once __DIR__ . '/../auth/config.php';
        require_once __DIR__ . '/../auth/authenticate.php';
        require_once __DIR__ . '/../auth/session.php';
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ============================================================
// ROLE-BASED ACCESS CONTROL
// ============================================================

function getCurrentUser(): ?array {
    $user = $_SESSION['user'] ?? null;
    if (!is_array($user)) {
        return null;
    }

    if (isset($user['role'])) {
        $user['role'] = strtolower((string) $user['role']);
        $_SESSION['user'] = $user;
    }

    return $user;
}

function requireRole(array $allowedRoles = []): bool {
    $user = getCurrentUser();
    if (!$user) {
        sendJson(['success' => false, 'message' => 'Not authenticated'], 401);
    }
    
    if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles, true)) {
        sendJson(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    return true;
}

function hasRole(string $role): bool {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

function canAccessAllStudents(): bool {
    $user = getCurrentUser();
    return $user && in_array($user['role'], ['principal', 'admin'], true);
}