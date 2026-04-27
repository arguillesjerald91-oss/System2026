<?php
/**
 * Check table structure
 */
include 'db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== USERS TABLE ===\n";
$stmt = $conn->query("DESCRIBE users");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n=== Count by table ===\n";
$tables = ['users', 'training_batches', 'pre_enrollment_applications', 'scholarship_programs', 'scholarship_applications', 'competency_units', 'competency_assessments', 'learning_modules', 'module_progress'];
foreach ($tables as $table) {
    try {
        $count = $conn->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "$table: $count rows\n";
    } catch (Exception $e) {
        echo "$table: ERROR - " . $e->getMessage() . "\n";
    }
}
?>