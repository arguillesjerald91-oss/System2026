<?php
include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

$conn->exec("DELETE FROM quizzes WHERE title = 'INFORMATION TECHNOLOGY'");
$conn->exec("DELETE FROM assignments WHERE title = 'ELECTRICAL REPAIRE'");
echo "Test data cleaned.\n";
?>