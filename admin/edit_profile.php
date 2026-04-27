<?php
session_start();
include 'db.php';

$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['userId'])) {
    echo "not_logged_in";
    exit;
}

$userId = $_SESSION['userId'];
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');

// Validate required fields
if (empty($username) || empty($email)) {
    echo "Username and email are required";
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email format";
    exit;
}

$avatarPath = null;

// Handle avatar upload
if (isset($_FILES['avatar']) && isset($_FILES['avatar']['error']) && $_FILES['avatar']['error'] == 0) {
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($_FILES['avatar']['size'] > $maxSize) {
        echo "File size exceeds 5MB";
        exit;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
        echo "Invalid file type. Only JPG, PNG, GIF, WEBP are allowed";
        exit;
    }

    $targetDir = __DIR__ . "/../uploads/avatars/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $fileName = time() . "_" . basename($_FILES['avatar']['name']);
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
        // store path relative to project root so it can be used in src attributes
        $avatarPath = 'uploads/avatars/' . $fileName;
    } else {
        echo "Failed to upload avatar";
        exit;
    }
}

// Update database using PDO
try {
    if ($avatarPath) {
        $sql = "UPDATE users SET Username = ?, Email = ?, avatar = ? WHERE UserID = ?";
        $stmt = $conn->prepare($sql);
        $ok = $stmt->execute([$username, $email, $avatarPath, $userId]);
    } else {
        $sql = "UPDATE users SET Username = ?, Email = ? WHERE UserID = ?";
        $stmt = $conn->prepare($sql);
        $ok = $stmt->execute([$username, $email, $userId]);
    }

    echo $ok ? 'success' : 'error';
} catch (PDOException $e) {
    echo 'error: ' . $e->getMessage();
}
?>
