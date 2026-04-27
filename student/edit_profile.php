<?php
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../db.php';

$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['userId'])) {
    echo json_encode(['status' => 'error', 'message' => 'not_logged_in']);
    exit;
}

$userId = $_SESSION['userId'];
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');

// Validate required fields
if (empty($username) || empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Username and email are required']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
    exit;
}

$avatarPath = null;

// Handle avatar upload
if (isset($_FILES['avatar']) && isset($_FILES['avatar']['error']) && $_FILES['avatar']['error'] == 0) {
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($_FILES['avatar']['size'] > $maxSize) {
        echo json_encode(['status' => 'error', 'message' => 'File size exceeds 5MB']);
        exit;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, GIF, WEBP are allowed']);
        exit;
    }

    $targetDir = __DIR__ . "/../uploads/avatars/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $fileName = time() . "_" . basename($_FILES['avatar']['name']);
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
        $avatarPath = 'uploads/avatars/' . $fileName;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload avatar']);
        exit;
    }
}

// Update database using PDO
try {
    // Try resolve users row in multiple ways to handle legacy data
    $urow = null;

    // 1) Direct by users.UserID from session
    $stmt = $conn->prepare("SELECT UserID FROM users WHERE UserID = ?");
    $stmt->execute([$userId]);
    $urow = $stmt->fetch(PDO::FETCH_ASSOC);

    // Load student info for further matching if needed
    $stud = null;
    if (!$urow) {
        $stmtS = $conn->prepare("SELECT FirstName, LastName, EmailAddr FROM student WHERE StudID = ?");
        $stmtS->execute([$userId]);
        $stud = $stmtS->fetch(PDO::FETCH_ASSOC);
    }

    // 2) Username equals StudID for student role
    if (!$urow) {
        $stmt2 = $conn->prepare("SELECT UserID FROM users WHERE Role = 'student' AND Username = ?");
        $stmt2->execute([(string)$userId]);
        $urow = $stmt2->fetch(PDO::FETCH_ASSOC);
    }

    // 3) Email match between student and users
    if (!$urow && $stud && !empty($stud['EmailAddr'])) {
        $stmt3 = $conn->prepare("SELECT UserID FROM users WHERE Email = ? ORDER BY UserID DESC LIMIT 1");
        $stmt3->execute([$stud['EmailAddr']]);
        $urow = $stmt3->fetch(PDO::FETCH_ASSOC);
    }

    // 4) Name match on Fname/Lname or FullName
    if (!$urow && $stud) {
        $full = trim(($stud['FirstName'] ?? '') . ' ' . ($stud['LastName'] ?? ''));
        // Try Fname/Lname
        $stmt4 = $conn->prepare("SELECT UserID FROM users WHERE (Fname = ? AND Lname = ?) OR (TRIM(IFNULL(FullName, '')) = ?) ORDER BY UserID DESC LIMIT 1");
        $stmt4->execute([$stud['FirstName'] ?? '', $stud['LastName'] ?? '', $full]);
        $urow = $stmt4->fetch(PDO::FETCH_ASSOC);
    }

    if (!$urow) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }

    $effectiveUserId = $urow['UserID'];

    if ($avatarPath) {
        $sql = "UPDATE users SET Username = ?, Email = ?, avatar = ? WHERE UserID = ?";
        $stmtU = $conn->prepare($sql);
        $ok = $stmtU->execute([$username, $email, $avatarPath, $effectiveUserId]);
    } else {
        $sql = "UPDATE users SET Username = ?, Email = ? WHERE UserID = ?";
        $stmtU = $conn->prepare($sql);
        $ok = $stmtU->execute([$username, $email, $effectiveUserId]);
    }

    if ($ok) {
        // Fetch back the saved avatar to confirm and return the correct path
        $response = ['status' => 'success'];
        
        $fetchStmt = $conn->prepare("SELECT avatar FROM users WHERE UserID = ?");
        $fetchStmt->execute([$effectiveUserId]);
        $fetchRow = $fetchStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!empty($fetchRow['avatar'])) {
            $response['avatarPath'] = '../' . ltrim($fetchRow['avatar'], '/');
        } elseif ($avatarPath) {
            // Fallback in case fetch doesn't reflect immediately
            $response['avatarPath'] = '../' . $avatarPath;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update profile']);
    }
    exit;

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>
