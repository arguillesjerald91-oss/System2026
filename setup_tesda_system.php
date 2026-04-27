<?php
/**
 * TESDA Auto Mechanic Training Centre - Complete Database Setup
 * All 5 Modules Integration
 */

include __DIR__ . '/db.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Setting up TESDA Auto Mechanic System Database</h2>";

// =====================================================
// MODULE 1: PRE-ENROLLMENT TABLES
// =====================================================
echo "<h3>Module 1: Pre-Enrollment</h3>";

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS training_batches (
        batch_id INT(11) NOT NULL AUTO_INCREMENT,
        batch_name VARCHAR(50) NOT NULL,
        schedule_type ENUM('Morning', 'Afternoon', 'Evening', 'Weekend') NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        max_slots INT(11) DEFAULT 25,
        enrolled_count INT(11) DEFAULT 0,
        status ENUM('Open', 'Full', 'Closed') DEFAULT 'Open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (batch_id),
        INDEX idx_status (status),
        INDEX idx_dates (start_date, end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p style='color: green;'>✓ training_batches</p>";
} catch (PDOException $e) { echo "<p style='color: orange;'>t_batches: " . substr($e->getMessage(), 0, 30) . "</p>"; }

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS pre_enrollment_applications (
        pre_enroll_id INT(11) NOT NULL AUTO_INCREMENT,
        application_number VARCHAR(20) NOT NULL UNIQUE,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        middle_name VARCHAR(100) DEFAULT NULL,
        birth_date DATE NOT NULL,
        gender ENUM('Male', 'Female', 'Other') NOT NULL,
        contact_number VARCHAR(20) NOT NULL,
        email_address VARCHAR(100) DEFAULT NULL,
        complete_address TEXT NOT NULL,
        barangay VARCHAR(100) NOT NULL,
        city_municipality VARCHAR(100) NOT NULL,
        province VARCHAR(100) NOT NULL,
        civil_status ENUM('Single', 'Married', 'Widowed', 'Separated') NOT NULL,
        citizenship VARCHAR(50) DEFAULT 'Filipino',
        highest_education VARCHAR(100) NOT NULL,
        school_last_attended VARCHAR(200) DEFAULT NULL,
        year_graduated INT(4) DEFAULT NULL,
        employment_status ENUM('Employed', 'Unemployed', 'Self-Employed', 'Student') NOT NULL,
        batch_id INT(11) DEFAULT NULL,
        application_status ENUM('Pending', 'Under Review', 'Accepted', 'Waitlisted', 'Rejected') DEFAULT 'Pending',
        admin_remarks TEXT,
        reviewed_by INT(11),
        reviewed_at TIMESTAMP NULL,
        submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (pre_enroll_id),
        INDEX idx_app_number (application_number),
        INDEX idx_status (application_status),
        INDEX idx_batch (batch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p style='color: green;'>✓ pre_enrollment_applications</p>";
} catch (PDOException $e) { echo "<p style='color: orange;'>pre_enroll: " . substr($e->getMessage(), 0, 30) . "</p>"; }

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS uploaded_documents (
        doc_id INT(11) NOT NULL AUTO_INCREMENT,
        pre_enroll_id INT(11) NOT NULL,
        doc_type ENUM('Birth Certificate', 'TOR', 'Medical', 'Barangay Clearance', 'Valid ID', '2x2 Picture') NOT NULL,
        file_path VARCHAR(255),
        file_name VARCHAR(255),
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('Pending', 'Uploaded', 'Verified', 'Rejected') DEFAULT 'Pending',
        PRIMARY KEY (doc_id),
        INDEX idx_pre_enroll (pre_enroll_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p style='color: green;'>✓ uploaded_documents</p>";
} catch (PDOException $e) { echo "<p style='color: orange;'>u_documents: " . substr($e->getMessage(), 0, 30) . "</p>"; }

// =====================================================
// MODULE 2: SCHOLARSHIP TABLES
// =====================================================
echo "<h3>Module 2: Scholarship Qualification</h3>";

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS scholarship_programs (
        program_id INT(11) NOT NULL AUTO_INCREMENT,
        program_code VARCHAR(20) NOT NULL,
        program_name VARCHAR(200) NOT NULL,
        description TEXT,
        scholarship_type ENUM('TWSP', 'PESFA', 'UAQTEA', 'Grants', 'Other') NOT NULL,
        scholarship_amount DECIMAL(10,2) DEFAULT 0,
        slots_available INT(11) DEFAULT 10,
        slots_filled INT(11) DEFAULT 0,
        eligibility_criteria TEXT,
        program_status ENUM('Active', 'Inactive') DEFAULT 'Active',
        application_deadline DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (program_id),
        INDEX idx_code (program_code),
        INDEX idx_status (program_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p style='color: green;'>✓ scholarship_programs</p>";
} catch (PDOException $e) { echo "<p style='color: orange;'>s_programs: " . substr($e->getMessage(), 0, 30) . "</p>"; }

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS scholarship_applications (
        application_id INT(11) NOT NULL AUTO_INCREMENT,
        pre_enroll_id INT(11) NOT NULL,
        program_id INT(11) NOT NULL,
        application_number VARCHAR(30) NOT NULL UNIQUE,
        household_income DECIMAL(10,2) DEFAULT 0,
        household_members INT(11) DEFAULT 0,
        is_4ps_beneficiary TINYINT(1) DEFAULT 0,
        is_pwd TINYINT(1) DEFAULT 0,
        is_ip TINYINT(1) DEFAULT 0,
        is_displaced_worker TINYINT(1) DEFAULT 0,
        financial_need_score DECIMAL(5,2) DEFAULT 0,
        academic_score DECIMAL(5,2) DEFAULT 0,
        interview_score DECIMAL(5,2) DEFAULT 0,
        total_score DECIMAL(5,2) DEFAULT 0,
        application_status ENUM('Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected') DEFAULT 'Draft',
        admin_remarks TEXT,
        reviewed_by INT(11),
        reviewed_at TIMESTAMP NULL,
        submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (application_id),
        INDEX idx_pre_enroll (pre_enroll_id),
        INDEX idx_program (program_id),
        INDEX idx_status (application_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p style='color: green;'>✓ scholarship_applications</p>";
} catch (PDOException $e) { echo "<p style='color: orange;'>s_applications: " . substr($e->getMessage(), 0, 30) . "</p>"; }

// =====================================================
// MODULE 3: COMPETENCY-BASED EVALUATION
// =====================================================
echo "<h3>Module 3: Competency-Based Evaluation</h3>";

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS competency_units (
        unit_id INT(11) NOT NULL AUTO_INCREMENT,
        unit_code VARCHAR(20) NOT NULL,
        unit_title VARCHAR(200) NOT NULL,
        unit_description TEXT,
        nctype ENUM('NC I', 'NC II', 'NC III') NOT NULL,
        competency_level INT(11) DEFAULT 1,
        hrs_required INT(11) DEFAULT 40,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (unit_id),
        INDEX idx_nctype (nctype),
        INDEX idx_code (unit_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p style='color: green;'>✓ competency_units</p>";
} catch (PDOException $e) { echo "<p style='color: orange;'>c_units: " . substr($e->getMessage(), 0, 30) . "</p>"; }

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS competency_assessments (
        assess_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        unit_id INT(11) NOT NULL,
        pre_assessment_score DECIMAL(5,2),
        practical_score DECIMAL(5,2),
        final_score DECIMAL(5,2),
        assessment_status ENUM('Not Started', 'In Progress', 'Passed', 'Failed', 'RPL') DEFAULT 'Not Started',
        assessment_date DATE,
        assessed_by INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (assess_id),
        INDEX idx_user (user_id),
        INDEX idx_unit (unit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p style='color: green;'>✓ competency_assessments</p>";
} catch (PDOException $e) { echo "<p style='color: orange;'>c_assessments: " . substr($e->getMessage(), 0, 30) . "</p>"; }

// =====================================================
// MODULE 4: LMS - LEARNING MODULES
// =====================================================
echo "<h3>Module 4: Learning Management System</h3>";

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS learning_modules (
        module_id INT(11) NOT NULL AUTO_INCREMENT,
        unit_id INT(11),
        module_title VARCHAR(200) NOT NULL,
        module_description TEXT,
        module_type ENUM('Video', 'PDF', 'Quiz', 'Practical') NOT NULL,
        content_url VARCHAR(255),
        duration_mins INT(11) DEFAULT 30,
        sort_order INT(11) DEFAULT 1,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (module_id),
        INDEX idx_unit (unit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p style='color: green;'>✓ learning_modules</p>";
} catch (PDOException $e) { echo "<p style='color: orange;'>l_modules: " . substr($e->getMessage(), 0, 30) . "</p>"; }

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS module_progress (
        progress_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        module_id INT(11) NOT NULL,
        progress_percent INT(11) DEFAULT 0,
        quiz_score DECIMAL(5,2),
        status ENUM('Not Started', 'In Progress', 'Completed') DEFAULT 'Not Started',
        started_at TIMESTAMP NULL,
        completed_at TIMESTAMP NULL,
        PRIMARY KEY (progress_id),
        INDEX idx_user (user_id),
        INDEX idx_module (module_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p style='color: green;'>✓ module_progress</p>";
} catch (PDOException $e) { echo "<p style='color: orange;'>m_progress: " . substr($e->getMessage(), 0, 30) . "</p>"; }

// =====================================================
// MODULE 5: REPORTS & ANALYTICS
// =====================================================
echo "<h3>Module 5: Reports & Analytics</h3>";

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS traker_study (
        tracer_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        employment_status ENUM('Employed', 'Self-Employed', 'Unemployed', 'Further Study') NOT NULL,
        company_name VARCHAR(200),
        company_address VARCHAR(255),
        position VARCHAR(100),
        monthly_salary DECIMAL(10,2),
        survey_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (tracer_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p style='color: green;'>✓ traker_study</p>";
} catch (PDOException $e) { echo "<p style='color: orange;'>traker: " . substr($e->getMessage(), 0, 30) . "</p>"; }

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS system_audit_log (
        log_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11),
        action VARCHAR(100) NOT NULL,
        module VARCHAR(50) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (log_id),
        INDEX idx_user (user_id),
        INDEX idx_module (module),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p style='color: green;'>✓ system_audit_log</p>";
} catch (PDOException $e) { echo "<p style='color: orange;'>audit_log: " . substr($e->getMessage(), 0, 30) . "</p>"; }

echo "<br><h3>Inserting Sample Data</h3>";

// Insert training batches
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM training_batches");
    if ($stmt->fetchColumn() == 0) {
        $conn->exec("INSERT INTO training_batches (batch_name, schedule_type, start_date, end_date, max_slots) VALUES
        ('Batch 2026-A', 'Morning', '2026-06-01', '2026-08-30', 25),
        ('Batch 2026-B', 'Afternoon', '2026-06-01', '2026-08-30', 25),
        ('Batch 2026-C', 'Evening', '2026-09-01', '2026-11-30', 20)");
        echo "<p style='color: green;'>✓ Training batches added</p>";
    }
} catch (PDOException $e) {}

// Insert competency units
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM competency_units");
    if ($stmt->fetchColumn() == 0) {
        $conn->exec("INSERT INTO competency_units (unit_code, unit_title, unit_description, nctype, competency_level, hrs_required) VALUES
        ('UTS301', 'Use Hand Tools', 'Proper use and maintenance of hand tools for automotive work', 'NC I', 1, 40),
        ('UTS302', 'Use Measuring Devices', 'Use of micrometers, calipers, and other measuring instruments', 'NC I', 2, 40),
        ('UTS303', 'Perform Engine Tune-Up', 'Diagnose and perform engine tune-up procedures', 'NC I', 3, 80),
        ('UTS304', 'Service Electrical Systems', 'Service automotive electrical systems and components', 'NC I', 4, 80),
        ('UTS305', 'Service Brake Systems', 'Inspect and repair brake systems', 'NC I', 5, 60),
        ('UTS306', 'Service Suspension Systems', 'Inspect and repair suspension systems', 'NC II', 1, 60)");
        echo "<p style='color: green;'>✓ Competency units added</p>";
    }
} catch (PDOException $e) {}

// Insert learning modules
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM learning_modules");
    if ($stmt->fetchColumn() == 0) {
        $conn->exec("INSERT INTO learning_modules (module_title, module_description, module_type, duration_mins, sort_order) VALUES
        ('Introduction to Automotive Systems', 'Overview of automotive mechanical systems', 'Video', 30, 1),
        ('Hand Tools Safety', 'Proper handling and safety of hand tools', 'PDF', 20, 2),
        ('Knowledge Check: Hand Tools', 'Quiz on hand tools knowledge', 'Quiz', 15, 3),
        ('Engine Fundamentals', 'Basic engine operation and components', 'Video', 45, 4),
        ('Electrical Systems Overview', 'Introduction to automotive electrical systems', 'Video', 40, 5)");
        echo "<p style='color: green;'>✓ Learning modules added</p>";
    }
} catch (PDOException $e) {}

echo "<br><h2 style='color: green;'>✓ Database Setup Complete!</h2>";
echo "<p>All 5 TESDA modules are ready:</p>";
echo "<ul>";
echo "<li>Module 1: Pre-Enrollment System</li>";
echo "<li>Module 2: Scholarship Qualification</li>";
echo "<li>Module 3: Competency-Based Evaluation</li>";
echo "<li>Module 4: Learning Management System</li>";
echo "<li>Module 5: Reports & Analytics</li>";
echo "</ul>";
echo "<p><a href='admin/admin_dashboard.php'>Go to Admin Dashboard</a></p>";