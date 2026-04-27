<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== EXISTING LEARNING MODULES ===\n";
$stmt = $conn->query("SELECT module_id, module_title, module_type, nc_level FROM learning_modules ORDER BY nc_level, module_type");
$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['nc_level'] . ' | ' . $row['module_type'] . ' | ' . $row['module_title'] . "\n";
    $count++;
}
echo "Total: $count modules\n";

echo "\n=== CHECKING QUIZZES ===\n";
$stmt = $conn->query("SELECT quiz_id, module_id, title, nc_level FROM quizzes");
$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['nc_level'] . ' | ' . $row['title'] . "\n";
    $count++;
}
echo "Total: $count quizzes\n";

echo "\n=== CHECKING ASSIGNMENTS ===\n";
$stmt = $conn->query("SELECT assignment_id, module_id, title, nc_level FROM assignments");
$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['nc_level'] . ' | ' . $row['title'] . "\n";
    $count++;
}
echo "Total: $count assignments\n";
?>