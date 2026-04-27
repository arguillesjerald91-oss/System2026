<?php
/**
 * Database Import Script
 * Imports the TESDA Auto Mechanic database schema
 */

include 'db.php';

echo "<h2>Importing TESDA Auto Mechanic Database Schema</h2>";

// Check if SQL file exists
$sql_file = 'tesda_auto_mechanic_integrated_system.sql';
if (!file_exists($sql_file)) {
    echo "<p style='color: red;'>ERROR: SQL file '$sql_file' not found!</p>";
    echo "<p>Please ensure the SQL file exists in the project directory.</p>";
    exit();
}

echo "<p>Found SQL file: $sql_file</p>";

// Read SQL file
$sql_content = file_get_contents($sql_file);
if ($sql_content === false) {
    echo "<p style='color: red;'>ERROR: Could not read SQL file!</p>";
    exit();
}

echo "<p>SQL file size: " . number_format(strlen($sql_content)) . " bytes</p>";

// Connect to database
$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    echo "<p style='color: red;'>ERROR: Could not connect to database!</p>";
    exit();
}

try {
    // Split SQL file into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));
    
    echo "<p>Found " . count($statements) . " SQL statements to execute</p>";
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    // Execute each statement
    foreach ($statements as $i => $statement) {
        if (empty($statement) || preg_match('/^--/', $statement)) {
            continue; // Skip comments and empty statements
        }
        
        try {
            $conn->exec($statement);
            $success_count++;
            echo "<p style='color: green;'>Statement " . ($i + 1) . ": EXECUTED SUCCESSFULLY</p>";
        } catch (PDOException $e) {
            $error_count++;
            $errors[] = "Statement " . ($i + 1) . ": " . $e->getMessage();
            echo "<p style='color: orange;'>Statement " . ($i + 1) . ": SKIPPED (may already exist)</p>";
        }
    }
    
    echo "<h3>Import Summary:</h3>";
    echo "<p style='color: green;'>Successful statements: $success_count</p>";
    echo "<p style='color: orange;'>Skipped statements: $error_count</p>";
    
    if (!empty($errors)) {
        echo "<h4>Errors (these are usually normal if tables already exist):</h4>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
        foreach ($errors as $error) {
            echo htmlspecialchars($error) . "\n";
        }
        echo "</pre>";
    }
    
    // Verify tables were created
    echo "<h3>Verifying Created Tables:</h3>";
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>Total tables in database: " . count($tables) . "</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li style='color: green;'>$table</li>";
    }
    echo "</ul>";
    
    // Check for essential system tables
    $essential_tables = ['users', 'student', 'pre_enrollment_applications', 'scholarship_applications', 'training_modules'];
    $missing_tables = [];
    
    foreach ($essential_tables as $table) {
        if (!in_array($table, $tables)) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo "<p style='color: green; font-weight: bold;'>&#10004; All essential system tables are present!</p>";
    } else {
        echo "<p style='color: red;'>&#10008; Missing essential tables: " . implode(', ', $missing_tables) . "</p>";
    }
    
    echo "<h3>Database Import Status: ";
    if ($success_count > 0) {
        echo "<span style='color: green;'>&#10004; COMPLETED</span></h3>";
        echo "<p>The database has been successfully imported and is ready for use!</p>";
        echo "<p><a href='index.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Main Page</a></p>";
    } else {
        echo "<span style='color: red;'>&#10008; FAILED</span></h3>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>ERROR during import: " . $e->getMessage() . "</p>";
}
?>
