<?php
/**
 * Database Integration Test Script
 * Tests all database connections and system integrations
 */

include 'db.php';

echo "<h2>TESDA Auto Mechanic Database Integration Test</h2>";

// Test 1: Basic Database Connection
echo "<h3>Test 1: Database Connection</h3>";
$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    echo "<p style='color: red;'>&#10008; Database connection FAILED</p>";
    exit();
} else {
    echo "<p style='color: green;'>&#10004; Database connection SUCCESSFUL</p>";
}

// Test 2: Database Schema Verification
echo "<h3>Test 2: Database Schema Verification</h3>";
$stmt = $conn->prepare("SHOW TABLES");
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$required_tables = [
    'users' => ['user_id', 'username', 'password', 'user_type', 'email'],
    'student' => ['StudID', 'FirstName', 'LastName', 'Email', 'Course'],
    'pre_enrollment_applications' => ['application_id', 'first_name', 'last_name', 'email', 'phone'],
    'scholarship_applications' => ['application_id', 'student_id', 'program_type', 'status'],
    'training_modules' => ['module_id', 'module_name', 'module_description', 'difficulty_level'],
    'user_access_assignments' => ['assignment_id', 'user_id', 'access_id', 'status'],
    'module_access_permissions' => ['permission_id', 'user_id', 'module_id', 'access_type']
];

$all_tests_passed = true;

foreach ($required_tables as $table => $required_columns) {
    echo "<h4>Testing table: $table</h4>";
    
    if (!in_array($table, $tables)) {
        echo "<p style='color: red;'>&#10008; Table '$table' does not exist</p>";
        $all_tests_passed = false;
        continue;
    }
    
    echo "<p style='color: green;'>&#10004; Table '$table' exists</p>";
    
    // Check columns
    $stmt = $conn->prepare("DESCRIBE $table");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($required_columns as $column) {
        if (in_array($column, $columns)) {
            echo "<p style='color: green;'>&#10004; Column '$column' exists</p>";
        } else {
            echo "<p style='color: red;'>&#10008; Column '$column' missing</p>";
            $all_tests_passed = false;
        }
    }
}

// Test 3: System File Integration
echo "<h3>Test 3: System File Integration</h3>";
$system_files = [
    'index.php' => 'Main landing page',
    'pre_enrollment.php' => 'Pre-enrollment system',
    'scholarship_application.php' => 'Scholarship application system',
    'login.php' => 'Login authentication system',
    'student/learning_modules.php' => 'Student module access',
    'admin/access_management.php' => 'Admin access control'
];

foreach ($system_files as $file => $description) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>&#10004; $file - $description</p>";
        
        // Test if file can include database connection
        if (strpos($file, '.php') !== false && $file !== 'index.php') {
            $content = file_get_contents($file);
            if (strpos($content, 'db.php') !== false || strpos($content, 'Database') !== false) {
                echo "<p style='color: green;'>&#10004; $file includes database integration</p>";
            } else {
                echo "<p style='color: orange;'>&#10071; $file may need database integration</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>&#10008; $file - $description (MISSING)</p>";
        $all_tests_passed = false;
    }
}

// Test 4: Database Operations
echo "<h3>Test 4: Database Operations Test</h3>";
try {
    // Test SELECT operation
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p style='color: green;'>&#10004; SELECT operation successful (Users count: {$result['count']})</p>";
    
    // Test INSERT operation (using a test table)
    $conn->beginTransaction();
    $stmt = $conn->prepare("CREATE TEMPORARY TABLE test_integration (id INT AUTO_INCREMENT PRIMARY KEY, test_data VARCHAR(50))");
    $stmt->execute();
    
    $stmt = $conn->prepare("INSERT INTO test_integration (test_data) VALUES (?)");
    $stmt->execute(['Database Integration Test']);
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM test_integration");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        echo "<p style='color: green;'>&#10004; INSERT operation successful</p>";
    } else {
        echo "<p style='color: red;'>&#10008; INSERT operation failed</p>";
        $all_tests_passed = false;
    }
    
    $conn->rollBack();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>&#10008; Database operations test failed: " . $e->getMessage() . "</p>";
    $all_tests_passed = false;
}

// Test 5: Session and Authentication Integration
echo "<h3>Test 5: Session and Authentication Integration</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<p style='color: green;'>&#10004; Session system active</p>";

// Test login functionality simulation
if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>&#10004; User session detected</p>";
} else {
    echo "<p style='color: orange;'>&#10071; No active user session (normal for testing)</p>";
}

// Test 6: File Permissions and Accessibility
echo "<h3>Test 6: File Permissions and Accessibility</h3>";
$critical_files = ['db.php', 'index.php', 'login.php', 'pre_enrollment.php'];

foreach ($critical_files as $file) {
    if (file_exists($file)) {
        if (is_readable($file)) {
            echo "<p style='color: green;'>&#10004; $file is readable</p>";
        } else {
            echo "<p style='color: red;'>&#10008; $file is not readable</p>";
            $all_tests_passed = false;
        }
    }
}

// Final Results
echo "<h3>Integration Test Results</h3>";
if ($all_tests_passed) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
    echo "<h4 style='margin: 0 0 10px 0;'>&#10004; ALL TESTS PASSED</h4>";
    echo "<p style='margin: 0;'>The TESDA Auto Mechanic system is fully integrated with the database and ready for use!</p>";
    echo "</div>";
    
    echo "<h4>System Ready!</h4>";
    echo "<p>You can now:</p>";
    echo "<ul>";
    echo "<li><a href='index.php' style='color: #2563eb;'>Access the main system</a></li>";
    echo "<li><a href='login.php' style='color: #2563eb;'>Test login functionality</a></li>";
    echo "<li><a href='pre_enrollment.php' style='color: #2563eb;'>Test pre-enrollment</a></li>";
    echo "<li><a href='scholarship_application.php' style='color: #2563eb;'>Test scholarship applications</a></li>";
    echo "</ul>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
    echo "<h4 style='margin: 0 0 10px 0;'>&#10008; SOME TESTS FAILED</h4>";
    echo "<p style='margin: 0;'>Please address the issues above before using the system.</p>";
    echo "</div>";
    
    echo "<h4>Recommended Actions:</h4>";
    echo "<ol>";
    echo "<li>Run the database import: <a href='import_database.php' style='color: #2563eb;'>Import Database</a></li>";
    echo "<li>Check database connection: <a href='setup_database.php' style='color: #2563eb;'>Setup Database</a></li>";
    echo "<li>Verify all system files are present</li>";
    echo "<li>Check file permissions</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<p><small>Test completed at: " . date('Y-m-d H:i:s') . "</small></p>";
?>
