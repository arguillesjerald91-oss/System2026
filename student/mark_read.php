<?php
session_start();
include 'db.php';

$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['userId']) || $_SESSION['userRole'] !== 'student') {
    exit;
}

$studentId = $_SESSION['userId'];
$type = $_POST['type'] ?? '';
$id = $_POST['id'] ?? '';
$subjectCode = $_POST['subject_code'] ?? '';

if ($type && $id) {
    try {
        switch ($type) {
            case 'notice':
                $conn->exec("CREATE TABLE IF NOT EXISTS notice_reads (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    notice_id VARCHAR(50),
                    StudID VARCHAR(50),
                    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_read (notice_id, StudID)
                )");
                $stmt = $conn->prepare("INSERT IGNORE INTO notice_reads (notice_id, StudID, read_at) VALUES (?, ?, NOW())");
                $stmt->execute([$id, $studentId]);
                break;
                
            case 'grade':
                $conn->exec("CREATE TABLE IF NOT EXISTS grade_reads (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    grade_id VARCHAR(50),
                    StudID VARCHAR(50),
                    subject_code VARCHAR(50),
                    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_read (grade_id, StudID, subject_code)
                )");
                $stmt = $conn->prepare("INSERT IGNORE INTO grade_reads (grade_id, StudID, subject_code, read_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$id, $studentId, $subjectCode]);
                break;
                
            case 'payment':
                $conn->exec("CREATE TABLE IF NOT EXISTS payment_reads (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    payment_id VARCHAR(50),
                    student_id VARCHAR(50),
                    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_read (payment_id, student_id)
                )");
                $stmt = $conn->prepare("INSERT IGNORE INTO payment_reads (payment_id, student_id, read_at) VALUES (?, ?, NOW())");
                $stmt->execute([$id, $studentId]);
                break;
                
            case 'tuition_fee':
                $conn->exec("CREATE TABLE IF NOT EXISTS tuition_reads (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    fee_id VARCHAR(50),
                    student_id VARCHAR(50),
                    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_read (fee_id, student_id)
                )");
                $stmt = $conn->prepare("INSERT IGNORE INTO tuition_reads (fee_id, student_id, read_at) VALUES (?, ?, NOW())");
                $stmt->execute([$id, $studentId]);
                break;
                
            case 'schedule':
                $conn->exec("CREATE TABLE IF NOT EXISTS schedule_reads (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    schedule_id VARCHAR(50),
                    student_id VARCHAR(50),
                    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_read (schedule_id, student_id)
                )");
                $stmt = $conn->prepare("INSERT IGNORE INTO schedule_reads (schedule_id, student_id, read_at) VALUES (?, ?, NOW())");
                $stmt->execute([$id, $studentId]);
                break;
                
            case 'course':
                $conn->exec("CREATE TABLE IF NOT EXISTS subject_reads (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    subject_id VARCHAR(50),
                    student_id VARCHAR(50),
                    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_read (subject_id, student_id)
                )");
                $stmt = $conn->prepare("INSERT IGNORE INTO subject_reads (subject_id, student_id, read_at) VALUES (?, ?, NOW())");
                $stmt->execute([$id, $studentId]);
                break;
        }
        echo "success";
    } catch (Exception $e) {
        error_log("Mark read error: " . $e->getMessage());
        echo "error";
    }
}
?>