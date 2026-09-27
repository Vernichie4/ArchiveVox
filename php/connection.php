<?php

// Load .env file with improved parsing
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        // Handle lines with = sign
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Remove quotes if present
            if (strpos($value, '"') === 0 || strpos($value, "'") === 0) {
                $value = substr($value, 1, -1);
            }
            if (!empty($name)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

function getDbConfig(): array {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $name = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');

    // Debug: Log if values are missing
    if (!$host || !$port || !$name || !$user || !$pass) {
        error_log("Missing DB config values - host: " . ($host ?: 'null') . ", port: " . ($port ?: 'null') . ", name: " . ($name ?: 'null') . ", user: " . ($user ?: 'null'));
    }

    return [
        'host' => $host ?: null,
        'port' => $port ?: null,
        'name' => $name ?: null,
        'user' => $user ?: null,
        'pass' => $pass ?: null
    ];
}

function createPdoConnection(): PDO {
    $config = getDbConfig();

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
        $config['host'],
        $config['port'],
        $config['name']
    );

    try {
        return new PDO(
            $dsn,
            $config['user'],
            $config['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        // Catch the crash and output a readable JSON error to the browser DevTools
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit;
    }
}
