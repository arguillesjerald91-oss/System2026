<?php
/**
 * Enable 2FA for test users
 */

include __DIR__ . '/db.php';

$db = new Database();
$conn = $db->getConnection();

// Enable 2FA for all users
$conn->exec("UPDATE users SET twofa_enabled = 1 WHERE email IS NOT NULL");

echo "<h2>2FA Enabled for All Users</h2>";
echo "<p>All users now require 2FA verification.</p>";
echo "<p><a href='login/index.php'>Go to Login</a></p>";