<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET");

// 1. Function to parse the .env file natively
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    // Read the file, ignoring empty lines and newlines
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments starting with #
        if (strpos(trim($line), '#') === 0) continue; 
        
        // Split by the first equal sign
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Set the environment variable
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
        }
    }
    return true;
}

// 2. Load the .env file (navigates up 3 folders from /php/api/teacher to /ArchiveVox)
$envPath = dirname(__DIR__, 3) . '/.env';
if (!loadEnv($envPath)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Environment file not found."]);
    exit();
}

// 3. Assign environment variables to database credentials
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3307';
$dbname = $_ENV['DB_NAME'] ?? 'archivevox';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? ''; 
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
$dev_mode = filter_var($_ENV['DEV_MODE'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

$response = [
    "status" => "error",
    "message" => "",
    "data" => null
];

if (!isset($_GET['student_id']) || empty($_GET['student_id'])) {
    http_response_code(400); 
    $response['message'] = "Missing required parameter: student_id";
    echo json_encode($response);
    exit();
}

$student_id = intval($_GET['student_id']);

try {
    // 4. Establish PDO connection using .env variables
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch Actual Miscue Scores
    $miscueQuery = "SELECT id, miscue, accuracy_percentage, comprehension_score, final_reading_level 
                    FROM assessment_result 
                    WHERE student_id = :student_id 
                    ORDER BY id DESC";
    $stmtMiscues = $pdo->prepare($miscueQuery);
    $stmtMiscues->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $stmtMiscues->execute();
    $miscueScores = $stmtMiscues->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Total Assessment Count
    $countQuery = "SELECT COUNT(*) AS total_assessments 
                   FROM assessment_result 
                   WHERE student_id = :student_id";
    $stmtCount = $pdo->prepare($countQuery);
    $stmtCount->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $stmtCount->execute();
    $assessmentCount = $stmtCount->fetchColumn();

    http_response_code(200); 
    $response['status'] = "success";
    $response['message'] = "Student records retrieved successfully.";
    $response['data'] = [
        "total_assessments" => (int)$assessmentCount,
        "assessments" => $miscueScores
    ];

} catch (PDOException $e) {
    http_response_code(500); 
    $response['message'] = $dev_mode ? "Database error: " . $e->getMessage() : "A database error occurred.";
}

echo json_encode($response);
?>