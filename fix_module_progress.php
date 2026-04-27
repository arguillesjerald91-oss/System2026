<?php
/**
 * Fix student_module_progress - populate from existing module enrollments
 * Uses enrollment_id from student_program_enrollments
 */

include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== FIXING MODULE PROGRESS RECORDS ===\n\n";

$fixed = 0;

// Get all active enrollments
$stmt = $conn->query("
    SELECT spe.student_id, spe.enrollment_id, spe.nc_level
    FROM student_program_enrollments spe
    WHERE spe.enrollment_status = 'Active'
");
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($enrollments as $enroll) {
    $studentId = $enroll['student_id'];
    $enrollmentId = $enroll['enrollment_id'];
    $ncLevel = $enroll['nc_level'];
    
    // Get modules for this NC level
    $stmt2 = $conn->prepare("
        SELECT nls.module_id, lm.module_title
        FROM nc_level_subjects nls
        JOIN learning_modules lm ON nls.module_id = lm.module_id
        WHERE nls.nc_level = ?
    ");
    $stmt2->execute([$ncLevel]);
    $modules = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($modules as $mod) {
        // Check if already exists in student_module_progress
        $checkStmt = $conn->prepare("SELECT progress_id FROM student_module_progress WHERE enrollment_id = ? AND module_id = ?");
        $checkStmt->execute([$enrollmentId, $mod['module_id']]);
        
        if (!$checkStmt->fetch()) {
            $insertStmt = $conn->prepare("
                INSERT INTO student_module_progress 
                (enrollment_id, module_id, progress_percentage, status, start_date)
                VALUES (?, ?, 0, 'Not Started', NOW())
            ");
            $insertStmt->execute([$enrollmentId, $mod['module_id']]);
            $fixed++;
        }
    }
    
    echo "NC $ncLevel (enrollment $enrollmentId): " . count($modules) . " modules\n";
}

echo "\nTotal new records: $fixed\n";

// Now verify
echo "\n=== VERIFICATION ===\n";
$stmt = $conn->query("
    SELECT spe.nc_level, 
           COUNT(DISTINCT spe.enrollment_id) as enrollments,
           (SELECT COUNT(*) FROM student_module_progress smp WHERE smp.enrollment_id = spe.enrollment_id) as modules
    FROM student_program_enrollments spe
    WHERE spe.enrollment_status = 'Active'
    GROUP BY spe.nc_level
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['nc_level']}: {$row['modules']} module records\n";
}

echo "\nDone! Students should now see their subjects.\n";
?>