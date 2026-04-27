<?php
session_start();
include 'db.php';

$database = new Database();
$conn = $database->getConnection();

$userId = $_SESSION['userId'] ?? null;
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($userId)) {
    echo "not_logged_in";
    exit;
}

// Validate passwords
if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo "All fields are required";
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo "New passwords do not match";
    exit;
}

if (strlen($newPassword) < 6) {
    echo "Password must be at least 6 characters long";
    exit;
}

try {
    // Fetch current password using PDO
    $sql = "SELECT Password FROM users WHERE UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "User not found";
        exit;
    }

    if (!password_verify($currentPassword, $user['Password'])) {
        echo "Incorrect current password";
        exit;
    }

    // Hash new password
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

    $update = $conn->prepare("UPDATE users SET Password = ? WHERE UserID = ?");
    $ok = $update->execute([$hashed, $userId]);
    echo $ok ? 'success' : 'error';
} catch (PDOException $e) {
    echo 'error: ' . $e->getMessage();
}
?>

