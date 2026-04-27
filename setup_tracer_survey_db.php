<?php
/**
 * TRACER Survey Database Setup
 */
session_start();
include 'db.php';
$database = new Database();
$conn = $database->getConnection();

$success = false;
$error = '';

// Check if table exists
$tableExists = false;
try {
    $result = $conn->query("SHOW TABLES LIKE 'tracer_survey_responses'");
    $tableExists = $result->rowCount() > 0;
    
    if (!$tableExists) {
        // Create the table
        $conn->exec("CREATE TABLE `tracer_survey_responses` (
            `survey_id` int(11) NOT NULL AUTO_INCREMENT,
            `student_id` int(11) NOT NULL,
            `survey_date` date DEFAULT NULL,
            `status` enum('Pending','Completed','Expired') DEFAULT 'Pending',
            `employment_status` enum('Employed','Self-Employed','Unemployed','Not Seeking','Continuing Education') DEFAULT 'Not Yet Determined',
            `company_name` varchar(255) DEFAULT NULL,
            `company_address` varchar(255) DEFAULT NULL,
            `job_position` varchar(255) DEFAULT NULL,
            `monthly_salary` decimal(12,2) DEFAULT 0,
            `employment_duration_months` int(11) DEFAULT 0,
            `industry` varchar(100) DEFAULT NULL,
            `course_related` varchar(255) DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`survey_id`),
            KEY `idx_student` (`student_id`),
            KEY `idx_status` (`status`),
            KEY `idx_employment` (`employment_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $success = true;
    } else {
        $success = true;
    }
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TRACER Survey Setup</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, sans-serif; background: #f3f4f6; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 500px; width: 100%; text-align: center; }
        h1 { font-size: 24px; margin-bottom: 20px; color: #1e40af; }
        .icon { font-size: 48px; margin-bottom: 20px; }
        .success { background: #d1fae5; color: #065f46; padding: 16px; border-radius: 10px; margin-bottom: 20px; }
        .error { background: #fee2e2; color: #dc2626; padding: 16px; border-radius: 10px; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 14px 28px; background: #2563eb; color: white; text-decoration: none; border-radius: 10px; font-weight: 600; margin-top: 16px; }
        .btn:hover { background: #1e40af; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($success): ?>
        <div class="icon">✓</div>
        <h1>TRACER Survey Ready!</h1>
        <div class="success">
            The tracer_survey_responses table has been created successfully.
        </div>
        <a href="admin/tracer_survey.php" class="btn">Go to TRACER Survey</a>
        <?php else: ?>
        <div class="icon">✗</div>
        <h1>Setup Failed</h1>
        <div class="error">
            Error: <?= htmlspecialchars($error) ?>
        </div>
        <a href="admin/setup_tracer_survey_db.php" class="btn">Try Again</a>
        <?php endif; ?>
    </div>
</body>
</html>