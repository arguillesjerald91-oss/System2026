-- Idempotent migration: create canonical `student_subjects` join table
-- This creates a table that links students to subjects and stores an optional assignment timestamp.
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS `student_subjects` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(100) NOT NULL,
  `subject_id` INT NOT NULL,
  `assigned_at` DATETIME DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_subject_unique` (`student_id`, `subject_id`),
  KEY `idx_subject_id` (`subject_id`),
  KEY `idx_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
