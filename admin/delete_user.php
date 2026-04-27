<?php 
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['userId'])) {
    header("Location: ../login.php");
    exit();
}
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if ($userType !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$userId = $_GET['id'] ?? 0;
if (!$userId) {
    header("Location: manage_students.php");
    exit();
}

if ($userId == $_SESSION['user_id']) {
    die("You cannot delete your own account");
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

$stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
$stmt->execute([$userId]);

$stmt = $conn->prepare("DELETE FROM student WHERE user_id = ?");
$stmt->execute([$userId]);

header("Location: manage_students.php?deleted=1");
exit();