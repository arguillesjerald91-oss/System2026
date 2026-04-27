<?php
/**
 * Import SQL Credentials to Database
 */

include __DIR__ . '/db.php';

$db = new Database();
$conn = $db->getConnection();

// Read and execute SQL file
$sql = file_get_contents(__DIR__ . '/test_users_credentials.sql');

// Remove comments for execution
$lines = explode("\n", $sql);
$clean_sql = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    if (!empty($trimmed) && substr($trimmed, 0, 2) !== '--') {
        $clean_sql[] = $line;
    }
}

$final_sql = implode("\n", $clean_sql);

// Execute each INSERT statement
try {
    $conn->exec($final_sql);
    echo "<h2 style='color: green;'>✓ Credentials imported successfully!</h2>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>Note: " . $e->getMessage() . "</p>";
}

echo "<h3>Login Credentials</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Username</th><th>Password</th><th>User Type</th></tr>";
echo "<tr><td>admin</td><td>password123</td><td>Admin</td></tr>";
echo "<tr><td>student1</td><td>password123</td><td>Student</td></tr>";
echo "<tr><td>trainee1</td><td>password123</td><td>Trainee</td></tr>";
echo "<tr><td>instructor1</td><td>password123</td><td>Instructor</td></tr>";
echo "<tr><td>unit1</td><td>password123</td><td>Instructional Unit</td></tr>";
echo "<tr><td>support1</td><td>password123</td><td>Support Staff</td></tr>";
echo "</table>";

echo "<p><a href='login/index.php'>Go to Login</a></p>";