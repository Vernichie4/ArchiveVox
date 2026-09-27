<?php
// php/test_db.php - Database connection test for Supabase (PostgreSQL)

echo "<h2>Database Connection Test (Supabase)</h2>";

// Load .env file manually
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

require_once __DIR__ . '/connection.php';

try {
    $config = getDbConfig();
    echo "<p>Connecting to: " . $config['host'] . ":" . $config['port'] . "</p>";
    echo "<p>Database: " . $config['name'] . "</p>";
    echo "<p>User: " . $config['user'] . "</p>";

    $pdo = createPdoConnection();
    echo "<p style='color:green'>SUCCESS! Connected to Supabase database!</p>";

    // Test query
    $result = $pdo->query("SELECT COUNT(*) as count FROM student");
    if ($result) {
        $row = $result->fetch();
        echo "<p>Number of students: " . $row['count'] . "</p>";
    }

    // Test another query
    $result = $pdo->query("SELECT version()");
    if ($result) {
        $row = $result->fetch();
        echo "<p>PostgreSQL version: " . $row['version'] . "</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red'>Failed to connect: " . $e->getMessage() . "</p>";
    echo "<p>Check your .env file configuration.</p>";
}
?>