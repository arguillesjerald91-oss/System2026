<?php
session_start();
include __DIR__ . '/../db.php';
include_once __DIR__ . '/../log_activity.php';
$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $school_id  = trim($_POST['school_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $course     = trim($_POST['course'] ?? '');
    $semester   = trim($_POST['semester'] ?? 'Not Enrolled');

    // ✅ Validation
    if (
        empty($school_id) || empty($first_name) || empty($last_name) ||
        empty($email)
    ) {
        header("Location: ../manage_students.php?error=All required fields must be filled");
        exit;
    }

    // ✅ Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../manage_students.php?error=Invalid email format");
        exit;
    }

    // ✅ Check if SchoolID already exists
    $checkSchool = $conn->prepare("SELECT StudID FROM student WHERE SchoolID = ?");
    $checkSchool->execute([$school_id]);
    if ($checkSchool->rowCount() > 0) {
        header("Location: ../manage_students.php?error=School ID already exists");
        exit;
    }

    // ✅ Check if username (SchoolID) already exists in users table
    $checkUser = $conn->prepare("SELECT UserID FROM users WHERE Username = ?");
    $checkUser->execute([$school_id]);
    if ($checkUser->rowCount() > 0) {
        header("Location: ../manage_students.php?error=A user account with this School ID already exists");
        exit;
    }

    try {
        $conn->beginTransaction();

        // ✅ Automatically create user account using SchoolID as username and password
        $hashedPassword = password_hash($school_id, PASSWORD_DEFAULT);
        $sqlUser = "INSERT INTO users (Username, Password, Role, Status) 
                    VALUES (?, ?, 'student', 'active')";
        $stmtUser = $conn->prepare($sqlUser);
        if (!$stmtUser->execute([$school_id, $hashedPassword])) {
            throw new Exception("Failed to create user account");
        }
        $user_id = $conn->lastInsertId();

        // ✅ Insert student details into the real student table (StudID auto-increment) - detect columns dynamically
        $colsStmt = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student' ORDER BY ORDINAL_POSITION");
        $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
        $firstNameCol = in_array('FirstName', $columns) ? 'FirstName' : (in_array('FName', $columns) ? 'FName' : 'FirstName');
        $lastNameCol = in_array('LastName', $columns) ? 'LastName' : (in_array('LName', $columns) ? 'LName' : 'LastName');
        $emailCol = in_array('EmailAddr', $columns) ? 'EmailAddr' : (in_array('Email', $columns) ? 'Email' : 'EmailAddr');
        
        $insertCols = ['SchoolID', $firstNameCol, $lastNameCol, $emailCol, 'user_id'];
        $insertVals = [$school_id, $first_name, $last_name, $email, $user_id];
        if (in_array('PhoneNo', $columns)) { $insertCols[] = 'PhoneNo'; $insertVals[] = $phone; }
        if (in_array('Phone', $columns)) { $insertCols[] = 'Phone'; $insertVals[] = $phone; }
        if (in_array('Course', $columns)) { $insertCols[] = 'Course'; $insertVals[] = $course; }
        if (in_array('YearLvl', $columns)) { $insertCols[] = 'YearLvl'; $insertVals[] = $year_level; }
        
        $sqlStudent = "INSERT INTO student (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', array_fill(0, count($insertVals), '?')) . ")";
        $stmtStudent = $conn->prepare($sqlStudent);
        if (!$stmtStudent->execute($insertVals)) {
            throw new Exception("Failed to create student record");
        }

        // ✅ Log the action
        logActivity("Student Added", "$first_name $last_name (School ID: $school_id) was added with auto-generated user account ($course, $year_level)", $conn);

        $conn->commit();

        header("Location: ../manage_students.php?added=1");
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Add Student Error: " . $e->getMessage());
        header("Location: ../manage_students.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}
?>
