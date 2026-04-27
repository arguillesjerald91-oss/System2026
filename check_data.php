<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== LEARNING MODULES ===\n";
$stmt = $conn->query("SELECT * FROM learning_modules");
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($modules as $m) {
    print_r($m);
}

echo "\n=== AUTO MECHANIC PROGRAMS ===\n";
$stmt = $conn->query("SELECT * FROM auto_mechanic_programs");
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($programs as $p) {
    print_r($p);
}

echo "\n=== PRE ENROLLMENT APPLICATIONS ===\n";
$stmt = $conn->query("SELECT * FROM pre_enrollment_applications");
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($apps as $a) {
    echo $a['application_number'] . " - " . $a['first_name'] . " " . $a['last_name'] . " - Status: " . $a['application_status'] . "\n";
}

echo "\n=== USERS ===\n";
$stmt = $conn->query("SELECT user_id, username, user_type, first_name, last_name FROM users");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['user_id'] . " - " . $row['username'] . " (" . $row['user_type'] . ") - " . $row['first_name'] . " " . $row['last_name'] . "\n";
}
?>