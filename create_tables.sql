-- =============================================
-- TESDA Auto Mechanic - Required Tables
-- Run this SQL in your MySQL database
-- =============================================

-- 1. Module Contents (for my_modules.php)
DROP TABLE IF EXISTS module_contents;
CREATE TABLE module_contents (
    content_id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content_type ENUM('Video','PDF','Link','Quiz','Assignment','Activity') NOT NULL,
    content_url VARCHAR(500),
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    duration_mins INT DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_published TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
);

-- 2. Assignments (for assignments.php)
DROP TABLE IF EXISTS assignments;
CREATE TABLE assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATETIME,
    max_score INT DEFAULT 100,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
);

DROP TABLE IF EXISTS assignment_submissions;
CREATE TABLE assignment_submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255),
    file_name VARCHAR(255),
    submission_text TEXT,
    score INT,
    feedback TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    graded_by INT,
    graded_at TIMESTAMP NULL
);

-- 3. Learning Materials (for learning_materials.php)
DROP TABLE IF EXISTS learning_materials;
CREATE TABLE learning_materials (
    material_id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    material_type ENUM('Video','Document','Presentation','Image','Archive') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
);

-- 4. Quizzes (for quizzes.php)
DROP TABLE IF EXISTS quizzes;
CREATE TABLE quizzes (
    quiz_id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    time_limit INT DEFAULT 30,
    passing_score INT DEFAULT 70,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
);

DROP TABLE IF EXISTS quiz_questions;
CREATE TABLE quiz_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('Multiple Choice','True/False','Short Answer','Essay') NOT NULL,
    options JSON,
    correct_answer TEXT,
    points_value DECIMAL(5,2) DEFAULT 1,
    question_order INT DEFAULT 0
);

DROP TABLE IF EXISTS quiz_attempts;
CREATE TABLE quiz_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    answers JSON,
    score DECIMAL(5,2),
    passed TINYINT(1) DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
);

-- 5. Module Lesson Contents (for my_modules.php)
DROP TABLE IF EXISTS module_lessons;
CREATE TABLE module_lessons (
    lesson_id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    lesson_title VARCHAR(200) NOT NULL,
    lesson_content LONGTEXT NOT NULL,
    lesson_type ENUM('Text','Video','Presentation','Interactive','Assessment') NOT NULL,
    lesson_duration_minutes INT,
    lesson_order INT,
    is_mandatory TINYINT(1) DEFAULT 1,
    lesson_status ENUM('Draft','Published','Archived') DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP()
);

-- =============================================
-- CREATE FOLDERS IN PROJECT ROOT:
-- 1. instructor/module_files/ (for uploaded videos/PDFs)
-- 2. instructor/uploads/ (for learning materials)
-- 3. instructor/submissions/ (for assignment uploads)
-- =============================================