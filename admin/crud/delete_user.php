<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();

header('Content-Type: application/json');

try {
    include __DIR__ . '/../../db.php';
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
ob_end_clean();

$id = $_POST['user_id'] ?? ($_GET['delete_user'] ?? null);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'No user ID provided']);
    exit;
}

try {
    $stmt = $conn->prepare('DELETE FROM users WHERE UserID = ?');
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete user: ' . $e->getMessage()]);
}
exit;