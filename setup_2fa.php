<?php
/**
 * Add 2FA columns and enable for users
 */

include __DIR__ . '/db.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Setting up 2FA</h2>";

// Add columns to users table
try {
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS twofa_enabled TINYINT(1) DEFAULT 0");
    echo "<p style='color: green;'>✓ Added twofa_enabled column</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>Note: " . $e->getMessage() . "</p>";
}

try {
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS twofa_secret VARCHAR(50) DEFAULT NULL");
    echo "<p style='color: green;'>✓ Added twofa_secret column</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>Note: " . $e->getMessage() . "</p>";
}

// Create twofa_codes table
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS twofa_codes (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        code VARCHAR(10) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p style='color: green;'>✓ Created twofa_codes table</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>Note: " . $e->getMessage() . "</p>";
}

// Enable 2FA for users with valid emails
try {
    $conn->exec("UPDATE users SET twofa_enabled = 1 WHERE email IS NOT NULL AND email != ''");
    echo "<p style='color: green;'>✓ Enabled 2FA for all users with email</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h3 style='color: green;'>2FA Setup Complete!</h3>";
echo "<p>Login credentials are:</p>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Username</th><th>Password</th><th>2FA</th></tr>";
echo "<tr><td>admin</td><td>password123</td><td>Enabled</td></tr>";
echo "<tr><td>student1</td><td>password123</td><td>Enabled</td></tr>";
echo "<tr><td>trainee1</td><td>password123</td><td>Enabled</td></tr>";
echo "<tr><td>instructor1</td><td>password123</td><td>Enabled</td></tr>";
echo "<tr><td>unit1</td><td>password123</td><td>Enabled</td></tr>";
echo "<tr><td>support1</td><td>password123</td><td>Enabled</td></tr>";
echo "</table>";

echo "<p><a href='login/index.php'>Go to Login</a></p>";