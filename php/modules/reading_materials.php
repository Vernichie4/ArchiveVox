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
        $stmt = $pdo->prepare('DELETE FROM reading_material WHERE material_id = :material_id');
        $stmt->execute([':material_id' => $materialId]);
        return ['success' => true, 'message' => 'Material deleted successfully'];
    } catch (PDOException $e) {
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