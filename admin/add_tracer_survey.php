<?php 
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['userId'])) {
    header("Location: ../login.php");
    exit();
}

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
} catch (PDOException $e) {
    // Table might already exist, continue
}

$errors = [];
$success = false;

$students = $conn->query("
    SELECT s.StudID, s.FirstName, s.LastName, s.Email, s.SchoolID, spe.nc_level
    FROM student s
    LEFT JOIN student_program_enrollments spe ON s.StudID = spe.student_id
    WHERE s.Status = 'Active'
    ORDER BY s.LastName ASC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)$_POST['student_id'];
    $employment_status = $_POST['employment_status'];
    $company_name = trim($_POST['company_name'] ?? '');
    $job_position = trim($_POST['job_position'] ?? '');
    $monthly_salary = (float)$_POST['monthly_salary'] ?? 0;
    $industry = $_POST['industry'];
    
    if (!$student_id) {
        $errors[] = "Please select a student";
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO tracer_survey_responses 
            (student_id, survey_date, status, employment_status, company_name, job_position, monthly_salary, industry)
            VALUES (?, NOW(), 'Completed', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $employment_status, $company_name, $job_position, $monthly_salary, $industry]);
        
        $success = true;
    }
}

$pageTitle = "Start TRACER Survey";
$pageSubtitle = "Graduate Employment Tracking";
$currentPage = "tracer_survey.php";

include 'sidebar_new.php';
?>

<div style="max-width: 700px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Create New TRACER Survey</h3>
            <a href="tracer_survey.php" class="btn" style="padding: 8px 16px; background: #f1f5f9; color: #374151; border-radius: 6px; text-decoration: none;">
                Back to Surveys
            </a>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                <strong>Please fix the following errors:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                <strong>Survey submitted successfully!</strong>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div style="display: grid; gap: 20px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Select Student *</label>
                        <select name="student_id" required style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?= $s['StudID'] ?>">
                                <?= htmlspecialchars(($s['LastName'] ?? '') . ', ' . ($s['FirstName'] ?? '')) ?> (<?= $s['SchoolID'] ?>) - <?= $s['nc_level'] ?? 'NC I' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Employment Status *</label>
                        <select name="employment_status" required style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                            <option value="Employed">Employed (Work for others)</option>
                            <option value="Self-Employed">Self-Employed/Business Owner</option>
                            <option value="Unemployed">Unemployed (Looking for work)</option>
                            <option value="Not Seeking">Not Seeking Employment</option>
                            <option value="Continuing Education">Continuing Education/Training</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374161;">Company/Business Name</label>
                        <input type="text" name="company_name" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" 
                            style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;"
                            placeholder="Company or Business Name">
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Job Position</label>
                        <input type="text" name="job_position" value="<?= htmlspecialchars($_POST['job_position'] ?? '') ?>" 
                            style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;"
                            placeholder="e.g., Automotive Mechanic, Service Technician">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Monthly Salary (PHP)</label>
                            <input type="number" name="monthly_salary" value="<?= $_POST['monthly_salary'] ?? '' ?>" min="0" step="0.01"
                                style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;"
                                placeholder="e.g., 15000">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Industry</label>
                            <select name="industry" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                                <option value="">Select Industry</option>
                                <option value="Automotive">Automotive/Vehicle</option>
                                <option value="Manufacturing">Manufacturing</option>
                                <option value="Construction">Construction</option>
                                <option value="Retail">Retail/Services</option>
                                <option value="Transportation">Transportation/Logistics</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn" style="padding: 14px 28px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 15px; cursor: pointer;">
                        Submit Survey
                    </button>
                    <a href="tracer_survey.php" class="btn" style="padding: 14px 28px; background: #f1f5f9; color: #374151; border-radius: 8px; text-decoration: none;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card" style="margin-top: 20px;">
        <div class="card-header" style="background: #fef3c7;">
            <h3 class="card-title">What is TRACER Survey?</h3>
        </div>
        <div class="card-body">
            <p style="color: #64748b; line-height: 1.6;">
                TRACER (Tracer Study) is a graduate employment tracking system required by TESDA. 
                It tracks the employment outcomes of graduates to assess the effectiveness of training programs 
                and improve curriculum based on industry needs.
            </p>
            <ul style="color: #64748b; margin-top: 12px; padding-left: 20px;">
                <li>Tracks employment status after graduation</li>
                <li>Monitors job relevance to training</li>
                <li>Measures salary and career progression</li>
                <li>Collects industry feedback for improvement</li>
            </ul>
        </div>
    </div>
</div>

</main>
</div>

</body>
</html>