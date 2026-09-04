<?php
require_once __DIR__ . '/../auth/config.php';

function createReadingActivity(array $data): array {
    global $pdo;

    $stmt = $pdo->prepare(
        'INSERT INTO reading_activity (student_id, material_id, started_at, finished_at, audio_filename, audio_path, duration_seconds, activity_status)
         VALUES (:student_id, :material_id, NOW(), NOW(), :audio_filename, :audio_path, :duration_seconds, :activity_status)'
    );

    $audioFilename = $data['audio_filename'] ?? null;

    $stmt->execute([
        ':student_id' => (int)($data['student_id'] ?? 0),
        ':material_id' => (int)($data['material_id'] ?? 0),
        ':audio_filename' => $audioFilename,
        ':audio_path' => $audioFilename ? ('uploads/audio/' . $audioFilename) : null,
        ':duration_seconds' => (int)($data['duration_seconds'] ?? 0),
        ':activity_status' => $data['activity_status'] ?? 'Completed',
    ]);

    return ['success' => true, 'activity_id' => (int)$pdo->lastInsertId()];
}

function saveAssessment(array $data): array {
    global $pdo;

    $stmt = $pdo->prepare(
        'INSERT INTO assessment_result (activity_id, total_words, words_correct, accuracy_percentage, wcpm, reading_time_seconds, substitutions, omissions, insertions, transcript, reading_level, teacher_feedback, part1_task1_score, part1_words_score, part1_total_score, story_number, miscues, words_read, minutes, seconds, comprehension_score, final_reading_level, observation_level, pronunciation_score, fluency_score, expression_score) VALUES (:activity_id, :total_words, :words_correct, :accuracy_percentage, :wcpm, :reading_time_seconds, :substitutions, :omissions, :insertions, :transcript, :reading_level, :teacher_feedback, :part1_task1_score, :part1_words_score, :part1_total_score, :story_number, :miscues, :words_read, :minutes, :seconds, :comprehension_score, :final_reading_level, :observation_level, :pronunciation_score, :fluency_score, :expression_score)'
    );

    $stmt->execute([
        ':activity_id' => $data['activity_id'],
        ':total_words' => $data['total_words'] ?? 0,
        ':words_correct' => $data['words_correct'] ?? 0,
        ':accuracy_percentage' => $data['accuracy_percentage'] ?? 0,
        ':wcpm' => $data['wcpm'] ?? 0,
        ':reading_time_seconds' => $data['reading_time_seconds'] ?? 0,
        ':substitutions' => $data['substitutions'] ?? 0,
        ':omissions' => $data['omissions'] ?? 0,
        ':insertions' => $data['insertions'] ?? 0,
        ':transcript' => $data['transcript'] ?? null,
        ':reading_level' => $data['reading_level'] ?? null,
        ':teacher_feedback' => $data['teacher_feedback'] ?? null,
        ':part1_task1_score' => $data['part1_task1_score'] ?? 0,
        ':part1_words_score' => $data['part1_words_score'] ?? 0,
        ':part1_total_score' => $data['part1_total_score'] ?? 0,
        ':story_number' => $data['story_number'] ?? null,
        ':miscues' => $data['miscues'] ?? 0,
        ':words_read' => $data['words_read'] ?? 0,
        ':minutes' => $data['minutes'] ?? 0,
        ':seconds' => $data['seconds'] ?? 0,
        ':comprehension_score' => $data['comprehension_score'] ?? 0,
        ':final_reading_level' => $data['final_reading_level'] ?? null,
        ':observation_level' => $data['observation_level'] ?? null,
        ':pronunciation_score' => $data['pronunciation_score'] ?? 0,
        ':fluency_score' => $data['fluency_score'] ?? 0,
        ':expression_score' => $data['expression_score'] ?? 0,
    ]);

    return ['success' => true, 'assessment_id' => (int) $pdo->lastInsertId()];
}

function getAssessmentSummary(): array {
    global $pdo;
    $stmt = $pdo->query('SELECT assessment_id, wcpm, reading_level, assessed_at FROM assessment_result ORDER BY assessed_at DESC LIMIT 10');
    return $stmt->fetchAll();
}
