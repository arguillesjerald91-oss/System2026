<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

// List all tables
$stmt = $conn->query("SHOW TABLES");
echo "All tables:\n";
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo "  - " . $row[0] . "\n";
}
?>