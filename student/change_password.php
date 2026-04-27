<?php
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../db.php';

$database = new Database();
$conn = $database->getConnection();

$userId = $_SESSION['userId'] ?? null;
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($userId)) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Validate passwords
if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['status' => 'error', 'message' => 'New passwords do not match']);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters long']);
    exit;
}

try {
    // For students: session userId is the StudID from student table
    // Get the user record by finding the SchoolID first, then matching with users table
    $studentSql = "SELECT s.SchoolID, s.user_id 
                   FROM student s 
                   WHERE s.StudID = ?";
    $studentStmt = $conn->prepare($studentSql);
    $studentStmt->execute([$userId]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['status' => 'error', 'message' => 'Student record not found']);
        exit;
    }

    // Get user account - either by user_id link or by SchoolID as Username
    $user = null;
    if (!empty($student['user_id'])) {
        // Preferred method: use the user_id link
        $sql = "SELECT UserID, Password, Role FROM users WHERE UserID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$student['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Fallback: find by SchoolID as Username
    if (!$user && !empty($student['SchoolID'])) {
        $sql2 = "SELECT UserID, Password, Role FROM users WHERE Username = ? AND Role = 'student'";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->execute([$student['SchoolID']]);
        $user = $stmt2->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User account not found. Please contact administrator.']);
        exit;
    }

    // Verify current password (supports both hashed and legacy plain-text)
    $passwordValid = password_verify($currentPassword, $user['Password']);
    if (!$passwordValid && $currentPassword === $user['Password']) {
        // Legacy plain-text password match
        $passwordValid = true;
    }

    if (!$passwordValid) {
        echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect']);
        exit;
    }

    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $updateSql = "UPDATE users SET Password = ? WHERE UserID = ?";
    $updateStmt = $conn->prepare($updateSql);
    $success = $updateStmt->execute([$hashedPassword, $user['UserID']]);
    
    if ($success) {
        echo json_encode(['status' => 'success', 'message' => 'Password changed successfully! You can now use your new password to login.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update password. Please try again.']);
    }
} catch (PDOException $e) {
    error_log("Change Password Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred. Please try again later.']);
}
exit;
?>
