<?php
include 'db.php';
$database = new Database();
$conn = $database->getConnection();

echo "Checking pre_enrollment_applications table structure:\n";
try {
    $stmt = $conn->query('DESCRIBE pre_enrollment_applications');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . PHP_EOL;
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
?>
