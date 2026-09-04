<?php
// php/api/auth/reset-password.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/authenticate.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'request') {
    // Handle password reset request
    $data = json_decode(file_get_contents('php://input'), true);
    $username = trim($data['username'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Username is required.']);
        exit;
    }
    
    // Check if user exists
    $user = fetchUserRecord($pdo, $username);
    if (!$user || isset($user['locked'])) {
        // Don't reveal if user exists or not
        echo json_encode(['success' => true, 'message' => 'If an account exists, a reset link has been sent.']);
        exit;
    }
    
    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Save token
    $stmt = $pdo->prepare("
        INSERT INTO password_reset_tokens (user_id, token, expires_at)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['user_id'], password_hash($token, PASSWORD_BCRYPT), $expires]);
    
    // In production, send email with reset link
    $resetLink = "https://yourdomain.com/reset-password?token=" . $token;
    
    echo json_encode([
        'success' => true,
        'message' => 'If an account exists, a reset link has been sent.',
        // Remove in production - for testing only
        'debug_token' => $token,
        'debug_link' => $resetLink
    ]);
    exit;
}

if ($action === 'verify') {
    // Verify reset token
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Token is required.']);
        exit;
    }
    
    // Find valid token
    $stmt = $pdo->prepare("
        SELECT * FROM password_reset_tokens 
        WHERE expires_at > NOW() AND used_at IS NULL
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute();
    $record = $stmt->fetch();
    
    if (!$record || !password_verify($token, $record['token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
        exit;
    }
    
    echo json_encode(['success' => true, 'message' => 'Token is valid.', 'user_id' => $record['user_id']]);
    exit;
}

if ($action === 'reset') {
    // Reset password
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    $newPassword = $data['password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';
    
    if (empty($token) || empty($newPassword) || $newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }
    
    // Find valid token
    $stmt = $pdo->prepare("
        SELECT * FROM password_reset_tokens 
        WHERE expires_at > NOW() AND used_at IS NULL
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute();
    $record = $stmt->fetch();
    
    if (!$record || !password_verify($token, $record['token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
        exit;
    }
    
    // Update password
    $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt = $pdo->prepare("UPDATE `user` SET password = ? WHERE user_id = ?");
    $stmt->execute([$hashed, $record['user_id']]);
    
    // Mark token as used
    $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token_id = ?");
    $stmt->execute([$record['token_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);