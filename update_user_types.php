<?php
/**
 * Update User Types
 * Add support for all required user types in the system
 */

include 'db.php';

echo "<h2>Updating User Types for Comprehensive Login System</h2>";

$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    echo "<p style='color: red;'>ERROR: Could not connect to database!</p>";
    exit();
}

// Step 1: Update users table to support all user types
echo "<h3>Step 1: Updating Users Table</h3>";
try {
    // Modify user_type column to include all required types
    $sql = "ALTER TABLE users MODIFY COLUMN user_type ENUM('admin', 'student', 'instructor', 'instructional_unit', 'support_staff', 'trainee') NOT NULL DEFAULT 'student'";
    $conn->exec($sql);
    echo "<p style='color: green;'>&#10004; Updated user_type column with all required user types</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: orange;'>&#10071; User type column may already be updated: " . $e->getMessage() . "</p>";
}

// Step 2: Create test accounts for all user types
echo "<h3>Step 2: Creating Test Accounts for All User Types</h3>";
$test_accounts = [
    [
        'username' => 'admin',
        'password' => 'admin123',
        'email' => 'admin@tesda-auto-mechanic.edu.ph',
        'user_type' => 'admin',
        'first_name' => 'System',
        'last_name' => 'Administrator'
    ],
    [
        'username' => 'student',
        'password' => 'student123',
        'email' => 'student@tesda-auto-mechanic.edu.ph',
        'user_type' => 'student',
        'first_name' => 'Test',
        'last_name' => 'Student'
    ],
    [
        'username' => 'instructor',
        'password' => 'instructor123',
        'email' => 'instructor@tesda-auto-mechanic.edu.ph',
        'user_type' => 'instructor',
        'first_name' => 'Test',
        'last_name' => 'Instructor'
    ],
    [
        'username' => 'instructional',
        'password' => 'instructional123',
        'email' => 'instructional@tesda-auto-mechanic.edu.ph',
        'user_type' => 'instructional_unit',
        'first_name' => 'Instructional',
        'last_name' => 'Unit Head'
    ],
    [
        'username' => 'support',
        'password' => 'support123',
        'email' => 'support@tesda-auto-mechanic.edu.ph',
        'user_type' => 'support_staff',
        'first_name' => 'Support',
        'last_name' => 'Staff'
    ],
    [
        'username' => 'trainee',
        'password' => 'trainee123',
        'email' => 'trainee@tesda-auto-mechanic.edu.ph',
        'user_type' => 'trainee',
        'first_name' => 'Test',
        'last_name' => 'Trainee'
    ]
];

foreach ($test_accounts as $account) {
    // Check if user already exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
    $stmt->execute([$account['username']]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if (!$exists) {
        try {
            $hashed_password = password_hash($account['password'], PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, email, user_type, first_name, last_name, status) VALUES (?, ?, ?, ?, ?, ?, 'active')";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $account['username'],
                $hashed_password,
                $account['email'],
                $account['user_type'],
                $account['first_name'],
                $account['last_name']
            ]);
            
            echo "<p style='color: green;'>&#10004; Created {$account['user_type']} account: {$account['username']}</p>";
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Error creating {$account['username']}: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>&#10071; {$account['username']} already exists</p>";
    }
}

// Step 3: Verify all user types
echo "<h3>Step 3: Verifying All User Types</h3>";
$stmt = $conn->prepare("SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type ORDER BY user_type");
$stmt->execute();
$user_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h4>User Type Distribution:</h4>";
echo "<table>";
echo "<tr><th>User Type</th><th>Count</th><th>Status</th></tr>";
foreach ($user_types as $type) {
    echo "<tr>";
    echo "<td><strong>{$type['user_type']}</strong></td>";
    echo "<td>{$type['count']}</td>";
    echo "<td style='color: green;'>Active</td>";
    echo "</tr>";
}
echo "</table>";

// Step 4: Create access levels for different user types
echo "<h3>Step 4: Setting Up Access Levels</h3>";
$access_levels = [
    ['level_name' => 'Super Admin', 'description' => 'Full system access'],
    ['level_name' => 'Admin', 'description' => 'Administrative access'],
    ['level_name' => 'Instructional Unit', 'description' => 'Training management access'],
    ['level_name' => 'Instructor', 'description' => 'Teaching and assessment access'],
    ['level_name' => 'Support Staff', 'description' => 'Support and administrative access'],
    ['level_name' => 'Trainee', 'description' => 'Learning access only']
];

foreach ($access_levels as $level) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM access_levels WHERE level_name = ?");
    $stmt->execute([$level['level_name']]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if (!$exists) {
        $sql = "INSERT INTO access_levels (level_name, description, created_at) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$level['level_name'], $level['description']]);
        echo "<p style='color: green;'>&#10004; Created access level: {$level['level_name']}</p>";
    } else {
        echo "<p style='color: blue;'>&#10071; Access level already exists: {$level['level_name']}</p>";
    }
}

echo "<h3>User Types Update Complete</h3>";
echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
echo "<p>&#10004; Database updated with all required user types</p>";
echo "<p>&#10004; Test accounts created for all user types</p>";
echo "<p>&#10004; Access levels configured</p>";
echo "</div>";

echo "<h4>Test Accounts Ready:</h4>";
echo "<ul>";
echo "<li><strong>Admin:</strong> username: admin, password: admin123</li>";
echo "<li><strong>Student:</strong> username: student, password: student123</li>";
echo "<li><strong>Instructor:</strong> username: instructor, password: instructor123</li>";
echo "<li><strong>Instructional Unit:</strong> username: instructional, password: instructional123</li>";
echo "<li><strong>Support Staff:</strong> username: support, password: support123</li>";
echo "<li><strong>Trainee:</strong> username: trainee, password: trainee123</li>";
echo "</ul>";
?>
