<?php
/**
 * Create Missing Essential Tables
 * Creates the users and student tables that are missing
 */

include 'db.php';

echo "<h2>Creating Missing Essential Tables</h2>";

$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    echo "<p style='color: red;'>ERROR: Could not connect to database!</p>";
    exit();
}

// Create users table
echo "<h3>Creating users table</h3>";
try {
    $sql = "CREATE TABLE IF NOT EXISTS users (
        user_id INT(11) NOT NULL AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        user_type ENUM('student', 'admin', 'instructor') NOT NULL DEFAULT 'student',
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (user_id),
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_user_type (user_type),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->exec($sql);
    echo "<p style='color: green;'>&#10004; users table created successfully</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: orange;'>&#10071; users table may already exist: " . $e->getMessage() . "</p>";
}

// Create student table
echo "<h3>Creating student table</h3>";
try {
    $sql = "CREATE TABLE IF NOT EXISTS student (
        StudID INT(11) NOT NULL AUTO_INCREMENT,
        FirstName VARCHAR(50) NOT NULL,
        LastName VARCHAR(50) NOT NULL,
        MiddleName VARCHAR(50) DEFAULT NULL,
        Email VARCHAR(100) NOT NULL UNIQUE,
        Phone VARCHAR(20) DEFAULT NULL,
        Address TEXT DEFAULT NULL,
        BirthDate DATE DEFAULT NULL,
        Gender ENUM('Male', 'Female', 'Other') DEFAULT NULL,
        Course VARCHAR(100) DEFAULT NULL,
        YearLevel INT(11) DEFAULT NULL,
        Section VARCHAR(20) DEFAULT NULL,
        Status ENUM('Enrolled', 'Not Enrolled', 'Graduated', 'Dropped') DEFAULT 'Not Enrolled',
        EnrollmentDate DATE DEFAULT NULL,
        GPA DECIMAL(3,2) DEFAULT NULL,
        user_id INT(11) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (StudID),
        UNIQUE KEY idx_email (Email),
        KEY idx_course (Course),
        KEY idx_status (Status),
        KEY idx_user_id (user_id),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->exec($sql);
    echo "<p style='color: green;'>&#10004; student table created successfully</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: orange;'>&#10071; student table may already exist: " . $e->getMessage() . "</p>";
}

// Create admin user for testing
echo "<h3>Creating default admin user</h3>";
try {
    // Check if admin user exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        // Create admin user
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, email, user_type, first_name, last_name, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['admin', $hashed_password, 'admin@tesda-auto-mechanic.edu.ph', 'admin', 'System', 'Administrator', 'active']);
        
        echo "<p style='color: green;'>&#10004; Default admin user created (username: admin, password: admin123)</p>";
        
        // Get the admin user_id
        $admin_id = $conn->lastInsertId();
        
        // Create corresponding student record for admin - detect columns dynamically
        $colsStmt = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student' ORDER BY ORDINAL_POSITION");
        $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
        $firstNameCol = in_array('FirstName', $columns) ? 'FirstName' : (in_array('FName', $columns) ? 'FName' : 'FirstName');
        $lastNameCol = in_array('LastName', $columns) ? 'LastName' : (in_array('LName', $columns) ? 'LName' : 'LastName');
        $emailCol = in_array('Email', $columns) ? 'Email' : (in_array('EmailAddr', $columns) ? 'EmailAddr' : 'Email');
        
        $insertCols = [$firstNameCol, $lastNameCol, $emailCol, 'user_id', 'Status'];
        $insertVals = ['System', 'Administrator', 'admin@tesda-auto-mechanic.edu.ph', $admin_id, 'Enrolled'];
        $sql = "INSERT INTO student (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', array_fill(0, count($insertVals), '?')) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->execute($insertVals);
        
        echo "<p style='color: green;'>&#10004; Admin student record created</p>";
    } else {
        echo "<p style='color: blue;'>&#10071; Admin user already exists</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error creating admin user: " . $e->getMessage() . "</p>";
}

// Create test student user
echo "<h3>Creating test student user</h3>";
try {
    // Check if test student exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = 'student'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        // Create student user
        $hashed_password = password_hash('student123', PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, email, user_type, first_name, last_name, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['student', $hashed_password, 'student@tesda-auto-mechanic.edu.ph', 'student', 'Test', 'Student', 'active']);
        
        echo "<p style='color: green;'>&#10004; Test student user created (username: student, password: student123)</p>";
        
        // Get the student user_id
        $student_id = $conn->lastInsertId();
        
        // Create corresponding student record - detect columns dynamically
        $colsStmt = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student' ORDER BY ORDINAL_POSITION");
        $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
        $firstNameCol = in_array('FirstName', $columns) ? 'FirstName' : (in_array('FName', $columns) ? 'FName' : 'FirstName');
        $lastNameCol = in_array('LastName', $columns) ? 'LastName' : (in_array('LName', $columns) ? 'LName' : 'LastName');
        $emailCol = in_array('Email', $columns) ? 'Email' : (in_array('EmailAddr', $columns) ? 'EmailAddr' : 'Email');
        
        $insertCols = [$firstNameCol, $lastNameCol, $emailCol, 'user_id', 'Status'];
        $insertVals = ['Test', 'Student', 'student@tesda-auto-mechanic.edu.ph', $student_id, 'Enrolled'];
        
        // Add Course and YearLevel if they exist
        if (in_array('Course', $columns)) $insertCols[] = 'Course';
        if (in_array('YearLevel', $columns)) { $insertCols[] = 'YearLevel'; $insertVals[] = 1; }
        
        $sql = "INSERT INTO student (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', array_fill(0, count($insertVals), '?')) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->execute($insertVals);
        
        echo "<p style='color: green;'>&#10004; Test student record created</p>";
    } else {
        echo "<p style='color: blue;'>&#10071; Test student user already exists</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error creating test student: " . $e->getMessage() . "</p>";
}

// Verify tables were created
echo "<h3>Verification</h3>";
$stmt = $conn->prepare("SHOW TABLES");
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$essential_tables = ['users', 'student'];
$all_present = true;

foreach ($essential_tables as $table) {
    if (in_array($table, $tables)) {
        echo "<p style='color: green;'>&#10004; $table table exists</p>";
        
        // Check if table has data
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p style='color: blue;'>&#10071; $table has {$result['count']} records</p>";
    } else {
        echo "<p style='color: red;'>&#10008; $table table still missing</p>";
        $all_present = false;
    }
}

if ($all_present) {
    echo "<h3>Success!</h3>";
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
    echo "<p>&#10004; All essential tables have been created successfully!</p>";
    echo "<p>The database is now fully integrated with the system.</p>";
    echo "</div>";
    
    echo "<h4>Test Accounts Created:</h4>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> username: admin, password: admin123</li>";
    echo "<li><strong>Student:</strong> username: student, password: student123</li>";
    echo "</ul>";
    
    echo "<p><a href='login.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Login System</a></p>";
} else {
    echo "<p style='color: red;'>Some tables are still missing. Please check the errors above.</p>";
}
?>
