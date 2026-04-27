<?php
include 'db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== All tables in database ===\n";
$stmt = $conn->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$subjectTables = [];
foreach ($tables as $table) {
    if (strpos(strtolower($table), 'subject') !== false) {
        $subjectTables[] = $table;
    }
}

if (!empty($subjectTables)) {
    echo "\nFound subject-related tables:\n";
    foreach ($subjectTables as $table) {
        echo "- $table\n";
        
        // Show structure
        $stmt = $conn->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "  Columns:\n";
        foreach ($columns as $col) {
            echo "    - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
        
        // Show sample data
        $stmt = $conn->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "  Records: $count\n";
        
        if ($count > 0) {
            $stmt = $conn->query("SELECT * FROM $table LIMIT 3");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "  Sample data:\n";
            foreach ($records as $record) {
                echo "    " . json_encode($record, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        echo "\n";
    }
} else {
    echo "\nNo subject tables found. Looking for similar tables...\n";
    
    // Check for course, module, etc.
    $similarTables = [];
    foreach ($tables as $table) {
        if (strpos(strtolower($table), 'course') !== false || 
            strpos(strtolower($table), 'module') !== false ||
            strpos(strtolower($table), 'learning') !== false) {
            $similarTables[] = $table;
        }
    }
    
    if (!empty($similarTables)) {
        echo "Found similar tables:\n";
        foreach ($similarTables as $table) {
            echo "- $table\n";
        }
    }
}
?>
