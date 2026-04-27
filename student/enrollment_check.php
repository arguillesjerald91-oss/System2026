<?php
/**
 * Helper function to check if a student/trainee is enrolled
 * Returns true if enrollment_status = 'Active' in student_program_enrollments
 */

function is_enrolled(PDO $conn, int $userId): bool {
    $stmt = $conn->prepare("
        SELECT 1 FROM student_program_enrollments 
        WHERE student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1) 
        AND enrollment_status = 'Active' 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return (bool)$stmt->fetchColumn();
}

function get_enrollment_info(PDO $conn, int $userId): ?array {
    $stmt = $conn->prepare("
        SELECT spe.*, p.program_name, p.nc_level
        FROM student_program_enrollments spe
        LEFT JOIN programs p ON spe.program_id = p.program_id
        WHERE spe.student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1) 
        AND spe.enrollment_status = 'Active' 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}