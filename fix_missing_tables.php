<?php
/**
 * Fix missing tables and column mismatches for student pages
 * Run this after setup_tesda_system.php to ensure compatibility
 */

include __DIR__ . '/db.php';
$db = new Database();
$conn = $db->getConnection();

$errors = 0;
$success = 0;

function runQuery($conn, $sql, $label, &$success, &$errors) {
    try {
        $conn->exec($sql);
        echo "<p style='color:green;'>✓ {$label}</p>";
        $success++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "<p style='color:orange;'>⚠ {$label} (already exists)</p>";
            $success++;
        } else {
            echo "<p style='color:red;'>✗ {$label}: " . htmlspecialchars(substr($e->getMessage(), 0, 100)) . "</p>";
            $errors++;
        }
    }
}

echo "<h2>🔧 Fixing Database Schema</h2>";

// 1. Rename learning_modules → training_modules if needed
echo "<h3>Checking table name: learning_modules → training_modules</h3>";
try {
    $learningExists = $conn->query("SHOW TABLES LIKE 'learning_modules'")->rowCount() > 0;
    $trainingExists = $conn->query("SHOW TABLES LIKE 'training_modules'")->rowCount() > 0;
    
    if ($learningExists && !$trainingExists) {
        $conn->exec("RENAME TABLE learning_modules TO training_modules");
        echo "<p style='color:green;'>✓ Renamed learning_modules → training_modules</p>";
        $success++;
    } else if ($trainingExists) {
        echo "<p style='color:orange;'>⚠ training_modules already exists</p>";
        $success++;
    } else {
        echo "<p style='color:blue;'>ℹ No learning_modules table to rename</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red;'>✗ Could not rename table: " . $e->getMessage() . "</p>";
    $errors++;
}

// 2. Drop and recreate competency_assessments with correct schema
echo "<h3>Recreating competency_assessments with correct schema</h3>";
try {
    $conn->exec("DROP TABLE IF EXISTS competency_assessments");
    echo "<p style='color:blue;'>ℹ Dropped old competency_assessments</p>";
} catch (PDOException $e) {
    echo "<p style='color:orange;'>⚠ Could not drop table: " . $e->getMessage() . "</p>";
}

runQuery($conn, "CREATE TABLE `competency_assessments` (
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
  CONSTRAINT `fk_assessment_unit` FOREIGN KEY (`unit_id`) REFERENCES `competency_units` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'competency_assessments (recreated)', $success, $errors);

// 3. Ensure student_program_enrollments exists (core dependency)
echo "<h3>Checking student_program_enrollments</h3>";
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

// 4. Create student_module_progress (missing)
echo "<h3>Creating student_module_progress</h3>";
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

// 5. Create student_lesson_progress (needed by module_lesson.php)
echo "<h3>Creating student_lesson_progress</h3>";
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

// 6. Create student_assessment_attempts (needed by assessments)
echo "<h3>Creating student_assessment_attempts</h3>";
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

// 7. Create student_assessment_answers (for storing student responses)
echo "<h3>Creating student_assessment_answers</h3>";
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

// 8. Ensure competency_units exists (used by my_competencies.php)
echo "<h3>Checking competency_units</h3>";
runQuery($conn, "CREATE TABLE IF NOT EXISTS `competency_units` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", 'competency_units', $success, $errors);

// 8b. Ensure tesda_competency_standards exists (FK for training_modules)
echo "<h3>Checking tesda_competency_standards</h3>";
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

// 9. Ensure training_modules has correct schema (matching integrated system)
echo "<h3>Verifying training_modules schema</h3>";
try {
    $result = $conn->query("DESCRIBE training_modules");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    $required = ['module_code', 'module_title', 'module_description', 'competency_id', 'module_duration_hours', 'module_type', 'difficulty_level', 'prerequisite_module_id', 'module_order', 'delivery_method', 'module_status', 'created_by', 'created_at', 'updated_at'];
    $missing = array_diff($required, $columns);
    
    if (count($missing) > 0) {
        echo "<p style='color:orange;'>⚠ training_modules missing columns: " . implode(', ', $missing) . ". Recreating table...</p>";
        $conn->exec("RENAME TABLE training_modules TO training_modules_old");
        $conn->exec("CREATE TABLE `training_modules` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        echo "<p style='color:green;'>✓ Recreated training_modules with correct schema</p>";
        $success++;
    } else {
        echo "<p style='color:green;'>✓ training_modules schema is correct</p>";
        $success++;
    }
} catch (PDOException $e) {
    echo "<p style='color:red;'>✗ Could not verify training_modules: " . $e->getMessage() . "</p>";
    $errors++;
}

// 9. Create module_assessments (needed by module_lesson.php)
echo "<h3>Creating module_assessments</h3>";
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

// 9. Create assessment_questions (for module assessments)
echo "<h3>Creating assessment_questions</h3>";
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

// 10. Create module_lessons (needed by learning modules)
echo "<h3>Creating module_lessons</h3>";
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

// 11. Create question_options (for multiple choice questions)
echo "<h3>Creating question_options</h3>";
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

// 8. Fix competency_units: add missing competency_category column (required by my_competencies.php)
echo "<h3>Fixing competency_units table</h3>";
try {
    $result = $conn->query("DESCRIBE competency_units");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('competency_category', $columns)) {
        $conn->exec("ALTER TABLE competency_units ADD COLUMN `competency_category` enum('Basic Competencies','Common Competencies','Core Competencies','Elective Competencies') NOT NULL DEFAULT 'Core Competencies' AFTER `unit_description`");
        echo "<p style='color:green;'>✓ Added competency_category to competency_units</p>";
        $success++;
    } else {
        echo "<p style='color:orange;'>⚠ competency_category already exists</p>";
        $success++;
    }
} catch (PDOException $e) {
    echo "<p style='color:red;'>✗ Could not add competency_category: " . $e->getMessage() . "</p>";
    $errors++;
}

echo "<hr><h2 style='color:green;'>✅ Fixes Applied</h2>";
echo "<p><strong>Successful operations:</strong> {$success}</p>";
echo "<p><strong>Errors:</strong> {$errors}</p>";
echo "<p><a href='student/learning_modules.php'>→ Test Learning Modules</a></p>";
echo "<p><a href='student/my_grades.php'>→ Test My Grades</a></p>";
echo "<p><a href='student/my_competencies.php'>→ Test My Competencies</a></p>";
