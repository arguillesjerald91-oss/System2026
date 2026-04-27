<?php
/**
 * Diagnose Login Issues
 * Comprehensive check of login system functionality
 */

include 'db.php';

echo "<h2>Login System Diagnosis</h2>";

// Test 1: Database Connection
echo "<h3>Test 1: Database Connection</h3>";
$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    echo "<p style='color: red;'>&#10008; Database connection FAILED</p>";
    echo "<p>Cannot proceed with login tests without database connection.</p>";
    exit();
} else {
    echo "<p style='color: green;'>&#10004; Database connection SUCCESS</p>";
}

// Test 2: Users Table Check
echo "<h3>Test 2: Users Table Check</h3>";
try {
    $stmt = $conn->prepare("DESCRIBE users");
    $stmt->execute();
    $users_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $required_columns = ['user_id', 'username', 'password', 'email', 'user_type', 'first_name', 'last_name', 'status'];
    $missing_columns = [];
    
    foreach ($required_columns as $col) {
        $column_exists = false;
        foreach ($users_columns as $column) {
            if ($column['Field'] === $col) {
                $column_exists = true;
                break;
            }
        }
        if (!$column_exists) {
            $missing_columns[] = $col;
        }
    }
    
    if (empty($missing_columns)) {
        echo "<p style='color: green;'>&#10004; Users table has all required columns</p>";
    } else {
        echo "<p style='color: red;'>&#10008; Users table missing columns: " . implode(', ', $missing_columns) . "</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>&#10008; Error checking users table: " . $e->getMessage() . "</p>";
}

// Test 3: Check User Data
echo "<h3>Test 3: User Data Check</h3>";
try {
    $stmt = $conn->prepare("SELECT user_id, username, email, user_type, status FROM users ORDER BY user_id");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($users) . " users in database:</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Type</th><th>Status</th></tr>";
    foreach ($users as $user) {
        $status_color = $user['status'] == 'active' ? 'green' : 'red';
        echo "<tr>";
        echo "<td>{$user['user_id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['user_type']}</td>";
        echo "<td style='color: $status_color;'>{$user['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>&#10008; Error checking user data: " . $e->getMessage() . "</p>";
}

// Test 4: Password Verification Test
echo "<h3>Test 4: Password Verification Test</h3>";
$password_tests = [
    ['username' => 'admin', 'password' => 'admin123', 'type' => 'admin'],
    ['username' => 'student', 'password' => 'student123', 'type' => 'student']
];

foreach ($password_tests as $test) {
    try {
        $sql = "SELECT user_id, username, password, user_type, status FROM users WHERE username = ? AND user_type = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$test['username'], $test['type']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            if (password_verify($test['password'], $user['password'])) {
                echo "<p style='color: green;'>&#10004; {$test['username']} ({$test['type']}) - Password verification SUCCESS</p>";
            } else {
                echo "<p style='color: red;'>&#10008; {$test['username']} ({$test['type']}) - Password verification FAILED</p>";
                echo "<p>Stored hash: " . substr($user['password'], 0, 20) . "...</p>";
            }
        } else {
            echo "<p style='color: red;'>&#10008; {$test['username']} ({$test['type']}) - User not found</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>&#10008; Error testing {$test['username']}: " . $e->getMessage() . "</p>";
    }
}

// Test 5: Simulate Login Process
echo "<h3>Test 5: Simulate Login Process</h3>";
$login_test = [
    'username' => 'admin',
    'password' => 'admin123',
    'user_type' => 'admin'
];

// Simulate the exact login logic from login.php
$username = trim($login_test['username']);
$password = trim($login_test['password']);
$userType = $login_test['user_type'];

echo "<p>Simulating login with: username='$username', user_type='$userType'</p>";

$error = '';
if (empty($username) || empty($password)) {
    $error = "Please enter both username and password";
    echo "<p style='color: red;'>&#10008; Validation failed: $error</p>";
} else {
    try {
        $sql = "SELECT user_id, username, password, email, user_type, first_name, last_name, status 
                FROM users 
                WHERE (username = ? OR email = ?) AND user_type = ? AND status = 'active'";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, $username, $userType]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p>SQL executed, found " . ($user ? "1" : "0") . " user</p>";
        
        if ($user) {
            echo "<p>User found: {$user['username']} ({$user['user_type']})</p>";
            echo "<p>Status: {$user['status']}</p>";
            
            if (password_verify($password, $user['password'])) {
                echo "<p style='color: green;'>&#10004; Login simulation SUCCESS</p>";
                echo "<p>Session data would be set:</p>";
                echo "<ul>";
                echo "<li>user_id: {$user['user_id']}</li>";
                echo "<li>username: {$user['username']}</li>";
                echo "<li>email: {$user['email']}</li>";
                echo "<li>user_type: {$user['user_type']}</li>";
                echo "<li>first_name: {$user['first_name']}</li>";
                echo "<li>last_name: {$user['last_name']}</li>";
                echo "</ul>";
            } else {
                echo "<p style='color: red;'>&#10008; Password verification failed</p>";
                echo "<p>Input password: '$password'</p>";
                echo "<p>Stored hash: {$user['password']}</p>";
            }
        } else {
            echo "<p style='color: red;'>&#10008; No user found matching criteria</p>";
            $error = "Invalid username or password";
        }
        
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        echo "<p style='color: red;'>&#10008; Database error: $error</p>";
    }
}

// Test 6: Check Session Configuration
echo "<h3>Test 6: Session Configuration</h3>";
echo "<p>Session status: " . session_status() . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session save path: " . session_save_path() . "</p>";

if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p style='color: green;'>&#10004; Session is active</p>";
} else {
    echo "<p style='color: red;'>&#10008; Session is not active</p>";
}

// Test 7: Check File Permissions
echo "<h3>Test 7: File Permissions</h3>";
$login_file = __DIR__ . '/login.php';
if (file_exists($login_file)) {
    if (is_readable($login_file)) {
        echo "<p style='color: green;'>&#10004; login.php is readable</p>";
    } else {
        echo "<p style='color: red;'>&#10008; login.php is not readable</p>";
    }
} else {
    echo "<p style='color: red;'>&#10008; login.php does not exist</p>";
}

// Test 8: Check for Common Login Issues
echo "<h3>Test 8: Common Login Issues Check</h3>";

// Check if users table has active users
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$stmt->execute();
$active_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "<p>Active users: $active_users</p>";

// Check if passwords are properly hashed
$stmt = $conn->prepare("SELECT username, password FROM users LIMIT 5");
$stmt->execute();
$users_check = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Password hash check:</p>";
foreach ($users_check as $user) {
    $hash_info = password_get_info($user['password']);
    if ($hash_info['algo'] === 0) {
        echo "<p style='color: orange;'>&#10071; {$user['username']} - Password not properly hashed</p>";
    } else {
        echo "<p style='color: green;'>&#10004; {$user['username']} - Password properly hashed</p>";
    }
}

echo "<h3>Diagnosis Summary</h3>";
echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #2563eb; margin: 10px 0;'>";
echo "<h4>Recommendations:</h4>";
echo "<ol>";
echo "<li>If password verification fails, run: <a href='fix_users_table.php'>fix_users_table.php</a></li>";
echo "<li>If database connection fails, check db.php configuration</li>";
echo "<li>If sessions don't work, check PHP session settings</li>";
echo "<li>Test live login at: <a href='login.php'>login.php</a></li>";
echo "</ol>";
echo "</div>";

echo "<p><a href='login.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Live Login</a></p>";
?>
