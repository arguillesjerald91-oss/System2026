<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

try {
    $conn->exec("ALTER TABLE student_program_enrollments ADD COLUMN auto_enrolled TINYINT(1) DEFAULT 0");
    echo "Column 'auto_enrolled' added successfully.\n";
} catch (Exception $e) {
    echo "Column may already exist or error: " . $e->getMessage() . "\n";
}
?>