<?php

function getDbConfig(): array {
    return [
        // Checks Railway first, then custom DB env, then falls back to local XAMPP
        'host' => getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3307',  
        'name' => getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'archivevox',
        'user' => getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root',
        'pass' => getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '',
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
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
        ]
    );
}