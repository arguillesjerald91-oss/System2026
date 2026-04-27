<?php
session_start();
ob_start(); // Start output buffering to catch any errors
try {
    include __DIR__ . '/../../db.php';
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
ob_end_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$id = $_POST['user_id'] ?? null;
$create_from = $_POST['create_from'] ?? 'manual';
$studentSelect = $_POST['studentSelect'] ?? null;
$fullname = trim($_POST['fullname'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? 'user';
$newpwd = trim($_POST['password'] ?? '');
$studID = null;

// Validate required fields - only username required
if (empty($id) || empty($username)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

// If user is created from a student, auto-fill info
if ($create_from === 'student' && !empty($studentSelect)) {
    $studID = (int)$studentSelect;
    $sstmt = $conn->prepare('SELECT StudID, FirstName, LastName, EmailAddr FROM student WHERE StudID = ?');
    $sstmt->execute([$studID]);
    $student = $sstmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        $fullname = trim($student['FirstName'] . ' ' . $student['LastName']);
        // Force username to be StudID for students
        $username = (string)$student['StudID'];
        $email = $email ?: $student['EmailAddr'];
        $role = 'student';
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Student not found']);
        exit;
    }
}

try {
    // Build update parameters - users table only has Username, Password, Role
    $params = [$username, $role];
    $sql = 'UPDATE users SET Username=?, Role=?';

    // Only update password if a new one is provided
    if (!empty($newpwd)) {
        $hash = password_hash($newpwd, PASSWORD_DEFAULT);
        $sql .= ', Password=?';
        $params[] = $hash;
    }

    $sql .= ' WHERE UserID=?';
    $params[] = $id;

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['status' => 'success', 'message' => 'User updated successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update user: ' . $e->getMessage()]);
}
exit;
