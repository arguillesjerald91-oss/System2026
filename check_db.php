<?php
include 'db.php';
$db = new Database();
$conn = $db->getConnection();

// Check tables
$stmt = $conn->prepare("SHOW TABLES");
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "=== TABLES ===\n";
echo implode(", ", $tables) . "\n\n";

// Check student_subjects structure
if (in_array('student_subjects', $tables)) {
    echo "=== student_subjects COLUMNS ===\n";
    $stmt = $conn->prepare("DESCRIBE student_subjects");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    // Check sample data
    echo "\n=== sample data from student_subjects ===\n";
    $stmt = $conn->prepare("SELECT * FROM student_subjects LIMIT 5");
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
}

// Check if student_schedules exists
if (in_array('student_schedules', $tables)) {
    echo "\n=== student_schedules COLUMNS ===\n";
    $stmt = $conn->prepare("DESCRIBE student_schedules");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}
?>
