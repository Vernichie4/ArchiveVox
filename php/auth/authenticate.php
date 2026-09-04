<?php
// php/api/auth/authenticate.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

// Rate limiting configuration
define('MAX_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 300); // 5 minutes in seconds

function getClientIP(): string {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 
          $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
          $_SERVER['HTTP_X_REAL_IP'] ?? 
          $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return explode(',', $ip)[0];
}

function getUserAgent(): string {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

function isIPLocked(PDO $pdo, string $ip): array {
    try {
        // Check if IP has too many failed attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts, MAX(attempt_time) as last_attempt
            FROM login_attempts 
            WHERE ip_address = ? 
            AND success = 0 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, LOCKOUT_DURATION]);
        $result = $stmt->fetch();
        
        return [
            'locked' => ($result['attempts'] ?? 0) >= MAX_ATTEMPTS,
            'attempts' => (int)($result['attempts'] ?? 0),
            'last_attempt' => $result['last_attempt'] ?? null
        ];
    } catch (Exception $e) {
        error_log('isIPLocked error: ' . $e->getMessage());
        return ['locked' => false, 'attempts' => 0, 'last_attempt' => null];
    }
}

function logLoginAttempt(PDO $pdo, ?int $userId, string $ip, bool $success): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (user_id, ip_address, success, user_agent)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $ip, $success ? 1 : 0, getUserAgent()]);
    } catch (Exception $e) {
        error_log('logLoginAttempt error: ' . $e->getMessage());
    }
}

function updateUserFailedAttempts(PDO $pdo, int $userId, bool $success): void {
    try {
        if ($success) {
            // Reset failed attempts on success
            $stmt = $pdo->prepare("UPDATE `user` SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?");
            $stmt->execute([$userId]);
        } else {
            // Increment failed attempts
            $stmt = $pdo->prepare("
                UPDATE `user` 
                SET failed_attempts = failed_attempts + 1,
                    locked_until = CASE 
                        WHEN failed_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? SECOND)
                        ELSE locked_until 
                    END
                WHERE user_id = ?
            ");
            $stmt->execute([MAX_ATTEMPTS, LOCKOUT_DURATION, $userId]);
        }
    } catch (Exception $e) {
        error_log('updateUserFailedAttempts error: ' . $e->getMessage());
    }
}

function fetchUserRecord(PDO $pdo, string $username): ?array {
    try {
        // Check if user is locked
        $stmt = $pdo->prepare("
            SELECT user_id, username, password, role, status, 
                   failed_attempts, locked_until
            FROM user 
            WHERE username = :username 
            LIMIT 1
        ");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        
        if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
            // Account is locked
            return ['locked' => true, 'locked_until' => $user['locked_until']];
        }
        
        return $user ?: null;
    } catch (Throwable $e) {
        error_log('fetchUserRecord error: ' . $e->getMessage());
        return null;
    }
}

function getUserDetails(PDO $pdo, int $userId, string $role): array {
    $role = strtolower($role);
    $details = [];
    
    try {
        if ($role === 'teacher') {
            $stmt = $pdo->prepare("
                SELECT first_name, last_name, email, teacher_id 
                FROM teacher 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $details = $stmt->fetch();
        } elseif ($role === 'principal') {
            $stmt = $pdo->prepare("
                SELECT first_name, last_name, email, principal_id 
                FROM principal 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $details = $stmt->fetch();
        } elseif ($role === 'admin') {
            $stmt = $pdo->prepare("
                SELECT 'Administrator' as first_name, '' as last_name, username as email
                FROM user 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $details = $stmt->fetch();
        }
    } catch (Exception $e) {
        error_log('getUserDetails error: ' . $e->getMessage());
    }
    
    return $details ?: [];
}

function authenticateUser(string $username, string $password): ?array {
    global $pdo;
    $ip = getClientIP();

    try {
        // Check IP lockout first
        $ipStatus = isIPLocked($pdo, $ip);
        if ($ipStatus['locked']) {
            $remaining = LOCKOUT_DURATION - (time() - strtotime($ipStatus['last_attempt']));
            error_log("IP $ip locked. Remaining: $remaining seconds");
            return [
                'error' => 'too_many_attempts',
                'message' => "Too many failed attempts. Please wait " . ceil($remaining / 60) . " minutes."
            ];
        }

        $user = fetchUserRecord($pdo, $username);
        
        // Check if account is locked
        if ($user && isset($user['locked']) && $user['locked'] === true) {
            $lockedUntil = new DateTime($user['locked_until']);
            $now = new DateTime();
            $diff = $now->diff($lockedUntil);
            $remaining = ($diff->days * 86400) + ($diff->h * 3600) + ($diff->i * 60) + $diff->s;
            
            return [
                'error' => 'account_locked',
                'message' => "Account temporarily locked. Please try again in " . ceil($remaining / 60) . " minutes."
            ];
        }

        // User not found
        if (!$user) {
            logLoginAttempt($pdo, null, $ip, false);
            return ['error' => 'invalid_credentials', 'message' => 'Invalid username or password.'];
        }

        // Check if user is active
        if (strtolower((string) ($user['status'] ?? '')) !== 'active') {
            logLoginAttempt($pdo, (int)$user['user_id'], $ip, false);
            return ['error' => 'account_inactive', 'message' => 'Account is inactive. Please contact support.'];
        }

        // Verify password - support both hash and plaintext (for dev)
        $verified = false;
        
        // Hash verification (production)
        if (password_verify($password, $user['password'])) {
            $verified = true;
        }
        
        // Plain text fallback (development only)
        if (!$verified && $password === $user['password']) {
            $verified = true;
            // Upgrade to hash on the fly
            try {
                $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                $stmt = $pdo->prepare("UPDATE `user` SET password = ? WHERE user_id = ?");
                $stmt->execute([$hashed, $user['user_id']]);
            } catch (Exception $e) {
                error_log('Password upgrade error: ' . $e->getMessage());
            }
        }

        if (!$verified) {
            // Update failed attempts
            updateUserFailedAttempts($pdo, (int)$user['user_id'], false);
            logLoginAttempt($pdo, (int)$user['user_id'], $ip, false);
            
            // Get remaining attempts
            $stmt = $pdo->prepare("SELECT failed_attempts FROM `user` WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $failed = $stmt->fetch();
            $remaining = MAX_ATTEMPTS - ($failed['failed_attempts'] ?? 0);
            
            return [
                'error' => 'invalid_credentials',
                'message' => "Invalid username or password." . ($remaining > 0 ? " ($remaining attempts remaining)" : "")
            ];
        }

        // Successful login
        $userId = (int)$user['user_id'];
        $role = strtolower((string) $user['role']);
        
        // Reset failed attempts
        updateUserFailedAttempts($pdo, $userId, true);
        
        // Log successful attempt
        logLoginAttempt($pdo, $userId, $ip, true);
        
        // Log to login_logs
        try {
            $stmt = $pdo->prepare("
                INSERT INTO login_logs (user_id, ip_address, device_info, login_time)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $ip, getUserAgent()]);
        } catch (Exception $e) {
            error_log('Login log error: ' . $e->getMessage());
        }
        
        // Update last login
        try {
            $stmt = $pdo->prepare("
                UPDATE `user` 
                SET last_login = NOW(), 
                    last_ip = ?,
                    last_device = ?,
                    failed_attempts = 0,
                    locked_until = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$ip, getUserAgent(), $userId]);
        } catch (Exception $e) {
            error_log('Update last login error: ' . $e->getMessage());
        }

        // Get user details
        $details = getUserDetails($pdo, $userId, $role);
        
        // Build user response
        $userData = [
            'user_id' => $userId,
            'username' => $user['username'],
            'role' => $role,
            'first_name' => $details['first_name'] ?? 'User',
            'last_name' => $details['last_name'] ?? '',
            'email' => $details['email'] ?? $user['username'],
            'teacher_id' => $details['teacher_id'] ?? null,
            'principal_id' => $details['principal_id'] ?? null
        ];

        return $userData;
        
    } catch (Exception $e) {
        error_log('Authentication error: ' . $e->getMessage());
        return ['error' => 'system_error', 'message' => 'System error. Please try again later.'];
    }
}