<?php
// php/test_db.php - Database connection test for MySQL

echo "<h2>Database Connection Test (MySQL)</h2>";

require_once __DIR__ . '/connection.php';

try {
    $pdo = createPdoConnection();
    echo "<p style='color:green'>SUCCESS! Connected to MySQL database!</p>";
    
    // Test query
    $result = $pdo->query("SELECT COUNT(*) as count FROM student");
    if ($result) {
        $row = $result->fetch();
        echo "<p>Number of students: " . $row['count'] . "</p>";
    }
    
    // Test another query
    $result = $pdo->query("SELECT VERSION() as version");
    if ($result) {
        $row = $result->fetch();
        echo "<p>MySQL version: " . $row['version'] . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Failed to connect: " . $e->getMessage() . "</p>";
    echo "<p>Check your MySQL configuration.</p>";
}
?>