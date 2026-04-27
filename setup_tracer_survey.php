<?php
/**
 * Create TRACER Survey Tables
 */
session_start();
include 'db.php';
$database = new Database();
$conn = $database->getConnection();

echo "Creating TRACER Survey tables...\n";

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS tracer_survey_responses (
        survey_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        survey_date DATE NOT NULL,
        status ENUM('Pending', 'Completed', 'Expired') DEFAULT 'Pending',
        employment_status ENUM('Employed', 'Self-Employed', 'Unemployed', 'Not Seeking', 'Continuing Education') DEFAULT 'Not Yet Determined',
        company_name VARCHAR(255) DEFAULT NULL,
        company_address VARCHAR(255) DEFAULT NULL,
        job_position VARCHAR(255) DEFAULT NULL,
        monthly_salary DECIMAL(12,2) DEFAULT 0,
        employment_duration_months INT DEFAULT 0,
        industry VARCHAR(100) DEFAULT NULL,
        course_related VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_status (status),
        INDEX idx_survey_date (survey_date),
        INDEX idx_employment (employment_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "✓ tracer_survey_responses table created\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone! <a href='admin/tracer_survey.php'>Go to TRACER Survey</a>";
?>