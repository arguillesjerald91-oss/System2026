<?php
include 'db.php';
$database = new Database();
$conn = $database->getConnection();

echo "Checking scholarship_applications table structure:\n";
try {
    $stmt = $conn->query('DESCRIBE scholarship_applications');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . ' - ' . $col['Null'] . ' - ' . $col['Key'] . "\n";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
