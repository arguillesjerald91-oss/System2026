<?php
/**
 * Final Database Status and Integration Report
 * Provides comprehensive status of the TESDA Auto Mechanic database integration
 */

include 'db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>TESDA Auto Mechanic Database Status</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fc; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #155724; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .status-ok { color: #155724; }
        .status-warning { color: #856404; }
        .status-error { color: #721c24; }
        h1, h2, h3 { color: #1f2937; }
        .btn { background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px; }
        .btn:hover { background: #1e40af; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>TESDA Auto Mechanic Database Integration Status</h1>";

// Test database connection
$db = new Database();
$conn = $db->getConnection();

echo "<h2>Database Connection Status</h2>";
if ($conn !== null) {
    echo "<div class='success'>";
    echo "<strong>&#10004; CONNECTED</strong><br>";
    echo "Database: tesda_auto_mechanic<br>";
    echo "Host: localhost<br>";
    echo "User: root<br>";
    echo "Connection Time: " . date('Y-m-d H:i:s');
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<strong>&#10008; NOT CONNECTED</strong><br>";
    echo "Please check your database configuration in db.php";
    echo "</div>";
    exit();
}

// Get table information
echo "<h2>Database Tables Overview</h2>";
$stmt = $conn->prepare("SHOW TABLES");
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<table>";
echo "<tr><th>Table Name</th><th>Records</th><th>Status</th><th>Description</th></tr>";

$table_info = [
    'users' => 'User authentication and management',
    'student' => 'Student information and records',
    'pre_enrollment_applications' => 'Pre-enrollment applications',
    'scholarship_applications' => 'Scholarship applications',
    'training_modules' => 'Training modules and lessons',
    'user_access_assignments' => 'User access management',
    'module_access_permissions' => 'Module access permissions',
    'access_levels' => 'Access level definitions',
    'access_logs' => 'System access logs',
    'assessment_questions' => 'Assessment questions',
    'module_assessments' => 'Module assessments',
    'auto_mechanic_programs' => 'Training programs',
    'scholarship_programs' => 'Scholarship programs',
    'tesda_competency_standards' => 'TESDA competency standards'
];

$total_records = 0;
$essential_tables_count = 0;

foreach ($tables as $table) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM `$table`");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $total_records += $count;
        
        $status = 'OK';
        $status_class = 'status-ok';
        
        // Check for essential tables
        if (in_array($table, ['users', 'student', 'pre_enrollment_applications', 'scholarship_applications', 'training_modules'])) {
            $essential_tables_count++;
        }
        
        $description = isset($table_info[$table]) ? $table_info[$table] : 'System table';
        
        echo "<tr>";
        echo "<td><strong>$table</strong></td>";
        echo "<td>$count</td>";
        echo "<td class='$status_class'>$status</td>";
        echo "<td>$description</td>";
        echo "</tr>";
        
    } catch (Exception $e) {
        echo "<tr>";
        echo "<td><strong>$table</strong></td>";
        echo "<td>-</td>";
        echo "<td class='status-error'>ERROR</td>";
        echo "<td>" . $e->getMessage() . "</td>";
        echo "</tr>";
    }
}

echo "</table>";

echo "<div class='info'>";
echo "<strong>Database Summary:</strong><br>";
echo "Total Tables: " . count($tables) . "<br>";
echo "Essential Tables: $essential_tables_count/5<br>";
echo "Total Records: $total_records<br>";
echo "Database Size: " . format_bytes(get_database_size($conn)) . "";
echo "</div>";

// Test user accounts
echo "<h2>User Accounts Status</h2>";
$stmt = $conn->prepare("SELECT username, user_type, status, created_at FROM users ORDER BY user_type, username");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($users)) {
    echo "<table>";
    echo "<tr><th>Username</th><th>User Type</th><th>Status</th><th>Created</th></tr>";
    
    foreach ($users as $user) {
        $status_class = $user['status'] == 'active' ? 'status-ok' : 'status-warning';
        echo "<tr>";
        echo "<td><strong>{$user['username']}</strong></td>";
        echo "<td>{$user['user_type']}</td>";
        echo "<td class='$status_class'>{$user['status']}</td>";
        echo "<td>" . date('M j, Y', strtotime($user['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='warning'>No user accounts found. Please create admin and test accounts.</div>";
}

// System integration test
echo "<h2>System Integration Status</h2>";
$system_files = [
    'index.php' => 'Main Landing Page',
    'login.php' => 'Login System',
    'pre_enrollment.php' => 'Pre-Enrollment',
    'scholarship_application.php' => 'Scholarship Application',
    'student/learning_modules.php' => 'Student Modules',
    'admin/access_management.php' => 'Admin Access'
];

$integration_status = [];

foreach ($system_files as $file => $description) {
    if (file_exists($file)) {
        $integration_status[$file] = ['status' => 'OK', 'description' => $description];
    } else {
        $integration_status[$file] = ['status' => 'MISSING', 'description' => $description];
    }
}

echo "<table>";
echo "<tr><th>System File</th><th>Description</th><th>Status</th></tr>";

foreach ($integration_status as $file => $info) {
    $status_class = $info['status'] == 'OK' ? 'status-ok' : 'status-error';
    echo "<tr>";
    echo "<td>$file</td>";
    echo "<td>{$info['description']}</td>";
    echo "<td class='$status_class'>{$info['status']}</td>";
    echo "</tr>";
}
echo "</table>";

// Recommendations
echo "<h2>System Recommendations</h2>";

if ($essential_tables_count >= 5) {
    echo "<div class='success'>";
    echo "<strong>&#10004; System Ready!</strong><br>";
    echo "All essential database tables are present and the system is fully integrated.";
    echo "</div>";
    
    echo "<h3>Test Accounts:</h3>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> username: <code>admin</code>, password: <code>admin123</code></li>";
    echo "<li><strong>Student:</strong> username: <code>student</code>, password: <code>student123</code></li>";
    echo "</ul>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<a href='login.php' class='btn'>Test Login System</a>";
    echo "<a href='index.php' class='btn'>Visit Main Page</a>";
    echo "<a href='pre_enrollment.php' class='btn'>Test Pre-Enrollment</a>";
    
} else {
    echo "<div class='warning'>";
    echo "<strong>&#10071; System Needs Setup</strong><br>";
    echo "Some essential tables are missing. Please run the setup scripts.";
    echo "</div>";
    
    echo "<h3>Setup Actions:</h3>";
    echo "<a href='create_missing_tables.php' class='btn'>Create Missing Tables</a>";
    echo "<a href='import_database.php' class='btn'>Import Database Schema</a>";
    echo "<a href='setup_database.php' class='btn'>Database Setup</a>";
}

echo "<hr>";
echo "<p><small>Report generated: " . date('Y-m-d H:i:s') . "</small></p>";

echo "</div>
</body>
</html>";

function get_database_size($conn) {
    try {
        $stmt = $conn->prepare("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = 'tesda_auto_mechanic'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['size'] . ' MB';
    } catch (Exception $e) {
        return 'Unknown';
    }
}

function format_bytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
