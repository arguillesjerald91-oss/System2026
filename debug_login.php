<?php
/**
 * Debug Login Issue
 */

include __DIR__ . '/db.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Debug: Login System Check</h2>";

// Check users table
echo "<h3>Users in database:</h3>";
$stmt = $conn->query("SELECT user_id, username, email, user_type, status, twofa_enabled FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Type</th><th>Status</th><th>2FA</th></tr>";
foreach ($users as $u) {
    echo "<tr>";
    echo "<td>" . $u['user_id'] . "</td>";
    echo "<td>" . $u['username'] . "</td>";
    echo "<td>" . ($u['email'] ?? 'NULL') . "</td>";
    echo "<td>" . $u['user_type'] . "</td>";
    echo "<td>" . $u['status'] . "</td>";
    echo "<td>" . ($u['twofa_enabled'] ?? 0) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check if twofa_enabled column exists
echo "<h3>Checking database structure:</h3>";
try {
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'twofa_enabled'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($col) {
        echo "<p style='color: green;'>✓ twofa_enabled column exists</p>";
    } else {
        echo "<p style='color: red;'>✗ twofa_enabled column missing</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Check twofa_codes table
try {
    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM twofa_codes");
    $cnt = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>TwoFA codes table: " . $cnt['cnt'] . " records</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>twofa_codes table missing</p>";
}

// Test password verification
echo "<h3>Test password hash:</h3>";
$test_password = 'password123';
$hash = '$2y$10$ibmTluGyIUvfggkdF1hHq.13oLbD877Zjgs6td0edQsII3T2Gq.PS';
if (password_verify($test_password, $hash)) {
    echo "<p style='color: green;'>✓ Password 'password123' is correct</p>";
} else {
    echo "<p style='color: red;'>✗ Password verification failed</p>";
}

echo "<h3>Test Credentials:</h3>";
echo "<p><strong>Username:</strong> admin</p>";
echo "<p><strong>Password:</strong> password123</p>";

echo "<p><a href='login/index.php'>Go to Login</a></p>";