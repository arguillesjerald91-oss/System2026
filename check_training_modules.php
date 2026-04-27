<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== training_modules ===\n";
$stmt = $conn->query("DESCRIBE training_modules");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . "\n";
}

echo "\n=== learning_modules ===\n";
$stmt = $conn->query("SELECT module_id FROM learning_modules WHERE nc_level = 'NC IV' LIMIT 3");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "learning_modules module_id: {$r['module_id']}\n";
}

echo "\n=== training_modules ===\n";
$stmt = $conn->query("SELECT module_id FROM training_modules WHERE nc_level = 'NC IV' LIMIT 3");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "training_modules module_id: {$r['module_id']}\n";
}
?>