<?php
include 'db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== Checking subject-related tables ===\n";

// Get all tables with 'subject' in the name
$stmt = $conn->query("SHOW TABLES LIKE '%subject%'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "\nTable: $table\n";
    
    // Get column structure
    $stmt = $conn->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns:\n";
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    // Get record count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM $table");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Records: $count\n";
    
    // Show sample data if available
    if ($count > 0) {
        $stmt = $conn->query("SELECT * FROM $table LIMIT 3");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Sample data:\n";
        foreach ($records as $record) {
            echo "  " . json_encode($record, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}

echo "\n=== Checking enrollment table ===\n";
$stmt = $conn->query("SHOW TABLES LIKE '%enrollment%'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "\nTable: $table\n";
    $stmt = $conn->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns:\n";
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM $table");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Records: $count\n";
}
?>
