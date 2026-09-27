<?php

function getDbConfig(): array {
    return [
        // Check $_SERVER first (best for FrankenPHP), then getenv(), then XAMPP fallback
        'host' => $_SERVER['MYSQLHOST'] ?? getenv('MYSQLHOST') ?: '127.0.0.1',
        'port' => $_SERVER['MYSQLPORT'] ?? getenv('MYSQLPORT') ?: '3307',  
        'name' => $_SERVER['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?: 'archivevox',
        'user' => $_SERVER['MYSQLUSER'] ?? getenv('MYSQLUSER') ?: 'root',
        'pass' => $_SERVER['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?: '',
        'charset' => $_SERVER['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4',
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

    try {
        return new PDO(
            $dsn,
            $config['user'],
            $config['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
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
