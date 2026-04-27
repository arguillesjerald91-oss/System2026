<?php
// Ensure session and DB are available for dynamic assets across student pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/../db.php';

// Normalize role terminology: 'student' → 'trainee'
if (isset($_SESSION['userRole']) && $_SESSION['userRole'] === 'student') {
    $_SESSION['userRole'] = 'trainee';
}

try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Throwable $e) {
    $conn = null;
}

// Expose a shared avatar path for pages that want it
$studentAvatarPath = "../images/admin.png"; // default
if (!empty($_SESSION['userId']) && $conn) {
    $sid = $_SESSION['userId'];
    try {
        $stmt = $conn->prepare("SELECT avatar FROM users WHERE user_id = ?");
        $stmt->execute([$sid]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!empty($u['avatar'])) {
            $studentAvatarPath = "../" . ltrim($u['avatar'], '/');
        }
    } catch (Throwable $e) {
        // Keep default on any error
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TESDA Trainee Portal</title>
    <link rel="stylesheet" href="css/student.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container">


  