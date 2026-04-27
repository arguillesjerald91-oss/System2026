<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== student table structure ===\n";
$stmt = $conn->query("DESCRIBE student");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n=== Check if student table has data ===\n";
$stmt = $conn->query("SELECT COUNT(*) as cnt FROM student");
echo "Count: " . $stmt->fetchColumn() . "\n";

echo "\n=== Check student_program_enrollments foreign keys ===\n";
$stmt = $conn->query("SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = 'tesda_auto_mechanic' AND TABLE_NAME = 'student_program_enrollments'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>