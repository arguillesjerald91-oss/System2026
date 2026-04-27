<?php
/**
 * AJAX: Get student enrollments for dropdowns
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$studentId = $_GET['student_id'] ?? $_POST['student_id'] ?? null;

if (!$studentId) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT 
            spe.enrollment_id,
            spe.enrollment_status,
            spe.nc_level,
            spe.certificate_number,
            tb.batch_code
        FROM student_program_enrollments spe
        LEFT JOIN training_batches tb ON spe.batch_id = tb.batch_id
        WHERE spe.student_id = ?
        ORDER BY spe.enrollment_id DESC
    ");
    $stmt->execute([$studentId]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($enrollments);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
