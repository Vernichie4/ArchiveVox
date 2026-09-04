<?php
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/config.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../../modules/reading_assessment.php';
require_once __DIR__ . '/../../services/WhisperService.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($action, $pdo);
            break;

        case 'POST':
            handlePost($action, $pdo);
            break;

        case 'PUT':
            handlePut($pdo);
            break;

        case 'DELETE':
            handleDelete($pdo);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    if ($method === 'POST' && $action === 'record') {
        http_response_code(200);
        echo json_encode([
            'success' => false,
            'message' => 'Assessment processing failed: ' . $e->getMessage(),
            'code' => 'ASSESSMENT_RECORD_UNEXPECTED'
        ]);
        return;
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function handleGet(string $action, PDO $pdo): void {
    if ($action === 'export') {
        exportAssessmentCsv($pdo);
        return;
    }

    if ($action === 'list') {
        $assessments = getAssessmentSummary();
        echo json_encode(['success' => true, 'assessments' => $assessments]);
        return;
    }

    if ($action === 'profile') {
        // Get full reading profile with CRLA scoring
        $assessmentId = (int)($_GET['id'] ?? 0);
        if ($assessmentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Assessment ID required']);
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT ar.*, s.first_name, s.last_name, s.lrn, rm.title as material_title
             FROM assessment_result ar
             JOIN reading_activity ra ON ar.activity_id = ra.activity_id
             JOIN student s ON ra.student_id = s.student_id
             JOIN reading_material rm ON ra.material_id = rm.material_id
             WHERE ar.assessment_id = :assessment_id'
        );
        $stmt->execute([':assessment_id' => $assessmentId]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assessment) {
            echo json_encode(['success' => false, 'message' => 'Assessment not found']);
            return;
        }

        // Get miscue details
        $stmt = $pdo->prepare(
            'SELECT * FROM reading_errors WHERE assessment_id = :assessment_id ORDER BY word_position ASC'
        );
        $stmt->execute([':assessment_id' => $assessmentId]);
        $miscues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate CRLA reading profile
        $profile = calculateCRLAProfile($assessment);
        $interpretation = getReadingProfileInterpretation(
            $profile['reading_level'],
            $profile['observation_level']
        );

        echo json_encode([
            'success' => true,
            'assessment' => $assessment,
            'reading_profile' => $profile,
            'interpretation' => $interpretation,
            'miscues' => $miscues
        ]);
        return;
    }

    if ($action === 'materials') {
        $grade = trim($_GET['grade'] ?? '');
        $language = trim($_GET['language'] ?? '');

        $sql = "SELECT material_id, title, language, grade_level, difficulty, ocr_text
                FROM reading_material
                WHERE status = 'Active'";
        $params = [];

        if ($grade !== '') {
            $sql .= ' AND grade_level = :grade';
            $params[':grade'] = $grade;
        }

        if ($language !== '') {
            $sql .= ' AND language = :language';
            $params[':language'] = $language;
        }

        $sql .= ' ORDER BY upload_date DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'materials' => $stmt->fetchAll()]);
        return;
    }

    if ($action === 'student') {
        $term = trim($_GET['term'] ?? '');

        if ($term === '') {
            echo json_encode(['success' => false, 'message' => 'Search term required']);
            return;
        }

        $stmt = $pdo->prepare(
            "SELECT s.student_id, s.lrn, s.first_name, s.middle_name, s.last_name,
                    sc.grade_level, c.section
             FROM student s
             LEFT JOIN student_category sc ON s.category_id = sc.category_id
             LEFT JOIN class c ON s.class_id = c.class_id
             WHERE s.is_active = 1
               AND (
                                        s.lrn LIKE :term_lrn
                                        OR s.first_name LIKE :term_first_name
                                        OR s.last_name LIKE :term_last_name
               )
             ORDER BY s.last_name, s.first_name
             LIMIT 10"
        );
                $like = '%' . $term . '%';
                $stmt->execute([
                        ':term_lrn' => $like,
                        ':term_first_name' => $like,
                        ':term_last_name' => $like
                ]);

        echo json_encode(['success' => true, 'students' => $stmt->fetchAll()]);
        return;
    }

    if ($action === 'history') {
        $studentId = (int)($_GET['student_id'] ?? 0);

        if ($studentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Student ID required']);
            return;
        }

        $stmt = $pdo->prepare(
            "SELECT ar.*, rm.title AS material_title, ra.activity_date
             FROM assessment_result ar
             JOIN reading_activity ra ON ar.activity_id = ra.activity_id
             JOIN reading_material rm ON ra.material_id = rm.material_id
             WHERE ra.student_id = :student_id
             ORDER BY ar.assessed_at DESC
             LIMIT 10"
        );
        $stmt->execute([':student_id' => $studentId]);

        echo json_encode(['success' => true, 'history' => $stmt->fetchAll()]);
        return;
    }

    $assessmentId = (int)($_GET['id'] ?? 0);
    if ($assessmentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Assessment ID required']);
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT ar.*, s.first_name, s.last_name, rm.title AS material_title
         FROM assessment_result ar
         JOIN reading_activity ra ON ar.activity_id = ra.activity_id
         JOIN student s ON ra.student_id = s.student_id
         JOIN reading_material rm ON ra.material_id = rm.material_id
         WHERE ar.assessment_id = :id'
    );
    $stmt->execute([':id' => $assessmentId]);

    echo json_encode(['success' => true, 'assessment' => $stmt->fetch()]);
}

function handlePost(string $action, PDO $pdo): void {
    if ($action === 'record') {
        handleRecordAssessment($pdo);
        return;
    }

    if ($action === 'update_comprehension') {
        handleUpdateComprehension($pdo);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new Exception('Invalid JSON data');
    }

    if ($action === 'start') {
        $studentId = (int)($data['student_id'] ?? 0);
        $materialId = (int)($data['material_id'] ?? 0);

        if ($studentId <= 0 || $materialId <= 0) {
            echo json_encode(['success' => false, 'message' => 'student_id and material_id are required']);
            return;
        }

        $result = createReadingActivity([
            'student_id' => $studentId,
            'material_id' => $materialId,
            'duration_seconds' => (int)($data['duration_seconds'] ?? 0),
            'audio_filename' => $data['audio_filename'] ?? null,
            'activity_status' => $data['activity_status'] ?? 'Completed'
        ]);

        echo json_encode($result);
        return;
    }

    $result = saveAssessment($data);
    echo json_encode($result);
}

function handlePut(PDO $pdo): void {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new Exception('Invalid JSON data');
    }

    $assessmentId = (int)($_GET['id'] ?? 0);
    if ($assessmentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Assessment ID required']);
        return;
    }

    $fields = [];
    $values = [':assessment_id' => $assessmentId];

    $allowedFields = [
        'accuracy_percentage', 'wcpm', 'reading_level',
        'teacher_feedback', 'comprehension_score',
        'pronunciation_score', 'fluency_score', 'expression_score'
    ];

    foreach ($data as $key => $value) {
        if (in_array($key, $allowedFields, true)) {
            $fields[] = $key . ' = :' . $key;
            $values[':' . $key] = $value;
        }
    }

    if (empty($fields)) {
        echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
        return;
    }

    $stmt = $pdo->prepare('UPDATE assessment_result SET ' . implode(', ', $fields) . ' WHERE assessment_id = :assessment_id');
    $stmt->execute($values);

    echo json_encode(['success' => true, 'message' => 'Assessment updated']);
}

function handleDelete(PDO $pdo): void {
    $assessmentId = (int)($_GET['id'] ?? 0);
    if ($assessmentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Assessment ID required']);
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM assessment_result WHERE assessment_id = :id');
    $stmt->execute([':id' => $assessmentId]);

    echo json_encode(['success' => true, 'message' => 'Assessment deleted']);
}

function handleRecordAssessment(PDO $pdo): void {
    // Long transcription can exceed default PHP timeout; keep this request alive.
    @set_time_limit(0);
    @ini_set('max_execution_time', '0');

    $activityId = 0;$language = $_POST['language'] ?? 'auto';
    $language = determineTranscriptionLanguage($_POST);

    // If the material is in Filipino/Tagalog, force Tagalog transcription
    $materialLanguage = $_POST['material_language'] ?? '';
    if ($materialLanguage === 'tl' || $materialLanguage === 'fil' || $materialLanguage === 'Tagalog') {
        $language = 'tl';
    }


    try {
        if (empty($_FILES) || !isset($_FILES['audio'])) {
            echo json_encode(['success' => false, 'message' => 'No audio file uploaded']);
            return;
        }

        $file = $_FILES['audio'];
        $studentId = (int)($_POST['student_id'] ?? 0);
        $materialId = (int)($_POST['material_id'] ?? 0);
        $originalText = trim($_POST['original_text'] ?? '');
        $durationSeconds = max(1, (int)($_POST['duration_seconds'] ?? 120));

        if ($studentId <= 0 || $materialId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Student and material are required']);
            return;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Audio upload failed with code: ' . (int)$file['error']]);
            return;
        }

        if ($originalText === '') {
            $stmt = $pdo->prepare('SELECT ocr_text FROM reading_material WHERE material_id = :material_id LIMIT 1');
            $stmt->execute([':material_id' => $materialId]);
            $material = $stmt->fetch();
            $originalText = trim((string)($material['ocr_text'] ?? ''));
        }

        $uploadDir = __DIR__ . '/../../../uploads/audio/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create audio upload directory']);
            return;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', (string)$file['name']);
        if ($safeName === '' || $safeName === null) {
            $safeName = 'reading.webm';
        }
        $filename = time() . '_' . $studentId . '_' . $safeName;
        $filePath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save audio']);
            return;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO reading_activity (student_id, material_id, started_at, audio_filename, audio_path, duration_seconds, activity_status)
             VALUES (:student_id, :material_id, NOW(), :audio_filename, :audio_path, :duration_seconds, 'Processing')"
        );
        $stmt->execute([
            ':student_id' => $studentId,
            ':material_id' => $materialId,
            ':audio_filename' => $filename,
            ':audio_path' => 'uploads/audio/' . $filename,
            ':duration_seconds' => $durationSeconds
        ]);
        $activityId = (int)$pdo->lastInsertId();

        $whisper = new WhisperService();
        $transcription = $whisper->transcribeAudio($filePath, $language);

        if (empty($transcription['success'])) {
            throw new Exception('Transcription failed: ' . ($transcription['error'] ?? 'Unknown error'));
        }

        $transcribedText = trim((string)($transcription['text'] ?? ''));
        $scores = calculateORF($transcribedText, $originalText, $durationSeconds);

        // Enhance with CRLA scoring
        $crlaProfile = calculateCRLAProfile($scores);

        $stmt = $pdo->prepare(
            'INSERT INTO assessment_result (
                activity_id, total_words, words_correct, accuracy_percentage,
                wcpm, reading_time_seconds, substitutions, omissions,
                insertions, repetitions, transcript, reading_level, comprehension_score,
                final_reading_level, observation_level, transcription_language, assessed_at
            ) VALUES (
                :activity_id, :total_words, :words_correct, :accuracy_percentage,
                :wcpm, :reading_time_seconds, :substitutions, :omissions,
                :insertions, :repetitions, :transcript, :reading_level, :comprehension_score,
                :final_reading_level, :observation_level, :transcription_language, NOW()
            )'
        );
        
        $stmt->execute([
            ':activity_id' => $activityId,
            ':total_words' => $scores['total_words'],
            ':words_correct' => $scores['words_correct'],
            ':accuracy_percentage' => $scores['accuracy'],
            ':wcpm' => $scores['wcpm'],
            ':reading_time_seconds' => $scores['time_seconds'],
            ':substitutions' => $scores['substitutions'],
            ':omissions' => $scores['omissions'],
            ':insertions' => $scores['insertions'],
            ':repetitions' => $scores['repetitions'],
            ':transcript' => $transcribedText,
            ':reading_level' => $scores['reading_level'],
            ':comprehension_score' => null,
            ':final_reading_level' => null,
            ':observation_level' => $crlaProfile['observation_level'],
            ':transcription_language' => $language
        ]);

        $assessmentId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "UPDATE reading_activity
             SET activity_status = 'Completed', finished_at = NOW()
             WHERE activity_id = :activity_id"
        );
        $stmt->execute([':activity_id' => $activityId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'assessment_id' => $assessmentId,
            'activity_id' => $activityId,
            'transcript' => $transcribedText,
            'scores' => $scores,
            'reading_profile' => $crlaProfile,
            'message' => 'Assessment completed successfully with CRLA scoring'
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        try {
            if ($activityId > 0) {
                $stmt = $pdo->prepare(
                    "UPDATE reading_activity
                     SET activity_status = 'Pending', finished_at = NOW()
                     WHERE activity_id = :activity_id"
                );
                $stmt->execute([':activity_id' => $activityId]);
            }
        } catch (Throwable $ignored) {
            // Do not throw from recovery path; we'll return the original error below.
        }

        echo json_encode([
            'success' => false,
            'message' => 'Error during transcription: ' . $e->getMessage(),
            'code' => 'ASSESSMENT_RECORD_FAILED'
        ]);
    }
}

function determineTranscriptionLanguage(array $postData): string {
    // Check if language was explicitly specified
    if (!empty($postData['language'])) {
        $lang = strtolower(trim($postData['language']));
        // Validate against supported languages
        $supportedLanguages = ['tl', 'fil', 'en', 'auto', 'ceb', 'ilo'];
        if (in_array($lang, $supportedLanguages)) {
            return $lang;
        }
    }
    
    // Check material language
    if (!empty($postData['material_language'])) {
        $materialLang = strtolower(trim($postData['material_language']));
        // Map common variations to Whisper language codes
        $languageMap = [
            'tagalog' => 'tl',
            'filipino' => 'tl',
            'fil' => 'tl',
            'tl' => 'tl',
            'english' => 'en',
            'en' => 'en',
            'cebuano' => 'ceb',
            'ceb' => 'ceb',
            'ilocano' => 'ilo',
            'ilo' => 'ilo'
        ];
        
        if (isset($languageMap[$materialLang])) {
            return $languageMap[$materialLang];
        }
    }
    
    // Default to auto-detection
    return 'auto';
}

/**
 * Handle comprehension score update and recalculate final CRLA profile
 */
function handleUpdateComprehension(PDO $pdo): void {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new Exception('Invalid JSON data');
    }

    $activityId = (int)($data['activity_id'] ?? 0);
    $comprehensionScore = (int)($data['comprehension_score'] ?? 0);

    if ($activityId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'activity_id is required']);
        return;
    }

    if ($comprehensionScore < 0 || $comprehensionScore > 7) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'comprehension_score must be between 0 and 7']);
        return;
    }

    try {
        // Get the assessment result and reading time for this activity
        $stmt = $pdo->prepare('
            SELECT ar.*, ra.reading_time_seconds
            FROM assessment_result ar
            JOIN reading_activity ra ON ar.activity_id = ra.activity_id
            WHERE ra.activity_id = :activity_id
        ');
        $stmt->execute([':activity_id' => $activityId]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assessment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Assessment not found']);
            return;
        }

        // Calculate Part 2 score from comprehension (mapping per CRLA Grade 3)
        $comprehensionMapping = [
            0 => 0,    // 0 correct answers → 0 points
            1 => 3,    // 1 correct → 3 points
            2 => 6,    // 2 correct → 6 points
            3 => 9,    // 3 correct → 9 points
            4 => 12,   // 4 correct → 12 points
            5 => 15,   // 5 correct → 15 points
            6 => 20,   // 6 correct → 20 points
            7 => 25    // 7 correct → 25 points (max)
        ];

        $part2Score = $comprehensionMapping[$comprehensionScore] ?? 0;

        // Recalculate reading level using accuracy + new comprehension score + reading time
        $accuracy = floatval($assessment['accuracy_percentage'] ?? 0);
        $readingTime = intval($assessment['reading_time_seconds'] ?? 0);
        $readingLevel = determineCRLAReadingLevel($accuracy, $part2Score, $readingTime);
        $observationLevel = determineObservationLevel(
            $assessment['substitutions'] ?? 0,
            $assessment['omissions'] ?? 0,
            $assessment['insertions'] ?? 0,
            $assessment['total_words'] ?? 0,
            $assessment['pronunciation_score'] ?? 5,
            $assessment['expression_score'] ?? 5
        );

        // Update assessment_result with comprehension score and recalculated reading level
        $stmt = $pdo->prepare('
            UPDATE assessment_result
            SET comprehension_score = :comprehension_score,
                part2_score = :part2_score,
                reading_level = :reading_level,
                observation_level = :observation_level,
                updated_at = NOW()
            WHERE activity_id = :activity_id
        ');

        $stmt->execute([
            ':comprehension_score' => $comprehensionScore,
            ':part2_score' => $part2Score,
            ':reading_level' => $readingLevel,
            ':observation_level' => $observationLevel,
            ':activity_id' => $activityId
        ]);

        // Return updated profile
        echo json_encode([
            'success' => true,
            'message' => 'Comprehension score saved and CRLA profile recalculated',
            'updated_reading_profile' => [
                'reading_level' => $readingLevel,
                'observation_level' => $observationLevel,
                'accuracy' => $accuracy,
                'wcpm' => floatval($assessment['wcpm'] ?? 0)
            ]
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error updating comprehension: ' . $e->getMessage()
        ]);
    }
}

function calculateORF(string $transcribed, string $original, int $timeSeconds = 120): array {
    // Normalize with advanced options
    $transcribedWords = normalizeWords($transcribed);
    $originalWords = normalizeWords($original);
    
    $totalWords = count($originalWords);
    $wordsCorrect = 0;
    $substitutions = 0;
    $omissions = 0;
    $insertions = 0;
    $repetitions = 0;
    $nearMisses = 0;
    
    // Count repetitions more intelligently
    // Only count as repetition if it's a content word (length > 2) repeated more than twice
    $wordFrequency = array_count_values($transcribedWords);
    foreach ($wordFrequency as $word => $count) {
        if ($count > 2 && strlen($word) > 2) {
            // Only count repetitions beyond the first occurrence
            $repetitions += $count - 1;
        }
    }
    
    // Align words with dynamic programming for better comparison
    $alignment = alignWords($transcribedWords, $originalWords);
    
    foreach ($alignment as $pair) {
        $transWord = $pair['transcribed'];
        $origWord = $pair['original'];
        
        if ($transWord === null && $origWord !== null) {
            $omissions++;
            continue;
        }
        
        if ($transWord !== null && $origWord === null) {
            $insertions++;
            continue;
        }
        
        // Both have words - check for match
        if ($transWord === $origWord) {
            $wordsCorrect++;
            continue;
        }
        
        // Check for contraction expansion match
        $expandedTrans = expandContractions($transWord);
        $expandedOrig = expandContractions($origWord);
        if ($expandedTrans === $expandedOrig) {
            $wordsCorrect++;
            continue;
        }
        
        // Check for near-match (Levenshtein distance <= 2 for words longer than 4 chars)
        $distance = levenshtein($transWord, $origWord);
        if ($distance <= 2 && strlen($origWord) >= 4) {
            $nearMisses++;
            $wordsCorrect++; // Count as correct for accuracy but note as miscue
            continue;
        }
        
        // Check for common substitution patterns (e.g., 'a' vs 'the', 'and' vs 'an')
        if (isCommonSubstitution($transWord, $origWord)) {
            $nearMisses++;
            $wordsCorrect++;
            continue;
        }
        
        // It's a real substitution
        $substitutions++;
    }
    
    $accuracy = $totalWords > 0 ? round(($wordsCorrect / $totalWords) * 100, 2) : 0.0;
    $safeSeconds = max(1, $timeSeconds);
    $wcpm = round(($wordsCorrect / $safeSeconds) * 60, 2);
    
    // Determine reading level using CRLA criteria
    $part2Score = 0; // Will be updated when comprehension is added
    $readingLevel = determineCRLAReadingLevel($accuracy, $part2Score, $safeSeconds);
    
    return [
        'total_words' => $totalWords,
        'words_correct' => $wordsCorrect,
        'accuracy' => $accuracy,
        'wcpm' => $wcpm,
        'time_seconds' => $safeSeconds,
        'substitutions' => $substitutions,
        'omissions' => $omissions,
        'insertions' => $insertions,
        'repetitions' => $repetitions,
        'near_misses' => $nearMisses,
        'reading_level' => $readingLevel,
        'miscue_breakdown' => [
            'total_miscues' => $substitutions + $omissions + $insertions,
            'accuracy_rate' => $accuracy . '%',
            'quality' => $accuracy >= 95 ? 'Excellent' : ($accuracy >= 90 ? 'Good' : 'Needs Improvement')
        ]
    ];
}

function isCommonSubstitution(string $word1, string $word2): bool {
    $commonSubstitutions = [
        // Articles
        ['a', 'an'],
        ['a', 'the'],
        ['an', 'the'],
        
        // Common function words
        ['and', 'an'],
        ['and', 'in'],
        ['and', 'on'],
        ['for', 'from'],
        ['of', 'off'],
        ['to', 'too'],
        ['to', 'two'],
        ['there', 'their'],
        ['theyre', 'their'],
        ['were', 'where'],
        ['we\'re', 'were'],
        ['your', 'you\'re'],
        ['its', 'it\'s'],
        ['whose', 'who\'s'],
        
        // Short words that get mixed up
        ['or', 'of'],
        ['is', 'as'],
        ['us', 'as'],
        ['so', 'to'],
    ];
    
    $w1 = strtolower($word1);
    $w2 = strtolower($word2);
    
    foreach ($commonSubstitutions as $pair) {
        if (($w1 === $pair[0] && $w2 === $pair[1]) || 
            ($w1 === $pair[1] && $w2 === $pair[0])) {
            return true;
        }
    }
    
    return false;
}

function expandContractions(string $word): string {
    $contractions = [
        "don't" => "do not",
        "doesn't" => "does not",
        "didn't" => "did not",
        "can't" => "cannot",
        "won't" => "will not",
        "couldn't" => "could not",
        "wouldn't" => "would not",
        "shouldn't" => "should not",
        "i'm" => "i am",
        "you're" => "you are",
        "he's" => "he is",
        "she's" => "she is",
        "it's" => "it is",
        "we're" => "we are",
        "they're" => "they are",
        "i've" => "i have",
        "you've" => "you have",
        "we've" => "we have",
        "they've" => "they have",
        "i'd" => "i would",
        "you'd" => "you would",
        "he'd" => "he would",
        "she'd" => "she would",
        "we'd" => "we would",
        "they'd" => "they would",
        "i'll" => "i will",
        "you'll" => "you will",
        "he'll" => "he will",
        "she'll" => "she will",
        "we'll" => "we will",
        "they'll" => "they will"
    ];
    
    return $contractions[strtolower($word)] ?? $word;
}

function alignWords(array $transcribed, array $original): array {
    $alignment = [];
    $i = 0;
    $j = 0;
    
    while ($i < count($transcribed) || $j < count($original)) {
        if ($i < count($transcribed) && $j < count($original)) {
            // Check if words match or are similar
            if (strtolower($transcribed[$i]) === strtolower($original[$j])) {
                $alignment[] = ['transcribed' => $transcribed[$i], 'original' => $original[$j]];
                $i++;
                $j++;
                continue;
            }
            
            // Check if next transcribed word matches current original (possible insertion)
            if ($i + 1 < count($transcribed) && 
                strtolower($transcribed[$i + 1]) === strtolower($original[$j])) {
                $alignment[] = ['transcribed' => $transcribed[$i], 'original' => null];
                $i++;
                continue;
            }
            
            // Check if current transcribed matches next original (possible omission)
            if ($j + 1 < count($original) && 
                strtolower($transcribed[$i]) === strtolower($original[$j + 1])) {
                $alignment[] = ['transcribed' => null, 'original' => $original[$j]];
                $j++;
                continue;
            }
            
            // No match found, align as substitution
            $alignment[] = ['transcribed' => $transcribed[$i], 'original' => $original[$j]];
            $i++;
            $j++;
        } elseif ($i < count($transcribed)) {
            // Insertion
            $alignment[] = ['transcribed' => $transcribed[$i], 'original' => null];
            $i++;
        } else {
            // Omission
            $alignment[] = ['transcribed' => null, 'original' => $original[$j]];
            $j++;
        }
    }
    
    return $alignment;
}

function normalizeWords(string $text): array {
    $text = trim($text);
    
    // Handle common contractions before removing punctuation
    $contractions = [
        "don't" => "do not",
        "doesn't" => "does not",
        "didn't" => "did not",
        "can't" => "cannot",
        "won't" => "will not",
        "couldn't" => "could not",
        "wouldn't" => "would not",
        "shouldn't" => "should not",
        "i'm" => "i am",
        "you're" => "you are",
        "he's" => "he is",
        "she's" => "she is",
        "it's" => "it is",
        "we're" => "we are",
        "they're" => "they are",
        "i've" => "i have",
        "you've" => "you have",
        "we've" => "we have",
        "they've" => "they have",
        "i'd" => "i would",
        "you'd" => "you would",
        "he'd" => "he would",
        "she'd" => "she would",
        "we'd" => "we would",
        "they'd" => "they would",
        "i'll" => "i will",
        "you'll" => "you will",
        "he'll" => "he will",
        "she'll" => "she will",
        "we'll" => "we will",
        "they'll" => "they will",
        "let's" => "let us",
        "ma'am" => "madam"
    ];

    // Handle Tagalog-specific contractions and common patterns
    $tagalogReplacements = [
        // Tagalog common contractions
        'di' => 'hindi',
        'd' => 'ang',
        'ng' => 'nang',
        'na' => 'na',
        'pa' => 'pa',
        'ba' => 'ba',
        'kasi' => 'kasi',
        'daw' => 'raw', // dialectal variation
        'raw' => 'raw',
        'po' => 'po',
        'ho' => 'ho',
        'naman' => 'naman',
        'natin' => 'natin',
        'kayo' => 'kayo',
        'sila' => 'sila',
        'ako' => 'ako',
        'ikaw' => 'ikaw',
        'siya' => 'siya',
        'tayo' => 'tayo',
        'kami' => 'kami',
        'sana' => 'sana',
        'wala' => 'wala',
        'may' => 'may',
        'meron' => 'meron',
        'saan' => 'saan',
        'bakit' => 'bakit',
        'paano' => 'paano',
        'sino' => 'sino',
        'ano' => 'ano',
        'gusto' => 'gusto',
        'kailangan' => 'kailangan',
        'maganda' => 'maganda',
        'masama' => 'masama',
        'malaki' => 'malaki',
        'maliit' => 'maliit',
        'maalam' => 'maalam',
        'mayabang' => 'mayabang',
        'magaling' => 'magaling',
        'mabuti' => 'mabuti',
        'masarap' => 'masarap',
        'maamo' => 'maamo',
        'malakas' => 'malakas',
        'mahina' => 'mahina',
        'malinis' => 'malinis',
        'madumi' => 'madumi',
        'malapit' => 'malapit',
        'malayo' => 'malayo',
        'maligaya' => 'maligaya',
        'malungkot' => 'malungkot',
        'masaya' => 'masaya',
        'hambing' => 'hambing',
        'magulo' => 'magulo',
        'maingay' => 'maingay',
        'tahimik' => 'tahimik',
        'malamig' => 'malamig',
        'mainit' => 'mainit',
        'malambot' => 'malambot',
        'matigas' => 'matigas',
        'malapot' => 'malapot',
        'malabnaw' => 'malabnaw',
        'malabo' => 'malabo',
        'malinaw' => 'malinaw',
        'mabaho' => 'mabaho',
        'mabango' => 'mabango',
        'bango' => 'bango',
        'buti' => 'buti',
        'sama' => 'sama',
        'ganda' => 'ganda',
        'pangit' => 'pangit',
        'halik' => 'halik',
        'subalit' => 'subalit',
        'ngunit' => 'ngunit',
        'gayunman' => 'gayunman',
        'samantala' => 'samantala',
        'samantalang' => 'samantalang',
        'bagaman' => 'bagaman',
        'kahit' => 'kahit',
        'kahit na' => 'kahit na',
        'dahil' => 'dahil',
        'dahil sa' => 'dahil sa',
        'upang' => 'upang',
        'upang makamit' => 'upang makamit',
        'upang makamtan' => 'upang makamtan',
        'upang makuha' => 'upang makuha',
        'upang makamtan ang' => 'upang makamtan ang',
        'upang makamit ang' => 'upang makamit ang',
        'upang makuha ang' => 'upang makuha ang',
        'upang makamtan ang mga' => 'upang makamtan ang mga',
        'upang makamit ang mga' => 'upang makamit ang mga',
        'upang makuha ang mga' => 'upang makuha ang mga',
        'libro' => 'libro',
        'aklat' => 'aklat',
        'pahayagan' => 'pahayagan',
        'dyaryo' => 'dyaryo',
        'magasin' => 'magasin',
        'komiks' => 'komiks',
        'tula' => 'tula',
        'kwento' => 'kwento',
        'maikling kwento' => 'maikling kwento',
        'pambansang awit' => 'pambansang awit',
        'kantang pambata' => 'kantang pambata',
        'sining' => 'sining',
        'musika' => 'musika',
        'sayaw' => 'sayaw',
        'laro' => 'laro',
        'palakasan' => 'palakasan',
        'paligsahan' => 'paligsahan',
        'sa' => 'sa',
        'nang' => 'nang',
        'paanong' => 'paanong',
        'kain' => 'kain',
        'inom' => 'inom',
        'tulog' => 'tulog',
        'gising' => 'gising',
        'lakad' => 'lakad',
        'takbo' => 'takbo',
        'sakay' => 'sakay',
        'baba' => 'baba',
        'akyat' => 'akyat',
        'bili' => 'bili',
        'benta' => 'benta',
        'bili na' => 'bili na',
        'benta na' => 'benta na',
        'bumili' => 'bumili',
        'bumenta' => 'bumenta',
        'nagbenta' => 'nagbenta',
        'nagbili' => 'nagbili',
        'pinagbili' => 'pinagbili',
        'pinagbenta' => 'pinagbenta',
        'magbenta' => 'magbenta',
        'magbili' => 'magbili',
        'kailangang' => 'kailangang',
        'magpakain' => 'magpakain',
        'malalaman' => 'malalaman',
        'masunurin' => 'masunurin',
        'sipag' => 'sipag',
        'tyaga' => 'tyaga',
        'tiyaga' => 'tiyaga',
        'pagsisikap' => 'pagsisikap',
        'pagsusumikap' => 'pagsusumikap',
        'ay' => 'ay',
        'batang' => 'batang',
        'bata' => 'bata',
        'kabataan' => 'kabataan',
        'kabataang' => 'kabataang',
        'matulungin' => 'matulungin',
        'mapagbigay' => 'mapagbigay',
        'mapagmalasakit' => 'mapagmalasakit',
        'mapagkalinga' => 'mapagkalinga',
        'handa' => 'handa',
        'handang' => 'handang',
        'naghahanda' => 'naghahanda',
        'naghahandang' => 'naghahandang',
        'kaibigan' => 'kaibigan',
        'kaibigang' => 'kaibigang',
        'niyo' => 'niyo',
        'ninyo' => 'ninyo',
        'noon' => 'noon',
        'ngayon' => 'ngayon',
        'mamaya' => 'mamaya',
        'bukas' => 'bukas',
        'kahapon' => 'kahapon',
        'araw' => 'araw',
        'gabi' => 'gabi',
        'umaga' => 'umaga',
        'hapon' => 'hapon',
        'oras' => 'oras',
        'minuto' => 'minuto',
        'segundo' => 'segundo',
        'araw-araw' => 'araw-araw',
        'linggo' => 'linggo',
        'buwan' => 'buwan',
        'taon' => 'taon',
        'linggo-linggo' => 'linggo-linggo',
        'buwan-buwan' => 'buwan-buwan',
        'taon-taon' => 'taon-taon',
        'pamilya' => 'pamilya',
        'magulang' => 'magulang',
        'anak' => 'anak',
        'kapatid' => 'kapatid',
        'pinsan' => 'pinsan',
        'lolo' => 'lolo',
        'lola' => 'lola',
        'apo' => 'apo',
        'tiyo' => 'tiyo',
        'tiya' => 'tiya',
        'tito' => 'tito',
        'tita' => 'tita',
        'kamag-anak' => 'kamag-anak',
        'ama' => 'ama',
        'ina' => 'ina',
        'tatay' => 'tatay',
        'nanay' => 'nanay',
        'mama' => 'mama',
        'papa' => 'papa',
        'kuya' => 'kuya',
        'ate' => 'ate',
        'bunso' => 'bunso',
        'lagi' => 'lagi',
        'madalas' => 'madalas',
        'minsan' => 'minsan',
        'bihira' => 'bihira',
        'hindi' => 'hindi',
        'oo' => 'oo',
        'opo' => 'opo',
        'huwag' => 'huwag',
        'wag' => 'huwag',
        'nais' => 'nais',
        'kapwa' => 'kapwa',
        'bayan' => 'bayan',
        'bayanihan' => 'bayanihan',
        'karwahe' => 'karwahe',
        'kalesa' => 'kalesa',
        'traysikel' => 'traysikel',
        'dyip' => 'dyip',
        'dyipni' => 'dyipni',
        'dyipney' => 'dyipney'


    ];


    foreach ($contractions as $contraction => $expanded) {
        $text = str_ireplace($contraction, $expanded, $text);
    }
    
    // Remove punctuation but keep apostrophes for possessives
    $text = preg_replace('/[^a-z0-9\s\']/', ' ', $text);
    $text = strtolower(trim($text));
    
    // Split and filter
    $words = preg_split('/\s+/', $text);
    if ($words === false) {
        return [];
    }
    
    return array_values(array_filter($words, fn($word) => $word !== ''));
}

// ============================================================
// CRLA SCORING ENHANCEMENTS (Phase 4)
// ============================================================

/**
 * Calculate comprehensive reading profile with CRLA scoring
 * Grade 3 CRLA Assessment Implementation
 */
function calculateCRLAProfile(array $assessmentData): array {
    // Extract key metrics
    $totalWords = $assessmentData['total_words'] ?? 0;
    $wordsCorrect = $assessmentData['words_correct'] ?? 0;
    $readingTimeSeconds = $assessmentData['reading_time_seconds'] ?? $assessmentData['time_seconds'] ?? 0;
    
    $accuracy = $totalWords > 0 ? ($wordsCorrect / $totalWords * 100) : 0;
    $readingTimeMinutes = max(0.1, $readingTimeSeconds / 60);
    $wcpm = $wordsCorrect / $readingTimeMinutes;

    // CRLA Part 1 Score (0-10) - from teacher input
    $part1Score = isset($assessmentData['part1_task1_score']) ? (int)$assessmentData['part1_task1_score'] : 0;

    // CRLA Part 2 Score (0-25) - based on comprehension questions answered correctly
    $comprehensionCorrect = $assessmentData['comprehension_score'] ?? 0;
    $part2Score = calculatePart2Score($comprehensionCorrect);

    // Determine reading level based on CRLA Grade 3 Criteria (including 3-minute cap)
    $readingLevel = determineCRLAReadingLevel($accuracy, $part2Score, $readingTimeSeconds);

    // Determine observation level (fluency level 1-4)
    $observationLevel = determineObservationLevel(
        $assessmentData['substitutions'] ?? 0,
        $assessmentData['omissions'] ?? 0,
        $assessmentData['insertions'] ?? 0,
        $totalWords,
        $assessmentData['pronunciation_score'] ?? 5,
        $assessmentData['expression_score'] ?? 5
    );

    // Miscue analysis summary
    $totalMiscues = ($assessmentData['substitutions'] ?? 0) + 
                    ($assessmentData['omissions'] ?? 0) + 
                    ($assessmentData['insertions'] ?? 0);

    return [
        'total_words' => $totalWords,
        'words_correct' => $wordsCorrect,
        'accuracy_percentage' => round($accuracy, 2),
        'wcpm' => round($wcpm, 2),
        'reading_time_seconds' => $readingTimeSeconds,
        'reading_level' => $readingLevel,
        'observation_level' => $observationLevel,
        'part1_score' => $part1Score,
        'part2_score' => $part2Score,
        'comprehension_correct' => $comprehensionCorrect,
        'miscues' => [
            'substitutions' => $assessmentData['substitutions'] ?? 0,
            'omissions' => $assessmentData['omissions'] ?? 0,
            'insertions' => $assessmentData['insertions'] ?? 0,
            'repetitions' => $assessmentData['repetitions'] ?? 0,
            'self_corrections' => $assessmentData['self_corrections'] ?? 0,
            'total' => $totalMiscues
        ],
        'scores' => [
            'pronunciation' => $assessmentData['pronunciation_score'] ?? 0,
            'fluency' => $assessmentData['fluency_score'] ?? 0,
            'expression' => $assessmentData['expression_score'] ?? 0
        ],
        'summary' => "WCPM: " . round($wcpm, 1) . " | Accuracy: " . round($accuracy, 1) . "% | Level: $readingLevel | Obs: $observationLevel"
    ];
}

/**
 * Convert comprehension score to CRLA Part 2 score
 * Part 2: 0-7 comprehension questions correct = 0-25 point scale
 */
function calculatePart2Score(int $questionsCorrect): int {
    $scoreMapping = [
        0 => 0,
        1 => 3,
        2 => 6,
        3 => 9,
        4 => 12,
        5 => 15,
        6 => 20,
        7 => 25
    ];

    $questionsCorrect = min($questionsCorrect, 7);
    return $scoreMapping[$questionsCorrect] ?? 0;
}

/**
 * Determine reading level based on CRLA Grade 3 Criteria
 * 
 * Reading Levels (Incorporating 3-minute time limit):
 * - Low Emerging Reader: Accuracy < 25% OR Part 2 Score < 6
 * - High Emerging Reader: Accuracy < 25% AND Part 2 Score >= 6
 * - Developing Reader: Accuracy 26-50% AND Part 2 Score 6-12
 * - Transitioning Reader: Accuracy 51-75% AND Part 2 Score 12-18
 * - Reading At Grade Level: Accuracy 76-100% AND Part 2 Score >= 19
 */
function determineCRLAReadingLevel(float $accuracy, int $part2Score, int $readingTimeSeconds = 0): string {
    // If reading exceeds 3 minutes, cap highest possible level at Transitioning Reader.
    if ($readingTimeSeconds > 180 && $accuracy >= 76) {
        return 'Transitioning Reader';
    }

    if ($accuracy < 25) {
        return $part2Score >= 6 ? 'High Emerging Reader' : 'Low Emerging Reader';
    } elseif ($accuracy > 25 && $accuracy <= 50) {
        return $part2Score >= 6 ? 'Developing Reader' : 'Emerging Reader';
    } elseif ($accuracy > 50 && $accuracy <= 75) {
        return $part2Score >= 12 ? 'Transitioning Reader' : 'Developing Reader';
    } elseif ($accuracy > 75) {
        return $part2Score >= 19 ? 'Reading At Grade Level' : 'Transitioning Reader';
    }

    return 'Emerging Reader';
}

/**
 * Determine observation level (fluency/fluency indicators 1-4)
 * 
 * Levels:
 * - Level 1: Reads word by word (high error rate)
 * - Level 2: Reads in chunks (moderate error rate)
 * - Level 3: Reads fluently with proper expression
 * - Level 4: Reads fluently but does not observe punctuation marks
 */
function determineObservationLevel(
    int $substitutions,
    int $omissions,
    int $insertions,
    int $totalWords,
    float $pronunciationScore = 5,
    float $expressionScore = 5
): string {
    $totalMistakes = $substitutions + $omissions + $insertions;
    $mistakeRate = $totalWords > 0 ? ($totalMistakes / $totalWords) * 100 : 0;

    // Level 3: Fluent with proper expression
    if ($mistakeRate < 10 && $expressionScore >= 8 && $pronunciationScore >= 8) {
        return 'Level 3';
    }
    // Level 4: Fluent but does not observe punctuation
    elseif ($mistakeRate < 10 && $expressionScore >= 6) {
        return 'Level 4';
    }
    // Level 2: Reads in chunks
    elseif ($mistakeRate < 20) {
        return 'Level 2';
    }
    // Level 1: Word-by-word reading
    else {
        return 'Level 1';
    }
}

/**
 * Get reading profile interpretation for teacher feedback
 */
function getReadingProfileInterpretation(string $readingLevel, string $observationLevel): array {
    $interpretations = [
        'Low Emerging Reader' => 'Learner scores 0 to 10 in Assessment Part 1. Requires intensive intervention and support.',
        'High Emerging Reader' => 'Learner reads less than 25% of passage in 3 minutes and answers at least 1 question correctly. Needs targeted intervention.',
        'Developing Reader' => 'Learner reads between 26% to 50% of passage accurately in 3 minutes and answers 2 to 3 questions correctly. Continue scaffolding support.',
        'Transitioning Reader' => 'Learner reads between 51% to 75% of passage accurately in 3 minutes and answers 4 to 5 questions correctly. Progressing toward grade level.',
        'Reading At Grade Level' => 'Learner reads between 76% to 100% of passage accurately in 3 minutes and answers 6 to 7 questions correctly. Meeting grade level expectations.'
    ];

    $observationDescriptions = [
        'Level 1' => 'Reads word by word with significant pausing between words.',
        'Level 2' => 'Reads in short phrases or chunks, with some pausing.',
        'Level 3' => 'Reads fluently but does not observe punctuation marks for expression.',
        'Level 4' => 'Reads fluently with proper expression and attention to punctuation.'
    ];

    return [
        'reading_level_interpretation' => $interpretations[$readingLevel] ?? 'Assessment pending.',
        'observation_level_description' => $observationDescriptions[$observationLevel] ?? 'Unable to assess fluency.',
        'intervention_needed' => in_array($readingLevel, ['Low Emerging Reader', 'High Emerging Reader', 'Developing Reader'])
    ];
}

function exportAssessmentCsv(PDO $pdo): void {
    $assessmentId = (int)($_GET['assessment_id'] ?? 0);
    $studentId = (int)($_GET['student_id'] ?? 0);

    $sql = '
        SELECT
            ar.assessment_id,
            ar.activity_id,
            s.lrn,
            CONCAT(s.first_name, " ", s.last_name) AS student_name,
            sc.grade_level,
            c.section,
            rm.title AS material_title,
            ar.accuracy_percentage,
            ar.wcpm,
            ar.reading_level,
            ar.final_reading_level,
            ar.observation_level,
            ar.comprehension_score,
            ar.assessed_at,
            ar.transcript
        FROM assessment_result ar
        JOIN reading_activity ra ON ar.activity_id = ra.activity_id
        JOIN student s ON ra.student_id = s.student_id
        LEFT JOIN student_category sc ON s.category_id = sc.category_id
        LEFT JOIN class c ON s.class_id = c.class_id
        JOIN reading_material rm ON ra.material_id = rm.material_id
    ';

    $conditions = [];
    $params = [];

    if ($assessmentId > 0) {
        $conditions[] = 'ar.assessment_id = :assessment_id';
        $params[':assessment_id'] = $assessmentId;
    }

    if ($studentId > 0) {
        $conditions[] = 'ra.student_id = :student_id';
        $params[':student_id'] = $studentId;
    }

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY ar.assessed_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="assessment-results-' . date('Ymd-His') . '.csv"');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new RuntimeException('Unable to open CSV output stream');
    }

    fputcsv($output, [
        'Assessment ID',
        'Activity ID',
        'LRN',
        'Student Name',
        'Grade Level',
        'Section',
        'Material Title',
        'Accuracy Percentage',
        'WCPM',
        'ORF Reading Level',
        'Final Reading Level',
        'Observation Level',
        'Comprehension Score',
        'Assessed At',
        'Transcript'
    ]);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['assessment_id'] ?? '',
            $row['activity_id'] ?? '',
            $row['lrn'] ?? '',
            $row['student_name'] ?? '',
            $row['grade_level'] ?? '',
            $row['section'] ?? '',
            $row['material_title'] ?? '',
            $row['accuracy_percentage'] ?? '',
            $row['wcpm'] ?? '',
            $row['reading_level'] ?? '',
            $row['final_reading_level'] ?? '',
            $row['observation_level'] ?? '',
            $row['comprehension_score'] ?? '',
            $row['assessed_at'] ?? '',
            $row['transcript'] ?? ''
        ]);
    }

    fclose($output);
}