<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

$userId = 53; // Patricia's user_id as we confirmed

echo "=== CHECKING ENROLLMENT FOR PATRICIA (user_id=$userId) ===\n\n";

// Get student ID
$stmt = $conn->prepare("SELECT StudID, FirstName, LastName FROM student WHERE user_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student) {
    $studID = $student['StudID'];
    echo "Student: {$student['FirstName']} {$student['LastName']} (StudID: $studID)\n\n";
    
    // Get active enrollment
    $stmt = $conn->prepare("
        SELECT spe.enrollment_id, spe.student_id, spe.nc_level, spe.enrollment_status
        FROM student_program_enrollments spe
        WHERE spe.student_id = ? AND spe.enrollment_status = 'Active'
        ORDER BY spe.enrollment_id DESC
    ");
    $stmt->execute([$studID]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Active Enrollments:\n";
    foreach ($enrollments as $e) {
        echo "  - Enrollment ID: {$e['enrollment_id']}\n";
        echo "  - Student ID: {$e['student_id']}\n";
        echo "  - NC Level: {$e['nc_level']}\n";
        echo "  - Status: {$e['enrollment_status']}\n";
    }
    
    if (count($enrollments) === 0) {
        echo "  (No active enrollments found!)\n";
    }
} else {
    echo "Student record NOT found for user_id $userId!\n";
}
?>