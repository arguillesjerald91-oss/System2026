<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== DEBUG: PATRICIA'S ACCESS (User ID based) ===\n\n";

// Find Patricia's user record
$stmt = $conn->prepare("SELECT u.user_id, u.username, u.first_name, u.last_name, s.StudID FROM users u JOIN student s ON u.user_id = s.user_id WHERE u.username LIKE '%patricia%' OR u.first_name LIKE '%Patricia%'");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Patricia not found!\n";
    exit;
}

$userId = $user['user_id'];
$studentId = $user['StudID'];

echo "User: {$user['first_name']} {$user['last_name']} (user_id: $userId, student_id: $studentId)\n\n";

// Check enrollment
$stmt = $conn->prepare("SELECT spe.* FROM student_program_enrollments spe WHERE spe.student_id = ? AND spe.enrollment_status = 'Active'");
$stmt->execute([$studentId]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Enrollment: NC Level = " . ($enrollment['nc_level'] ?? 'NULL') . ", Enrollment ID = " . ($enrollment['enrollment_id'] ?? 'NULL') . "\n\n";

// Check student_module_enrollment (user_id based)
$stmt = $conn->prepare("SELECT COUNT(*) FROM student_module_enrollment WHERE student_id = ?");
$stmt->execute([$userId]);
echo "student_module_enrollment entries: " . $stmt->fetchColumn() . "\n\n";

// Check module_progress (user_id based)
$stmt = $conn->prepare("SELECT COUNT(*) FROM module_progress WHERE user_id = ?");
$stmt->execute([$userId]);
echo "module_progress entries: " . $stmt->fetchColumn() . "\n\n";

// Check what modules exist for NC IV
$stmt = $conn->prepare("
    SELECT lm.module_id, lm.module_title, lm.nc_level 
    FROM learning_modules lm 
    WHERE lm.nc_level = 'NC IV' AND lm.is_active = 1
    ORDER BY lm.module_id LIMIT 5
");
$stmt->execute();
echo "\nSample NC IV modules in learning_modules:\n";
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- ID {$r['module_id']}: {$r['module_title']}\n";
}

// Check what modules exist in nc_level_subjects
$stmt = $conn->prepare("
    SELECT nls.module_id, lm.module_title
    FROM nc_level_subjects nls
    JOIN learning_modules lm ON nls.module_id = lm.module_id
    WHERE nls.nc_level = 'NC IV'
    ORDER BY nls.sort_order LIMIT 5
");
$stmt->execute();
echo "\nSample NC IV modules in nc_level_subjects:\n";
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- ID {$r['module_id']}: {$r['module_title']}\n";
}

// Test query used in student_dashboard.php
echo "\n=== TESTING DASHBOARD QUERY ===\n";
$stmt = $conn->prepare("
    SELECT lm.module_id, lm.module_title, lm.module_type, smp.progress_percentage as progress_percent, smp.status 
    FROM student_module_progress smp
    JOIN learning_modules lm ON smp.module_id = lm.module_id
    WHERE smp.user_id = ? AND smp.status != 'Completed'
    ORDER BY smp.progress_percentage DESC
");
$stmt->execute([$userId]);
$progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Result: " . count($progress) . " modules in progress\n";

if (count($progress) === 0) {
    echo "\n*** No progress found - checking if we need to create records ***\n";
}
?>