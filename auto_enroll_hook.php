<?php
/**
 * Auto-enrollment hook - call this after student enrollment
 * This function should be called from enroll_trainees.php and pre_enrollment approval process
 */

function autoEnrollInNCSubjects($studentId, $userId, $ncLevel) {
    global $conn;
    
    $enrolledCount = 0;
    
    // Get all modules for this NC level
    $stmt = $conn->prepare("
        SELECT nls.module_id, lm.module_title
        FROM nc_level_subjects nls
        JOIN learning_modules lm ON nls.module_id = lm.module_id
        WHERE nls.nc_level = ?
        ORDER BY nls.sort_order ASC
    ");
    $stmt->execute([$ncLevel]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($subjects as $subject) {
        // Check if already enrolled
        $checkStmt = $conn->prepare("SELECT enroll_id FROM student_module_enrollment WHERE student_id = ? AND module_id = ?");
        $checkStmt->execute([$userId, $subject['module_id']]);
        if ($checkStmt->fetch()) {
            continue;
        }
        
        // Enroll in module
        $insertStmt = $conn->prepare("INSERT INTO student_module_enrollment (student_id, module_id, enrolled_at, status) VALUES (?, ?, NOW(), 'Enrolled')");
        $insertStmt->execute([$userId, $subject['module_id']]);
        
        // Add module progress tracking
        $progressStmt = $conn->prepare("INSERT INTO module_progress (user_id, module_id, progress_percent, status) VALUES (?, ?, 0, 'Not Started')");
        $progressStmt->execute([$userId, $subject['module_id']]);
        
        $enrolledCount++;
    }
    
    return [
        'success' => true,
        'nc_level' => $ncLevel,
        'modules_enrolled' => $enrolledCount
    ];
}

// Include this in enrollment process
if (php_sapi_name() === 'cli' && basename(__FILE__) == basename($argv[0])) {
    // Called directly - show status
    include 'db.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "=== AUTO-ENROLLMENT HOOK STATUS ===\n";
    
    // Show pending enrollments
    $stmt = $conn->query("
        SELECT s.StudID, s.FirstName, s.LastName, spe.nc_level, spe.enrollment_id, spe.auto_enrolled
        FROM student s
        JOIN student_program_enrollments spe ON s.StudID = spe.student_id
        WHERE spe.enrollment_status = 'Active'
        ORDER BY spe.enrollment_id DESC
    ");
    
    $pending = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = ($row['auto_enrolled'] ?? 0) ? 'Enrolled' : 'Pending';
        echo "{$row['FirstName']} {$row['LastName']} - {$row['nc_level']}: $status\n";
        if (!($row['auto_enrolled'] ?? 0)) $pending++;
    }
    
    echo "\nPending auto-enrollments: $pending\n";
    
    if ($pending > 0) {
        echo "Run: php auto_enroll_students.php\n";
    }
}
?>