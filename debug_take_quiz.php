<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== DEBUG: Patricia's take_quiz.php ===\n\n";

// Patricia's user_id
$stmt = $conn->prepare("SELECT user_id FROM users WHERE first_name = 'Patricia' OR username LIKE '%patricia%'");
$stmt->execute();
$userId = $stmt->fetchColumn();

echo "User ID: $userId\n\n";

// Check what's in session variables
echo "=== Check enrollment query for user_id $userId ===\n";

$ncStmt = $conn->prepare("
    SELECT spe.*, p.program_name
    FROM student_program_enrollments spe
    LEFT JOIN programs p ON spe.program_id = p.program_id
    WHERE spe.student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1)
    AND spe.enrollment_status = 'Active'
    ORDER BY spe.enrollment_id DESC LIMIT 1
");
$ncStmt->execute([$userId]);
$enrollment = $ncStmt->fetch(PDO::FETCH_ASSOC);

if ($enrollment) {
    echo "Enrollment found!\n";
    echo "  - enrollment_id: {$enrollment['enrollment_id']}\n";
    echo "  - student_id: {$enrollment['student_id']}\n";
    echo "  - nc_level: {$enrollment['nc_level']}\n";
    echo "  - enrollment_status: {$enrollment['enrollment_status']}\n";
} else {
    echo "NO ENROLLMENT FOUND!\n";
    
    // Check student record
    $stmt = $conn->prepare("SELECT * FROM student WHERE user_id = ?");
    $stmt->execute([$userId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        echo "Student record: StudID = {$student['StudID']}\n";
    }
    
    // Check all enrollments for this student
    echo "\nAll enrollments:\n";
    $stmt = $conn->query("SELECT * FROM student_program_enrollments WHERE student_id IN (SELECT StudID FROM student WHERE user_id = $userId)");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - ID {$r['enrollment_id']}: NC {$r['nc_level']}, Status: {$r['enrollment_status']}\n";
    }
}
?>