<?php
session_start();
include 'db.php';

// Log the logout if user was logged in
if (isset($_SESSION['user_id'])) {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        try {
            $logStmt = $conn->prepare("
                INSERT INTO access_logs (user_id, access_type, resource_type, access_action, access_timestamp, access_status)
                VALUES (?, 'Logout', 'System', 'User Logout', NOW(), 'Success')
            ");
            $logStmt->execute([$_SESSION['user_id']]);
        } catch (PDOException $e) {
            // Log error but continue with logout
            error_log("Logout logging error: " . $e->getMessage());
        }
    }
}

// Destroy all session data
session_unset();
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header('Location: login/index.php');
exit;
?>
