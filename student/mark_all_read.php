<?php
session_start();
include 'db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['userId']) || $_SESSION['userRole'] !== 'student') {
    echo "error";
    exit;
}

$studentId = $_SESSION['userId'];

try {
    // 1) Mark all notices as read
    $conn->exec("CREATE TABLE IF NOT EXISTS notice_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notice_id VARCHAR(50),
        StudID VARCHAR(50),
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_read (notice_id, StudID)
    )");
    
    $stmtNotices = $conn->prepare("SELECT notice_id FROM notices");
    $stmtNotices->execute();
    $notices = $stmtNotices->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($notices as $notice) {
        $insertStmt = $conn->prepare("INSERT IGNORE INTO notice_reads (notice_id, StudID, read_at) VALUES (?, ?, NOW())");
        $insertStmt->execute([$notice['notice_id'], $studentId]);
    }
    
    // 2) Mark all grades as read
    $conn->exec("CREATE TABLE IF NOT EXISTS grade_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grade_id VARCHAR(50),
        StudID VARCHAR(50),
        subject_code VARCHAR(50),
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_read (grade_id, StudID, subject_code)
    )");
    
    $stmtGrades = $conn->prepare("SELECT grade_id, subject_code FROM grades WHERE StudID = ?");
    $stmtGrades->execute([$studentId]);
    $grades = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($grades as $grade) {
        $insertGrade = $conn->prepare("INSERT IGNORE INTO grade_reads (grade_id, StudID, subject_code, read_at) VALUES (?, ?, ?, NOW())");
        $insertGrade->execute([$grade['grade_id'], $studentId, $grade['subject_code']]);
    }
    
    // 3) Mark all courses/subjects as read
    $stmtSubjects = $conn->prepare("SELECT DISTINCT SubjectID FROM student_subjects WHERE StudID = ?");
    $stmtSubjects->execute([$studentId]);
    $subjects = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($subjects as $subject) {
        $insertSubject = $conn->prepare("INSERT IGNORE INTO subject_reads (subject_id, student_id, read_at) VALUES (?, ?, NOW())");
        $insertSubject->execute([$subject['SubjectID'], $studentId]);
    }
    
    // 4) Mark all tuition fees as read
    $stmtTuition = $conn->prepare("SELECT fee_id FROM tuition_fees WHERE StudID = ?");
    $stmtTuition->execute([$studentId]);
    $tuitions = $stmtTuition->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tuitions as $tuition) {
        $insertTuition = $conn->prepare("INSERT IGNORE INTO tuition_reads (fee_id, student_id, read_at) VALUES (?, ?, NOW())");
        $insertTuition->execute([$tuition['fee_id'], $studentId]);
    }
    
    // 5) Mark all payments as read
    $stmtPayments = $conn->prepare("SELECT payment_id FROM payments WHERE StudID = ? AND receipt_number LIKE 'INV%'");
    $stmtPayments->execute([$studentId]);
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($payments as $payment) {
        $insertPayment = $conn->prepare("INSERT IGNORE INTO payment_reads (payment_id, student_id, read_at) VALUES (?, ?, NOW())");
        $insertPayment->execute([$payment['payment_id'], $studentId]);
    }
    
    // 6) Mark all schedules as read for this student
    $conn->exec("CREATE TABLE IF NOT EXISTS schedule_reads (
      id INT AUTO_INCREMENT PRIMARY KEY,
      schedule_id VARCHAR(50),
      student_id VARCHAR(50),
      read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY unique_read (schedule_id, student_id)
    )");
    
    if (class_exists('PDO')) {
        $stmtSched = $conn->prepare("SELECT s.schedule_id 
                                     FROM student_schedules ss 
                                     JOIN schedules s ON ss.schedule_id = s.schedule_id 
                                     WHERE ss.StudID = ?");
        $stmtSched->execute([$studentId]);
        $scheds = $stmtSched->fetchAll(PDO::FETCH_ASSOC);
        foreach ($scheds as $s) {
            $ins = $conn->prepare("INSERT IGNORE INTO schedule_reads (schedule_id, student_id, read_at) VALUES (?, ?, NOW())");
            $ins->execute([$s['schedule_id'], $studentId]);
        }
    }
    
    echo "success";
} catch (Exception $e) {
    error_log("Mark all read error: " . $e->getMessage());
    echo "error";
}
?>