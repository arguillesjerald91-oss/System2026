<?php
/**
 * Database Setup and Integration Script
 * Ensures the TESDA Auto Mechanic database is properly created and integrated
 */

include 'db.php';

echo "<h2>TESDA Auto Mechanic Database Setup</h2>";

// Step 1: Test Database Connection
echo "<h3>Step 1: Testing Database Connection</h3>";
$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    echo "<p style='color: red;'>ERROR: Could not connect to database 'tesda_auto_mechanic'</p>";
    echo "<p>Attempting to create database...</p>";
    
    // Try to create the database
    try {
        $conn = new PDO("mysql:host=localhost", "root", "");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database
        $conn->exec("CREATE DATABASE IF NOT EXISTS tesda_auto_mechanic CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        echo "<p style='color: green;'>Database 'tesda_auto_mechanic' created successfully!</p>";
        
        // Connect to the new database
        $conn = new PDO("mysql:host=localhost;dbname=tesda_auto_mechanic", "root", "");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("set names utf8");
        
    } catch(PDOException $e) {
        echo "<p style='color: red;'>ERROR creating database: " . $e->getMessage() . "</p>";
        exit();
    }
} else {
    echo "<p style='color: green;'>Successfully connected to database 'tesda_auto_mechanic'</p>";
}

// Step 2: Check if tables exist
echo "<h3>Step 2: Checking Database Tables</h3>";
$stmt = $conn->prepare("SHOW TABLES");
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($tables)) {
    echo "<p style='color: orange;'>No tables found. Need to import database schema.</p>";
    echo "<p>Please run the SQL file: tesda_auto_mechanic_integrated_system.sql</p>";
} else {
    echo "<p style='color: green;'>Found " . count($tables) . " tables in database</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
}

// Step 3: Check essential tables for the system
echo "<h3>Step 3: Checking Essential System Tables</h3>";
$essential_tables = [
    'users' => 'User authentication and management',
    'student' => 'Student information',
    'pre_enrollment_applications' => 'Pre-enrollment applications',
    'scholarship_applications' => 'Scholarship applications',
    'training_modules' => 'Training modules',
    'user_access_assignments' => 'Access management',
    'module_access_permissions' => 'Module permissions'
];

foreach ($essential_tables as $table => $description) {
    if (in_array($table, $tables)) {
        echo "<p style='color: green;'>&#10004; $table - $description</p>";
    } else {
        echo "<p style='color: red;'>&#10008; $table - $description (MISSING)</p>";
    }
}

// Step 4: Test basic database operations
echo "<h3>Step 4: Testing Database Operations</h3>";
try {
    // Test basic query
    $stmt = $conn->prepare("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'tesda_auto_mechanic'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p style='color: green;'>Database query test passed. Found {$result['table_count']} tables.</p>";
    
    // Test insert capability
    $conn->beginTransaction();
    $stmt = $conn->prepare("CREATE TABLE IF NOT EXISTS setup_test (id INT AUTO_INCREMENT PRIMARY KEY, test_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $stmt->execute();
    $stmt = $conn->prepare("INSERT INTO setup_test () VALUES ()");
    $stmt->execute();
    $conn->rollBack();
    echo "<p style='color: green;'>Database write test passed.</p>";
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>Database operation test failed: " . $e->getMessage() . "</p>";
}

// Step 5: Check system integration
echo "<h3>Step 5: Checking System Integration</h3>";

// Check if key system files exist and can connect to database
$system_files = [
    'pre_enrollment.php' => 'Pre-enrollment system',
    'scholarship_application.php' => 'Scholarship application system',
    'login.php' => 'Login system',
    'student/learning_modules.php' => 'Student learning modules',
    'admin/access_management.php' => 'Admin access management'
];

foreach ($system_files as $file => $description) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>&#10004; $file - $description</p>";
    } else {
        echo "<p style='color: red;'>&#10008; $file - $description (MISSING)</p>";
    }
}

// Step 6: Recommendations
echo "<h3>Step 6: Setup Recommendations</h3>";
echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #2563eb; margin: 10px 0;'>";
echo "<h4>Next Steps:</h4>";
echo "<ol>";
echo "<li>If tables are missing, import the SQL file: <code>tesda_auto_mechanic_integrated_system.sql</code></li>";
echo "<li>Ensure MySQL server is running on localhost</li>";
echo "<li>Verify database credentials in <code>db.php</code> (currently: root/empty password)</li>";
echo "<li>Test the system by accessing: <a href='index.php'>index.php</a></li>";
echo "<li>Test login functionality: <a href='login.php'>login.php</a></li>";
echo "</ol>";
echo "</div>";

echo "<h3>Database Connection Status: ";
if ($conn !== null) {
    echo "<span style='color: green;'>&#10004; CONNECTED</span></h3>";
} else {
    echo "<span style='color: red;'>&#10008; NOT CONNECTED</span></h3>";
}

echo "<p><small>Database: tesda_auto_mechanic | Host: localhost | User: root</small></p>";
?>
