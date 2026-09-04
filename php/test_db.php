<?php
// php/test_db.php - Database connection test

echo "<h2>Database Connection Test</h2>";

// You need to put YOUR MySQL Workbench password here
// Whatever password you use to connect in MySQL Workbench
$mysql_password = ''; // <-- PUT YOUR PASSWORD HERE

$host = 'localhost';
$dbname = 'archivevox';
$username = 'root';

// Test with the password you use for MySQL Workbench
$conn = @new mysqli($host, $username, $mysql_password, $dbname);

if ($conn->connect_error) {
    echo "<p style='color:red'>Failed to connect: " . $conn->connect_error . "</p>";
    echo "<p>Try changing the password in the script above.</p>";
} else {
    echo "<p style='color:green'>SUCCESS! Connected to database!</p>";
    
    // Test query
    $result = $conn->query("SELECT COUNT(*) as count FROM student");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Number of students: " . $row['count'] . "</p>";
    }
    $conn->close();
}
?>