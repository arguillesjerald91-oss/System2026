<?php
/**
 * Add User Script
 * 
 * This script creates user accounts for the portal system.
 * For STUDENT users: Username = StudID (from student table)
 * This allows students to log in with their Student ID as username.
 * 
 * The login process uses the Username field to link back to the student record.
 */
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');

try {
    // Include database
    $dbPath = __DIR__ . '/../../db.php';
    if (!file_exists($dbPath)) {
        throw new Exception('Database config file not found');
    }
    include $dbPath;
    
    // Include log activity if available
    $logPath = __DIR__ . '/../log_activity.php';
    if (file_exists($logPath)) {
        include_once $logPath;
    }

    if (!class_exists('Database')) {
        throw new Exception('Database class not found');
    }

    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Failed to connect to database');
    }

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Connection error: ' . $e->getMessage()]);
    exit;
}

try {
    ob_end_clean();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
        exit;
    }

    $create_from = $_POST['create_from'] ?? 'manual';
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $password = trim($_POST['password'] ?? '');
    $studID = null;

    // If creating from student, fetch student record
    if ($create_from === 'student' && !empty($_POST['studentSelect'])) {
        $studID = (int) $_POST['studentSelect'];
        $sstmt = $conn->prepare('SELECT StudID, SchoolID, FirstName, LastName, EmailAddr FROM student WHERE StudID = ?');
        $sstmt->execute([$studID]);
        $student = $sstmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            $fullname = trim($student['FirstName'] . ' ' . $student['LastName']);
            // Force username to be SchoolID for students, fallback to StudID if SchoolID is empty
            $username = (string)($student['SchoolID'] ?: $student['StudID']);
            $email = $email ?: $student['EmailAddr'];
            $role = 'student';
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Student not found']);
            exit;
        }
    }

    // Validate required fields - only username and password required for users table
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required']);
        exit;
    }

    // Check for duplicate username
    $chk = $conn->prepare('SELECT COUNT(*) as cnt FROM users WHERE Username = ?');
    if (!$chk) {
        throw new Exception('Failed to prepare duplicate check query');
    }
    $chk->execute([$username]);
    $result = $chk->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['cnt'] > 0) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
        exit;
    }

    // For student users, verify the StudID exists in student table
    if ($role === 'student' && $studID) {
        $verifyStud = $conn->prepare('SELECT COUNT(*) as cnt FROM student WHERE StudID = ?');
        if (!$verifyStud) {
            throw new Exception('Failed to prepare student verification query');
        }
        $verifyStud->execute([$studID]);
        $studResult = $verifyStud->fetch(PDO::FETCH_ASSOC);
        
        if (!$studResult || $studResult['cnt'] == 0) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Student not found in database']);
            exit;
        }
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert into users table
    $ins = $conn->prepare('INSERT INTO users (Username, Password, Role) VALUES (?, ?, ?)');
    if (!$ins) {
        throw new Exception('Failed to prepare insert query');
    }
    
    if (!$ins->execute([$username, $hash, $role])) {
        throw new Exception('Failed to execute insert query');
    }
    
    // Log activity if function exists
    if (function_exists('logActivity')) {
        logActivity('User Created', "New user created - Username: $username, Role: $role" . ($studID ? ", StudID: $studID" : ""), $conn);
    }
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success', 
        'message' => 'User created successfully',
        'password' => $password
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
exit;
?>
