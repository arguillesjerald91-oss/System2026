<?php
/**
 * Generate SQL Script for Test Users
 * Run this to generate proper password hashes
 */

$password = 'password123';

// Generate bcrypt hash
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "-- =====================================================\n";
echo "-- TEST USERS CREDENTIALS FOR TESDA LOGIN SYSTEM\n";
echo "-- Database: tesda_auto_mechanic\n";
echo "-- All passwords are: " . $password . "\n";
echo "-- =====================================================\n\n";

$users = [
    ['admin', 'admin', 'admin@tesda.gov.ph', 'admin', 'System', 'Administrator'],
    ['student1', 'password123', 'student1@tesda.gov.ph', 'student', 'Juan', 'Dela Cruz'],
    ['trainee1', 'password123', 'trainee1@tesda.gov.ph', 'trainee', 'Maria', 'Santos'],
    ['instructor1', 'password123', 'instructor1@tesda.gov.ph', 'instructor', 'Pedro', 'Garcia'],
    ['instructor2', 'password123', 'instructor2@tesda.gov.ph', 'instructor', 'Ana', 'Reyes'],
    ['unit1', 'password123', 'unit1@tesda.gov.ph', 'instructional_unit', 'Roberto', 'Mendoza'],
    ['support1', 'password123', 'support1@tesda.gov.ph', 'support_staff', 'Carmen', 'Lopez'],
    ['support2', 'password123', 'support2@tesda.gov.ph', 'support_staff', 'Daniel', 'Torres'],
];

foreach ($users as $user) {
    $hashed = password_hash($user[1], PASSWORD_DEFAULT);
    echo "INSERT INTO users (username, password, email, user_type, first_name, last_name, status, email_verified) \n";
    echo "VALUES ('{$user[0]}', '$hashed', '{$user[2]}', '{$user[3]}', '{$user[4]}', '{$user[5]}', 'active', 1)\n";
    echo "ON DUPLICATE KEY UPDATE username = username;\n\n";
}

echo "-- =====================================================\n";
echo "-- LOGIN CREDENTIALS\n";
echo "-- =====================================================\n";
echo "-- | Username    | Password    | User Type        |\n";
echo "-- |-------------|-------------|------------------|\n";
echo "-- | admin       | password123 | Admin            |\n";
echo "-- | student1    | password123 | Student          |\n";
echo "-- | trainee1    | password123 | Trainee          |\n";
echo "-- | instructor1 | password123 | Instructor       |\n";
echo "-- | unit1       | password123 | Instructional Unit |\n";
echo "-- | support1    | password123 | Support Staff    |\n";
echo "-- =====================================================\n";