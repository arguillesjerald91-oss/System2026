<?php
/**
 * Show stored password hashes
 */

include __DIR__ . '/db.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Stored Password Hashes</h2>";

$stmt = $conn->query("SELECT username, password FROM users WHERE username IN ('admin', 'student', 'instructor', 'trainee', 'instructional', 'support')");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $u) {
    echo "<p><strong>" . $u['username'] . ":</strong><br>" . $u['password'] . "</p>";
    echo "<textarea style='width:100%'>UPDATE users SET password = '" . $u['password'] . "' WHERE username = '" . $u['username'] . "';</textarea><br><br>";
}

// Generate correct hashes for password123
echo "<h3>Generate Correct Hashes</h3>";
$password = 'password123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "<p><strong>Hash for 'password123':</strong></p>";
echo "<code>" . $hash . "</code>";

echo "<h3>Quick Fix Script - Reset Passwords</h3>";
echo "<form method='POST'>";
echo "<button type='submit' style='padding: 10px 20px; background: #3b82f6; color: white; border: none; cursor: pointer;'>Reset All Passwords to password123</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_hash = password_hash('password123', PASSWORD_DEFAULT);
    $conn->exec("UPDATE users SET password = '$new_hash'");
    echo "<p style='color: green;'>✓ All passwords reset to 'password123'</p>";
}

echo "<p><a href='login/index.php'>Go to Login</a></p>";