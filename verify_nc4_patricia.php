<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== VERIFY NC IV STUDENT ACCESS (PATRICIA SANTOS) ===\n\n";

// Find Patricia
$stmt = $conn->query("
    SELECT s.StudID, s.FirstName, s.LastName, u.user_id, spe.nc_level
    FROM student s
    JOIN users u ON s.user_id = u.user_id
    JOIN student_program_enrollments spe ON s.StudID = spe.student_id
    WHERE spe.enrollment_status = 'Active'
    AND spe.nc_level = 'NC IV'
");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($students as $student) {
    $userId = $student['user_id'];
    $ncLevel = $student['nc_level'];
    $name = $student['FirstName'] . ' ' . $student['LastName'];
    
    echo "Student: $name ($ncLevel)\n";
    echo str_repeat("=", 50) . "\n";
    
    // Get enrolled modules
    $modStmt = $conn->prepare("
        SELECT lm.module_id, lm.module_title, lm.module_type, lm.duration_mins, sme.status
        FROM student_module_enrollment sme
        JOIN learning_modules lm ON sme.module_id = lm.module_id
        WHERE sme.student_id = ?
        ORDER BY lm.module_type, lm.module_title
    ");
    $modStmt->execute([$userId]);
    $modules = $modStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $basic = $common = $core = 0;
    echo "\n--- ENROLLED MODULES ---\n";
    foreach ($modules as $mod) {
        $type = $mod['module_type'];
        $category = strpos($mod['module_title'], 'Basic') !== false ? 'Basic' : 
                   (strpos($mod['module_title'], 'Common') !== false ? 'Common' : 'Core');
        if ($category == 'Basic') $basic++;
        if ($category == 'Common') $common++;
        if ($category == 'Core') $core++;
        
        echo "- [$type] {$mod['module_title']} ({$mod['duration_mins']} mins)\n";
    }
    echo "\nTotal: " . count($modules) . " modules (Basic: $basic, Common: $common, Core: $core)\n";
    
    // Get available quizzes for NC IV
    $quizStmt = $conn->prepare("SELECT quiz_id, title FROM quizzes WHERE nc_level = ? ORDER BY title");
    $quizStmt->execute([$ncLevel]);
    $quizzes = $quizStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\n--- AVAILABLE QUIZZES ---\n";
    foreach ($quizzes as $q) {
        echo "- {$q['title']}\n";
    }
    echo "Total: " . count($quizzes) . " quizzes\n";
    
    // Get available assignments for NC IV
    $assignStmt = $conn->prepare("SELECT assignment_id, title FROM assignments WHERE nc_level = ? ORDER BY title");
    $assignStmt->execute([$ncLevel]);
    $assignments = $assignStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\n--- AVAILABLE ASSIGNMENTS ---\n";
    foreach ($assignments as $a) {
        echo "- {$a['title']}\n";
    }
    echo "Total: " . count($assignments) . " assignments\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "=== ALL STUDENTS AND THEIR NC LEVEL SUBJECTS ===\n\n";

$stmt = $conn->query("
    SELECT s.FirstName, s.LastName, spe.nc_level,
           (SELECT COUNT(*) FROM student_module_enrollment WHERE student_id = u.user_id) as modules
    FROM student s
    JOIN users u ON s.user_id = u.user_id
    JOIN student_program_enrollments spe ON s.StudID = spe.student_id
    WHERE spe.enrollment_status = 'Active'
    ORDER BY spe.nc_level, s.FirstName
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['FirstName']} {$row['LastName']} -> {$row['nc_level']}: {$row['modules']} modules\n";
}
?>