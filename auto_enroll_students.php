<?php
/**
 * Auto-enroll new trainees in all subjects for their NC level
 * Uses student_module_enrollment table
 */

include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== AUTO-ENROLLMENT IN NC SUBJECTS ===\n\n";

// Get students who are enrolled but haven't had subjects auto-assigned yet
$stmt = $conn->query("
    SELECT s.StudID, s.FirstName, s.LastName, s.user_id, spe.nc_level, spe.enrollment_id
    FROM student s
    JOIN student_program_enrollments spe ON s.StudID = spe.student_id
    WHERE spe.enrollment_status = 'Active'
    AND (spe.auto_enrolled IS NULL OR spe.auto_enrolled = 0)
    ORDER BY spe.enrollment_id DESC
    LIMIT 20
");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($students)) {
    echo "No students need auto-enrollment.\n";
    exit;
}

echo "Found " . count($students) . " students to enroll.\n\n";

$totalEnrolled = 0;

foreach ($students as $student) {
    $studentId = $student['StudID'];
    $userId = $student['user_id'];
    $ncLevel = $student['nc_level'];
    $enrollmentId = $student['enrollment_id'];
    $name = $student['FirstName'] . ' ' . $student['LastName'];
    
    echo "Processing: $name (Student ID: $studentId, User ID: $userId, $ncLevel)\n";
    
    // Get all modules for this NC level from nc_level_subjects
    $stmt = $conn->prepare("
        SELECT nls.mapping_id, nls.module_id, nls.is_required, lm.module_title, lm.duration_mins
        FROM nc_level_subjects nls
        JOIN learning_modules lm ON nls.module_id = lm.module_id
        WHERE nls.nc_level = ?
        ORDER BY nls.sort_order ASC
    ");
    $stmt->execute([$ncLevel]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $enrolledCount = 0;
    foreach ($subjects as $subject) {
        // Check if already enrolled in this module (use user_id for the check)
        $checkStmt = $conn->prepare("SELECT enroll_id FROM student_module_enrollment WHERE student_id = ? AND module_id = ?");
        $checkStmt->execute([$userId, $subject['module_id']]);
        if ($checkStmt->fetch()) {
            continue; // Already enrolled
        }
        
        // Enroll in module
        $insertStmt = $conn->prepare("INSERT INTO student_module_enrollment (student_id, module_id, enrolled_at, status) VALUES (?, ?, NOW(), 'Enrolled')");
        $insertStmt->execute([$userId, $subject['module_id']]);
        $enrolledCount++;
    }
    
    // Also add to module_progress for tracking (use user_id based on table structure)
    foreach ($subjects as $subject) {
        $checkStmt = $conn->prepare("SELECT progress_id FROM module_progress WHERE user_id = ? AND module_id = ?");
        $checkStmt->execute([$userId, $subject['module_id']]);
        if (!$checkStmt->fetch()) {
            $insertStmt = $conn->prepare("INSERT INTO module_progress (user_id, module_id, progress_percent, status) VALUES (?, ?, 0, 'Not Started')");
            $insertStmt->execute([$userId, $subject['module_id']]);
        }
    }
    
    // Mark as auto-enrolled
    $updateStmt = $conn->prepare("UPDATE student_program_enrollments SET auto_enrolled = 1 WHERE enrollment_id = ?");
    $updateStmt->execute([$enrollmentId]);
    
    echo "  - Enrolled in $enrolledCount modules for $ncLevel\n";
    $totalEnrolled += $enrolledCount;
}

echo "\n=== ENROLLMENT COMPLETE ===\n";
echo "Total new enrollments: $totalEnrolled\n";

// Display enrollment summary by NC level
echo "\n=== CURRENT MODULE ENROLLMENT STATS BY NC LEVEL ===\n";
foreach (['NC I', 'NC II', 'NC III', 'NC IV'] as $level) {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT sme.student_id) as student_count, COUNT(sme.enroll_id) as module_count
        FROM student_module_enrollment sme
        JOIN learning_modules lm ON sme.module_id = lm.module_id
        WHERE lm.nc_level = ?
    ");
    $stmt->execute([$level]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "$level: {$row['student_count']} students, {$row['module_count']} module enrollments\n";
}
?>