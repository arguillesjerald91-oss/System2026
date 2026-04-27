<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== STUDENT MODULE ACCESS SUMMARY ===\n\n";

// For each student, show their enrolled modules
$stmt = $conn->query("
    SELECT s.StudID, s.FirstName, s.LastName, u.user_id, spe.nc_level
    FROM student s
    JOIN users u ON s.user_id = u.user_id
    JOIN student_program_enrollments spe ON s.StudID = spe.student_id
    WHERE spe.enrollment_status = 'Active'
    ORDER BY spe.nc_level, s.FirstName
");
while ($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $userId = $student['user_id'];
    $ncLevel = $student['nc_level'];
    
    // Count enrolled modules
    $modStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM student_module_enrollment WHERE student_id = ?");
    $modStmt->execute([$userId]);
    $moduleCount = $modStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    // Count available quizzes for their NC level
    $quizStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM quizzes WHERE nc_level = ?");
    $quizStmt->execute([$ncLevel]);
    $quizCount = $quizStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    // Count available assignments
    $assignStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM assignments WHERE nc_level = ?");
    $assignStmt->execute([$ncLevel]);
    $assignCount = $assignStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    echo "{$student['FirstName']} {$student['LastName']} ({$student['nc_level']})\n";
    echo "  - Enrolled Modules: $moduleCount\n";
    echo "  - Available Quizzes: $quizCount\n";
    echo "  - Available Assignments: $assignCount\n\n";
}

echo "=== QUIZ AND ASSIGNMENT SAMPLE (NC I) ===\n";
$stmt = $conn->query("SELECT quiz_id, title, nc_level FROM quizzes WHERE nc_level = 'NC I' LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- Quiz: {$row['title']}\n";
}

echo "\n";
$stmt = $conn->query("SELECT assignment_id, title, nc_level FROM assignments WHERE nc_level = 'NC I' LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- Assignment: {$row['title']}\n";
}

echo "\n=== CURRICULUM DATA VERIFICATION ===\n\n";

// Total competencies by NC level (each module = 1 competency)
foreach (['NC I', 'NC II', 'NC III', 'NC IV'] as $level) {
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM nc_level_subjects WHERE nc_level = ?) as subjects,
            (SELECT COUNT(*) FROM learning_modules WHERE nc_level = ?) as modules,
            (SELECT COUNT(*) FROM quizzes WHERE nc_level = ?) as quizzes,
            (SELECT COUNT(*) FROM assignments WHERE nc_level = ?) as assignments
    ");
    $stmt->execute([$level, $level, $level, $level]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "$level:\n";
    echo "  - Subjects/Mappings: {$row['subjects']}\n";
    echo "  - Learning Modules: {$row['modules']}\n";
    echo "  - Quizzes: {$row['quizzes']}\n";
    echo "  - Assignments: {$row['assignments']}\n\n";
}
?>