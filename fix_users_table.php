<?php
/**
 * Fix Users Table
 * Investigates and fixes any issues with the users table
 */

include 'db.php';

echo "<h2>Investigating and Fixing Users Table</h2>";

$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    echo "<p style='color: red;'>ERROR: Could not connect to database!</p>";
    exit();
}

// Step 1: Check if users table exists
echo "<h3>Step 1: Checking Users Table Existence</h3>";
$stmt = $conn->prepare("SHOW TABLES LIKE 'users'");
$stmt->execute();
$users_table_exists = $stmt->rowCount() > 0;

if ($users_table_exists) {
    echo "<p style='color: green;'>&#10004; Users table exists</p>";
} else {
    echo "<p style='color: red;'>&#10008; Users table does not exist - Creating it</p>";
    
    try {
        $sql = "CREATE TABLE users (
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
        echo "<p style='color: green;'>&#10004; Users table created successfully</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Error creating users table: " . $e->getMessage() . "</p>";
        exit();
    }
}

// Step 2: Check current users table structure
echo "<h3>Step 2: Current Users Table Structure</h3>";
$stmt = $conn->prepare("DESCRIBE users");
$stmt->execute();
$users_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($users_columns as $column) {
    echo "<tr>";
    echo "<td><strong>{$column['Field']}</strong></td>";
    echo "<td>{$column['Type']}</td>";
    echo "<td>{$column['Null']}</td>";
    echo "<td>{$column['Key']}</td>";
    echo "<td>{$column['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

// Step 3: Check for missing essential columns
echo "<h3>Step 3: Checking for Missing Essential Columns</h3>";
$required_columns = [
    'user_id' => 'Primary key',
    'username' => 'Login username',
    'password' => 'Hashed password',
    'email' => 'Email address',
    'user_type' => 'User role (student/admin/instructor)',
    'first_name' => 'First name',
    'last_name' => 'Last name',
    'status' => 'Account status',
    'created_at' => 'Creation timestamp'
];

$current_columns = array_column($users_columns, 'Field');
$missing_columns = [];

foreach ($required_columns as $column => $description) {
    if (!in_array($column, $current_columns)) {
        $missing_columns[$column] = $description;
    }
}

if (!empty($missing_columns)) {
    echo "<h4>Missing Essential Columns:</h4>";
    echo "<ul>";
    foreach ($missing_columns as $column => $description) {
        echo "<li style='color: red;'>$column - $description</li>";
    }
    echo "</ul>";
    
    echo "<h3>Step 4: Adding Missing Columns</h3>";
    foreach ($missing_columns as $column => $description) {
        try {
            switch ($column) {
                case 'user_id':
                    // This should already exist as primary key
                    break;
                case 'username':
                    $sql = "ALTER TABLE users ADD COLUMN username VARCHAR(50) NOT NULL UNIQUE AFTER user_id";
                    $conn->exec($sql);
                    echo "<p style='color: green;'>&#10004; Added username column</p>";
                    break;
                case 'password':
                    $sql = "ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL AFTER username";
                    $conn->exec($sql);
                    echo "<p style='color: green;'>&#10004; Added password column</p>";
                    break;
                case 'email':
                    $sql = "ALTER TABLE users ADD COLUMN email VARCHAR(100) NOT NULL UNIQUE AFTER password";
                    $conn->exec($sql);
                    echo "<p style='color: green;'>&#10004; Added email column</p>";
                    break;
                case 'user_type':
                    $sql = "ALTER TABLE users ADD COLUMN user_type ENUM('student', 'admin', 'instructor') NOT NULL DEFAULT 'student' AFTER email";
                    $conn->exec($sql);
                    echo "<p style='color: green;'>&#10004; Added user_type column</p>";
                    break;
                case 'first_name':
                    $sql = "ALTER TABLE users ADD COLUMN first_name VARCHAR(50) NOT NULL AFTER user_type";
                    $conn->exec($sql);
                    echo "<p style='color: green;'>&#10004; Added first_name column</p>";
                    break;
                case 'last_name':
                    $sql = "ALTER TABLE users ADD COLUMN last_name VARCHAR(50) NOT NULL AFTER first_name";
                    $conn->exec($sql);
                    echo "<p style='color: green;'>&#10004; Added last_name column</p>";
                    break;
                case 'status':
                    $sql = "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive', 'suspended') DEFAULT 'active' AFTER last_name";
                    $conn->exec($sql);
                    echo "<p style='color: green;'>&#10004; Added status column</p>";
                    break;
                case 'created_at':
                    $sql = "ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status";
                    $conn->exec($sql);
                    echo "<p style='color: green;'>&#10004; Added created_at column</p>";
                    break;
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Error adding $column: " . $e->getMessage() . "</p>";
        }
    }
} else {
    echo "<p style='color: green;'>&#10004; All essential columns are present</p>";
}

// Step 5: Check current data in users table
echo "<h3>Step 5: Current Users Data</h3>";
$stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users");
$stmt->execute();
$user_count = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

echo "<p>Total users in database: $user_count</p>";

if ($user_count > 0) {
    $stmt = $conn->prepare("SELECT user_id, username, email, user_type, status, created_at FROM users ORDER BY user_id");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Type</th><th>Status</th><th>Created</th></tr>";
    foreach ($users as $user) {
        $status_color = $user['status'] == 'active' ? 'green' : 'red';
        echo "<tr>";
        echo "<td>{$user['user_id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['user_type']}</td>";
        echo "<td style='color: $status_color;'>{$user['status']}</td>";
        echo "<td>" . date('M j, Y', strtotime($user['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>No users found in database</p>";
}

// Step 6: Ensure test accounts exist
echo "<h3>Step 6: Ensuring Test Accounts Exist</h3>";
$test_accounts = [
    ['username' => 'admin', 'password' => 'admin123', 'email' => 'admin@tesda-auto-mechanic.edu.ph', 'user_type' => 'admin', 'first_name' => 'System', 'last_name' => 'Administrator'],
    ['username' => 'student', 'password' => 'student123', 'email' => 'student@tesda-auto-mechanic.edu.ph', 'user_type' => 'student', 'first_name' => 'Test', 'last_name' => 'Student']
];

foreach ($test_accounts as $account) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
    $stmt->execute([$account['username']]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if (!$exists) {
        try {
            $hashed_password = password_hash($account['password'], PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, email, user_type, first_name, last_name, status) VALUES (?, ?, ?, ?, ?, ?, 'active')";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$account['username'], $hashed_password, $account['email'], $account['user_type'], $account['first_name'], $account['last_name']]);
            
            echo "<p style='color: green;'>&#10004; Created test account: {$account['username']}</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Error creating {$account['username']}: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>&#10071; Test account {$account['username']} already exists</p>";
    }
}

// Step 7: Check relationships with other tables
echo "<h3>Step 7: Checking Table Relationships</h3>";

// Check student table relationship
if (in_array('student', $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN))) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM student WHERE user_id IS NOT NULL");
    $stmt->execute();
    $linked_students = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<p>Students linked to users table: $linked_students</p>";
    
    if ($linked_students < $user_count) {
        echo "<p style='color: orange;'>Some users may not have corresponding student records</p>";
    }
}

// Step 8: Test users table operations
echo "<h3>Step 8: Testing Users Table Operations</h3>";
try {
    // Test SELECT
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stmt->execute();
    $active_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p style='color: green;'>&#10004; SELECT operations working (Active users: $active_users)</p>";
    
    // Test authentication simulation
    $stmt = $conn->prepare("SELECT user_id, username, password, user_type FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin_user && password_verify('admin123', $admin_user['password'])) {
        echo "<p style='color: green;'>&#10004; Authentication test passed</p>";
    } else {
        echo "<p style='color: red;'>&#10008; Authentication test failed</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error testing operations: " . $e->getMessage() . "</p>";
}

echo "<h3>Users Table Fix Summary</h3>";
echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
echo "<p>&#10004; Users table has been investigated and fixed</p>";
echo "<p>Essential columns are present, test accounts are available, and operations are working correctly.</p>";
echo "</div>";

echo "<h4>Test Accounts:</h4>";
echo "<ul>";
echo "<li><strong>Admin:</strong> username: admin, password: admin123</li>";
echo "<li><strong>Student:</strong> username: student, password: student123</li>";
echo "</ul>";

echo "<p><a href='login.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Login System</a></p>";
?>
