-- =====================================================
-- COMPLETE DATABASE SETUP FOR TESDA AUTO MECHANIC SYSTEM
-- =====================================================
-- This is a standalone, all-in-one SQL script that creates
-- all tables, relationships, sample data, and fixes required
-- for Modules 1-5 to function fully.
--
-- INSTRUCTIONS:
-- 1. Open phpMyAdmin or MySQL command line
-- 2. Select/Create database: tesda_auto_mechanic
-- 3. Import/run this entire file
-- =====================================================

-- Use the database (creates if doesn't exist)
CREATE DATABASE IF NOT EXISTS `tesda_auto_mechanic` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `tesda_auto_mechanic`;

-- =====================================================
-- DISABLE FOREIGN KEY CHECKS FOR CLEAN SETUP
-- =====================================================
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- DROP EXISTING TABLES (clean slate)
-- =====================================================
DROP TABLE IF EXISTS `student_assessment_answers`;
DROP TABLE IF EXISTS `student_assessment_attempts`;
DROP TABLE IF EXISTS `student_lesson_progress`;
DROP TABLE IF EXISTS `student_module_progress`;
DROP TABLE IF EXISTS `student_program_enrollments`;
DROP TABLE IF EXISTS `competency_achievements`;
DROP TABLE IF EXISTS `pre_enrollment_assessments`;
DROP TABLE IF EXISTS `pre_enrollment_requirements`;
DROP TABLE IF EXISTS `pre_enrollment_applications`;
DROP TABLE IF EXISTS `scholarship_requirements`;
DROP TABLE IF EXISTS `scholarship_applications`;
DROP TABLE IF EXISTS `scholarship_programs`;
DROP TABLE IF EXISTS `equipment_reservations`;
DROP TABLE IF EXISTS `workshop_equipment`;
DROP TABLE IF EXISTS `question_options`;
DROP TABLE IF EXISTS `assessment_questions`;
DROP TABLE IF EXISTS `module_assessments`;
DROP TABLE IF EXISTS `module_lessons`;
DROP TABLE IF EXISTS `training_modules`;
DROP TABLE IF EXISTS `learning_modules`;
DROP TABLE IF EXISTS `competency_units`;
DROP TABLE IF EXISTS `tesda_competency_standards`;
DROP TABLE IF EXISTS `training_batches`;
DROP TABLE IF EXISTS `auto_mechanic_programs`;
DROP TABLE IF EXISTS `access_logs`;
DROP TABLE IF EXISTS `module_access_permissions`;
DROP TABLE IF EXISTS `user_access_assignments`;
DROP TABLE IF EXISTS `access_levels`;
DROP TABLE IF EXISTS `system_audit_log`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `student`;
DROP TABLE IF EXISTS `users`;

-- =====================================================
-- CORE USER MANAGEMENT TABLES
-- =====================================================

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `user_type` ENUM('student','admin','instructor','instructional_unit','support_staff','trainee') NOT NULL DEFAULT 'student',
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `status` ENUM('active','inactive','suspended') DEFAULT 'active',
  `email_verified` TINYINT(1) DEFAULT 0,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `verification_token` VARCHAR(255) DEFAULT NULL,
  `twofa_enabled` TINYINT(1) DEFAULT 0,
  `twofa_secret` VARCHAR(50) DEFAULT NULL,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  INDEX `idx_username` (`username`),
  INDEX `idx_email` (`email`),
  INDEX `idx_user_type` (`user_type`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `Fname` VARCHAR(100) NOT NULL,
  `Lname` VARCHAR(100) NOT NULL,
  `Email` VARCHAR(150) NOT NULL,
  `Password` VARCHAR(255) NOT NULL,
  `Role` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  INDEX `idx_user_id` (`user_id`),
  CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `student` (
  `StudID` INT(11) NOT NULL,
  `StudType` VARCHAR(20) DEFAULT NULL,
  `SchoolID` VARCHAR(20) DEFAULT NULL,
  `FName` VARCHAR(50) DEFAULT NULL,
  `LName` VARCHAR(50) DEFAULT NULL,
  `MName` VARCHAR(50) DEFAULT NULL,
  `ExtName` VARCHAR(10) DEFAULT NULL,
  `Age` INT(3) DEFAULT NULL,
  `DateOfBirth` DATE DEFAULT NULL,
  `PlaceOfBirth` VARCHAR(100) DEFAULT NULL,
  `Province` VARCHAR(50) DEFAULT NULL,
  `Sex` VARCHAR(10) DEFAULT NULL,
  `CivilStatus` VARCHAR(20) DEFAULT NULL,
  `Religion` VARCHAR(20) DEFAULT NULL,
  `Nationality` VARCHAR(50) DEFAULT NULL,
  `PhoneNo` VARCHAR(15) DEFAULT NULL,
  `EmailAddr` VARCHAR(100) DEFAULT NULL,
  `FathersName` VARCHAR(100) DEFAULT NULL,
  `MothersMName` VARCHAR(100) DEFAULT NULL,
  `Course` VARCHAR(100) DEFAULT NULL,
  `YearLvl` VARCHAR(10) DEFAULT NULL,
  `Semester` VARCHAR(10) DEFAULT NULL,
  `Block` VARCHAR(20) DEFAULT NULL,
  `DeptID` INT(11) DEFAULT NULL,
  `Summer` TINYINT(1) DEFAULT 0,
  `CHEDScholar` VARCHAR(10) DEFAULT 'No',
  `EnrollmentClass` VARCHAR(20) DEFAULT 'Regular',
  `Address` VARCHAR(150) DEFAULT NULL,
  `Guardian` VARCHAR(100) DEFAULT NULL,
  `LastSchoolAtt` VARCHAR(100) DEFAULT NULL,
  `QRcode` VARCHAR(255) DEFAULT NULL,
  `IsGraduate` TINYINT(1) DEFAULT 0,
  `GrantAuthorityNumber` VARCHAR(100) DEFAULT NULL,
  `YearGranted` VARCHAR(10) DEFAULT NULL,
  `DateGranted` TIMESTAMP NULL DEFAULT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `username` VARCHAR(100) DEFAULT NULL,
  `password` VARCHAR(255) DEFAULT NULL,
  `program_id` INT(11) NOT NULL DEFAULT 0,
  `account_status` ENUM('Active','Inactive','Pending','Suspended') DEFAULT 'Pending',
  `last_login` DATETIME DEFAULT NULL,
  `reset_token` VARCHAR(100) DEFAULT NULL,
  `reset_token_expires` DATETIME DEFAULT NULL,
  `failed_login_attempts` TINYINT(4) DEFAULT 0,
  `account_locked_until` DATETIME DEFAULT NULL,
  `email_verified` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`StudID`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_email` (`EmailAddr`),
  CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample data for core tables
INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `user_type`, `first_name`, `last_name`, `status`, `email_verified`) VALUES
(1, 'admin', '$2y$10$lhO277aygaA5obXWLqIj5usjxBFzS1wFXodu8JCYZpzfiq/dpKwdO', 'admin@tesda.gov.ph', 'admin', 'System', 'Administrator', 'active', 1),
(2, 'student1', '$2y$10$ibmTluGyIUvfggkdF1hHq.13oLbD877Zjgs6td0edQsII3T2Gq.PS', 'student1@tesda.gov.ph', 'student', 'Juan', 'Dela Cruz', 'active', 1),
(3, 'instructor1', '$2y$10$wgHb1wuczhxjdjWmuGASous20rOtlqX1N9XbncJLXzBR3yfA3E.52', 'instructor1@tesda.gov.ph', 'instructor', 'Pedro', 'Garcia', 'active', 1),
(4, 'trainee1', '$2y$10$S1LlaaYL/TnMaSqK0.yjnuLmN6pz2d12./DWx.ycNFN8lVMOBri6S', 'trainee1@tesda.gov.ph', 'trainee', 'Maria', 'Santos', 'active', 1),
(5, 'unit1', '$2y$10$yyq6gpGrmfdoCQR7pNs06.nZoaIWFaUkMdRuVdWACte4Iw.EZXR3O', 'unit1@tesda.gov.ph', 'instructional_unit', 'Roberto', 'Mendoza', 'active', 1),
(6, 'support1', '$2y$10$XPMNO0.dEG5ciAFvuPcp9.lQdNNhdjAuK8IwrYIpIIMen0AcxFVqa', 'support1@tesda.gov.ph', 'support_staff', 'Carmen', 'Lopez', 'active', 1);

INSERT INTO `admins` (`admin_id`, `user_id`, `Fname`, `Lname`, `Email`, `Password`, `Role`) VALUES
(1, 1, 'System', 'Administrator', 'admin@tesda.gov.ph', '$2y$10$lhO277aygaA5obXWLqIj5usjxBFzS1wFXodu8JCYZpzfiq/dpKwdO', 'Admin');

INSERT INTO `student` (`StudID`, `user_id`, `FName`, `LName`, `EmailAddr`, `Course`, `YearLvl`, `Semester`, `SchoolID`, `PhoneNo`, `account_status`, `program_id`) VALUES
(2, 2, 'Juan', 'Dela Cruz', 'student1@tesda.gov.ph', 'Automotive Servicing NC II', 'NC II', '1st Sem', '22-000001', '09171234567', 'Active', 1);

-- =====================================================
-- MODULE 1: PRE-ENROLLMENT SYSTEM
-- =====================================================

-- 1.1 Pre-enrollment applications (MAIN TABLE)
CREATE TABLE `pre_enrollment_applications` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 1.2 Pre-enrollment requirements (documents)
CREATE TABLE `pre_enrollment_requirements` (
  `requirement_id` int(11) NOT NULL AUTO_INCREMENT,
  `pre_enroll_id` int(11) NOT NULL,
  `requirement_type` enum('Birth Certificate','High School Diploma','Transcript of Records','NBI Clearance','Barangay Clearance','Medical Certificate','2x2 Picture','Valid ID','Certificate of Indigency','Parent\'s Consent') NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 1.3 Pre-enrollment assessments (tests)
CREATE TABLE `pre_enrollment_assessments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- MODULE 2: SCHOLARSHIP QUALIFICATION
-- =====================================================

-- 2.1 Scholarship programs
CREATE TABLE `scholarship_programs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.2 Scholarship applications
CREATE TABLE `scholarship_applications` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2.3 Scholarship requirements
CREATE TABLE `scholarship_requirements` (
  `scholar_req_id` int(11) NOT NULL AUTO_INCREMENT,
  `scholarship_app_id` int(11) NOT NULL,
  `requirement_type` enum('Income Tax Return','Certificate of Indigency','Barangay Certificate','Parent\'s Income Certificate','School Card','Character Reference','Essay','Recommendation Letter') NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- MODULE 3: COMPETENCY-BASED EVALUATION
-- =====================================================

-- 3.1 Competency units (simple version used by student pages)
CREATE TABLE `competency_units` (
  `unit_id` int(11) NOT NULL AUTO_INCREMENT,
  `unit_code` varchar(20) NOT NULL,
  `unit_title` varchar(200) NOT NULL,
  `unit_description` text NOT NULL,
  `competency_category` enum('Basic Competencies','Common Competencies','Core Competencies','Elective Competencies') NOT NULL DEFAULT 'Core Competencies',
  `nctype` enum('NC I','NC II','NC III','NC IV','Diploma') NOT NULL,
  `competency_level` int(11) DEFAULT 1,
  `hrs_required` int(11) DEFAULT 40,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`),
  UNIQUE KEY `unit_code` (`unit_code`),
  KEY `idx_nctype` (`nctype`),
  KEY `idx_code` (`unit_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3.2 TESDA competency standards (full version)
CREATE TABLE `tesda_competency_standards` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3.3 Competency assessments (used by student my_competencies page)
CREATE TABLE `competency_assessments` (
  `assessment_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `pre_assessment_score` decimal(5,2) DEFAULT NULL,
  `practical_score` decimal(5,2) DEFAULT NULL,
  `final_score` decimal(5,2) DEFAULT NULL,
  `assessment_status` enum('Not Started','In Progress','Passed','Failed','RPL') DEFAULT 'Not Started',
  `assessment_date` timestamp NULL DEFAULT NULL,
  `assessed_by` int(11) DEFAULT NULL,
  `assessor_name` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`assessment_id`),
  KEY `fk_assessment_user` (`user_id`),
  KEY `fk_assessment_unit` (`unit_id`),
  CONSTRAINT `fk_assessment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_assessment_unit` FOREIGN KEY (`unit_id`) REFERENCES `competency_units` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3.4 Competency achievements (certificates issued)
CREATE TABLE `competency_achievements` (
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
  CONSTRAINT `fk_achievement_assessor` FOREIGN KEY (`assessor_id`) REFERENCES `admins` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- MODULE 4: LMS - LEARNING MANAGEMENT SYSTEM
-- =====================================================

-- 4.1 Training modules (note: was 'learning_modules' in some installs)
CREATE TABLE `training_modules` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4.2 Module lessons
CREATE TABLE `module_lessons` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4.3 Module assessments
CREATE TABLE `module_assessments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4.4 Assessment questions
CREATE TABLE `assessment_questions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4.5 Question options (for multiple choice)
CREATE TABLE `question_options` (
  `option_id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` varchar(500) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `option_order` int(11) DEFAULT NULL,
  PRIMARY KEY (`option_id`),
  KEY `fk_option_question` (`question_id`),
  CONSTRAINT `fk_option_question` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- STUDENT PROGRESS TRACKING (Critical for student pages)
-- =====================================================

-- 4.6 Student enrollments in training programs
CREATE TABLE `student_program_enrollments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4.7 Student module progress
CREATE TABLE `student_module_progress` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4.8 Student lesson progress
CREATE TABLE `student_lesson_progress` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4.9 Student assessment attempts
CREATE TABLE `student_assessment_attempts` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4.10 Student assessment answers
CREATE TABLE `student_assessment_answers` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- MODULE 5: ACCESS CONTROL & LOGGING
-- =====================================================

-- 5.1 Access levels
CREATE TABLE `access_levels` (
  `access_id` int(11) NOT NULL AUTO_INCREMENT,
  `level_name` varchar(50) NOT NULL,
  `level_description` text NOT NULL,
  `access_permissions` json NOT NULL,
  `level_hierarchy` int(11) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  PRIMARY KEY (`access_id`),
  UNIQUE KEY `level_name` (`level_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5.2 User access assignments
CREATE TABLE `user_access_assignments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5.3 Module access permissions
CREATE TABLE `module_access_permissions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5.4 Access logs
CREATE TABLE `access_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- AUTO MECHANIC TRAINING CENTRE SPECIFIC TABLES
-- =====================================================

-- 5.5 Training programs (auto mechanic)
CREATE TABLE `auto_mechanic_programs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5.6 Training batches
CREATE TABLE `training_batches` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5.7 Workshop equipment
CREATE TABLE `workshop_equipment` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5.8 Equipment reservations
CREATE TABLE `equipment_reservations` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- MODULE 5: REPORTS & ANALYTICS
-- =====================================================

CREATE TABLE `system_audit_log` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- ENABLE FOREIGN KEY CHECKS
-- =====================================================
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- SAMPLE DATA INSERTIONS
-- =====================================================

-- Insert access levels
INSERT INTO `access_levels` (`level_name`, `level_description`, `access_permissions`, `level_hierarchy`) VALUES
('Student', 'Basic student access to enrolled modules', '{"view_modules": true, "view_lessons": true, "take_assessments": true, "view_progress": true}', 1),
('Instructor', 'Instructor access to teaching resources', '{"view_modules": true, "edit_modules": true, "grade_assessments": true, "view_progress": true, "manage_students": true}', 2),
('Administrator', 'Full system administration access', '{"all_access": true}', 3),
('Guest', 'Limited guest access for demo purposes', '{"view_public_content": true}', 0);

-- Insert auto mechanic programs
INSERT INTO `auto_mechanic_programs` (`program_code`, `program_title`, `program_description`, `tesda_qualification_code`, `program_duration_hours`, `program_level`, `entry_requirements`, `career_opportunities`) VALUES
('AM-NCII-001', 'Automotive Servicing NC II', 'Comprehensive training in automotive servicing including engine repair, transmission systems, and brake systems', 'COC-AT-512-001', 432, 'NC II', 'High school graduate, physically fit, with basic mechanical aptitude', 'Automotive mechanic, service technician, maintenance supervisor, diagnostic specialist'),
('AM-NCI-001', 'Automotive Servicing NC I', 'Basic automotive servicing fundamentals for beginners', 'COC-AT-511-001', 216, 'NC I', 'Elementary graduate, at least 18 years old', 'Automotive helper, apprentice mechanic, service attendant'),
('AM-NCIII-001', 'Automotive Servicing NC III', 'Advanced automotive diagnostics and repair', 'COC-AT-513-001', 648, 'NC III', 'Automotive Servicing NC II certified, 2 years experience', 'Master technician, service manager, diagnostic expert, training instructor');

-- Insert training batches
INSERT INTO `training_batches` (`batch_code`, `batch_name`, `program_id`, `start_date`, `end_date`, `training_schedule`, `max_trainees`, `batch_status`) VALUES
('BATCH-2026A', 'Batch 2026-A Morning', 1, '2026-06-01', '2026-08-30', 'Morning', 25, 'Upcoming'),
('BATCH-2026B', 'Batch 2026-B Afternoon', 1, '2026-06-01', '2026-08-30', 'Afternoon', 25, 'Upcoming'),
('BATCH-2026C', 'Batch 2026-C Evening', 1, '2026-09-01', '2026-11-30', 'Evening', 20, 'Upcoming');

-- Insert competency units (used by my_competencies.php)
INSERT INTO `competency_units` (`unit_code`, `unit_title`, `unit_description`, `competency_category`, `nctype`, `competency_level`, `hrs_required`) VALUES
('UTS301', 'Use Hand Tools', 'Proper use and maintenance of hand tools for automotive work', 'Core Competencies', 'NC I', 1, 40),
('UTS302', 'Use Measuring Devices', 'Use of micrometers, calipers, and other measuring instruments', 'Core Competencies', 'NC I', 2, 40),
('UTS303', 'Perform Engine Tune-Up', 'Diagnose and perform engine tune-up procedures', 'Core Competencies', 'NC II', 1, 80),
('UTS304', 'Service Electrical Systems', 'Service automotive electrical systems and components', 'Core Competencies', 'NC II', 2, 80),
('UTS305', 'Service Brake Systems', 'Inspect and repair brake systems', 'Core Competencies', 'NC II', 3, 60),
('UTS306', 'Service Suspension Systems', 'Inspect and repair suspension systems', 'Core Competencies', 'NC II', 4, 60);

-- Insert TESDA competency standards
INSERT INTO `tesda_competency_standards` (`competency_code`, `competency_title`, `competency_category`, `description`, `performance_criteria`, `evidence_requirements`, `assessment_methods`, `required_hours`, `competency_level`) VALUES
('AM-BC-001', 'Apply Safety Practices in Automotive Workplace', 'Basic Competencies', 'Implement safety procedures and practices in automotive workshop environment', '1.1 Identify workplace hazards, 1.2 Use appropriate PPE, 1.3 Follow emergency procedures', 'Observation checklist, safety test, practical demonstration', 'Direct observation, written test, portfolio', 40, 'NC I'),
('AM-CC-001', 'Use Hand Tools and Equipment', 'Common Competencies', 'Properly use and maintain automotive hand tools and basic equipment', '2.1 Select appropriate tools, 2.2 Use tools safely, 2.3 Maintain tools', 'Tool identification test, practical demonstration, maintenance log', 'Practical demonstration, oral questioning', 60, 'NC I'),
('AM-CO-001', 'Perform Engine Maintenance', 'Core Competencies', 'Conduct routine engine maintenance and basic repairs', '3.1 Inspect engine components, 3.2 Replace filters and fluids, 3.3 Perform tune-up', 'Maintenance records, practical demonstration, written test', 'Direct observation, portfolio, third party assessment', 80, 'NC II'),
('AM-CO-002', 'Repair Brake System', 'Core Competencies', 'Diagnose and repair automotive brake systems', '4.1 Inspect brake components, 4.2 Replace brake pads/shoes, 4.3 Bleed brake system', 'Diagnostic reports, repair documentation, practical test', 'Direct observation, portfolio, written test', 120, 'NC II');

-- Insert scholarship programs
INSERT INTO `scholarship_programs` (`program_code`, `program_name`, `program_type`, `description`, `eligibility_criteria`, `benefits_description`, `max_slots`, `income_requirement_max`) VALUES
('TESDA-FS-001', 'TESDA Free Scholarship', 'Full Scholarship', 'Full coverage of training fees for qualified students', 'Monthly household income not exceeding PHP 15,000, high school graduate, 18-45 years old', '100% tuition fee coverage, training materials, assessment fees', 50, 15000.00),
('TESDA-TA-001', 'Training Allowance Program', 'Training Allowance', 'Monthly allowance for deserving students', 'Monthly household income not exceeding PHP 10,000, good academic standing, regular attendance', 'PHP 2,500 monthly allowance for 6 months', 30, 10000.00),
('TESDA-PA-001', 'Private Sector Scholarship', 'Partial Scholarship', 'Partial scholarship funded by industry partners', 'Monthly household income not exceeding PHP 20,000, commitment to work with partner company', '50% tuition coverage, job placement assistance', 25, 20000.00);

-- =====================================================
-- SAMPLE PRE-ENROLLMENT APPLICATIONS (Module 1)
-- =====================================================
INSERT INTO `pre_enrollment_applications` (
    application_number, first_name, last_name, middle_name, birth_date, gender,
    contact_number, email_address, complete_address, barangay, city_municipality, province, postal_code,
    civil_status, citizenship, highest_educational_attainment, school_last_attended, year_graduated,
    employment_status, monthly_income, preferred_training_schedule, preferred_start_date,
    has_previous_tesda_training, previous_tesa_course, reason_for_applying,
    emergency_contact_name, emergency_contact_relationship, emergency_contact_number,
    application_status, submission_date, review_date, interview_date, assessment_date, remarks,
    reviewed_by, reviewed_at
) VALUES
-- 1. Qualified (Approved)
(
    'APP-2025-0001', 'Juan', 'Dela Cruz', 'M.', '1998-05-15', 'Male',
    '09171234567', 'juan.delacruz1@example.com', '123 Main St., Brgy. San Antonio', 'San Antonio', 'Makati City', 'Metro Manila', '1200',
    'Single', 'Filipino', 'College Graduate', 'University of the Philippines', 2020,
    'Unemployed', NULL, 'Morning', '2025-06-01',
    0, NULL, 'I want to pursue a career in automotive servicing and enhance my technical skills.',
    'Maria Dela Cruz', 'Mother', '09179876543',
    'Qualified', '2025-09-15 09:00:00', '2025-09-20 10:00:00', NULL, NULL, 'Excellent academic background and strong motivation.',
    1, '2025-09-20 10:00:00'
),
-- 2. Not Qualified (Rejected)
(
    'APP-2025-0002', 'Maria', 'Santos', 'L.', '1995-08-22', 'Female',
    '09182345678', 'maria.santos@example.com', '456 Rizal Ave., Brgy. Santa Ana', 'Santa Ana', 'Manila', 'Metro Manila', '1000',
    'Married', 'Filipino', 'High School', 'Santa Ana High School', 2013,
    'Employed', 12000.00, 'Afternoon', '2025-07-01',
    0, NULL, 'To gain certification and improve job prospects.',
    'Jose Santos', 'Husband', '09191234567',
    'Not Qualified', '2025-09-16 11:30:00', '2025-09-21 14:00:00', NULL, NULL, 'Does not meet educational attainment requirement for the program.',
    1, '2025-09-21 14:00:00'
),
-- 3. Pending (just submitted)
(
    'APP-2025-0003', 'Pedro', 'Reyes', 'S.', '2000-03-10', 'Male',
    '09193456789', 'pedro.reyes@example.com', '789 Mabini St., Brgy. San Isidro', 'San Isidro', 'Quezon City', 'Metro Manila', '1100',
    'Single', 'Filipino', 'College Undergraduate', 'Quezon City University', NULL,
    'Student', NULL, 'Evening', '2025-09-01',
    0, NULL, 'To acquire automotive skills while studying.',
    'Ana Reyes', 'Sister', '09194567890',
    'Pending', '2025-10-01 08:00:00', NULL, NULL, NULL, NULL,
    NULL, NULL
),
-- 4. Under Review
(
    'APP-2025-0004', 'Ana', 'Garcia', 'P.', '1997-11-05', 'Female',
    '09195678901', 'ana.garcia@example.com', '1010 Del Monte Ave., Brgy. Del Monte', 'Del Monte', 'Quezon City', 'Metro Manila', '1105',
    'Single', 'Filipino', 'Vocational', 'TESDA Accredited Center', 2019,
    'Self-Employed', 18000.00, 'Weekend', '2025-08-15',
    1, 'Electrical Installation NC II', 'To formalize my automotive knowledge with a TESDA NC II.',
    'Luis Garcia', 'Brother', '09196789012',
    'Under Review', '2025-09-20 14:00:00', NULL, NULL, NULL, 'Document verification in progress.',
    NULL, NULL
),
-- 5. Waitlisted
(
    'APP-2025-0005', 'Roberto', 'Mendoza', 'T.', '1985-07-30', 'Male',
    '09197890123', 'roberto.mendoza@example.com', '2020 EDSA, Brgy. Cubao', 'Cubao', 'Quezon City', 'Metro Manila', '1109',
    'Married', 'Filipino', 'High School', 'Cubao High School', 2003,
    'Employed', 25000.00, 'Morning', '2025-10-01',
    0, NULL, 'To shift careers and become a certified auto mechanic.',
    'Susan Mendoza', 'Wife', '09198901234',
    'Waitlisted', '2025-09-10 10:00:00', '2025-09-25 16:30:00', NULL, NULL, 'Slots full; placed on waitlist for next batch.',
    1, '2025-09-25 16:30:00'
),
-- 6. Enrolled
(
    'APP-2025-0006', 'Carmen', 'Lopez', 'R.', '2002-02-14', 'Female',
    '09199012345', 'carmen.lopez@example.com', '3030 Taft Ave., Brgy. Malate', 'Malate', 'Manila', 'Metro Manila', '1004',
    'Single', 'Filipino', 'College Undergraduate', 'De La Salle University', NULL,
    'Student', NULL, 'Afternoon', '2025-06-15',
    0, NULL, 'To gain practical automotive skills for future employment.',
    'Elena Lopez', 'Aunt', '09190123456',
    'Enrolled', '2025-08-01 11:00:00', '2025-08-05 09:00:00', '2025-08-03 10:00:00', '2025-08-10 14:00:00', 'Completed enrollment for Batch 2026-A.',
    1, '2025-08-05 09:00:00'
),
-- 7. Submitted (awaiting initial review)
(
    'APP-2025-0007', 'Daniel', 'Torres', 'F.', '1993-09-25', 'Male',
    '09191234567', 'daniel.torres@example.com', '4040 Shaw Blvd., Brgy. Wack-Wack', 'Wack-Wack', 'Mandaluyong City', 'Metro Manila', '1550',
    'Single', 'Filipino', 'College Graduate', 'Ateneo de Manila University', 2015,
    'Employed', 35000.00, 'Evening', '2025-09-15',
    0, NULL, 'To supplement my engineering background with hands-on auto skills.',
    'Megan Torres', 'Sister', '09192345678',
    'Submitted', '2025-10-05 13:00:00', NULL, NULL, NULL, NULL,
    NULL, NULL
),
-- 8. Draft (incomplete/not yet submitted)
(
    'APP-2025-0008', 'Elena', 'Cruz', 'G.', '2005-04-18', 'Female',
    '09193456789', 'elena.cruz@example.com', '5050 Chino Roces Ave., Brgy. Pio del Pilar', 'Pio del Pilar', 'Makati City', 'Metro Manila', '1200',
    'Single', 'Filipino', 'High School', 'Makati High School', 2023,
    'Student', NULL, 'Morning', NULL,
    0, NULL, 'To start my career in auto mechanics right after high school.',
    'Jose Cruz', 'Father', '09194567890',
    'Draft', NULL, NULL, NULL, NULL, NULL,
    NULL, NULL
),
-- 9. For Interview
(
    'APP-2025-0009', 'Luis', 'Navarro', 'H.', '1990-12-03', 'Male',
    '09195678901', 'luis.navarro@example.com', '6060 Ortigas Ave., Brgy. Greenhills', 'Greenhills', 'San Juan', 'Metro Manila', '1500',
    'Single', 'Filipino', 'College Graduate', 'University of Santo Tomas', 2012,
    'Employed', 40000.00, 'Evening', '2025-11-01',
    1, 'Automotive Servicing NC I', 'To obtain NC II certification for career advancement.',
    'Isabella Navarro', 'Wife', '09196789012',
    'For Interview', '2025-10-02 09:00:00', '2025-10-06 11:00:00', '2025-10-10 10:00:00', NULL, 'Shortlisted for interview.',
    1, '2025-10-06 11:00:00'
);

-- =====================================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- =====================================================
CREATE INDEX idx_pre_enroll_status_date ON pre_enrollment_applications(application_status, submission_date);
CREATE INDEX idx_scholarship_status_score ON scholarship_applications(application_status, total_score);
CREATE INDEX idx_module_progress_status ON student_module_progress(status, completion_date);
CREATE INDEX idx_assessment_attempt_status ON student_assessment_attempts(status, end_time);
CREATE INDEX idx_access_log_timestamp_user ON access_logs(access_timestamp, user_id);
CREATE INDEX idx_equipment_status_category ON workshop_equipment(equipment_status, equipment_category);
CREATE INDEX idx_reservation_status_date ON equipment_reservations(reservation_status, reservation_date);

-- =====================================================
-- TRIGGERS FOR AUTOMATIC UPDATES
-- =====================================================
DELIMITER $$

-- Update batch enrollment count automatically
CREATE TRIGGER update_batch_enrollment_count 
AFTER INSERT ON student_program_enrollments
FOR EACH ROW
BEGIN
    UPDATE training_batches 
    SET current_enrolled = current_enrolled + 1 
    WHERE batch_id = NEW.batch_id;
END$$

-- Update scholarship slots Taken when approved
CREATE TRIGGER update_scholarship_slots 
AFTER UPDATE ON scholarship_applications
FOR EACH ROW
BEGIN
    IF NEW.application_status = 'Approved' AND OLD.application_status != 'Approved' THEN
        UPDATE scholarship_programs 
        SET current_slots_taken = current_slots_taken + 1 
        WHERE program_id = NEW.program_id;
    END IF;
END$$

-- Log module access automatically
CREATE TRIGGER log_module_access 
AFTER INSERT ON student_module_progress
FOR EACH ROW
BEGIN
    INSERT INTO access_logs (user_id, access_type, resource_type, resource_id, access_action, access_timestamp)
    VALUES (
        (SELECT student_id FROM student_program_enrollments WHERE enrollment_id = NEW.enrollment_id),
        'Module Access',
        'Module',
        NEW.module_id,
        'View',
        NEW.start_date
    );
END$$

DELIMITER ;

-- =====================================================
-- STORED PROCEDURES FOR COMMON OPERATIONS
-- =====================================================
DELIMITER $$

-- Calculate student progress
CREATE PROCEDURE calculate_student_progress(IN p_enrollment_id INT)
BEGIN
    DECLARE total_modules INT DEFAULT 0;
    DECLARE completed_modules INT DEFAULT 0;
    DECLARE progress_percentage DECIMAL(5,2) DEFAULT 0;
    
    SELECT COUNT(*) INTO total_modules
    FROM student_module_progress
    WHERE enrollment_id = p_enrollment_id;
    
    SELECT COUNT(*) INTO completed_modules
    FROM student_module_progress
    WHERE enrollment_id = p_enrollment_id AND status = 'Completed';
    
    IF total_modules > 0 THEN
        SET progress_percentage = (completed_modules / total_modules) * 100;
    END IF;
    
    UPDATE student_program_enrollments
    SET notes = CONCAT('Progress: ', progress_percentage, '% (', completed_modules, '/', total_modules, ' modules completed)')
    WHERE enrollment_id = p_enrollment_id;
END$$

-- Check scholarship eligibility
CREATE PROCEDURE check_scholarship_eligibility(IN p_application_id INT)
BEGIN
    DECLARE income_eligible BOOLEAN DEFAULT FALSE;
    DECLARE score_eligible BOOLEAN DEFAULT FALSE;
    DECLARE final_status VARCHAR(20) DEFAULT 'Under Review';
    
    -- Check income eligibility
    IF EXISTS (
        SELECT 1 FROM scholarship_applications sa
        JOIN scholarship_programs sp ON sa.program_id = sp.program_id
        WHERE sa.scholarship_app_id = p_application_id
        AND sa.household_income <= sp.income_requirement_max
    ) THEN
        SET income_eligible = TRUE;
    END IF;
    
    -- Check score eligibility (>=75)
    IF EXISTS (
        SELECT 1 FROM scholarship_applications
        WHERE scholarship_app_id = p_application_id
        AND total_score >= 75
    ) THEN
        SET score_eligible = TRUE;
    END IF;
    
    -- Determine final status
    IF income_eligible AND score_eligible THEN
        SET final_status = 'Approved';
    ELSEIF income_eligible AND total_score >= 60 THEN
        SET final_status = 'Waitlisted';
    ELSE
        SET final_status = 'Rejected';
    END IF;
    
    UPDATE scholarship_applications
    SET application_status = final_status
    WHERE scholarship_app_id = p_application_id;
END$$

DELIMITER ;

-- =====================================================
-- COMPLETION MESSAGE
-- =====================================================
SELECT 'Database setup complete! All tables created successfully.' AS Status;
