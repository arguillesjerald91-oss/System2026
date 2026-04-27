-- =====================================================
-- ADVANCED DOCUMENT MANAGEMENT SYSTEM
-- TOR, Certificates, Diploma, Documents Modules
-- =====================================================
-- This script creates tables for advanced document management
-- including transcripts, certificates, diplomas, and centralized
-- document repository with versioning, permissions, and audit trails.
-- =====================================================

-- Use the database
USE `tesda_auto_mechanic`;

-- =====================================================
-- DOCUMENT CATEGORIES
-- =====================================================
CREATE TABLE IF NOT EXISTS `document_categories` (
  `category_id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `category_code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `parent_category_id` INT(11) DEFAULT NULL,
  `requires_approval` TINYINT(1) DEFAULT 0,
  `allowed_user_types` JSON DEFAULT NULL,
  `retention_period_months` INT(11) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  INDEX `idx_category_code` (`category_code`),
  INDEX `idx_parent_category` (`parent_category_id`),
  CONSTRAINT `fk_doc_parent_category` FOREIGN KEY (`parent_category_id`) REFERENCES `document_categories`(`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- CENTRAL DOCUMENT REPOSITORY
-- =====================================================
CREATE TABLE IF NOT EXISTS `documents` (
  `document_id` INT(11) NOT NULL AUTO_INCREMENT,
  `document_number` VARCHAR(50) NOT NULL UNIQUE,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category_id` INT(11) NOT NULL,
  `document_type` ENUM('TOR','Certificate','Diploma','Transcript','ID','Registration','Scholarship','Other') NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_size` BIGINT(20) NOT NULL,
  `file_extension` VARCHAR(20) DEFAULT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `file_hash` VARCHAR(64) DEFAULT NULL,
  `version_number` INT(11) DEFAULT 1,
  `is_latest_version` TINYINT(1) DEFAULT 1,
  `related_document_id` INT(11) DEFAULT NULL,
  `student_id` INT(11) DEFAULT NULL,
  `enrollment_id` INT(11) DEFAULT NULL,
  `batch_id` INT(11) DEFAULT NULL,
  `program_id` INT(11) DEFAULT NULL,
  `issue_date` DATE DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `status` ENUM('Draft','Pending','Approved','Rejected','Expired','Archived','Revoked') DEFAULT 'Pending',
  `verification_code` VARCHAR(100) DEFAULT NULL,
  `verification_url` VARCHAR(255) DEFAULT NULL,
  `tags` JSON DEFAULT NULL,
  `confidentiality_level` ENUM('Public','Internal','Confidential','Restricted') DEFAULT 'Internal',
  `access_count` INT(11) DEFAULT 0,
  `last_accessed` DATETIME DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  INDEX `idx_document_number` (`document_number`),
  INDEX `idx_category` (`category_id`),
  INDEX `idx_document_type` (`document_type`),
  INDEX `idx_student` (`student_id`),
  INDEX `idx_enrollment` (`enrollment_id`),
  INDEX `idx_batch` (`batch_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_verification_code` (`verification_code`),
  CONSTRAINT `fk_doc_category` FOREIGN KEY (`category_id`) REFERENCES `document_categories`(`category_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_doc_student` FOREIGN KEY (`student_id`) REFERENCES `student`(`StudID`) ON DELETE SET NULL,
  CONSTRAINT `fk_doc_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `student_program_enrollments`(`enrollment_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_doc_batch` FOREIGN KEY (`batch_id`) REFERENCES `training_batches`(`batch_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- DOCUMENT VERSION HISTORY
-- =====================================================
CREATE TABLE IF NOT EXISTS `document_versions` (
  `version_id` INT(11) NOT NULL AUTO_INCREMENT,
  `document_id` INT(11) NOT NULL,
  `version_number` INT(11) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_size` BIGINT(20) NOT NULL,
  `file_hash` VARCHAR(64) NOT NULL,
  `change_reason` TEXT DEFAULT NULL,
  `changes_description` TEXT DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version_id`),
  UNIQUE KEY `uk_doc_version` (`document_id`,`version_number`),
  INDEX `idx_document_id` (`document_id`),
  CONSTRAINT `fk_version_document` FOREIGN KEY (`document_id`) REFERENCES `documents`(`document_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_version_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- DOCUMENT ACCESS PERMISSIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS `document_permissions` (
  `permission_id` INT(11) NOT NULL AUTO_INCREMENT,
  `document_id` INT(11) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `user_type` ENUM('student','admin','instructor','support_staff','instructional_unit') DEFAULT NULL,
  `access_level` ENUM('View','Download','Edit','Delete','Share','Admin') DEFAULT 'View',
  `granted_by` INT(11) NOT NULL,
  `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `reason` TEXT DEFAULT NULL,
  PRIMARY KEY (`permission_id`),
  INDEX `idx_document` (`document_id`),
  INDEX `idx_user` (`user_id`,`user_type`),
  CONSTRAINT `fk_permission_document` FOREIGN KEY (`document_id`) REFERENCES `documents`(`document_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_permission_granter` FOREIGN KEY (`granted_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- DOCUMENT ACCESS AUDIT LOG
-- =====================================================
CREATE TABLE IF NOT EXISTS `document_access_logs` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `document_id` INT(11) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `user_type` VARCHAR(50) DEFAULT NULL,
  `action` ENUM('View','Download','Edit','Delete','Share','Generate_Link','Verify') NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `session_id` VARCHAR(100) DEFAULT NULL,
  `access_granted` TINYINT(1) DEFAULT 1,
  `denial_reason` TEXT DEFAULT NULL,
  `accessed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  INDEX `idx_document` (`document_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_accessed_at` (`accessed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- STUDENT DOCUMENT REQUESTS WORKFLOW
-- =====================================================
CREATE TABLE IF NOT EXISTS `document_requests` (
  `request_id` INT(11) NOT NULL AUTO_INCREMENT,
  `request_number` VARCHAR(50) NOT NULL UNIQUE,
  `student_id` INT(11) NOT NULL,
  `document_type` ENUM('Official Transcript','Certificate','Diploma','Certification','ID','Good Moral','Honorable Dismissal','Other') NOT NULL,
  `purpose` VARCHAR(255) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `copies_requested` INT(11) DEFAULT 1,
  `urgent` TINYINT(1) DEFAULT 0,
  `status` ENUM('Pending','Processing','Ready for Pickup','Delivered','Cancelled','Rejected') DEFAULT 'Pending',
  `request_date` DATE DEFAULT (CURRENT_DATE),
  `processing_date` DATETIME DEFAULT NULL,
  `release_date` DATE DEFAULT NULL,
  `completed_date` DATETIME DEFAULT NULL,
  `processed_by` INT(11) DEFAULT NULL,
  `assigned_to` INT(11) DEFAULT NULL,
  `department` ENUM('Registrar','Certification','Academic','Admission','Finance','Records','Other') DEFAULT 'Registrar',
  `priority` ENUM('Low','Normal','High','Urgent') DEFAULT 'Normal',
  `collection_method` ENUM('Pickup','Mail','Email','Digital Download','Third Party') DEFAULT 'Pickup',
  `payment_required` DECIMAL(10,2) DEFAULT 0.00,
  `payment_status` ENUM('Pending','Paid','Waived','Partial') DEFAULT 'Pending',
  `remarks` TEXT DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  INDEX `idx_request_number` (`request_number`),
  INDEX `idx_student` (`student_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_department` (`department`),
  INDEX `idx_assigned` (`assigned_to`),
  CONSTRAINT `fk_request_student` FOREIGN KEY (`student_id`) REFERENCES `student`(`StudID`) ON DELETE CASCADE,
  CONSTRAINT `fk_request_processor` FOREIGN KEY (`processed_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- DOCUMENT REQUEST NOTES
-- =====================================================
CREATE TABLE IF NOT EXISTS `document_request_notes` (
  `note_id` INT(11) NOT NULL AUTO_INCREMENT,
  `request_id` INT(11) NOT NULL,
  `note_text` TEXT NOT NULL,
  `note_type` ENUM('General','Status Update','Follow-up','Internal','Student Communication') DEFAULT 'General',
  `is_internal` TINYINT(1) DEFAULT 0,
  `added_by` INT(11) NOT NULL,
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`note_id`),
  INDEX `idx_request` (`request_id`),
  INDEX `idx_added_by` (`added_by`),
  CONSTRAINT `fk_note_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests`(`request_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_note_adder` FOREIGN KEY (`added_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- DOCUMENT TEMPLATES
-- =====================================================
CREATE TABLE IF NOT EXISTS `document_templates` (
  `template_id` INT(11) NOT NULL AUTO_INCREMENT,
  `template_name` VARCHAR(100) NOT NULL,
  `template_code` VARCHAR(50) NOT NULL UNIQUE,
  `document_type` ENUM('TOR','Certificate','Diploma','Letter','Form') NOT NULL,
  `template_content` LONGTEXT NOT NULL,
  `template_css` LONGTEXT DEFAULT NULL,
  `variables` JSON DEFAULT NULL,
  `default_template` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`template_id`),
  INDEX `idx_template_code` (`template_code`),
  INDEX `idx_doc_type` (`document_type`),
  CONSTRAINT `fk_template_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- STAFF DEPARTMENT ASSIGNMENTS
-- =====================================================
CREATE TABLE IF NOT EXISTS `staff_department_assignments` (
  `assignment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `department` ENUM('Registrar','Certification','Academic','Admission','Finance','Records','IT','HR') NOT NULL,
  `primary_role` VARCHAR(100) DEFAULT NULL,
  `secondary_roles` JSON DEFAULT NULL,
  `permissions_override` JSON DEFAULT NULL,
  `assigned_by` INT(11) NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `uk_user_department` (`user_id`,`department`),
  INDEX `idx_department` (`department`),
  INDEX `idx_user` (`user_id`),
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TRANSCRIPT OF RECORDS (TOR)
-- =====================================================
CREATE TABLE IF NOT EXISTS `transcripts` (
  `transcript_id` INT(11) NOT NULL AUTO_INCREMENT,
  `transcript_number` VARCHAR(50) NOT NULL UNIQUE,
  `student_id` INT(11) NOT NULL,
  `enrollment_id` INT(11) NOT NULL,
  `program_id` INT(11) NOT NULL,
  `batch_id` INT(11) DEFAULT NULL,
  `issue_date` DATE NOT NULL,
  `effective_date` DATE DEFAULT NULL,
  `total_units` DECIMAL(6,2) DEFAULT 0.00,
  `total_hours` INT(11) DEFAULT 0,
  `gpa` DECIMAL(4,2) DEFAULT 0.00,
  `overall_rating` VARCHAR(20) DEFAULT NULL,
  `academic_standing` ENUM('Good','Probation','Suspended','Expelled') DEFAULT 'Good',
  `honors` VARCHAR(100) DEFAULT NULL,
  `degree_conferred` TINYINT(1) DEFAULT 0,
  `conferred_date` DATE DEFAULT NULL,

  -- PDF generation and verification
  `pdf_file_path` VARCHAR(500) DEFAULT NULL,
  `pdf_generated` TINYINT(1) DEFAULT 0,
  `pdf_generated_at` DATETIME DEFAULT NULL,
  `digital_signature` TEXT DEFAULT NULL,
  `verification_code` VARCHAR(100) DEFAULT NULL,
  `verification_url` VARCHAR(255) DEFAULT NULL,

  -- Status and workflow
  `status` ENUM('Draft','Pending Approval','Approved','Issued','Delivered','Archived','Recalled','Superseded') DEFAULT 'Draft',
  `version` INT(11) DEFAULT 1,
  `previous_version_id` INT(11) DEFAULT NULL,
  `superseded_by` INT(11) DEFAULT NULL,

  -- Metadata
  `remarks` TEXT DEFAULT NULL,
  `confidential_notes` TEXT DEFAULT NULL,
  `tags` JSON DEFAULT NULL,

  -- Audit fields
  `prepared_by` INT(11) DEFAULT NULL,
  `reviewed_by` INT(11) DEFAULT NULL,
  `approved_by` INT(11) DEFAULT NULL,
  `issued_by` INT(11) DEFAULT NULL,
  `delivered_by` INT(11) DEFAULT NULL,
  `prepared_at` DATETIME DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `issued_at` DATETIME DEFAULT NULL,
  `delivered_at` DATETIME DEFAULT NULL,

  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`transcript_id`),
  UNIQUE KEY `uk_transcript_number` (`transcript_number`),
  INDEX `idx_student` (`student_id`),
  INDEX `idx_enrollment` (`enrollment_id`),
  INDEX `idx_program` (`program_id`),
  INDEX `idx_batch` (`batch_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_verification` (`verification_code`),
  INDEX `idx_issue_date` (`issue_date`),
  CONSTRAINT `fk_transcript_student` FOREIGN KEY (`student_id`) REFERENCES `student`(`StudID`) ON DELETE CASCADE,
  CONSTRAINT `fk_transcript_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `student_program_enrollments`(`enrollment_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transcript_program` FOREIGN KEY (`program_id`) REFERENCES `auto_mechanic_programs`(`program_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transcript_prev_version` FOREIGN KEY (`previous_version_id`) REFERENCES `transcripts`(`transcript_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TRANSCRIPT GRADES (TOR DETAILS)
-- =====================================================
CREATE TABLE IF NOT EXISTS `transcript_grades` (
  `grade_id` INT(11) NOT NULL AUTO_INCREMENT,
  `transcript_id` INT(11) NOT NULL,
  `module_id` INT(11) NOT NULL,
  `competency_unit_id` INT(11) DEFAULT NULL,
  `course_code` VARCHAR(50) NOT NULL,
  `course_title` VARCHAR(255) NOT NULL,
  `units` DECIMAL(4,2) NOT NULL,
  `contact_hours` INT(11) DEFAULT 0,
  `grade` VARCHAR(10) NOT NULL,
  `grade_point` DECIMAL(4,2) NOT NULL,
  `grade_type` ENUM('Numerical','Letter','Pass/Fail','Competency Based') DEFAULT 'Numerical',
  `semester` VARCHAR(20) DEFAULT NULL,
  `academic_year` VARCHAR(20) DEFAULT NULL,
  `taken_date` DATE DEFAULT NULL,
  `is_repeated` TINYINT(1) DEFAULT 0,
  `repeat_count` INT(11) DEFAULT 0,
  `remarks` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`grade_id`),
  UNIQUE KEY `uk_transcript_module` (`transcript_id`,`module_id`),
  INDEX `idx_transcript` (`transcript_id`),
  INDEX `idx_module` (`module_id`),
  CONSTRAINT `fk_grade_transcript` FOREIGN KEY (`transcript_id`) REFERENCES `transcripts`(`transcript_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grade_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules`(`module_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- TRANSCRIPT CHANGE HISTORY
-- =====================================================
CREATE TABLE IF NOT EXISTS `transcript_history` (
  `history_id` INT(11) NOT NULL AUTO_INCREMENT,
  `transcript_id` INT(11) NOT NULL,
  `change_type` ENUM('Create','Update','Grade Change','Status Change','Issue','Deliver','Recall','Supersede','Correction') NOT NULL,
  `field_changed` VARCHAR(100) DEFAULT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `change_reason` TEXT DEFAULT NULL,
  `changed_by` INT(11) NOT NULL,
  `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  INDEX `idx_transcript` (`transcript_id`),
  INDEX `idx_changed_by` (`changed_by`),
  INDEX `idx_changed_at` (`changed_at`),
  CONSTRAINT `fk_history_transcript` FOREIGN KEY (`transcript_id`) REFERENCES `transcripts`(`transcript_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- CERTIFICATES
-- =====================================================
CREATE TABLE IF NOT EXISTS `certificates` (
  `certificate_id` INT(11) NOT NULL AUTO_INCREMENT,
  `certificate_number` VARCHAR(50) NOT NULL UNIQUE,
  `student_id` INT(11) NOT NULL,
  `enrollment_id` INT(11) NOT NULL,
  `certificate_type` ENUM('Certificate of Completion','Competency Certificate','NC Certificate','Skill Certificate','Completion','Achievement','Custom') DEFAULT 'Certificate of Completion',
  `competency_id` INT(11) DEFAULT NULL,
  `module_ids` JSON DEFAULT NULL,
  `program_id` INT(11) DEFAULT NULL,
  `nc_level` ENUM('NC I','NC II','NC III','NC IV','Diploma','Special') DEFAULT NULL,

  -- Template and design
  `template_id` INT(11) DEFAULT NULL,
  `custom_template_data` JSON DEFAULT NULL,

  -- Issue details
  `issue_date` DATE NOT NULL,
  `valid_from` DATE DEFAULT NULL,
  `valid_until` DATE DEFAULT NULL,
  `expiry_notified` TINYINT(1) DEFAULT 0,
  `expiry_notified_at` DATETIME DEFAULT NULL,

  -- PDF and verification
  `pdf_file_path` VARCHAR(500) DEFAULT NULL,
  `pdf_generated` TINYINT(1) DEFAULT 0,
  `pdf_generated_at` DATETIME DEFAULT NULL,
  `digital_signature` TEXT DEFAULT NULL,
  `verification_code` VARCHAR(100) DEFAULT NULL,
  `verification_url` VARCHAR(255) DEFAULT NULL,
  `qrcode_data` TEXT DEFAULT NULL,

  -- Status and workflow
  `status` ENUM('Draft','Pending','Approved','Issued','Active','Expired','Revoked','Cancelled') DEFAULT 'Draft',
  `revocation_reason` TEXT DEFAULT NULL,
  `revoked_by` INT(11) DEFAULT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `replacement_for` INT(11) DEFAULT NULL,

  -- Metadata
  `title` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `achievement_description` TEXT DEFAULT NULL,
  `performance_level` VARCHAR(50) DEFAULT NULL,
  `score` DECIMAL(5,2) DEFAULT NULL,
  `max_score` DECIMAL(5,2) DEFAULT NULL,
  `honors` VARCHAR(100) DEFAULT NULL,
  `awards` JSON DEFAULT NULL,

  -- Audit
  `prepared_by` INT(11) DEFAULT NULL,
  `reviewed_by` INT(11) DEFAULT NULL,
  `approved_by` INT(11) DEFAULT NULL,
  `issued_by` INT(11) DEFAULT NULL,
  `prepared_at` DATETIME DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `issued_at` DATETIME DEFAULT NULL,

  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`certificate_id`),
  UNIQUE KEY `uk_certificate_number` (`certificate_number`),
  INDEX `idx_student` (`student_id`),
  INDEX `idx_enrollment` (`enrollment_id`),
  INDEX `idx_competency` (`competency_id`),
  INDEX `idx_program` (`program_id`),
  INDEX `idx_cert_type` (`certificate_type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_nc_level` (`nc_level`),
  INDEX `idx_verification` (`verification_code`),
  INDEX `idx_expiry` (`valid_until`),
  CONSTRAINT `fk_cert_student` FOREIGN KEY (`student_id`) REFERENCES `student`(`StudID`) ON DELETE CASCADE,
  CONSTRAINT `fk_cert_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `student_program_enrollments`(`enrollment_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cert_template` FOREIGN KEY (`template_id`) REFERENCES `document_templates`(`template_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- CERTIFICATE COMPETENCIES DETAILS
-- =====================================================
CREATE TABLE IF NOT EXISTS `certificate_competencies` (
  `cert_comp_id` INT(11) NOT NULL AUTO_INCREMENT,
  `certificate_id` INT(11) NOT NULL,
  `module_id` INT(11) DEFAULT NULL,
  `competency_unit_id` INT(11) DEFAULT NULL,
  `competency_code` VARCHAR(50) DEFAULT NULL,
  `competency_title` VARCHAR(255) DEFAULT NULL,
  `score` DECIMAL(5,2) DEFAULT NULL,
  `grade` VARCHAR(10) DEFAULT NULL,
  `is_core` TINYINT(1) DEFAULT 0,
  `is_elective` TINYINT(1) DEFAULT 0,
  `completed_date` DATE DEFAULT NULL,
  `assessor_id` INT(11) DEFAULT NULL,
  `assessment_id` INT(11) DEFAULT NULL,
  PRIMARY KEY (`cert_comp_id`),
  INDEX `idx_certificate` (`certificate_id`),
  INDEX `idx_module` (`module_id`),
  INDEX `idx_competency` (`competency_unit_id`),
  CONSTRAINT `fk_certcomp_certificate` FOREIGN KEY (`certificate_id`) REFERENCES `certificates`(`certificate_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_certcomp_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules`(`module_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_certcomp_competency` FOREIGN KEY (`competency_unit_id`) REFERENCES `competency_units`(`unit_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- CERTIFICATE HISTORY
-- =====================================================
CREATE TABLE IF NOT EXISTS `certificate_history` (
  `history_id` INT(11) NOT NULL AUTO_INCREMENT,
  `certificate_id` INT(11) NOT NULL,
  `action` ENUM('Create','Issue','Renew','Revoke','Cancel','Replace','Update') NOT NULL,
  `field_changed` VARCHAR(100) DEFAULT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `reason` TEXT DEFAULT NULL,
  `performed_by` INT(11) NOT NULL,
  `performed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  INDEX `idx_certificate` (`certificate_id`),
  INDEX `idx_performed_by` (`performed_by`),
  INDEX `idx_performed_at` (`performed_at`),
  CONSTRAINT `fk_hist_certificate` FOREIGN KEY (`certificate_id`) REFERENCES `certificates`(`certificate_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_user` FOREIGN KEY (`performed_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- DIPLOMAS
-- =====================================================
CREATE TABLE IF NOT EXISTS `diplomas` (
  `diploma_id` INT(11) NOT NULL AUTO_INCREMENT,
  `diploma_number` VARCHAR(50) NOT NULL UNIQUE,
  `student_id` INT(11) NOT NULL,
  `enrollment_id` INT(11) NOT NULL,
  `program_id` INT(11) NOT NULL,
  `batch_id` INT(11) DEFAULT NULL,

  -- Graduation details
  `graduation_date` DATE DEFAULT NULL,
  `convocation_date` DATE DEFAULT NULL,
  `ceremony_venue` VARCHAR(255) DEFAULT NULL,
  `diploma_type` ENUM('Bachelor','Associate','Certificate','Diploma','Advanced Diploma','Master','Doctorate') DEFAULT 'Diploma',
  `major` VARCHAR(100) DEFAULT NULL,
  `minor` VARCHAR(100) DEFAULT NULL,
  `concentration` VARCHAR(100) DEFAULT NULL,
  `honors` ENUM('None','Cum Laude','Magna Cum Laude','Summa Cum Laude','With Honors') DEFAULT 'None',
  `honors_description` VARCHAR(255) DEFAULT NULL,
  `general_average` DECIMAL(4,2) DEFAULT 0.00,
  `units_earned` DECIMAL(6,2) DEFAULT 0.00,
  `total_hours` INT(11) DEFAULT 0,

  -- Template and design
  `template_id` INT(11) DEFAULT NULL,
  `custom_template_data` JSON DEFAULT NULL,
  `signatory_line1` VARCHAR(255) DEFAULT NULL,
  `signatory_line2` VARCHAR(255) DEFAULT NULL,
  `signatory_line3` VARCHAR(255) DEFAULT NULL,

  -- PDF generation
  `pdf_file_path` VARCHAR(500) DEFAULT NULL,
  `pdf_generated` TINYINT(1) DEFAULT 0,
  `pdf_generated_at` DATETIME DEFAULT NULL,
  `digital_signature` TEXT DEFAULT NULL,
  `verification_code` VARCHAR(100) DEFAULT NULL,
  `verification_url` VARCHAR(255) DEFAULT NULL,

  -- Status workflow
  `status` ENUM('Draft','Pending Approval','Approved','Printed','Awarded','Conferred','Replaced','Cancelled') DEFAULT 'Draft',
  `printed` TINYINT(1) DEFAULT 0,
  `awarded` TINYINT(1) DEFAULT 0,
  `awarded_at` DATETIME DEFAULT NULL,
  `conferred` TINYINT(1) DEFAULT 0,
  `conferred_at` DATETIME DEFAULT NULL,

  -- Replacement tracking
  `replacement_count` INT(11) DEFAULT 0,
  `replacement_reason` TEXT DEFAULT NULL,
  `replacement_requested_by` INT(11) DEFAULT NULL,
  `replacement_approved_by` INT(11) DEFAULT NULL,

  -- Metadata
  `remarks` TEXT DEFAULT NULL,
  `confidential_notes` TEXT DEFAULT NULL,
  `tags` JSON DEFAULT NULL,

  -- Audit
  `prepared_by` INT(11) DEFAULT NULL,
  `reviewed_by` INT(11) DEFAULT NULL,
  `approved_by` INT(11) DEFAULT NULL,
  `printed_by` INT(11) DEFAULT NULL,
  `conferred_by` INT(11) DEFAULT NULL,
  `prepared_at` DATETIME DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `printed_at` DATETIME DEFAULT NULL,
  

  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`diploma_id`),
  UNIQUE KEY `uk_diploma_number` (`diploma_number`),
  INDEX `idx_student` (`student_id`),
  INDEX `idx_enrollment` (`enrollment_id`),
  INDEX `idx_program` (`program_id`),
  INDEX `idx_batch` (`batch_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_graduation` (`graduation_date`),
  INDEX `idx_verification` (`verification_code`),
  CONSTRAINT `fk_diploma_student` FOREIGN KEY (`student_id`) REFERENCES `student`(`StudID`) ON DELETE CASCADE,
  CONSTRAINT `fk_diploma_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `student_program_enrollments`(`enrollment_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_diploma_program` FOREIGN KEY (`program_id`) REFERENCES `auto_mechanic_programs`(`program_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- DIPLOMA MODULES
-- =====================================================
CREATE TABLE IF NOT EXISTS `diploma_modules` (
  `diploma_module_id` INT(11) NOT NULL AUTO_INCREMENT,
  `diploma_id` INT(11) NOT NULL,
  `module_id` INT(11) NOT NULL,
  `units` DECIMAL(4,2) NOT NULL,
  `grade` VARCHAR(10) DEFAULT NULL,
  `grade_point` DECIMAL(4,2) DEFAULT NULL,
  `status` ENUM('Passed','Failed','Incomplete','Withdrawn') DEFAULT 'Passed',
  `completed_date` DATE DEFAULT NULL,
  `instructor_id` INT(11) DEFAULT NULL,
  PRIMARY KEY (`diploma_module_id`),
  INDEX `idx_diploma` (`diploma_id`),
  INDEX `idx_module` (`module_id`),
  CONSTRAINT `fk_diplomamod_diploma` FOREIGN KEY (`diploma_id`) REFERENCES `diplomas`(`diploma_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_diplomamod_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules`(`module_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- DIPLOMA HISTORY
-- =====================================================
CREATE TABLE IF NOT EXISTS `diploma_history` (
  `history_id` INT(11) NOT NULL AUTO_INCREMENT,
  `diploma_id` INT(11) NOT NULL,
  `action` ENUM('Create','Update','Approve','Print','Award','Confer','Replace','Cancel','Correct') NOT NULL,
  `field_changed` VARCHAR(100) DEFAULT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `reason` TEXT DEFAULT NULL,
  `performed_by` INT(11) NOT NULL,
  `performed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  INDEX `idx_diploma` (`diploma_id`),
  INDEX `idx_performed_by` (`performed_by`),
  CONSTRAINT `fk_hist_diploma` FOREIGN KEY (`diploma_id`) REFERENCES `diplomas`(`diploma_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_user_diploma` FOREIGN KEY (`performed_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- NOTIFICATIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `user_type` ENUM('student','admin','instructor','support_staff','instructional_unit','all') DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `notification_type` ENUM('Document Status','Request Update','Expiry Warning','System','Alert','Reminder') DEFAULT 'System',
  `priority` ENUM('Low','Normal','High','Urgent') DEFAULT 'Normal',
  `related_entity_type` ENUM('document','certificate','diploma','transcript','request','user') DEFAULT NULL,
  `related_entity_id` INT(11) DEFAULT NULL,
  `action_url` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  INDEX `idx_user` (`user_id`,`is_read`),
  INDEX `idx_type` (`notification_type`),
  INDEX `idx_priority` (`priority`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- EMAIL QUEUE
-- =====================================================
CREATE TABLE IF NOT EXISTS `email_queue` (
  `email_id` INT(11) NOT NULL AUTO_INCREMENT,
  `recipient_email` VARCHAR(255) NOT NULL,
  `recipient_name` VARCHAR(255) DEFAULT NULL,
  `recipient_user_id` INT(11) DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` LONGTEXT NOT NULL,
  `email_type` ENUM('Document Ready','Request Update','Expiry Warning','Verification','General') DEFAULT 'General',
  `priority` ENUM('Low','Normal','High','Urgent') DEFAULT 'Normal',
  `attachments` JSON DEFAULT NULL,
  `max_retries` INT(11) DEFAULT 3,
  `retry_count` INT(11) DEFAULT 0,
  `status` ENUM('Pending','Sent','Failed','Scheduled') DEFAULT 'Pending',
  `scheduled_at` DATETIME DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `failed_at` DATETIME DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`email_id`),
  INDEX `idx_recipient` (`recipient_email`),
  INDEX `idx_status` (`status`),
  INDEX `idx_scheduled` (`scheduled_at`),
  INDEX `idx_user` (`recipient_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- INSERT DEFAULT CATEGORIES
-- =====================================================
INSERT IGNORE INTO `document_categories` (`category_name`, `category_code`, `description`, `requires_approval`, `allowed_user_types`) VALUES
('Transcript of Records', 'TOR', 'Official academic transcripts and records', 1, '["admin","support_staff","registrar","student"]'),
('Certificates', 'CERT', 'Certificates of completion and competency', 1, '["admin","support_staff","registrar","student"]'),
('Diplomas', 'DIPL', 'Diploma and degree documents', 1, '["admin","support_staff","registrar","student"]'),
('Identification', 'ID', 'ID cards and identification documents', 1, '["admin","support_staff","registrar","student"]'),
('Registration', 'REG', 'Registration and enrollment documents', 0, '["admin","support_staff","registrar","student"]'),
('Scholarship', 'SCHOL', 'Scholarship applications and documents', 0, '["admin","support_staff","registrar","student"]'),
('Other', 'OTHER', 'Miscellaneous documents', 0, '["admin","support_staff","registrar","student"]');

-- =====================================================
-- INSERT DEFAULT TEMPLATES
-- =====================================================
INSERT IGNORE INTO `document_templates` (`template_name`, `template_code`, `document_type`, `template_content`, `default_template`) VALUES
('Default Transcript Template', 'TOR-DEFAULT', 'TOR', '<html><body><h1>Transcript of Records</h1><p>Student: {{student_name}}</p><p>GPA: {{gpa}}</p></body></html>', 1),
('Default Certificate Template', 'CERT-DEFAULT', 'Certificate', '<html><body><div class="certificate"><h1>Certificate of Completion</h1><p>This is to certify that {{student_name}}</p></div></body></html>', 1),
('Default Diploma Template', 'DIPL-DEFAULT', 'Diploma', '<html><body><div class="diploma"><h1>Diploma</h1><p>Presented to {{student_name}}</p></div></body></html>', 1);

COMMIT;
