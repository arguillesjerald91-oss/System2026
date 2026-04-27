<?php
session_start();
include __DIR__ . '/../db.php';
include_once __DIR__ . '/../log_activity.php';
$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $course     = trim($_POST['course']);
    $year_level = trim($_POST['year_level']);
    $semester   = trim($_POST['semester']); 
    $status     = trim($_POST['status']);

    // Update student record using the real schema
    $sql = "UPDATE student 
            SET FirstName = ?, 
                LastName = ?, 
                EmailAddr = ?, 
                PhoneNo = ?, 
                Course = ?, 
                YearLvl = ?, 
                Semester = ?
            WHERE StudID = ?";
    
    $stmt = $conn->prepare($sql);
    $executed = $stmt->execute([
        $first_name, 
        $last_name, 
        $email, 
        $phone, 
        $course, 
        $year_level,
        $semester, 
        $student_id
    ]);

    if ($executed) {
        // Log activity
        logActivity('Student Updated', "Student information updated - ID: $student_id, Name: $first_name $last_name, Course: $course, Year: $year_level, Semester: $semester", $conn);
        
        header("Location: ../manage_students.php?updated=1");
        exit;
    } else {
        header("Location: ../manage_students.php?error=Update failed");
        exit;
    }
}
?>
