<?php
// Database connection settings
$host = '127.0.0.1:3307';
$db   = 'archivevox';
$user = 'root'; // Update if your local setup uses a different username
$pass = '';     // Update if your local setup uses a password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Fetch all active students who don't have a user account yet
    $stmt = $pdo->query("SELECT student_id, lrn, first_name, last_name FROM student WHERE user_id IS NULL AND lrn IS NOT NULL");
    $students = $stmt->fetchAll();

    if (empty($students)) {
        echo "<h3>No students found that need user accounts.</h3>";
        exit;
    }

    echo "<h3>Starting Account Generation...</h3><ul>";

    foreach ($students as $student) {
        // We will use their LRN as both username and default password
        $username = $student['lrn'];
        $default_password = $student['lrn'];
        $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
        
        $pdo->beginTransaction();

        try {
            // 1. Insert into user table
            $insertUser = $pdo->prepare("INSERT INTO user (username, password, role, status) VALUES (?, ?, 'Student', 'Active')");
            $insertUser->execute([$username, $hashed_password]);
            
            // Get the newly created user_id
            $new_user_id = $pdo->lastInsertId();

            // 2. Update the student table with the new user_id
            $updateStudent = $pdo->prepare("UPDATE student SET user_id = ? WHERE student_id = ?");
            $updateStudent->execute([$new_user_id, $student['student_id']]);

            $pdo->commit();
            
            echo "<li>✅ Created account for <strong>{$student['first_name']} {$student['last_name']}</strong> (Username: $username)</li>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<li>❌ Error creating account for {$student['first_name']} {$student['last_name']}: " . $e->getMessage() . "</li>";
        }
    }

    echo "</ul><h3>Done!</h3>";
    echo "<p><strong>Important:</strong> Please delete this script from your server after running it for security reasons.</p>";

} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>