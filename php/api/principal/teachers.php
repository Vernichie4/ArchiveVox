<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../_helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $user = getCurrentUser();
    if (!$user || !in_array($user['role'], ['principal', 'admin'])) {
        sendJson(['success' => false, 'message' => 'Access denied'], 403);
    }

    // --- GET ---
    if ($method === 'GET') {
        if ($action === 'list') {
            $stmt = $pdo->prepare('
                SELECT 
                    t.teacher_id,
                    t.user_id,
                    u.username,
                    u.status,
                    t.email,
                    t.first_name,
                    t.last_name,
                    (SELECT COUNT(*)
                     FROM student s
                     WHERE s.teacher_id = t.teacher_id
                       AND s.is_active = 1) AS student_count
                FROM teacher t
                JOIN user u ON t.user_id = u.user_id
                ORDER BY t.first_name, t.last_name
            ');
            $stmt->execute();
            $teachers = $stmt->fetchAll();
            sendJson(['success' => true, 'teachers' => $teachers]);
            
        } elseif ($action === 'get') {
            $teacherId = $_GET['id'] ?? 0;
            
            $stmt = $pdo->prepare('
                SELECT 
                    t.teacher_id,
                    t.user_id,
                    u.username,
                    u.status,
                    t.email,
                    t.first_name,
                    t.last_name,
                    t.teacher_category_id,
                    (SELECT COUNT(*)
                    FROM student s
                    WHERE s.teacher_id = t.teacher_id
                    AND s.is_active = 1) AS student_count,
                    (SELECT AVG(ar.wcpm)
                    FROM assessment_result ar
                    INNER JOIN reading_activity ra ON ar.activity_id = ra.activity_id
                    INNER JOIN student s ON ra.student_id = s.student_id
                    WHERE s.teacher_id = t.teacher_id
                    AND ar.wcpm > 0) AS avg_wcpm
                FROM teacher t
                JOIN user u ON t.user_id = u.user_id
                WHERE t.teacher_id = :teacher_id
            ');
            $stmt->execute([':teacher_id' => $teacherId]);
            $teacher = $stmt->fetch();
            
            if (!$teacher) {
                sendJson(['success' => false, 'message' => 'Teacher not found'], 404);
            }
            
            $stmt = $pdo->prepare('
                SELECT 
                    s.student_id,
                    s.lrn,
                    s.first_name,
                    s.middle_name,
                    s.last_name,
                    s.gender,
                    s.is_active,
                    sc.grade_level,
                    c.section,
                    (SELECT COUNT(*) FROM reading_activity ra WHERE ra.student_id = s.student_id) AS assessment_count
                FROM student s
                LEFT JOIN student_category sc ON s.category_id = sc.category_id
                LEFT JOIN class c ON s.class_id = c.class_id
                WHERE s.teacher_id = :teacher_id
                AND s.is_active = 1
                ORDER BY s.last_name, s.first_name
            ');
            $stmt->execute([':teacher_id' => $teacherId]);
            $teacher['students'] = $stmt->fetchAll();
            
            sendJson(['success' => true, 'teacher' => $teacher]);

        } elseif ($action === 'students') {
            $teacherId = $_GET['id'] ?? 0;
            if (!$teacherId) {
                sendJson(['success' => false, 'message' => 'Teacher ID required'], 400);
            }
            $stmt = $pdo->prepare('
                SELECT 
                    s.student_id,
                    s.lrn,
                    s.first_name,
                    s.middle_name,
                    s.last_name,
                    s.gender,
                    s.birthdate,
                    s.is_active,
                    s.date_registered,
                    sc.grade_level,
                    c.section,
                    (SELECT COUNT(*) 
                    FROM reading_activity ra 
                    WHERE ra.student_id = s.student_id) AS assessment_count,
                    (SELECT AVG(ar.wcpm) 
                    FROM assessment_result ar 
                    INNER JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                    WHERE ra.student_id = s.student_id) AS avg_wcpm,
                    (SELECT AVG(ar.accuracy_percentage) 
                    FROM assessment_result ar 
                    INNER JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                    WHERE ra.student_id = s.student_id) AS avg_accuracy,
                    (SELECT MAX(ar.assessed_at) 
                    FROM assessment_result ar 
                    INNER JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                    WHERE ra.student_id = s.student_id) AS last_assessment_date
                FROM student s
                LEFT JOIN student_category sc ON s.category_id = sc.category_id
                LEFT JOIN class c ON s.class_id = c.class_id
                WHERE s.teacher_id = :teacher_id
                AND s.is_active = 1
                ORDER BY s.last_name, s.first_name
            ');
            $stmt->execute([':teacher_id' => $teacherId]);
            $students = $stmt->fetchAll();
            sendJson(['success' => true, 'students' => $students]);
        }
    }

    // --- PUT ---
    elseif ($method === 'PUT') {
        $data = readJsonInput();
        $teacherId = $_GET['id'] ?? 0;
        if (!$teacherId) {
            sendJson(['success' => false, 'message' => 'Teacher ID required'], 400);
        }
        
        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;
        $email = $data['email'] ?? null;
        $status = $data['status'] ?? null;
        
        $updates = [];
        $params = [':teacher_id' => $teacherId];
        
        if ($firstName !== null) {
            $updates[] = 'first_name = :first_name';
            $params[':first_name'] = $firstName;
        }
        if ($lastName !== null) {
            $updates[] = 'last_name = :last_name';
            $params[':last_name'] = $lastName;
        }
        // Note: we don't update department because it doesn't exist

        if ($updates) {
            $sql = 'UPDATE teacher SET ' . implode(', ', $updates) . ' WHERE teacher_id = :teacher_id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        if ($email !== null) {
            $stmt = $pdo->prepare('UPDATE teacher SET email = :email WHERE teacher_id = :teacher_id');
            $stmt->execute([':email' => $email, ':teacher_id' => $teacherId]);
        }

        if ($status !== null) {
            $stmt = $pdo->prepare('UPDATE user SET status = :status WHERE user_id = (SELECT user_id FROM teacher WHERE teacher_id = :teacher_id)');
            $stmt->execute([':status' => $status, ':teacher_id' => $teacherId]);
        }
        
        sendJson(['success' => true, 'message' => 'Teacher updated successfully']);
    }

    // --- DELETE ---
    elseif ($method === 'DELETE') {
        $teacherId = $_GET['id'] ?? 0;
        if (!$teacherId) {
            sendJson(['success' => false, 'message' => 'Teacher ID required'], 400);
        }
        $stmt = $pdo->prepare('
            UPDATE user SET status = :status 
            WHERE user_id = (SELECT user_id FROM teacher WHERE teacher_id = :teacher_id)
        ');
        $stmt->execute([':status' => 'Inactive', ':teacher_id' => $teacherId]);
        sendJson(['success' => true, 'message' => 'Teacher deactivated successfully']);
    }

    // --- POST (CREATE TEACHER) ---
    elseif ($method === 'POST') {
        $data = readJsonInput();
        $action = $_GET['action'] ?? '';

        if ($action === 'create') {
            // Validate required fields
            $firstName = trim($data['first_name'] ?? '');
            $lastName  = trim($data['last_name'] ?? '');
            $gradeLevel = trim($data['grade_level'] ?? '');
            $section   = trim($data['section'] ?? '');

            if (!$firstName || !$lastName || !$gradeLevel || !$section) {
                sendJson(['success' => false, 'message' => 'First name, last name, grade level and section are required.'], 400);
            }

            // --- 1) Get or create teacher_category ---
            // Determine school year (use current academic year, e.g., "2025-2026")
            $currentYear = date('Y');
            $nextYear = $currentYear + 1;
            $schoolYear = $currentYear . '-' . $nextYear;

            // Check if category exists for this grade & section & school year
            $stmt = $pdo->prepare('
                SELECT teacher_category_id 
                FROM teacher_category 
                WHERE grade_level = :grade_level 
                  AND section = :section 
                  AND school_year = :school_year
            ');
            $stmt->execute([
                ':grade_level' => $gradeLevel,
                ':section'     => $section,
                ':school_year' => $schoolYear
            ]);
            $category = $stmt->fetch();

            if (!$category) {
                // Insert new category
                $stmt = $pdo->prepare('
                    INSERT INTO teacher_category (grade_level, section, school_year)
                    VALUES (:grade_level, :section, :school_year)
                ');
                $stmt->execute([
                    ':grade_level' => $gradeLevel,
                    ':section'     => $section,
                    ':school_year' => $schoolYear
                ]);
                $teacherCategoryId = $pdo->lastInsertId();
            } else {
                $teacherCategoryId = $category['teacher_category_id'];
            }

            // --- 2) Generate username and email ---
            $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName) . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $lastName));
            $username = $baseUsername;
            $counter = 1;
            while (true) {
                $stmt = $pdo->prepare('SELECT user_id FROM user WHERE username = :username');
                $stmt->execute([':username' => $username]);
                if (!$stmt->fetch()) break;
                $username = $baseUsername . $counter++;
            }

            $email = $username . '@school.org';
            $defaultPassword = password_hash('stacruzCen3lem', PASSWORD_DEFAULT);

            // --- 3) Insert user and teacher (transaction) ---
            $pdo->beginTransaction();
            try {
                // Insert user
                $stmt = $pdo->prepare('
                    INSERT INTO user (username, password, role, status)
                    VALUES (:username, :password, :role, :status)
                ');
                $stmt->execute([
                    ':username' => $username,
                    ':password' => $defaultPassword,
                    ':role'     => 'teacher',
                    ':status'   => 'Active'
                ]);
                $userId = $pdo->lastInsertId();

                // Insert teacher (without department)
                $stmt = $pdo->prepare('
                    INSERT INTO teacher (user_id, teacher_category_id, first_name, last_name, email)
                    VALUES (:user_id, :teacher_category_id, :first_name, :last_name, :email)
                ');
                $stmt->execute([
                    ':user_id'    => $userId,
                    ':teacher_category_id' => $teacherCategoryId,
                    ':first_name' => $firstName,
                    ':last_name'  => $lastName,
                    ':email'      => $email
                ]);
                $teacherId = $pdo->lastInsertId();

                // Insert class (grade + section) for this teacher
                $stmt = $pdo->prepare('
                    INSERT INTO class (grade_level, section, teacher_id)
                    VALUES (:grade_level, :section, :teacher_id)
                ');
                $stmt->execute([
                    ':grade_level' => $gradeLevel,
                    ':section'     => $section,
                    ':teacher_id'  => $teacherId
                ]);

                $pdo->commit();

                sendJson([
                    'success' => true,
                    'message' => 'Teacher created successfully.',
                    'teacher' => [
                        'teacher_id' => $teacherId,
                        'username'   => $username,
                        'email'      => $email,
                        'first_name' => $firstName,
                        'last_name'  => $lastName,
                        'grade_level'=> $gradeLevel,
                        'section'    => $section
                    ]
                ]);
            } catch (PDOException $e) {
                $pdo->rollBack();
                sendJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
            } catch (Exception $e) {
                $pdo->rollBack();
                sendJson(['success' => false, 'message' => 'General error: ' . $e->getMessage()], 500);
            }
        }
    }

    else {
        http_response_code(405);
        sendJson(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    sendJson(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}