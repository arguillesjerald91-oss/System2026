<?php
/**
 * System Activity Logger
 * Logs all admin activities to the system_logs table
 */

function logActivity($logType, $message, $conn = null) {
    try {
        // Use global connection if not provided
        if ($conn === null) {
            global $database;
            if (!isset($database)) {
                include_once __DIR__ . '/../db.php';
                $database = new Database();
            }
            $conn = $database->getConnection();
        }

        // Get current user info from session
        $username = $_SESSION['userName'] ?? 'System';
        
        // Check if system_logs table exists
        $checkTable = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $checkTable->execute(['portal_logs']);
        
        if ($checkTable->fetchColumn() > 0) {
            // Insert log entry
            $stmt = $conn->prepare("INSERT INTO portal_logs (log_type, message, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$logType, $message]);
            return true;
        }
    } catch (Exception $e) {
        // Silently fail - don't break the application if logging fails
        error_log("Activity logging error: " . $e->getMessage());
    }
    return false;
}

?>
