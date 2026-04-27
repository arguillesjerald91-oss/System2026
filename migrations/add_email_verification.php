<?php
/**
 * Database Migration: Add Email Verification
 * Adds columns for email verification to users table
 */

include __DIR__ . '/../db.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Email Verification Database Migration</h2>";

try {
    // Add columns to users table if they don't exist
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) DEFAULT 0");
    echo "<p style='color: green;'>✓ Added email_verified column</p>";
    
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL DEFAULT NULL");
    echo "<p style='color: green;'>✓ Added email_verified_at column</p>";
    
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) DEFAULT NULL");
    echo "<p style='color: green;'>✓ Added verification_token column</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: orange;'>Note: " . $e->getMessage() . "</p>";
}

// Create email_verifications table
echo "<h3>Creating email_verifications table</h3>";
try {
    $sql = "CREATE TABLE IF NOT EXISTS email_verifications (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        email VARCHAR(100) NOT NULL,
        token VARCHAR(255) NOT NULL,
        token_type ENUM('verification', 'login_verification') DEFAULT 'verification',
        expires_at TIMESTAMP NOT NULL,
        used_at TIMESTAMP NULL DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_user_id (user_id),
        INDEX idx_token (token),
        INDEX idx_token_type (token_type),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->exec($sql);
    echo "<p style='color: green;'>✓ email_verifications table created</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: orange;'>Note: " . $e->getMessage() . "</p>";
}

// Create login_notifications table for tracking notification preferences
echo "<h3>Creating login_notifications table</h3>";
try {
    $sql = "CREATE TABLE IF NOT EXISTS login_notifications (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        notification_type ENUM('login_alert', 'password_change', 'email_change', 'security_alert') NOT NULL,
        method ENUM('email', 'sms', 'both') DEFAULT 'email',
        is_enabled TINYINT(1) DEFAULT 1,
        last_notified_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_user_id (user_id),
        INDEX idx_notification_type (notification_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->exec($sql);
    echo "<p style='color: green;'>✓ login_notifications table created</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: orange;'>Note: " . $e->getMessage() . "</p>";
}

// Update existing users to have email_verified = 1 (for backwards compatibility)
echo "<h3>Updating existing users</h3>";
try {
    // Update users with verified emails to have email_verified = 1
    $conn->exec("UPDATE users SET email_verified = 1 WHERE email IS NOT NULL AND email != ''");
    echo "<p style='color: green;'>✓ Updated existing users</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>Note: " . $e->getMessage() . "</p>";
}

echo "<h3 style='color: green;'>Migration completed successfully!</h3>";
echo "<p><a href='../login/index.php'>Go to Login</a></p>";