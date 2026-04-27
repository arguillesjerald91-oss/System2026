<?php
/**
 * Complete Database Setup for TESDA Auto Mechanic Training Centre
 * This script creates ALL tables required for all 5 modules.
 * Run this once to initialize the database from scratch.
 */

include __DIR__ . '/db.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>🛠️ TESDA Database Full Setup</h2>";
echo "<p>Creating all tables for Modules 1-5...</p>";

$errors = 0;
$success = 0;

// =====================================================
// HELPER FUNCTION
// =====================================================
function runQuery($conn, $sql, $label, &$success, &$errors) {
    try {
        $conn->exec($sql);
        echo "<p style='color:green;'>✓ {$label}</p>";
        $success++;
    } catch (PDOException $e) {
        // If error is "already exists", still count as success
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "<p style='color:orange;'>⚠ {$label} (already exists)</p>";
            $success++;
        } else {
            echo "<p style='color:red;'>✗ {$label}: " . htmlspecialchars(substr($e->getMessage(), 0, 100)) . "</p>";
            $errors++;
        }
    }
}

// =====================================================
// MODULE 1: PRE-ENROLLMENT SYSTEM
// =====================================================
echo "<h3>📋 Module 1: Pre-Enrollment System</h3>";

// 1. pre_enrollment_applications
runQuery($conn, "CREATE TABLE IF NOT EXISTS `pre_enrollment_applications` (
  `pre_enroll_id` int(11) NOT NULL AUTO_INCREMENT,
  `application_number` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `birth_date` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email_address` varchar(100) NOT NULL,
  `complete_address` text NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `city_municipality` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated') NOT NULL,
  `citizenship` varchar(50) NOT NULL DEFAULT 'Filipino',
  `highest_educational_attainment` enum('Elementary','High School','College Undergraduate','College Graduate','Vocational','Post Graduate') NOT NULL,
  `school_last_attended` varchar(200) DEFAULT NULL,
  `year_graduated` int(4) DEFAULT NULL,
  `employment_status` enum('Employed','Unemployed','Self-Employed','Student') NOT NULL,
  `monthly_income` decimal(10,2) DEFAULT NULL,
  `preferred_training_schedule` enum('Morning','Afternoon','Evening','Weekend') NOT NULL,
  `preferred_start_date` date DEFAULT NULL,
  `has_previous_tesda_training` tinyint(1) DEFAULT 0,
  `previous_tesa_course` varchar(200) DEFAULT NULL,
  `reason_for_applying` text NOT NULL,
  `emergency_contact_name` varchar(100) NOT NULL,
  `emergency_contact_relationship` varchar(50) NOT NULL,
  `emergency_contact_number` varchar(20) NOT NULL,
  `application_status` enum('Draft','Submitted','Pending','Under Review','For Interview','Qualified','Not Qualified','Waitlisted','Enrolled') DEFAULT 'Draft',
  `submission_date` timestamp NULL DEFAULT NULL,
  `review_date` timestamp NULL DEFAULT NULL,
  `interview_date` timestamp NULL DEFAULT NULL,
  `assessment_date` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pre_enroll_id`),
  UNIQUE KEY `application_number` (`application_number`),
  KEY `idx_application_status` (`application_status`),
  KEY `idx_preferred_schedule` (`preferred_training_schedule`),
  KEY `fk_reviewed_by` (`reviewed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'pre_enrollment_applications', $success, $errors);

// 2. pre_enrollment_requirements
runQuery($conn, "CREATE TABLE IF NOT EXISTS `pre_enrollment_requirements` (
  `requirement_id` int(11) NOT NULL AUTO_INCREMENT,
  `pre_enroll_id` int(11) NOT NULL,
  `requirement_type` enum('Birth Certificate','High School Diploma','Transcript of Records','NBI Clearance','Barangay Clearance','Medical Certificate','2x2 Picture','Valid ID','Certificate of Indigency','Parent\\'s Consent') NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `upload_date` timestamp NULL DEFAULT NULL,
  `status` enum('Pending','Uploaded','Verified','Rejected') DEFAULT 'Pending',
  `rejection_reason` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`requirement_id`),
  KEY `fk_pre_enroll_req` (`pre_enroll_id`),
  CONSTRAINT `fk_pre_enroll_req` FOREIGN KEY (`pre_enroll_id`) REFERENCES `pre_enrollment_applications` (`pre_enroll_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'pre_enrollment_requirements', $success, $errors);

// 3. pre_enrollment_assessments
runQuery($conn, "CREATE TABLE IF NOT EXISTS `pre_enrollment_assessments` (
  `assessment_id` int(11) NOT NULL AUTO_INCREMENT,
  `pre_enroll_id` int(11) NOT NULL,
  `assessment_type` enum('Aptitude Test','Skills Assessment','Physical Fitness','Mechanical Aptitude','Literacy Test','Numeracy Test') NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `max_score` decimal(5,2) DEFAULT NULL,
  `percentage_score` decimal(5,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `assessed_by` int(11) DEFAULT NULL,
  `assessment_date` timestamp NULL DEFAULT NULL,
  `status` enum('Passed','Failed','Conditional') DEFAULT NULL,
  PRIMARY KEY (`assessment_id`),
  KEY `fk_pre_enroll_assess` (`pre_enroll_id`),
  CONSTRAINT `fk_pre_enroll_assess` FOREIGN KEY (`pre_enroll_id`) REFERENCES `pre_enrollment_applications` (`pre_enroll_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'pre_enrollment_assessments', $success, $errors);

// =====================================================
// MODULE 2: SCHOLARSHIP QUALIFICATION
// =====================================================
echo "<h3>💰 Module 2: Scholarship Qualification</h3>";

runQuery($conn, "CREATE TABLE IF NOT EXISTS `scholarship_programs` (
  `program_id` int(11) NOT NULL AUTO_INCREMENT,
  `program_name` varchar(200) NOT NULL,
  `program_code` varchar(20) NOT NULL,
  `program_type` enum('Full Scholarship','Partial Scholarship','Training Allowance','Tool Allowance','Transportation Allowance') NOT NULL,
  `description` text NOT NULL,
  `eligibility_criteria` text NOT NULL,
  `benefits_description` text NOT NULL,
  `max_slots` int(11) DEFAULT NULL,
  `current_slots_taken` int(11) DEFAULT 0,
  `application_deadline` date DEFAULT NULL,
  `program_status` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  `income_requirement_max` decimal(10,2) DEFAULT NULL,
  `grade_requirement_min` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`program_id`),
  UNIQUE KEY `program_code` (`program_code`),
  KEY `idx_program_status` (`program_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'scholarship_programs', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `scholarship_applications` (
  `scholarship_app_id` int(11) NOT NULL AUTO_INCREMENT,
  `pre_enroll_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `application_number` varchar(20) NOT NULL,
  `household_income` decimal(10,2) NOT NULL,
  `household_members` int(11) NOT NULL,
  `family_head_occupation` varchar(100) DEFAULT NULL,
  `family_head_monthly_income` decimal(10,2) DEFAULT NULL,
  `special_circumstances` text DEFAULT NULL,
  `financial_need_score` decimal(5,2) DEFAULT NULL,
  `academic_score` decimal(5,2) DEFAULT NULL,
  `interview_score` decimal(5,2) DEFAULT NULL,
  `total_score` decimal(5,2) DEFAULT NULL,
  `application_status` enum('Draft','Submitted','Under Review','For Interview','Approved','Rejected','Waitlisted') DEFAULT 'Draft',
  `submission_date` timestamp NULL DEFAULT NULL,
  `review_date` timestamp NULL DEFAULT NULL,
  `interview_date` timestamp NULL DEFAULT NULL,
  `decision_date` timestamp NULL DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`scholarship_app_id`),
  UNIQUE KEY `scholarship_app_number` (`application_number`),
  KEY `fk_scholarship_pre_enroll` (`pre_enroll_id`),
  KEY `fk_scholarship_program` (`program_id`),
  CONSTRAINT `fk_scholarship_pre_enroll` FOREIGN KEY (`pre_enroll_id`) REFERENCES `pre_enrollment_applications` (`pre_enroll_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scholarship_program` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'scholarship_applications', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `scholarship_requirements` (
  `scholar_req_id` int(11) NOT NULL AUTO_INCREMENT,
  `scholarship_app_id` int(11) NOT NULL,
  `requirement_type` enum('Income Tax Return','Certificate of Indigency','Barangay Certificate','Parent\\'s Income Certificate','School Card','Character Reference','Essay','Recommendation Letter') NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `upload_date` timestamp NULL DEFAULT NULL,
  `status` enum('Pending','Uploaded','Verified','Rejected') DEFAULT 'Pending',
  `rejection_reason` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`scholar_req_id`),
  KEY `fk_scholarship_app_req` (`scholarship_app_id`),
  CONSTRAINT `fk_scholarship_app_req` FOREIGN KEY (`scholarship_app_id`) REFERENCES `scholarship_applications` (`scholarship_app_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'scholarship_requirements', $success, $errors);

// =====================================================
// MODULE 3: COMPETENCY-BASED EVALUATION
// =====================================================
echo "<h3>📊 Module 3: Competency-Based Evaluation</h3>";

runQuery($conn, "CREATE TABLE IF NOT EXISTS `tesda_competency_standards` (
  `competency_id` int(11) NOT NULL AUTO_INCREMENT,
  `competency_code` varchar(20) NOT NULL,
  `competency_title` varchar(200) NOT NULL,
  `competency_category` enum('Basic Competencies','Common Competencies','Core Competencies','Elective Competencies') NOT NULL,
  `description` text NOT NULL,
  `performance_criteria` text NOT NULL,
  `evidence_requirements` text NOT NULL,
  `assessment_methods` text NOT NULL,
  `required_hours` int(11) DEFAULT NULL,
  `prerequisite_competency_id` int(11) DEFAULT NULL,
  `competency_level` enum('NC I','NC II','NC III','NC IV','Diploma') DEFAULT NULL,
  `status` enum('Active','Inactive','Under Review') DEFAULT 'Active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`competency_id`),
  UNIQUE KEY `competency_code` (`competency_code`),
  KEY `idx_competency_category` (`competency_category`),
  KEY `idx_competency_level` (`competency_level`),
  KEY `fk_prerequisite_competency` (`prerequisite_competency_id`),
  CONSTRAINT `fk_prerequisite_competency` FOREIGN KEY (`prerequisite_competency_id`) REFERENCES `tesda_competency_standards` (`competency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'tesda_competency_standards', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `training_modules` (
  `module_id` int(11) NOT NULL AUTO_INCREMENT,
  `module_code` varchar(20) NOT NULL,
  `module_title` varchar(200) NOT NULL,
  `competency_id` int(11) NOT NULL,
  `module_description` text NOT NULL,
  `learning_objectives` text NOT NULL,
  `module_duration_hours` int(11) NOT NULL,
  `module_type` enum('Theory','Practical','Assessment','Combined') NOT NULL,
  `difficulty_level` enum('Beginner','Intermediate','Advanced') NOT NULL,
  `prerequisite_module_id` int(11) DEFAULT NULL,
  `module_order` int(11) DEFAULT NULL,
  `delivery_method` enum('Face-to-Face','Online','Blended','Self-Paced') DEFAULT 'Face-to-Face',
  `module_status` enum('Draft','Active','Inactive','Under Review') DEFAULT 'Draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`module_id`),
  UNIQUE KEY `module_code` (`module_code`),
  KEY `fk_module_competency` (`competency_id`),
  KEY `idx_module_type` (`module_type`),
  KEY `idx_difficulty_level` (`difficulty_level`),
  CONSTRAINT `fk_module_competency` FOREIGN KEY (`competency_id`) REFERENCES `tesda_competency_standards` (`competency_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'training_modules', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `module_lessons` (
  `lesson_id` int(11) NOT NULL AUTO_INCREMENT,
  `module_id` int(11) NOT NULL,
  `lesson_title` varchar(200) NOT NULL,
  `lesson_content` longtext NOT NULL,
  `lesson_type` enum('Text','Video','Presentation','Interactive','Assessment') NOT NULL,
  `lesson_duration_minutes` int(11) DEFAULT NULL,
  `lesson_order` int(11) DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `lesson_status` enum('Draft','Published','Archived') DEFAULT 'Draft',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lesson_id`),
  KEY `fk_lesson_module` (`module_id`),
  CONSTRAINT `fk_lesson_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`module_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'module_lessons', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `module_assessments` (
  `assessment_id` int(11) NOT NULL AUTO_INCREMENT,
  `module_id` int(11) NOT NULL,
  `assessment_title` varchar(200) NOT NULL,
  `assessment_type` enum('Quiz','Practical Demonstration','Written Test','Portfolio','Peer Assessment','Self Assessment') NOT NULL,
  `total_items` int(11) DEFAULT NULL,
  `passing_score` decimal(5,2) NOT NULL,
  `time_limit_minutes` int(11) DEFAULT NULL,
  `attempts_allowed` int(11) DEFAULT 3,
  `assessment_instructions` text DEFAULT NULL,
  `assessment_status` enum('Draft','Published','Archived') DEFAULT 'Draft',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`assessment_id`),
  KEY `fk_assessment_module` (`module_id`),
  CONSTRAINT `fk_assessment_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`module_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'module_assessments', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `assessment_questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('Multiple Choice','True/False','Short Answer','Essay','Practical Task','Identification') NOT NULL,
  `question_order` int(11) DEFAULT NULL,
  `points_value` decimal(5,2) DEFAULT NULL,
  `correct_answer` text DEFAULT NULL,
  `question_explanation` text DEFAULT NULL,
  `difficulty_level` enum('Easy','Medium','Hard') DEFAULT 'Medium',
  PRIMARY KEY (`question_id`),
  KEY `fk_question_assessment` (`assessment_id`),
  CONSTRAINT `fk_question_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `module_assessments` (`assessment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'assessment_questions', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `question_options` (
  `option_id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` varchar(500) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `option_order` int(11) DEFAULT NULL,
  PRIMARY KEY (`option_id`),
  KEY `fk_option_question` (`question_id`),
  CONSTRAINT `fk_option_question` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'question_options', $success, $errors);

// =====================================================
// MODULE 4: LMS - LEARNING MODULES
// =====================================================
echo "<h3>📚 Module 4: Learning Management System</h3>";

runQuery($conn, "CREATE TABLE IF NOT EXISTS `access_levels` (
  `access_id` int(11) NOT NULL AUTO_INCREMENT,
  `level_name` varchar(50) NOT NULL,
  `level_description` text NOT NULL,
  `access_permissions` json NOT NULL,
  `level_hierarchy` int(11) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  PRIMARY KEY (`access_id`),
  UNIQUE KEY `level_name` (`level_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'access_levels', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `user_access_assignments` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `access_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `expiry_date` timestamp NULL DEFAULT NULL,
  `status` enum('Active','Inactive','Expired') DEFAULT 'Active',
  `user_type` enum('student','admin','instructor') NOT NULL DEFAULT 'student',
  `assigned_by_type` enum('student','admin','instructor') DEFAULT 'admin',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `unique_user_access` (`user_id`, `user_type`, `access_id`, `status`),
  KEY `fk_user_access_user` (`user_id`, `user_type`),
  KEY `fk_user_access_level` (`access_id`),
  KEY `idx_access_status` (`status`),
  KEY `idx_expiry_date` (`expiry_date`),
  CONSTRAINT `fk_user_access_level` FOREIGN KEY (`access_id`) REFERENCES `access_levels` (`access_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'user_access_assignments', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `module_access_permissions` (
  `permission_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('student','admin','instructor') NOT NULL DEFAULT 'student',
  `module_id` int(11) NOT NULL,
  `access_type` enum('View','Download','Edit','Assess','Admin') NOT NULL,
  `granted_by` int(11) DEFAULT NULL,
  `granted_by_type` enum('student','admin','instructor') DEFAULT 'admin',
  `granted_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `expiry_date` timestamp NULL DEFAULT NULL,
  `access_status` enum('Active','Inactive','Expired') DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `unique_module_access` (`user_id`, `user_type`, `module_id`, `access_type`, `access_status`),
  KEY `fk_module_access_user` (`user_id`, `user_type`),
  KEY `fk_module_access_module` (`module_id`),
  KEY `idx_module_access_status` (`access_status`),
  KEY `idx_module_expiry_date` (`expiry_date`),
  CONSTRAINT `fk_module_access_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`module_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'module_access_permissions', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `access_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `access_type` varchar(50) NOT NULL,
  `resource_type` enum('Module','Lesson','Assessment','Resource','System') NOT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `access_action` enum('Login','Logout','View','Download','Upload','Edit','Delete','Submit','Assess') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `access_timestamp` timestamp DEFAULT CURRENT_TIMESTAMP,
  `access_status` enum('Success','Failed','Blocked') DEFAULT 'Success',
  `failure_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_access_user` (`user_id`),
  KEY `idx_access_timestamp` (`access_timestamp`),
  KEY `idx_access_action` (`access_action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'access_logs', $success, $errors);

// =====================================================
// MODULE 5: REPORTS & ANALYTICS - SUPPORT TABLES
// =====================================================
echo "<h3>📈 Module 5: Reports & Analytics</h3>";

runQuery($conn, "CREATE TABLE IF NOT EXISTS `system_audit_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_module` (`module`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'system_audit_log', $success, $errors);

// =====================================================
// AUTO MECHANIC SPECIFIC TABLES
// =====================================================
echo "<h3>🔧 Auto Mechanic Training Centre Tables</h3>";

runQuery($conn, "CREATE TABLE IF NOT EXISTS `auto_mechanic_programs` (
  `program_id` int(11) NOT NULL AUTO_INCREMENT,
  `program_code` varchar(20) NOT NULL,
  `program_title` varchar(200) NOT NULL,
  `program_description` text NOT NULL,
  `tesda_qualification_code` varchar(20) NOT NULL,
  `program_duration_hours` int(11) NOT NULL,
  `program_level` enum('NC I','NC II','NC III','NC IV','Diploma') NOT NULL,
  `entry_requirements` text NOT NULL,
  `career_opportunities` text NOT NULL,
  `industry_partners` text DEFAULT NULL,
  `equipment_required` text DEFAULT NULL,
  `training_fee` decimal(10,2) DEFAULT NULL,
  `program_status` enum('Active','Inactive','Under Review') DEFAULT 'Active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`program_id`),
  UNIQUE KEY `program_code` (`program_code`),
  KEY `idx_program_level` (`program_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'auto_mechanic_programs', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `training_batches` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `program_id` int(11) NOT NULL,
  `batch_code` varchar(20) NOT NULL,
  `batch_name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `training_schedule` enum('Morning','Afternoon','Evening','Weekend','Flexible') NOT NULL,
  `training_days` varchar(50) DEFAULT NULL,
  `max_trainees` int(11) NOT NULL,
  `current_enrolled` int(11) DEFAULT 0,
  `instructor_id` int(11) DEFAULT NULL,
  `training_venue` varchar(200) DEFAULT NULL,
  `batch_status` enum('Upcoming','Ongoing','Completed','Cancelled','Suspended') DEFAULT 'Upcoming',
  `enrollment_deadline` date DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`batch_id`),
  UNIQUE KEY `batch_code` (`batch_code`),
  KEY `fk_batch_program` (`program_id`),
  KEY `fk_batch_instructor` (`instructor_id`),
  CONSTRAINT `fk_batch_program` FOREIGN KEY (`program_id`) REFERENCES `auto_mechanic_programs` (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'training_batches', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `workshop_equipment` (
  `equipment_id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_code` varchar(20) NOT NULL,
  `equipment_name` varchar(200) NOT NULL,
  `equipment_category` enum('Hand Tools','Power Tools','Diagnostic Equipment','Lifting Equipment','Safety Equipment','Specialized Tools') NOT NULL,
  `equipment_description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `available_quantity` int(11) NOT NULL DEFAULT 1,
  `manufacturer` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(10,2) DEFAULT NULL,
  `maintenance_schedule` varchar(100) DEFAULT NULL,
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `equipment_status` enum('Available','In Use','Under Maintenance','Damaged','Lost','Retired') DEFAULT 'Available',
  `location` varchar(100) DEFAULT NULL,
  `responsible_person` int(11) DEFAULT NULL,
  PRIMARY KEY (`equipment_id`),
  UNIQUE KEY `equipment_code` (`equipment_code`),
  KEY `idx_equipment_category` (`equipment_category`),
  KEY `idx_equipment_status` (`equipment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'workshop_equipment', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `equipment_reservations` (
  `reservation_id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `quantity_reserved` int(11) NOT NULL DEFAULT 1,
  `reservation_status` enum('Pending','Approved','Rejected','Completed','Cancelled') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `return_date` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reservation_id`),
  KEY `fk_reservation_equipment` (`equipment_id`),
  KEY `fk_reservation_user` (`user_id`),
  KEY `fk_reservation_batch` (`batch_id`),
  CONSTRAINT `fk_reservation_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `workshop_equipment` (`equipment_id`),
  CONSTRAINT `fk_reservation_user` FOREIGN KEY (`user_id`) REFERENCES `student` (`StudID`),
  CONSTRAINT `fk_reservation_batch` FOREIGN KEY (`batch_id`) REFERENCES `training_batches` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'equipment_reservations', $success, $errors);

// =====================================================
// STUDENT PROGRESS TRACKING - CRITICAL FOR learning_modules.php
// =====================================================
echo "<h3>📈 Student Progress Tracking</h3>";

runQuery($conn, "CREATE TABLE IF NOT EXISTS `student_program_enrollments` (
  `enrollment_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `pre_enroll_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `enrollment_date` date NOT NULL,
  `enrollment_status` enum('Active','On Leave','Dropped','Completed','Transferred') DEFAULT 'Active',
  `completion_date` date DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `certification_status` enum('Not Eligible','In Progress','Issued','Failed') DEFAULT 'Not Eligible',
  `certificate_number` varchar(50) DEFAULT NULL,
  `certificate_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`enrollment_id`),
  KEY `fk_enrollment_student` (`student_id`),
  KEY `fk_enrollment_pre_enroll` (`pre_enroll_id`),
  KEY `fk_enrollment_batch` (`batch_id`),
  CONSTRAINT `fk_enrollment_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`StudID`),
  CONSTRAINT `fk_enrollment_pre_enroll` FOREIGN KEY (`pre_enroll_id`) REFERENCES `pre_enrollment_applications` (`pre_enroll_id`),
  CONSTRAINT `fk_enrollment_batch` FOREIGN KEY (`batch_id`) REFERENCES `training_batches` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'student_program_enrollments', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `student_module_progress` (
  `progress_id` int(11) NOT NULL AUTO_INCREMENT,
  `enrollment_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `start_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `completion_date` timestamp NULL DEFAULT NULL,
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `status` enum('Not Started','In Progress','Completed','Failed','Dropped') DEFAULT 'Not Started',
  `final_score` decimal(5,2) DEFAULT NULL,
  `attempts_count` int(11) DEFAULT 0,
  `last_access_date` timestamp NULL DEFAULT NULL,
  `time_spent_minutes` int(11) DEFAULT 0,
  `instructor_notes` text DEFAULT NULL,
  PRIMARY KEY (`progress_id`),
  KEY `fk_progress_enrollment` (`enrollment_id`),
  KEY `fk_progress_module` (`module_id`),
  CONSTRAINT `fk_progress_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `student_program_enrollments` (`enrollment_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_progress_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`module_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'student_module_progress', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `student_lesson_progress` (
  `lesson_progress_id` int(11) NOT NULL AUTO_INCREMENT,
  `module_progress_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `access_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `completion_date` timestamp NULL DEFAULT NULL,
  `status` enum('Not Started','In Progress','Completed') DEFAULT 'Not Started',
  `time_spent_minutes` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`lesson_progress_id`),
  KEY `fk_lesson_progress_module` (`module_progress_id`),
  KEY `fk_lesson_progress_lesson` (`lesson_id`),
  CONSTRAINT `fk_lesson_progress_module` FOREIGN KEY (`module_progress_id`) REFERENCES `student_module_progress` (`progress_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lesson_progress_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `module_lessons` (`lesson_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'student_lesson_progress', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `student_assessment_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `module_progress_id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `attempt_number` int(11) NOT NULL,
  `start_time` timestamp DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `percentage_score` decimal(5,2) DEFAULT NULL,
  `status` enum('In Progress','Submitted','Graded','Failed','Passed') DEFAULT 'In Progress',
  `graded_by` int(11) DEFAULT NULL,
  `graded_date` timestamp NULL DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  PRIMARY KEY (`attempt_id`),
  KEY `fk_attempt_module_progress` (`module_progress_id`),
  KEY `fk_attempt_assessment` (`assessment_id`),
  CONSTRAINT `fk_attempt_module_progress` FOREIGN KEY (`module_progress_id`) REFERENCES `student_module_progress` (`progress_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attempt_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `module_assessments` (`assessment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'student_assessment_attempts', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `student_assessment_answers` (
  `answer_id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `student_answer` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `points_earned` decimal(5,2) DEFAULT NULL,
  `answer_time` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`answer_id`),
  KEY `fk_answer_attempt` (`attempt_id`),
  KEY `fk_answer_question` (`question_id`),
  CONSTRAINT `fk_answer_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `student_assessment_attempts` (`attempt_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_answer_question` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'student_assessment_answers', $success, $errors);

runQuery($conn, "CREATE TABLE IF NOT EXISTS `competency_achievements` (
  `achievement_id` int(11) NOT NULL AUTO_INCREMENT,
  `enrollment_id` int(11) NOT NULL,
  `competency_id` int(11) NOT NULL,
  `assessment_date` date NOT NULL,
  `assessor_id` int(11) NOT NULL,
  `assessment_method` enum('Direct Observation','Portfolio','Written Test','Practical Demonstration','Third Party') NOT NULL,
  `assessment_result` enum('Competent','Not Yet Competent','Exceeded Expectations') NOT NULL,
  `evidence_collected` text DEFAULT NULL,
  `assessor_comments` text DEFAULT NULL,
  `certificate_issued` tinyint(1) DEFAULT 0,
  `certificate_date` date DEFAULT NULL,
  `certificate_number` varchar(50) DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  PRIMARY KEY (`achievement_id`),
  KEY `fk_achievement_enrollment` (`enrollment_id`),
  KEY `fk_achievement_competency` (`competency_id`),
  KEY `fk_achievement_assessor` (`assessor_id`),
  CONSTRAINT `fk_achievement_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `student_program_enrollments` (`enrollment_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_achievement_competency` FOREIGN KEY (`competency_id`) REFERENCES `tesda_competency_standards` (`competency_id`),
  CONSTRAINT `fk_achievement_assessor` FOREIGN KEY (`assessor_id`) REFERENCES `admins` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'competency_achievements', $success, $errors);

// =====================================================
// SAMPLE DATA INSERTION (Module 1 - Auto Mechanic Programs)
// =====================================================
echo "<h3>📦 Inserting Sample Data</h3>";

// Check if sample data already exists
$progCount = $conn->query("SELECT COUNT(*) FROM auto_mechanic_programs")->fetchColumn();
if ($progCount == 0) {
    runQuery($conn, "INSERT INTO `auto_mechanic_programs` (`program_code`, `program_title`, `program_description`, `tesda_qualification_code`, `program_duration_hours`, `program_level`, `entry_requirements`, `career_opportunities`) VALUES
    ('AM-NCII-001', 'Automotive Servicing NC II', 'Comprehensive training in automotive servicing including engine repair, transmission systems, and brake systems', 'COC-AT-512-001', 432, 'NC II', 'High school graduate, physically fit, with basic mechanical aptitude', 'Automotive mechanic, service technician, maintenance supervisor, diagnostic specialist'),
    ('AM-NCI-001', 'Automotive Servicing NC I', 'Basic automotive servicing fundamentals for beginners', 'COC-AT-511-001', 216, 'NC I', 'Elementary graduate, at least 18 years old', 'Automotive helper, apprentice mechanic, service attendant'),
    ('AM-NCIII-001', 'Automotive Servicing NC III', 'Advanced automotive diagnostics and repair', 'COC-AT-513-001', 648, 'NC III', 'Automotive Servicing NC II certified, 2 years experience', 'Master technician, service manager, diagnostic expert, training instructor')", 'auto_mechanic_programs sample', $success, $errors);
}

// Access levels
$accessCount = $conn->query("SELECT COUNT(*) FROM access_levels")->fetchColumn();
if ($accessCount == 0) {
    runQuery($conn, "INSERT INTO `access_levels` (`level_name`, `level_description`, `access_permissions`, `level_hierarchy`) VALUES
    ('Student', 'Basic student access to enrolled modules', '{\"view_modules\": true, \"view_lessons\": true, \"take_assessments\": true, \"view_progress\": true}', 1),
    ('Instructor', 'Instructor access to teaching resources', '{\"view_modules\": true, \"edit_modules\": true, \"grade_assessments\": true, \"view_progress\": true, \"manage_students\": true}', 2),
    ('Administrator', 'Full system administration access', '{\"all_access\": true}', 3),
    ('Guest', 'Limited guest access for demo purposes', '{\"view_public_content\": true}', 0)", 'access_levels sample', $success, $errors);
}

// Competency standards
$compCount = $conn->query("SELECT COUNT(*) FROM tesda_competency_standards")->fetchColumn();
if ($compCount == 0) {
    runQuery($conn, "INSERT INTO `tesda_competency_standards` (`competency_code`, `competency_title`, `competency_category`, `description`, `performance_criteria`, `evidence_requirements`, `assessment_methods`, `required_hours`, `competency_level`) VALUES
    ('AM-BC-001', 'Apply Safety Practices in Automotive Workplace', 'Basic Competencies', 'Implement safety procedures and practices in automotive workshop environment', '1.1 Identify workplace hazards, 1.2 Use appropriate PPE, 1.3 Follow emergency procedures', 'Observation checklist, safety test, practical demonstration', 'Direct observation, written test, portfolio', 40, 'NC I'),
    ('AM-CC-001', 'Use Hand Tools and Equipment', 'Common Competencies', 'Properly use and maintain automotive hand tools and basic equipment', '2.1 Select appropriate tools, 2.2 Use tools safely, 2.3 Maintain tools', 'Tool identification test, practical demonstration, maintenance log', 'Practical demonstration, oral questioning', 60, 'NC I'),
    ('AM-CO-001', 'Perform Engine Maintenance', 'Core Competencies', 'Conduct routine engine maintenance and basic repairs', '3.1 Inspect engine components, 3.2 Replace filters and fluids, 3.3 Perform tune-up', 'Maintenance records, practical demonstration, written test', 'Direct observation, portfolio, third party assessment', 80, 'NC II'),
    ('AM-CO-002', 'Repair Brake System', 'Core Competencies', 'Diagnose and repair automotive brake systems', '4.1 Inspect brake components, 4.2 Replace brake pads/shoes, 4.3 Bleed brake system', 'Diagnostic reports, repair documentation, practical test', 'Direct observation, portfolio, written test', 120, 'NC II')", 'tesda_competency_standards sample', $success, $errors);
}

// Training batches
$batchCount = $conn->query("SELECT COUNT(*) FROM training_batches")->fetchColumn();
if ($batchCount == 0) {
    runQuery($conn, "INSERT INTO `training_batches` (`batch_code`, `batch_name`, `program_id`, `start_date`, `end_date`, `training_schedule`, `max_trainees`, `batch_status`) VALUES
    ('BATCH-2026A', 'Batch 2026-A Morning', 1, '2026-06-01', '2026-08-30', 'Morning', 25, 'Upcoming'),
    ('BATCH-2026B', 'Batch 2026-B Afternoon', 1, '2026-06-01', '2026-08-30', 'Afternoon', 25, 'Upcoming'),
    ('BATCH-2026C', 'Batch 2026-C Evening', 1, '2026-09-01', '2026-11-30', 'Evening', 20, 'Upcoming')", 'training_batches sample', $success, $errors);
}

echo "<hr>";
echo "<h2 style='color:green;'>✅ Setup Complete!</h2>";
echo "<p><strong>Tables created:</strong> {$success}</p>";
echo "<p><strong>Errors/warnings:</strong> {$errors}</p>";
echo "<p><a href='admin/admin_dashboard.php'>→ Go to Admin Dashboard</a></p>";
echo "<p><a href='admin/pre_enrollment_management.php'>→ Manage Pre-Enrollments</a></p>";
echo "<p><small>Note: If you see errors about duplicate tables, they may already exist. Check your database.</small></p>";
