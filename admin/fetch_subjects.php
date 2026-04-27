<?php
include_once __DIR__ . '/db.php';
$database = new Database();
$conn = $database->getConnection();

$course = $_GET['course'] ?? '';
$year = $_GET['year'] ?? '';
$semester = $_GET['semester'] ?? '';

if ($course && $year) {
    // Fetch subjects matching the student's year level only
    // Note: subject table doesn't have semester column, only year_level
    $query = "SELECT SubjectID as id, SubCode as course_code, SubName as title, Unit as units, year_level 
              FROM subject 
              WHERE year_level = ?
              ORDER BY SubCode";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$year]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($subjects);
} else {
    echo json_encode([]);
}
?>