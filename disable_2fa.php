<?php
/**
 * Disable 2FA for simpler login
 */

include __DIR__ . '/db.php';

$db = new Database();
$conn = $db->getConnection();

// Disable 2FA for all users
$conn->exec("UPDATE users SET twofa_enabled = 0");

echo "✓ 2FA disabled for all users";
echo "<p>Now you can login with just username and password.</p>";
echo "<p><a href='login/index.php'>Go to Login</a></p>";