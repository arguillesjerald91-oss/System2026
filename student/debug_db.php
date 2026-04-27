<?php
// debug_db.php - Quick DB dump for grades and enrollment
include 'db.php';
$db = new Database();
$conn = $db->getConnection();

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Grades Table (grading_period = "Posted")</h2>';
$sql = "SELECT * FROM grades WHERE grading_period = 'Posted'" . ($student_id ? " AND StudID = $student_id" : "");
$stmt = $conn->prepare($sql);
$stmt->execute();
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo '<pre>';
print_r($grades);
echo '</pre>';

echo '<h2>Enrollment Table</h2>';
$sql2 = "SELECT * FROM enrollment" . ($student_id ? " WHERE StudID = $student_id" : "");
$stmt2 = $conn->prepare($sql2);
$stmt2->execute();
$enroll = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo '<pre>';
print_r($enroll);
echo '</pre>';

// Optionally, show all subjects for reference
echo '<h2>Subject Table</h2>';
$sql3 = "SELECT * FROM subject";
$stmt3 = $conn->prepare($sql3);
$stmt3->execute();
$subjects = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo '<pre>';
print_r($subjects);
echo '</pre>';
?>
