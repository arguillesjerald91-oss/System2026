<?php
header('Content-Type: application/json');
session_start();
include '../db.php';
include_once '../log_activity.php';

$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['student_id'])) {
        $student_id = $_POST['student_id'];

        try {
            // Get student info before deleting for logging
            $getStmt = $conn->prepare("SELECT FirstName, LastName, StudID FROM student WHERE StudID = :student_id");
            $getStmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $getStmt->execute();
            $studentData = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            // Disable foreign key checks
            $conn->exec("SET FOREIGN_KEY_CHECKS = 0");

            // Delete student record from the real table
            $stmt = $conn->prepare("DELETE FROM student WHERE StudID = :student_id");
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->execute();

            // Enable foreign key checks again
            $conn->exec("SET FOREIGN_KEY_CHECKS = 1");

            if ($stmt->rowCount() > 0) {
                // Log activity
                if ($studentData) {
                    logActivity('Student Deleted', "Student account deleted - ID: $student_id, Name: {$studentData['FirstName']} {$studentData['LastName']}", $conn);
                } else {
                    logActivity('Student Deleted', "Student account deleted - ID: $student_id", $conn);
                }

                echo json_encode(['status' => 'success', 'message' => 'Student deleted successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Student not found']);
            }

        } catch (PDOException $e) {
            // Enable checks back in case of failure
            $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
            echo json_encode(['status' => 'error', 'message' => 'Error deleting student: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid student ID']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
exit;
?>
