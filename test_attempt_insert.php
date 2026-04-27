<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

// Try inserting an attempt
$stmt = $conn->prepare("INSERT INTO quiz_attempts (quiz_id, user_id, answers, score, passed, attempted_at) VALUES (?, ?, ?, ?, ?, NOW())");
try {
    $stmt->execute([51, 53, '{}', 80, 1]);
    echo "Insert successful!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>