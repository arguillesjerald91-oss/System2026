<?php
/**
 * Fetch enrolled subject IDs for a student
 * Used to filter available schedules during schedule assignment
 */
session_start();
header('Content-Type: application/json');
include 'db.php';

$database = new Database();
$conn = $database->getConnection();

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if ($student_id <= 0) {
    echo json_encode(['status' => 'error', 'subjects' => [], 'enrolled_schedules' => []]);
    exit;
}

try {
    // Get enrolled subject_ids from enrollment table (enrollment → schedules → subject)
    $sql = "SELECT DISTINCT s.subject_id
            FROM enrollment e
            JOIN schedules s ON e.schedule_id = s.schedule_id
            WHERE e.StudID = ?
            ORDER BY s.subject_id ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$student_id]);
    $enrolledSubjects = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Also get enrolled schedule_ids
    $sql2 = "SELECT DISTINCT e.schedule_id
            FROM enrollment e
            WHERE e.StudID = ?
            ORDER BY e.schedule_id ASC";
    
    $stmt2 = $conn->prepare($sql2);
    $stmt2->execute([$student_id]);
    $enrolledSchedules = $stmt2->fetchAll(PDO::FETCH_COLUMN, 0);
    
    echo json_encode([
        'status' => 'success',
        'subjects' => $enrolledSubjects,
        'enrolled_schedules' => $enrolledSchedules,
        'count' => count($enrolledSubjects)
    ]);
} catch (PDOException $e) {
    error_log("Error fetching enrolled subjects: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'subjects' => [], 'enrolled_schedules' => [], 'message' => 'Database error']);
}
exit;
?>
