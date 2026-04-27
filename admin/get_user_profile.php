<?php
session_start();
include 'db.php';

$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'not_logged_in']);
    exit;
}

$userId = $_SESSION['userId'];

try {
    $sql = "SELECT Username, Email FROM users WHERE UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'success' => true,
            'username' => $user['Username'],
            'email' => $user['Email']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
