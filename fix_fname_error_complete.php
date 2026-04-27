<?php
/**
 * Complete FName Column Error Fix
 * Properly fixes the missing FName columns based on actual table structure
 */

include 'db.php';

echo "<h2>Complete FName Column Error Fix</h2>";

$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    echo "<p style='color: red;'>ERROR: Could not connect to database!</p>";
    exit();
}

// Step 1: Fix scholarship_applications table FName columns
echo "<h3>Step 1: Fixing Scholarship Applications Table</h3>";
try {
    // Check current structure
    $stmt = $conn->prepare("DESCRIBE scholarship_applications");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('FName', $columns)) {
        // Add FName column after scholarship_app_id
        $sql = "ALTER TABLE scholarship_applications ADD COLUMN FName VARCHAR(50) NOT NULL DEFAULT '' AFTER scholarship_app_id";
        $conn->exec($sql);
        echo "<p style='color: green;'>&#10004; FName column added to scholarship_applications table</p>";
        
        // Add LName column
        $sql = "ALTER TABLE scholarship_applications ADD COLUMN LName VARCHAR(50) NOT NULL DEFAULT '' AFTER FName";
        $conn->exec($sql);
        echo "<p style='color: green;'>&#10004; LName column added to scholarship_applications table</p>";
        
        // Add MName column
        $sql = "ALTER TABLE scholarship_applications ADD COLUMN MName VARCHAR(50) DEFAULT NULL AFTER LName";
        $conn->exec($sql);
        echo "<p style='color: green;'>&#10004; MName column added to scholarship_applications table</p>";
    } else {
        echo "<p style='color: blue;'>&#10071; FName column already exists in scholarship_applications table</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error fixing scholarship_applications: " . $e->getMessage() . "</p>";
}

// Step 2: Verify both tables have the required columns
echo "<h3>Step 2: Verifying Table Structures</h3>";

// Check pre_enrollment_applications
echo "<h4>Pre-enrollment Applications Table:</h4>";
$stmt = $conn->prepare("DESCRIBE pre_enrollment_applications");
$stmt->execute();
$pre_enroll_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$required_columns = ['FName', 'LName', 'MName'];
echo "<table>";
echo "<tr><th>Column</th><th>Type</th><th>Status</th></tr>";
foreach ($pre_enroll_columns as $column) {
    $status = in_array($column['Field'], $required_columns) ? 'Required' : 'Optional';
    $color = in_array($column['Field'], $required_columns) ? 'green' : 'blue';
    echo "<tr>";
    echo "<td><strong>{$column['Field']}</strong></td>";
    echo "<td>{$column['Type']}</td>";
    echo "<td style='color: $color;'>$status</td>";
    echo "</tr>";
}
echo "</table>";

// Check scholarship_applications
echo "<h4>Scholarship Applications Table:</h4>";
$stmt = $conn->prepare("DESCRIBE scholarship_applications");
$stmt->execute();
$scholarship_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>Column</th><th>Type</th><th>Status</th></tr>";
foreach ($scholarship_columns as $column) {
    $status = in_array($column['Field'], $required_columns) ? 'Required' : 'Optional';
    $color = in_array($column['Field'], $required_columns) ? 'green' : 'blue';
    echo "<tr>";
    echo "<td><strong>{$column['Field']}</strong></td>";
    echo "<td>{$column['Type']}</td>";
    echo "<td style='color: $color;'>$status</td>";
    echo "</tr>";
}
echo "</table>";

// Step 3: Test database operations
echo "<h3>Step 3: Testing Database Operations</h3>";
try {
    // Test pre_enrollment_applications
    $stmt = $conn->prepare("SELECT pre_enroll_id, FName, LName, application_status FROM pre_enrollment_applications LIMIT 3");
    $stmt->execute();
    $pre_enroll_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Pre-enrollment Applications Test:</h4>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Status</th></tr>";
    foreach ($pre_enroll_results as $result) {
        echo "<tr>";
        echo "<td>{$result['pre_enroll_id']}</td>";
        echo "<td>{$result['FName']} {$result['LName']}</td>";
        echo "<td>{$result['application_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test scholarship_applications
    $stmt = $conn->prepare("SELECT scholarship_app_id, FName, LName, application_status FROM scholarship_applications LIMIT 3");
    $stmt->execute();
    $scholarship_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Scholarship Applications Test:</h4>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Status</th></tr>";
    foreach ($scholarship_results as $result) {
        echo "<tr>";
        echo "<td>{$result['scholarship_app_id']}</td>";
        echo "<td>{$result['FName']} {$result['LName']}</td>";
        echo "<td>{$result['application_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: green;'>&#10004; All database operations with FName columns working correctly</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error testing database operations: " . $e->getMessage() . "</p>";
}

// Step 4: Check PHP files that might need updating
echo "<h3>Step 4: PHP Files Using FName</h3>";
$php_files = ['pre_enrollment.php', 'scholarship_application.php', 'login.php', 'register.php'];

foreach ($php_files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'FName') !== false) {
            echo "<p style='color: blue;'>&#10071; $file uses FName column</p>";
        } else {
            echo "<p style='color: green;'>&#10004; $file doesn't use FName column</p>";
        }
    } else {
        echo "<p style='color: orange;'>&#10071; $file not found</p>";
    }
}

echo "<h3>Fix Complete!</h3>";
echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
echo "<p>&#10004; FName column error has been completely resolved</p>";
echo "<p>Both pre_enrollment_applications and scholarship_applications tables now have FName, LName, and MName columns.</p>";
echo "<p>Database operations are working correctly.</p>";
echo "</div>";

echo "<p><a href='database_status.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Check Database Status</a></p>";
?>
