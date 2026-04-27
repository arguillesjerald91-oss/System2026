-- =====================================================
-- TEST USERS CREDENTIALS FOR TESDA LOGIN SYSTEM
-- Database: tesda_auto_mechanic
-- All passwords are: password123
-- =====================================================

-- Run this SQL in phpMyAdmin or MySQL to add test users

INSERT INTO users (username, password, email, user_type, first_name, last_name, status, email_verified) 
VALUES ('admin', '$2y$10$lhO277aygaA5obXWLqIj5usjxBFzS1wFXodu8JCYZpzfiq/dpKwdO', 'admin@tesda.gov.ph', 'admin', 'System', 'Administrator', 'active', 1)
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO users (username, password, email, user_type, first_name, last_name, status, email_verified) 
VALUES ('student1', '$2y$10$ibmTluGyIUvfggkdF1hHq.13oLbD877Zjgs6td0edQsII3T2Gq.PS', 'student1@tesda.gov.ph', 'student', 'Juan', 'Dela Cruz', 'active', 1)
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO users (username, password, email, user_type, first_name, last_name, status, email_verified) 
VALUES ('trainee1', '$2y$10$S1LlaaYL/TnMaSqK0.yjnuLmN6pz2d12./DWx.ycNFN8lVMOBri6S', 'trainee1@tesda.gov.ph', 'trainee', 'Maria', 'Santos', 'active', 1)
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO users (username, password, email, user_type, first_name, last_name, status, email_verified) 
VALUES ('instructor1', '$2y$10$wgHb1wuczhxjdjWmuGASous20rOtlqX1N9XbncJLXzBR3yfA3E.52', 'instructor1@tesda.gov.ph', 'instructor', 'Pedro', 'Garcia', 'active', 1)
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO users (username, password, email, user_type, first_name, last_name, status, email_verified) 
VALUES ('instructor2', '$2y$10$y5B68cuxwn/HN4PstaU1UO.mOKQXDzQfbMExU9FoM/eSq03elIPBC', 'instructor2@tesda.gov.ph', 'instructor', 'Ana', 'Reyes', 'active', 1)
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO users (username, password, email, user_type, first_name, last_name, status, email_verified) 
VALUES ('unit1', '$2y$10$yyq6gpGrmfdoCQR7pNs06.nZoaIWFaUkMdRuVdWACte4Iw.EZXR3O', 'unit1@tesda.gov.ph', 'instructional_unit', 'Roberto', 'Mendoza', 'active', 1)
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO users (username, password, email, user_type, first_name, last_name, status, email_verified) 
VALUES ('support1', '$2y$10$XPMNO0.dEG5ciAFvuPcp9.lQdNNhdjAuK8IwrYIpIIMen0AcxFVqa', 'support1@tesda.gov.ph', 'support_staff', 'Carmen', 'Lopez', 'active', 1)
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO users (username, password, email, user_type, first_name, last_name, status, email_verified) 
VALUES ('support2', '$2y$10$qSd261RoIyl3V6iClSdTO.vU7Je9Qv.b1Xu2DxlIdE4FtQVbvwSKu', 'support2@tesda.gov.ph', 'support_staff', 'Daniel', 'Torres', 'active', 1)
ON DUPLICATE KEY UPDATE username = username;

-- =====================================================
-- LOGIN CREDENTIALS (Use these to login)
-- =====================================================
-- | Username    | Password    | User Type        |
-- |-------------|-------------|------------------|
-- | admin       | password123 | Admin            |
-- | student1    | password123 | Student          |
-- | trainee1    | password123 | Trainee          |
-- | instructor1 | password123 | Instructor       |
-- | unit1       | password123 | Instructional Unit |
-- | support1    | password123 | Support Staff    |
-- =====================================================