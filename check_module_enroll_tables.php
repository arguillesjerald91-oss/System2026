<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== student_module_enrollment ===\n";
$stmt = $conn->query("DESCRIBE student_module_enrollment");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . " | " . $r['Type'] . "\n";
}

echo "\n=== module_progress ===\n";
$stmt = $conn->query("DESCRIBE module_progress");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . " | " . $r['Type'] . "\n";
}

echo "\n=== Check data in student_module_enrollment ===\n";
$stmt = $conn->query("SELECT * FROM student_module_enrollment LIMIT 3");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}
?>