<?php
// From php/modules/ go up one level to php/auth/config.php
require_once __DIR__ . '/../auth/config.php';

function uploadReadingMaterial(array $data): array {
    global $pdo;
    
    if (!isset($pdo)) {
        return ['success' => false, 'message' => 'Database connection not established'];
    }

    try {
        // ✅ UPDATED SQL: Added :total_words to the columns and VALUES
        $stmt = $pdo->prepare(
            'INSERT INTO reading_material (teacher_id, title, description, language, material_type, grade_level, difficulty, original_filename, file_path, ocr_text, total_words, status) 
             VALUES (:teacher_id, :title, :description, :language, :material_type, :grade_level, :difficulty, :original_filename, :file_path, :ocr_text, :total_words, :status)'
        );

        $stmt->execute([
            ':teacher_id' => $data['teacher_id'],
            ':title' => $data['title'],
            ':description' => $data['description'] ?? null,
            ':language' => $data['language'] ?? 'English',
            ':material_type' => $data['material_type'] ?? 'Custom',
            ':grade_level' => $data['grade_level'],
            ':difficulty' => $data['difficulty'] ?? 'Average',
            ':original_filename' => $data['original_filename'] ?? null,
            ':file_path' => $data['file_path'] ?? '',
            ':ocr_text' => $data['ocr_text'] ?? null,
            ':total_words' => $data['total_words'] ?? 0, // ✅ Calculate this in the API call
            ':status' => $data['status'] ?? 'Active',
        ]);

        return [
            'success' => true, 
            'material_id' => (int) $pdo->lastInsertId(),
            'message' => 'Material saved successfully'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

function editReadingMaterial(int $materialId, array $data): array {
    global $pdo;

    if (!isset($pdo)) {
        return ['success' => false, 'message' => 'Database connection not established'];
    }

    $allowed = ['title','description','language','material_type','grade_level','difficulty','original_filename','file_path','ocr_text','status','total_words'];
    $fields = [];
    $values = [':material_id' => $materialId];

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
        $stmt = $pdo->prepare('UPDATE reading_material SET ' . implode(', ', $fields) . ' WHERE material_id = :material_id');
        $stmt->execute($values);
        return ['success' => true, 'message' => 'Material updated successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function deleteReadingMaterial(int $materialId): array {
    global $pdo;

    if (!isset($pdo)) {
        return ['success' => false, 'message' => 'Database connection not established'];
    }

    try {
        $pdo->beginTransaction();

        // Check if material is used in any assignments
        $checkStmt = $pdo->prepare('SELECT assignment_id FROM reading_assignment_material WHERE material_id = :material_id');
        $checkStmt->execute([':material_id' => $materialId]);
        $assignments = $checkStmt->fetchAll(PDO::FETCH_COLUMN);

        if ($assignments) {
            // For each assignment, delete all related data
            foreach ($assignments as $assignmentId) {
                // Get all assignment materials for this assignment
                $matStmt = $pdo->prepare('SELECT assignment_material_id FROM reading_assignment_material WHERE assignment_id = :assignment_id');
                $matStmt->execute([':assignment_id' => $assignmentId]);
                $assignmentMaterials = $matStmt->fetchAll(PDO::FETCH_COLUMN);

                // Delete reading activities for each assignment material
                foreach ($assignmentMaterials as $amId) {
                    $raStmt = $pdo->prepare('DELETE FROM reading_activity WHERE assignment_material_id = :assignment_material_id');
                    $raStmt->execute([':assignment_material_id' => $amId]);
                }

                // Delete assignment materials
                $delMatStmt = $pdo->prepare('DELETE FROM reading_assignment_material WHERE assignment_id = :assignment_id');
                $delMatStmt->execute([':assignment_id' => $assignmentId]);

                // Delete the assignment
                $assignStmt = $pdo->prepare('DELETE FROM reading_assignment WHERE assignment_id = :assignment_id');
                $assignStmt->execute([':assignment_id' => $assignmentId]);
            }
        }

        // Delete quizzes that reference this material (quiz table has material_id, not assignment_material_id)
        $quizStmt = $pdo->prepare('SELECT quiz_id FROM quiz WHERE material_id = :material_id');
        $quizStmt->execute([':material_id' => $materialId]);
        $quizIds = $quizStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($quizIds as $quizId) {
            // Delete quiz answers
            $ansStmt = $pdo->prepare('DELETE FROM quiz_answer WHERE question_id IN (SELECT question_id FROM quiz_question WHERE quiz_id = :quiz_id)');
            $ansStmt->execute([':quiz_id' => $quizId]);

            // Delete quiz questions
            $qStmt = $pdo->prepare('DELETE FROM quiz_question WHERE quiz_id = :quiz_id');
            $qStmt->execute([':quiz_id' => $quizId]);

            // Delete quiz attempts
            $qaStmt = $pdo->prepare('DELETE FROM quiz_attempt WHERE quiz_id = :quiz_id');
            $qaStmt->execute([':quiz_id' => $quizId]);
        }

        // Delete the material (this will cascade delete quizzes due to FK constraint)
        $stmt = $pdo->prepare('DELETE FROM reading_material WHERE material_id = :material_id');
        $stmt->execute([':material_id' => $materialId]);

        $pdo->commit();

        $message = 'Material deleted successfully';
        if ($assignments) {
            $message .= " along with " . count($assignments) . " assignment(s) that used it.";
        }

        return ['success' => true, 'message' => $message];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function viewReadingMaterials(): array {
    global $pdo;
    
    // Check if $pdo exists
    if (!isset($pdo)) {
        error_log('viewReadingMaterials: $pdo not set');
        return [];
    }

    try {
        $stmt = $pdo->query('
            SELECT 
                material_id, 
                title, 
                description,
                grade_level, 
                material_type, 
                language, 
                status, 
                ocr_text,
                total_words,
                file_path,
                upload_date
            FROM reading_material 
            ORDER BY material_id DESC
        ');
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('viewReadingMaterials error: ' . $e->getMessage());
        return [];
    }
}