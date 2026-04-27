<?php
/**
 * Fix SchoolID Column Error
 * Investigates and fixes the missing SchoolID column issue
 */

include 'db.php';

echo "<h2>Investigating SchoolID Column Error</h2>";

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
echo "<ul>";
foreach ($tables as $table) {
    echo "<li>$table</li>";
}
echo "</ul>";

// Step 2: Check which tables have SchoolID column
echo "<h3>Step 2: Checking for SchoolID Column</h3>";
$tables_with_schoolid = [];
$tables_without_schoolid = [];

foreach ($tables as $table) {
    try {
        $stmt = $conn->prepare("DESCRIBE $table");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('SchoolID', $columns)) {
            $tables_with_schoolid[] = $table;
        } else {
            $tables_without_schoolid[] = $table;
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>Error checking table $table: " . $e->getMessage() . "</p>";
    }
}

echo "<h4>Tables WITH SchoolID column:</h4>";
if (!empty($tables_with_schoolid)) {
    echo "<ul>";
    foreach ($tables_with_schoolid as $table) {
        echo "<li style='color: green;'>$table</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>No tables have SchoolID column</p>";
}

echo "<h4>Tables WITHOUT SchoolID column:</h4>";
if (!empty($tables_without_schoolid)) {
    echo "<ul>";
    foreach ($tables_without_schoolid as $table) {
        echo "<li style='color: orange;'>$table</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: green;'>All tables have SchoolID column</p>";
}

// Step 3: Check student table structure specifically
echo "<h3>Step 3: Student Table Structure</h3>";
if (in_array('student', $tables)) {
    $stmt = $conn->prepare("DESCRIBE student");
    $stmt->execute();
    $student_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($student_columns as $column) {
        echo "<tr>";
        echo "<td><strong>{$column['Field']}</strong></td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if SchoolID is missing from student table
    $student_column_names = array_column($student_columns, 'Field');
    if (!in_array('SchoolID', $student_column_names)) {
        echo "<h3>Step 4: Adding SchoolID Column to Student Table</h3>";
        
        try {
            // Add SchoolID column to student table
            $sql = "ALTER TABLE student ADD COLUMN SchoolID VARCHAR(50) DEFAULT NULL AFTER StudID";
            $conn->exec($sql);
            echo "<p style='color: green;'>&#10004; SchoolID column added to student table</p>";
            
            // Add index for SchoolID
            $sql = "ALTER TABLE student ADD INDEX idx_schoolid (SchoolID)";
            $conn->exec($sql);
            echo "<p style='color: green;'>&#10004; SchoolID index added</p>";
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Error adding SchoolID column: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: green;'>&#10004; SchoolID column already exists in student table</p>";
    }
} else {
    echo "<p style='color: red;'>Student table not found!</p>";
}

// Step 4: Check if there are any PHP files trying to use SchoolID
echo "<h3>Step 4: Checking PHP Files for SchoolID Usage</h3>";
$php_files = glob('*.php');
$files_using_schoolid = [];

foreach ($php_files as $file) {
    if (is_file($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'SchoolID') !== false) {
            $files_using_schoolid[] = $file;
        }
    }
}

if (!empty($files_using_schoolid)) {
    echo "<h4>Files using SchoolID:</h4>";
    echo "<ul>";
    foreach ($files_using_schoolid as $file) {
        echo "<li style='color: blue;'>$file</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No PHP files found using SchoolID</p>";
}

// Step 5: Test database operations
echo "<h3>Step 5: Testing Database Operations</h3>";
try {
    // Test SELECT with SchoolID
    $stmt = $conn->prepare("SELECT StudID, SchoolID, FirstName, LastName FROM student LIMIT 5");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Student Records (with SchoolID):</h4>";
    echo "<table>";
    echo "<tr><th>StudID</th><th>SchoolID</th><th>Name</th></tr>";
    foreach ($students as $student) {
        echo "<tr>";
        echo "<td>{$student['StudID']}</td>";
        echo "<td>" . ($student['SchoolID'] ?? 'NULL') . "</td>";
        echo "<td>{$student['FirstName']} {$student['LastName']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: green;'>&#10004; Database operations with SchoolID working correctly</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error testing SchoolID operations: " . $e->getMessage() . "</p>";
}

echo "<h3>Fix Summary</h3>";
echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
echo "<p>&#10004; SchoolID column issue has been investigated and fixed</p>";
echo "<p>The SchoolID column has been added to the student table if it was missing.</p>";
echo "</div>";

echo "<p><a href='database_status.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Check Database Status</a></p>";
?>
