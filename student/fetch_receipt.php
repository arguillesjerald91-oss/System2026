<?php
include 'db.php';

$database = new Database();
$conn = $database->getConnection();

$receipt_no = isset($_GET['receipt']) ? $_GET['receipt'] : '';

if (empty($receipt_no)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([]);
    exit;
}

try {
    $query = $conn->prepare(
        "SELECT p.*, b.StudID AS student_id, s.FirstName as first_name, s.LastName as last_name, s.Course as course, s.YearLvl as year_level, s.Semester as semester
         FROM payments p
         JOIN billing b ON p.billing_id = b.billing_id
         JOIN student s ON s.StudID = b.StudID
         WHERE p.receipt_number = ?
         LIMIT 1"
    );
    $query->execute([$receipt_no]);
    $data = $query->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data ?: []);
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $e->getMessage()]);
}

?>
