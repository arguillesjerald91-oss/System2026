<?php
/**
 * Check Database Structure
 * Examine current database to understand user types and structure
 */

include 'db.php';

echo "<h2>Database Structure Analysis</h2>";

$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    echo "<p style='color: red;'>ERROR: Could not connect to database!</p>";
    exit();
}

// Check users table
echo "<h3>Users Table Analysis</h3>";
$stmt = $conn->prepare("SHOW TABLES LIKE 'users'");
$stmt->execute();
$users_exists = $stmt->rowCount() > 0;

if ($users_exists) {
    echo "<p style='color: green;'>&#10004; Users table exists</p>";
    
    $stmt = $conn->prepare("DESCRIBE users");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Current Users Table Structure:</h4>";
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td><strong>{$column['Field']}</strong></td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check current user types
    $stmt = $conn->prepare("SELECT DISTINCT user_type FROM users");
    $stmt->execute();
    $user_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h4>Current User Types:</h4>";
    echo "<ul>";
    foreach ($user_types as $type) {
        echo "<li style='color: green;'>&#10004; $type</li>";
    }
    echo "</ul>";
    
    // Show current users
    $stmt = $conn->prepare("SELECT user_id, username, user_type, status FROM users ORDER BY user_id");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Current Users:</h4>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>Type</th><th>Status</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['user_id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['user_type']}</td>";
        echo "<td>{$user['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} else {
    echo "<p style='color: red;'>&#10008; Users table does not exist</p>";
}

// Check other relevant tables
echo "<h3>Other Relevant Tables</h3>";
$stmt = $conn->prepare("SHOW TABLES");
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$relevant_tables = ['student', 'training_modules', 'access_levels', 'user_access_assignments'];
echo "<h4>System Tables Status:</h4>";
foreach ($relevant_tables as $table) {
    if (in_array($table, $tables)) {
        echo "<p style='color: green;'>&#10004; $table</p>";
    } else {
        echo "<p style='color: red;'>&#10008; $table (MISSING)</p>";
    }
}

echo "<h3>Required User Types for System</h3>";
echo "<ul>";
echo "<li><strong>Admin</strong> - System administration</li>";
echo "<li><strong>Trainees/Students</strong> - Training participants</li>";
echo "<li><strong>Instructional Unit</strong> - Training management</li>";
echo "<li><strong>Instructors</strong> - Course instructors</li>";
echo "<li><strong>Support Staff</strong> - Administrative support</li>";
echo "</ul>";

echo "<h3>Next Steps</h3>";
echo "<p>Based on the analysis, I will create a comprehensive login system that supports all required user types.</p>";
?>
