<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== Check batch_id constraint ===\n";
$stmt = $conn->query("SELECT COLUMN_NAME, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'tesda_auto_mechanic' AND TABLE_NAME = 'student_program_enrollments' AND COLUMN_NAME = 'batch_id'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n=== Check if training_batches has data ===\n";
$stmt = $conn->query("SELECT * FROM training_batches");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Make batch_id nullable in enrollment table ===\n";
try {
    $conn->exec("ALTER TABLE student_program_enrollments MODIFY COLUMN batch_id INT NULL");
    echo "batch_id is now nullable\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>