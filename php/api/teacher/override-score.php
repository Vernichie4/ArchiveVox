<?php
require_once '../../auth/session.php';
require_once '../../connection.php';

$data = json_decode(file_get_contents('php://input'), true);

// Ensure the user is authorized to modify this record
$stmt = $conn->prepare("UPDATE assessment_result ar
    JOIN reading_activity ra ON ar.activity_id = ra.activity_id
    JOIN reading_assignment r_assign ON ra.assignment_material_id = r_assign.assignment_id
    SET 
        ar.manual_substitutions = ?, 
        ar.manual_omissions = ?, 
        ar.manual_insertions = ?, 
        ar.manual_repetitions = ?, 
        ar.manual_miscues = ?, 
        ar.manual_words_correct = ?,
        ar.manual_accuracy_percentage = ?, 
        ar.manual_wcpm = ?, 
        ar.is_overridden = 1 
    WHERE ar.assessment_id = ? AND r_assign.teacher_id = ?");

$stmt->bind_param("iiiiiiidii", 
    $data['manual_substitutions'], 
    $data['manual_omissions'], 
    $data['manual_insertions'], 
    $data['manual_repetitions'], 
    $data['manual_miscues'], 
    $data['manual_words_correct'],
    $data['manual_accuracy_percentage'], 
    $data['manual_wcpm'], 
    $data['assessment_id'], 
    $_SESSION['teacher_id'] // Assuming this is how your auth handles teacher sessions
);

if($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Override saved successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error."]);
}
?>