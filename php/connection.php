<?php

// Load .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!empty($name)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

function getDbConfig(): array {
    return [
        'host' => getenv('MYSQLHOST') ?: getenv('DB_HOST'),
        'port' => getenv('MYSQLPORT') ?: getenv('DB_PORT'),
        'name' => getenv('MYSQLDATABASE') ?: getenv('DB_NAME'),
        'user' => getenv('MYSQLUSER') ?: getenv('DB_USER'),
        'pass' => getenv('MYSQLPASSWORD') ?: getenv('DB_PASS'),
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ];
}

function createPdoConnection(): PDO {
    $config = getDbConfig();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

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
}