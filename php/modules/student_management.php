<?php
require_once __DIR__ . '/../auth/config.php';

function getOrCreateClass(int $teacherId, string $gradeLevel, string $section): ?int {
    global $pdo;
    
    if (empty($section)) {
        return null;
    }
    
    $stmt = $pdo->prepare('
        SELECT class_id FROM class 
        WHERE teacher_id = :teacher_id AND grade_level = :grade_level AND section = :section
        LIMIT 1
    ');
    $stmt->execute([
        ':teacher_id' => $teacherId,
        ':grade_level' => $gradeLevel,
        ':section' => $section
    ]);
    $class = $stmt->fetch();
    
    if ($class) {
        return (int) $class['class_id'];
    }
    
    $stmt = $pdo->prepare('
        INSERT INTO class (teacher_id, grade_level, section, school_year) 
        VALUES (:teacher_id, :grade_level, :section, :school_year)
    ');
    $schoolYear = date('Y') . '-' . (date('Y') + 1);
    $stmt->execute([
        ':teacher_id' => $teacherId,
        ':grade_level' => $gradeLevel,
        ':section' => $section,
        ':school_year' => $schoolYear
    ]);
    
    return (int) $pdo->lastInsertId();
}

function getStudentsByTeacher(int $teacherId): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('
            SELECT 
                s.student_id,
                s.lrn,  -- Changed from student_no
                s.first_name,
                s.middle_name,
                s.last_name,
                s.gender,
                s.birthdate,
                s.is_active,
                s.date_registered,
                sc.grade_level,
                c.section,
                (SELECT COUNT(*) FROM reading_activity ra WHERE ra.student_id = s.student_id) as activity_count,
                (SELECT MAX(assessed_at) FROM assessment_result ar 
                 JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                 WHERE ra.student_id = s.student_id) as last_assessed
            FROM student s
            LEFT JOIN student_category sc ON s.category_id = sc.category_id
            LEFT JOIN class c ON s.class_id = c.class_id
            WHERE s.teacher_id = :teacher_id AND s.is_active = 1
            ORDER BY s.last_name, s.first_name
        ');
        $stmt->execute([':teacher_id' => $teacherId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getStudentById(int $studentId): ?array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('
            SELECT s.*, 
                   sc.grade_level, 
                   c.section,
                   (SELECT AVG(accuracy_percentage) FROM assessment_result ar 
                    JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                    WHERE ra.student_id = s.student_id) as avg_accuracy
            FROM student s
            LEFT JOIN student_category sc ON s.category_id = sc.category_id
            LEFT JOIN class c ON s.class_id = c.class_id
            WHERE s.student_id = :student_id
        ');
        $stmt->execute([':student_id' => $studentId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

function registerStudent(array $data): array {
    global $pdo;

    if (empty($data['first_name']) || empty($data['last_name'])) {
        return ['success' => false, 'message' => 'First name and last name are required'];
    }

    // Validate LRN (if provided)
    if (!empty($data['lrn']) && !preg_match('/^\d{6}$/', $data['lrn'])) {
        return ['success' => false, 'message' => 'LRN must be exactly 6 digits'];
    }

    // Check for duplicate LRN
    if (!empty($data['lrn'])) {
        $stmt = $pdo->prepare('SELECT student_id FROM student WHERE lrn = :lrn');
        $stmt->execute([':lrn' => $data['lrn']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'LRN already exists'];
        }
    }

    // ----- Get or create category (grade level) -----
    $gradeLevel = $data['grade_level'] ?? 'Grade 2';
    $categoryId = $data['category_id'] ?? null;
    if (!$categoryId && !empty($gradeLevel)) {
        $stmt = $pdo->prepare('SELECT category_id FROM student_category WHERE grade_level = :grade_level LIMIT 1');
        $stmt->execute([':grade_level' => $gradeLevel]);
        $category = $stmt->fetch();
        if ($category) {
            $categoryId = $category['category_id'];
        } else {
            $stmt = $pdo->prepare('INSERT INTO student_category (grade_level, school_year) VALUES (:grade_level, :school_year)');
            $stmt->execute([
                ':grade_level' => $gradeLevel,
                ':school_year' => date('Y') . '-' . (date('Y') + 1)
            ]);
            $categoryId = (int) $pdo->lastInsertId();
        }
    }

    // ----- Get or create class (grade level + section) -----
    $section = trim($data['section'] ?? '');
    $classId = null;
    if (!empty($section)) {
        $teacherId = (int)($data['teacher_id'] ?? 0);
        if ($teacherId > 0) {
            $classId = getOrCreateClass($teacherId, $gradeLevel, $section);
        }
    }

    // ----- Insert student -----
    try {
        $stmt = $pdo->prepare('
            INSERT INTO student (
                teacher_id, category_id, class_id, lrn,
                first_name, middle_name, last_name,
                gender, birthdate, is_active
            ) VALUES (
                :teacher_id, :category_id, :class_id, :lrn,
                :first_name, :middle_name, :last_name,
                :gender, :birthdate, :is_active
            )
        ');

        $stmt->execute([
            ':teacher_id'   => $data['teacher_id'],
            ':category_id'  => $categoryId,
            ':class_id'     => $classId,
            ':lrn'          => $data['lrn'] ?? null,
            ':first_name'   => $data['first_name'],
            ':middle_name'  => $data['middle_name'] ?? null,
            ':last_name'    => $data['last_name'],
            ':gender'       => $data['gender'] ?? null,
            ':birthdate'    => $data['birthdate'] ?? null,
            ':is_active'    => 1
        ]);

        return [
            'success'    => true,
            'student_id' => (int) $pdo->lastInsertId(),
            'message'    => 'Student registered successfully'
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function editStudent(int $studentId, array $data): array {
    global $pdo;
    
    $allowed = ['lrn', 'first_name', 'middle_name', 'last_name', 'gender', 'birthdate', 'class_id'];
    $fields = [];
    $values = [':student_id' => $studentId];
    
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed, true)) {
            $fields[] = $key . ' = :' . $key;
            $values[':' . $key] = $value;
        }
    }
    
    if (!$fields) {
        return ['success' => false, 'message' => 'No changes provided.'];
    }
    
    try {
        $stmt = $pdo->prepare('UPDATE student SET ' . implode(', ', $fields) . ' WHERE student_id = :student_id');
        $stmt->execute($values);
        return ['success' => true, 'message' => 'Student updated successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function archiveStudent(int $studentId): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('UPDATE student SET is_active = 0, archived_at = CURRENT_TIMESTAMP WHERE student_id = :student_id');
        $stmt->execute([':student_id' => $studentId]);
        return ['success' => true, 'message' => 'Student archived successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function searchStudents(string $term): array {
    global $pdo;
    
    try {
        $term = trim($term);
        $searchTerm = '%' . $term . '%';
        
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
                (SELECT COUNT(*) FROM reading_activity ra WHERE ra.student_id = s.student_id) as activity_count,
                (SELECT MAX(assessed_at) FROM assessment_result ar 
                 JOIN reading_activity ra ON ar.activity_id = ra.activity_id 
                 WHERE ra.student_id = s.student_id) as last_assessed
            FROM student s
            LEFT JOIN student_category sc ON s.category_id = sc.category_id
            LEFT JOIN class c ON s.class_id = c.class_id
            WHERE s.is_active = 1
            AND (
                s.lrn LIKE :term 
                OR s.first_name LIKE :term 
                OR s.last_name LIKE :term
                OR CONCAT(s.first_name, " ", s.last_name) LIKE :term
                OR CONCAT(s.last_name, ", ", s.first_name) LIKE :term
                OR CONCAT(s.first_name, " ", s.middle_name, " ", s.last_name) LIKE :term
            )
            ORDER BY 
                CASE 
                    WHEN s.lrn LIKE :term THEN 1 
                    WHEN s.first_name LIKE :term THEN 2 
                    WHEN s.last_name LIKE :term THEN 3 
                    ELSE 4 
                END,
                s.last_name, s.first_name
            LIMIT 50
        ');
        $stmt->execute([':term' => $searchTerm]);
        $results = $stmt->fetchAll();
        
        // Debug log
        error_log('Search term: "' . $term . '" - Found ' . count($results) . ' students');
        
        return $results;
    } catch (PDOException $e) {
        error_log('Search error: ' . $e->getMessage());
        return [];
    }
}