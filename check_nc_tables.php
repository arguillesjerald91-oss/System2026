<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== Checking auto_mechanic_programs ===\n";
$stmt = $conn->query("SELECT * FROM auto_mechanic_programs");
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($programs);

echo "\n=== Checking columns in tables ===\n";
$tables = ['learning_modules', 'module_contents', 'quizzes', 'assignments', 'learning_materials', 'pre_enrollment_applications'];
foreach ($tables as $table) {
    echo "\n$table columns:\n";
    $stmt = $conn->query("DESCRIBE $table");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - " . $row['Field'] . "\n";
    }
}

echo "\n=== Checking student_program_enrollments ===\n";
$stmt = $conn->query("SHOW TABLES LIKE 'student_program_enrollments'");
if ($stmt->fetch()) {
    echo "Table exists\n";
} else {
    echo "Table does NOT exist\n";
}
?>