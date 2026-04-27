<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== quiz_questions table structure ===\n";
$stmt = $conn->query("DESCRIBE quiz_questions");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . " | " . $r['Type'] . " | " . ($r['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}
?>