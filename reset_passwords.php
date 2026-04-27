<?php
/**
 * Reset all passwords to password123
 */

include __DIR__ . '/db.php';

$db = new Database();
$conn = $db->getConnection();

$new_hash = password_hash('password123', PASSWORD_DEFAULT);
$conn->exec("UPDATE users SET password = '$new_hash'");

echo "✓ All passwords reset to 'password123'";
echo "<p>Hash: " . $new_hash . "</p>";
echo "<p><a href='login/index.php'>Go to Login</a></p>";