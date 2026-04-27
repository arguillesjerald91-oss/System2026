<?php
/**
 * This script creates user accounts for all students in the student table
 * who don't already have a user account.
 * 
 * Username and Password will be set to their School ID
 */

session_start();
include 'db.php';
include_once __DIR__ . '/log_activity.php';

$database = new Database();
$conn = $database->getConnection();

// Check if user is admin
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
    die('Unauthorized access');
}

try {
    $conn->beginTransaction();
    
    // Get all students who don't have a user account yet
    $sql = "SELECT s.StudID, s.SchoolID, s.FirstName, s.LastName, s.EmailAddr 
            FROM student s
            LEFT JOIN users u ON s.SchoolID = u.Username
            WHERE u.UserID IS NULL AND s.SchoolID IS NOT NULL AND s.SchoolID != ''";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $studentsWithoutAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $created = 0;
    $errors = 0;
    $errorMessages = [];
    
    foreach ($studentsWithoutAccounts as $student) {
        try {
            $schoolID = $student['SchoolID'];
            $studID = $student['StudID'];
            
            // Create user account with School ID as both username and password
            $hashedPassword = password_hash($schoolID, PASSWORD_DEFAULT);
            
            $insertUser = $conn->prepare("INSERT INTO users (Username, Password, Role, Status) VALUES (?, ?, 'student', 'active')");
            $insertUser->execute([$schoolID, $hashedPassword]);
            $userID = $conn->lastInsertId();
            
            // Update student record to link to user account
            $updateStudent = $conn->prepare("UPDATE student SET user_id = ? WHERE StudID = ?");
            $updateStudent->execute([$userID, $studID]);
            
            $created++;
            
        } catch (Exception $e) {
            $errors++;
            $errorMessages[] = "Error creating account for {$student['FirstName']} {$student['LastName']} (School ID: {$student['SchoolID']}): " . $e->getMessage();
            error_log("Sync Error for student {$student['SchoolID']}: " . $e->getMessage());
        }
    }
    
    $conn->commit();
    
    // Log the activity
    if ($created > 0) {
        logActivity('Student Accounts Synced', "Created $created user accounts for existing students", $conn);
    }
    
    // Redirect with results
    if ($errors > 0) {
        $errorMsg = "$created accounts created successfully. $errors errors occurred.";
        header("Location: manage_students.php?synced=$created&sync_errors=$errors");
    } else {
        header("Location: manage_students.php?synced=$created");
    }
    exit;
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Sync Error: " . $e->getMessage());
    header("Location: manage_students.php?error=sync_failed");
    exit;
}
?>
