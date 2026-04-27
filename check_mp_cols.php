<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== module_progress columns ===\n";
$stmt = $conn->query("DESCRIBE module_progress");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . " | " . $r['Type'] . "\n";
}
?>