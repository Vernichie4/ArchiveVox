<?php
require_once __DIR__ . '/config.php';

function normalizeSessionUser(array $user): array {
    if (isset($user['role'])) {
        $user['role'] = strtolower((string) $user['role']);
    }
    return $user;
}

function loginUser(array $user): void {
    if (!isset($user['role']) || $user['role'] === '') {
        $user['role'] = 'teacher';
    }

    $_SESSION['user'] = normalizeSessionUser($user);
    $_SESSION['logged_in_at'] = time();
}

function logoutUser(): void {
    session_unset();
    session_destroy();
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user']);
}

function currentUser(): ?array {
    if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return null;
    }

    $normalized = normalizeSessionUser($_SESSION['user']);
    $_SESSION['user'] = $normalized;
    return $normalized;
}