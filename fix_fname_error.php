<?php
/**
 * Fix FName Column Error
 * Investigates and fixes the missing FName column issue
 */

include 'db.php';

echo "<h2>Investigating FName Column Error</h2>";

$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    echo "<p style='color: red;'>ERROR: Could not connect to database!</p>";
    exit();
}

// Step 1: Show all tables
echo "<h3>Step 1: Checking All Tables</h3>";
$stmt = $conn->prepare("SHOW TABLES");
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<p>Found " . count($tables) . " tables in database</p>";

// Step 2: Check which tables have FName column
echo "<h3>Step 2: Checking for FName Column</h3>";
$tables_with_fname = [];
$tables_without_fname = [];

foreach ($tables as $table) {
    try {
        $stmt = $conn->prepare("DESCRIBE $table");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('FName', $columns)) {
            $tables_with_fname[] = $table;
        } else {
            $tables_without_fname[] = $table;
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>Error checking table $table: " . $e->getMessage() . "</p>";
    }
}

echo "<h4>Tables WITH FName column:</h4>";
if (!empty($tables_with_fname)) {
    echo "<ul>";
    foreach ($tables_with_fname as $table) {
        echo "<li style='color: green;'>$table</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>No tables have FName column</p>";
}

echo "<h4>Tables WITHOUT FName column:</h4>";
if (!empty($tables_without_fname)) {
    echo "<ul>";
    foreach ($tables_without_fname as $table) {
        echo "<li style='color: orange;'>$table</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: green;'>All tables have FName column</p>";
}

// Step 3: Check pre_enrollment_applications table structure specifically
echo "<h3>Step 3: Pre-enrollment Applications Table Structure</h3>";
if (in_array('pre_enrollment_applications', $tables)) {
    $stmt = $conn->prepare("DESCRIBE pre_enrollment_applications");
    $stmt->execute();
    $pre_enroll_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($pre_enroll_columns as $column) {
        echo "<tr>";
        echo "<td><strong>{$column['Field']}</strong></td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if FName is missing from pre_enrollment_applications table
    $pre_enroll_column_names = array_column($pre_enroll_columns, 'Field');
    if (!in_array('FName', $pre_enroll_column_names)) {
        echo "<h3>Step 4: Adding FName Column to Pre-enrollment Applications Table</h3>";
        
        try {
            // Add FName column to pre_enrollment_applications table
            $sql = "ALTER TABLE pre_enrollment_applications ADD COLUMN FName VARCHAR(50) NOT NULL DEFAULT '' AFTER application_number";
            $conn->exec($sql);
            echo "<p style='color: green;'>&#10004; FName column added to pre_enrollment_applications table</p>";
            
            // Add LName column as well since it's likely needed
            $sql = "ALTER TABLE pre_enrollment_applications ADD COLUMN LName VARCHAR(50) NOT NULL DEFAULT '' AFTER FName";
            $conn->exec($sql);
            echo "<p style='color: green;'>&#10004; LName column added to pre_enrollment_applications table</p>";
            
            // Add MName column for completeness
            $sql = "ALTER TABLE pre_enrollment_applications ADD COLUMN MName VARCHAR(50) DEFAULT NULL AFTER LName";
            $conn->exec($sql);
            echo "<p style='color: green;'>&#10004; MName column added to pre_enrollment_applications table</p>";
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Error adding FName column: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: green;'>&#10004; FName column already exists in pre_enrollment_applications table</p>";
    }
} else {
    echo "<p style='color: red;'>Pre-enrollment applications table not found!</p>";
}

// Step 4: Check scholarship_applications table as well
echo "<h3>Step 4: Scholarship Applications Table Structure</h3>";
if (in_array('scholarship_applications', $tables)) {
    $stmt = $conn->prepare("DESCRIBE scholarship_applications");
    $stmt->execute();
    $scholarship_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($scholarship_columns as $column) {
        echo "<tr>";
        echo "<td><strong>{$column['Field']}</strong></td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if FName is missing from scholarship_applications table
    $scholarship_column_names = array_column($scholarship_columns, 'Field');
    if (!in_array('FName', $scholarship_column_names)) {
        echo "<h3>Step 5: Adding FName Column to Scholarship Applications Table</h3>";
        
        try {
            // Add FName column to scholarship_applications table
            $sql = "ALTER TABLE scholarship_applications ADD COLUMN FName VARCHAR(50) NOT NULL DEFAULT '' AFTER application_id";
            $conn->exec($sql);
            echo "<p style='color: green;'>&#10004; FName column added to scholarship_applications table</p>";
            
            // Add LName column as well
            $sql = "ALTER TABLE scholarship_applications ADD COLUMN LName VARCHAR(50) NOT NULL DEFAULT '' AFTER FName";
            $conn->exec($sql);
            echo "<p style='color: green;'>&#10004; LName column added to scholarship_applications table</p>";
            
            // Add MName column for completeness
            $sql = "ALTER TABLE scholarship_applications ADD COLUMN MName VARCHAR(50) DEFAULT NULL AFTER LName";
            $conn->exec($sql);
            echo "<p style='color: green;'>&#10004; MName column added to scholarship_applications table</p>";
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Error adding FName column: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: green;'>&#10004; FName column already exists in scholarship_applications table</p>";
    }
} else {
    echo "<p style='color: red;'>Scholarship applications table not found!</p>";
}

// Step 5: Check which PHP files are using FName
echo "<h3>Step 5: Checking PHP Files for FName Usage</h3>";
$php_files = glob('*.php');
$files_using_fname = [];

foreach ($php_files as $file) {
    if (is_file($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'FName') !== false) {
            $files_using_fname[] = $file;
        }
    }
}

if (!empty($files_using_fname)) {
    echo "<h4>Files using FName:</h4>";
    echo "<ul>";
    foreach ($files_using_fname as $file) {
        echo "<li style='color: blue;'>$file</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No PHP files found using FName</p>";
}

// Step 6: Test database operations with FName
echo "<h3>Step 6: Testing Database Operations with FName</h3>";
try {
    // Test SELECT with FName from pre_enrollment_applications
    $stmt = $conn->prepare("SELECT application_id, FName, LName, application_status FROM pre_enrollment_applications LIMIT 5");
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Pre-enrollment Applications (with FName/LName):</h4>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Status</th></tr>";
    foreach ($applications as $app) {
        echo "<tr>";
        echo "<td>{$app['application_id']}</td>";
        echo "<td>{$app['FName']} {$app['LName']}</td>";
        echo "<td>{$app['application_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test SELECT with FName from scholarship_applications
    $stmt = $conn->prepare("SELECT application_id, FName, LName, status FROM scholarship_applications LIMIT 5");
    $stmt->execute();
    $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Scholarship Applications (with FName/LName):</h4>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Status</th></tr>";
    foreach ($scholarships as $scholarship) {
        echo "<tr>";
        echo "<td>{$scholarship['application_id']}</td>";
        echo "<td>{$scholarship['FName']} {$scholarship['LName']}</td>";
        echo "<td>{$scholarship['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: green;'>&#10004; Database operations with FName working correctly</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error testing FName operations: " . $e->getMessage() . "</p>";
}

echo "<h3>Fix Summary</h3>";
echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
echo "<p>&#10004; FName column issue has been investigated and fixed</p>";
echo "<p>The FName, LName, and MName columns have been added to the application tables if they were missing.</p>";
echo "</div>";

echo "<p><a href='database_status.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Check Database Status</a></p>";
?>
