<?php
/**
 * Enroll 4 Trainees (NC I - NC IV)
 * Creates users, student records and enrolls them in appropriate NC Level programs
 */

include 'C:/new/htdocs/project/db.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== Enrolling 4 Trainees by NC Level ===\n\n";

// Clean up existing test users
$conn->exec("DELETE FROM student_program_enrollments WHERE pre_enroll_id >= 10");
$conn->exec("DELETE FROM student WHERE user_id >= 30");
$conn->exec("DELETE FROM pre_enrollment_applications WHERE pre_enroll_id >= 10");
$conn->exec("DELETE FROM users WHERE user_id >= 30");

// Trainee data - one for each NC Level
$trainees = [
    ['first_name' => 'Mark', 'last_name' => 'Villanueva', 'email' => 'mark.villanueva@email.com', 'nc_level' => 'NC I'],
    ['first_name' => 'Catherine', 'last_name' => 'Mabini', 'email' => 'catherine.mabini@email.com', 'nc_level' => 'NC II'],
    ['first_name' => 'Fernando', 'last_name' => 'Ocampo', 'email' => 'fernando.ocampo@email.com', 'nc_level' => 'NC III'],
    ['first_name' => 'Patricia', 'last_name' => 'Santos', 'email' => 'patricia.santos@email.com', 'nc_level' => 'NC IV']
];

// Clean up existing test users (by email pattern)
$conn->exec("DELETE FROM student_program_enrollments WHERE pre_enroll_id >= 10");
$conn->exec("DELETE FROM student WHERE email LIKE '%@email.com%' OR user_id >= 40");
$conn->exec("DELETE FROM pre_enrollment_applications WHERE email_address LIKE '%@email.com%'");
$conn->exec("DELETE FROM users WHERE email LIKE '%@email.com%' OR user_id >= 40");

$credentials = [];

foreach ($trainees as $i => $trainee) {
    $ncLevel = $trainee['nc_level'];
    $username = 'trainee_nc' . ($i + 1);
    
    // Generate password
    $password = 'Tesda' . date('Y') . '!' . ($i + 1); // e.g., Tesda2026!1
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    echo "Creating trainee: " . $trainee['first_name'] . " " . $trainee['last_name'] . " ($ncLevel)\n";
    
    try {
        // 1. Insert into users table
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, user_type, first_name, last_name, status, created_at) VALUES (?, ?, ?, 'trainee', ?, ?, 'active', NOW())");
        $stmt->execute([$username, $hashedPassword, $trainee['email'], $trainee['first_name'], $trainee['last_name']]);
        
        $userId = $conn->lastInsertId();
        echo "  - User ID: $userId\n";
        
        // 2. Insert into student table (linked to users) - detect columns dynamically
        $colsStmt = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student' ORDER BY ORDINAL_POSITION");
        $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
        $firstNameCol = in_array('FirstName', $columns) ? 'FirstName' : (in_array('FName', $columns) ? 'FName' : 'FirstName');
        $lastNameCol = in_array('LastName', $columns) ? 'LastName' : (in_array('LName', $columns) ? 'LName' : 'LastName');
        $emailCol = in_array('Email', $columns) ? 'Email' : (in_array('EmailAddr', $columns) ? 'EmailAddr' : 'Email');
        
        $insertCols = ['SchoolID', $firstNameCol, $lastNameCol, $emailCol, 'Status', 'EnrollmentDate', 'user_id'];
        $insertVals = ['TESDA-' . str_pad($userId, 4, '0', STR_PAD_LEFT), $trainee['first_name'], $trainee['last_name'], $trainee['email'], 'Enrolled', date('Y-m-d H:i:s'), $userId];
        $stmt = $conn->prepare("INSERT INTO student (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', array_fill(0, count($insertVals), '?')) . ")");
        $stmt->execute($insertVals);
        
        $studId = $conn->lastInsertId();
        echo "  - Student ID: $studId, SchoolID: $schoolId\n";
        
        // 3. Insert into pre_enrollment_applications for tracking
        $appNumber = 'APP-2026-' . str_pad($i + 10, 4, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("INSERT INTO pre_enrollment_applications (
            application_number, first_name, last_name, email_address, 
            application_status, nc_level, submission_date
        ) VALUES (?, ?, ?, ?, 'Enrolled', ?, NOW())");
        $stmt->execute([$appNumber, $trainee['first_name'], $trainee['last_name'], $trainee['email'], $ncLevel]);
        
        $appId = $conn->lastInsertId();
        echo "  - Application: $appNumber\n";
        
        // 4. Enroll in program (using student.StudID)
        $stmt = $conn->prepare("INSERT INTO student_program_enrollments (student_id, pre_enroll_id, enrollment_date, enrollment_status, created_at) VALUES (?, ?, NOW(), 'Active', NOW())");
        $stmt->execute([$studId, $appId]);
        
        $enrollmentId = $conn->lastInsertId();
        echo "  - Enrolled in $ncLevel (Enrollment ID: $enrollmentId)\n";
        
        // Save credentials
        $credentials[] = [
            'name' => $trainee['first_name'] . ' ' . $trainee['last_name'],
            'nc_level' => $ncLevel,
            'username' => $username,
            'password' => $password,
            'login_url' => 'http://localhost/login.php'
        ];
        
        echo "  - Credentials: $username / $password\n\n";
        
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Summary ===\n";
echo "Total trainees created: " . count($credentials) . "\n\n";

echo "=== LOGIN CREDENTIALS ===\n";
echo str_repeat("=", 60) . "\n";
foreach ($credentials as $cred) {
    echo "Name: {$cred['name']}\n";
    echo "NC Level: {$cred['nc_level']}\n";
    echo "Username: {$cred['username']}\n";
    echo "Password: {$cred['password']}\n";
    echo str_repeat("-", 40) . "\n";
}
echo str_repeat("=", 60) . "\n";
echo "\nLogin URL: http://localhost/login.php\n";

// Save credentials to file for reference
$output = json_encode($credentials, JSON_PRETTY_PRINT);
file_put_contents('C:/new/htdocs/project/trainee_credentials.json', $output);
echo "\nCredentials saved to trainee_credentials.json\n";
?>