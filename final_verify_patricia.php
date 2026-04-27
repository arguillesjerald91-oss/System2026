<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== FINAL VERIFICATION: PATRICIA NC IV SUBJECTS ===\n\n";

// Find Patricia's user
$stmt = $conn->prepare("SELECT user_id FROM users WHERE first_name LIKE '%Patricia%' LIMIT 1");
$stmt->execute();
$userId = $stmt->fetchColumn();

if (!$userId) {
    echo "User not found!\n";
    exit;
}

echo "Patricia's user_id: $userId\n\n";

// Get NC level
$stmt = $conn->prepare("
    SELECT spe.nc_level
    FROM student_program_enrollments spe
    WHERE spe.student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1)
    AND spe.enrollment_status = 'Active'
");
$stmt->execute([$userId]);
$ncLevel = $stmt->fetchColumn();

echo "NC Level: $ncLevel\n\n";

// Get modules from learning_modules
$stmt = $conn->prepare("
    SELECT lm.module_id, lm.module_title, lm.module_type, lm.duration_mins,
           COALESCE(mp.progress_percent, 0) as progress,
           COALESCE(mp.status, 'Not Started') as status
    FROM learning_modules lm
    LEFT JOIN module_progress mp ON lm.module_id = mp.module_id AND mp.user_id = ?
    WHERE lm.nc_level = ? AND lm.is_active = 1
    ORDER BY lm.module_id
");
$stmt->execute([$userId, $ncLevel]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== LEARNING MODULES FOR $ncLevel ===\n";
$total = 0;
foreach ($modules as $m) {
    echo "- [{$m['module_type']}] {$m['module_title']} ({$m['duration_mins']} mins) - {$m['status']}: {$m['progress']}%\n";
    $total++;
}

echo "\nTotal modules: $total\n";

// Get quizzes
$stmt = $conn->prepare("
    SELECT quiz_id, title 
    FROM quizzes 
    WHERE nc_level = ? AND is_active = 1
    ORDER BY title
");
$stmt->execute([$ncLevel]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n=== QUIZZES FOR $ncLevel ===\n";
foreach ($quizzes as $q) {
    echo "- {$q['title']}\n";
}
echo "Total quizzes: " . count($quizzes) . "\n";

// Get assignments
$stmt = $conn->prepare("
    SELECT assignment_id, title 
    FROM assignments 
    WHERE nc_level = ? 
    ORDER BY title
");
$stmt->execute([$ncLevel]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n=== ASSIGNMENTS FOR $ncLevel ===\n";
foreach ($assignments as $a) {
    echo "- {$a['title']}\n";
}
echo "Total assignments: " . count($assignments) . "\n";

echo "\n=== READY FOR PATRICIA'S PAGES ===\n";
?>