<?php
// Start session only if not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (!isset($_GET['tuition_id'])) {
    echo json_encode(['error' => 'Tuition ID not provided']);
    exit;
}

$tuition_id = $_GET['tuition_id'];

$database = new Database();
$conn = $database->getConnection();

try {
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            s.StudID as student_id,
            s.SchoolID as school_id,
            s.FirstName as first_name,
            s.LastName as last_name,
            s.Course as course,
            s.YearLvl as year_level,
            s.Semester as semester
        FROM tuition t
        JOIN student s ON s.StudID = t.StudID
        WHERE t.tuition_id = ?
    ");
    
    $stmt->execute([$tuition_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        echo json_encode($record);
    } else {
        echo json_encode(['error' => 'Tuition record not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
