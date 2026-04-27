<?php
/**
 * Add Test Users for All User Types
 */

include __DIR__ . '/db.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Adding Test Users</h2>";

$users = [
    [
        'username' => 'admin',
        'password' => 'admin123',
        'email' => 'admin@tesda.gov.ph',
        'user_type' => 'admin',
        'first_name' => 'System',
        'last_name' => 'Administrator'
    ],
    [
        'username' => 'student1',
        'password' => 'student123',
        'email' => 'student1@tesda.gov.ph',
        'user_type' => 'student',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz'
    ],
    [
        'username' => 'trainee1',
        'password' => 'trainee123',
        'email' => 'trainee1@tesda.gov.ph',
        'user_type' => 'trainee',
        'first_name' => 'Maria',
        'last_name' => 'Santos'
    ],
    [
        'username' => 'instructor1',
        'password' => 'instructor123',
        'email' => 'instructor1@tesda.gov.ph',
        'user_type' => 'instructor',
        'first_name' => 'Pedro',
        'last_name' => 'Garcia'
    ],
    [
        'username' => 'instructor2',
        'password' => 'instructor123',
        'email' => 'instructor2@tesda.gov.ph',
        'user_type' => 'instructor',
        'first_name' => 'Ana',
        'last_name' => 'Reyes'
    ],
    [
        'username' => 'unit1',
        'password' => 'unit123',
        'email' => 'unit1@tesda.gov.ph',
        'user_type' => 'instructional_unit',
        'first_name' => 'Roberto',
        'last_name' => 'Mendoza'
    ],
    [
        'username' => 'support1',
        'password' => 'support123',
        'email' => 'support1@tesda.gov.ph',
        'user_type' => 'support_staff',
        'first_name' => 'Carmen',
        'last_name' => 'Lopez'
    ],
    [
        'username' => 'support2',
        'password' => 'support123',
        'email' => 'support2@tesda.gov.ph',
        'user_type' => 'support_staff',
        'first_name' => 'Daniel',
        'last_name' => 'Torres'
    ]
];

foreach ($users as $user) {
    // Check if user exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$user['username']]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        echo "<p style='color: orange;'>⚠ User '" . $user['username'] . "' already exists - skipping</p>";
        continue;
    }
    
    $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        INSERT INTO users (username, password, email, user_type, first_name, last_name, status, email_verified)
        VALUES (?, ?, ?, ?, ?, ?, 'active', 1)
    ");
    
    try {
        $stmt->execute([
            $user['username'],
            $hashed_password,
            $user['email'],
            $user['user_type'],
            $user['first_name'],
            $user['last_name']
        ]);
        echo "<p style='color: green;'>✓ Added user: " . $user['username'] . " (" . $user['user_type'] . ") - Password: " . $user['password'] . "</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Failed to add " . $user['username'] . ": " . $e->getMessage() . "</p>";
    }
}

echo "<h3>Test Credentials</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>User Type</th><th>Username</th><th>Password</th></tr>";
echo "<tr><td>Admin</td><td>admin</td><td>admin123</td></tr>";
echo "<tr><td>Student</td><td>student1</td><td>student123</td></tr>";
echo "<tr><td>Trainee</td><td>trainee1</td><td>trainee123</td></tr>";
echo "<tr><td>Instructor</td><td>instructor1</td><td>instructor123</td></tr>";
echo "<tr><td>Instructional Unit</td><td>unit1</td><td>unit123</td></tr>";
echo "<tr><td>Support Staff</td><td>support1</td><td>support123</td></tr>";
echo "</table>";

echo "<p><a href='login/index.php'>Go to Login</a></p>";