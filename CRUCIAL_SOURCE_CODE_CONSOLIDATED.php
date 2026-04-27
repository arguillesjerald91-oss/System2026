<?php
/**
 * TESDA AUTO MECHANIC TRAINING CENTRE - CRUCIAL SOURCE CODE CONSOLIDATION
 * 
 * This file contains the essential source code components of the TESDA Auto Mechanic 
 * Training Centre integrated system. It includes the core functionality for:
 * - Database connection and configuration
 * - User authentication and login systems
 * - Main application entry point
 * - Pre-enrollment system
 * - Database setup and management
 * 
 * Project Structure: PHP-based web application with MySQL backend
 * Author: TESDA Development Team
 * Date: Consolidated on April 18, 2026
 */

// =============================================================================
// SECTION 1: DATABASE CONNECTION CLASS (db.php)
// =============================================================================

if (!class_exists('Database')) {
    class Database {
        private $host = "localhost";
        private $db_name = "tesda_auto_mechanic";
        private $username = "root";
        private $password = "";
        public $conn = null;

        /**
         * Create and return a PDO connection or null on failure.
         * @return \PDO|null
         */
        public function getConnection(): ?\PDO {
            $this->conn = null;
            try {
                $this->conn = new \PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password,
                    [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
                $this->conn->exec("set names utf8");
            } catch(\PDOException $exception) {
                // For now echo the error; in production consider logging instead
                echo "Connection error: " . $exception->getMessage();
            }
            return $this->conn;
        }
    }
}

// =============================================================================
// SECTION 2: MAIN APPLICATION ENTRY POINT (index.php - Core Logic)
// =============================================================================

/**
 * Main Application Logic for TESDA Auto Mechanic Training Centre
 * This section contains the core PHP logic from index.php
 */

// Session management and user authentication checks
session_start();

// Database initialization
$database = new Database();
$conn = $database->getConnection();

// User authentication check for protected areas
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function getCurrentUser() {
    if (isUserLoggedIn()) {
        return [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'user_type' => $_SESSION['user_type'],
            'full_name' => $_SESSION['full_name'] ?? ''
        ];
    }
    return null;
}

function redirectByUserType($userType) {
    $redirects = [
        'admin' => 'admin/admin_dashboard.php',
        'student' => 'student/student_dashboard.php',
        'trainee' => 'student/student_dashboard.php',
        'instructor' => 'instructor/instructor_dashboard.php',
        'instructional_unit' => 'instructional_unit/dashboard.php',
        'support_staff' => 'support/dashboard.php'
    ];
    
    if (isset($redirects[$userType])) {
        header('Location: ' . $redirects[$userType]);
        exit;
    }
}

// =============================================================================
// SECTION 3: LOGIN SYSTEM WITH 2FA (login/index.php - Core Logic)
// =============================================================================

/**
 * Professional Login System with Two-Factor Authentication
 * Supports multiple user types: Admin, Student, Trainee, Instructor, Instructional Unit, Support Staff
 */

class LoginSystem {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Create 2FA tables if not exist
     */
    public function create2FATables() {
        try {
            $this->conn->exec("CREATE TABLE IF NOT EXISTS twofa_codes (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                code VARCHAR(10) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                used TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $this->conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS twofa_enabled TINYINT(1) DEFAULT 0");
            $this->conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS twofa_secret VARCHAR(50) DEFAULT NULL");
        } catch (PDOException $e) {
            // Tables may already exist
        }
    }
    
    /**
     * Generate 6-digit verification code
     */
    public function generateCode() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Send verification email with 2FA code
     */
    public function sendVerificationEmail($email, $code, $userName) {
        $subject = 'Your Verification Code - TESDA Auto Mechanic';
        $body = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verification Code</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fc; padding: 30px; border-radius: 10px;">
        <h2 style="color: #2563eb; margin-bottom: 20px;">Two-Factor Authentication</h2>
        <p>Hi ' . htmlspecialchars($userName) . ',</p>
        <p>Your verification code is:</p>
        <div style="background: #ffffff; padding: 20px; text-align: center; font-size: 32px; letter-spacing: 10px; font-weight: bold; margin: 20px 0; border-radius: 8px; border: 2px solid #2563eb;">
            ' . $code . '
        </div>
        <p style="color: #6b7280; font-size: 14px;">This code will expire in 5 minutes.</p>
    </div>
</body>
</html>';
        
        $headers = "From: TESDA <noreply@tesda.gov.ph>\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($email, $subject, $body, $headers);
    }
    
    /**
     * Authenticate user with username/password
     */
    public function authenticateUser($username, $password, $userType) {
        $sql = "SELECT user_id, username, password, email, user_type, first_name, last_name, status, twofa_enabled 
                FROM users 
                WHERE (username = ? OR email = ?) AND user_type = ? AND status = 'active'";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$username, $username, $userType]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
    
    /**
     * Complete login by setting session variables
     */
    public function completeLogin($user) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['login_time'] = time();
        $_SESSION['userId'] = $user['user_id'];
        $_SESSION['userRole'] = $user['user_type'];
        
        // Update last login
        $stmt = $this->conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        
        return true;
    }
    
    /**
     * Verify 2FA code
     */
    public function verify2FACode($userId, $code) {
        $stmt = $this->conn->prepare("
            SELECT code FROM twofa_codes 
            WHERE user_id = ? AND code = ? AND expires_at > NOW() AND used = 0
        ");
        $stmt->execute([$userId, $code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $stmt = $this->conn->prepare("UPDATE twofa_codes SET used = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            return true;
        }
        return false;
    }
}

// =============================================================================
// SECTION 4: PRE-ENROLLMENT SYSTEM (pre_enrollment.php - Core Logic)
// =============================================================================

/**
 * Pre-Enrollment System for TESDA Auto Mechanic Training Centre
 * Handles new student applications and enrollment processing
 */

class PreEnrollmentSystem {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Validate pre-enrollment application data
     */
    public function validateApplication($data) {
        $errors = [];
        
        // Required fields validation
        $required_fields = [
            'first_name', 'last_name', 'birth_date', 'gender', 'contact_number',
            'email_address', 'complete_address', 'barangay', 'city_municipality',
            'province', 'civil_status', 'highest_educational_attainment',
            'employment_status', 'preferred_training_schedule', 'reason_for_applying',
            'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_number'
        ];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
            }
        }
        
        // Email validation
        if (!empty($data['email_address']) && !filter_var($data['email_address'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address format";
        }
        
        // Age validation
        if (!empty($data['birth_date'])) {
            $birthDateObj = new DateTime($data['birth_date']);
            $today = new DateTime();
            $age = $today->diff($birthDateObj)->y;
            if ($age < 16) {
                $errors[] = "Applicant must be at least 16 years old";
            }
            if ($birthDateObj > $today) {
                $errors[] = "Birth date cannot be in future";
            }
        }
        
        // Year graduated validation
        if (!empty($data['year_graduated'])) {
            $currentYear = (int)date('Y');
            $yearGraduated = (int)$data['year_graduated'];
            if ($yearGraduated < 1950 || $yearGraduated > $currentYear) {
                $errors[] = "Invalid year graduated";
            }
        }
        
        return $errors;
    }
    
    /**
     * Check for duplicate applications
     */
    public function checkDuplicateApplication($email) {
        $stmt = $this->conn->prepare("SELECT pre_enroll_id FROM pre_enrollment_applications WHERE email_address = ? AND application_status NOT IN ('Rejected', 'Not Qualified')");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Generate application number
     */
    public function generateApplicationNumber() {
        return 'APP-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Submit pre-enrollment application
     */
    public function submitApplication($data) {
        $application_number = $this->generateApplicationNumber();
        
        $sql = "INSERT INTO pre_enrollment_applications (
            application_number, first_name, last_name, middle_name, birth_date, gender,
            contact_number, email_address, complete_address, barangay, city_municipality,
            province, postal_code, civil_status, citizenship, highest_educational_attainment,
            school_last_attended, year_graduated, employment_status, monthly_income,
            preferred_training_schedule, preferred_start_date, has_previous_tesda_training,
            previous_tesa_course, reason_for_applying, emergency_contact_name,
            emergency_contact_relationship, emergency_contact_number, application_status,
            application_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";
        
        $params = [
            $application_number,
            $data['first_name'],
            $data['last_name'],
            $data['middle_name'] ?? '',
            $data['birth_date'],
            $data['gender'],
            $data['contact_number'],
            $data['email_address'],
            $data['complete_address'],
            $data['barangay'],
            $data['city_municipality'],
            $data['province'],
            $data['postal_code'] ?? '',
            $data['civil_status'],
            $data['citizenship'] ?? 'Filipino',
            $data['highest_educational_attainment'],
            $data['school_last_attended'] ?? '',
            $data['year_graduated'] ?? null,
            $data['employment_status'],
            $data['monthly_income'] ?? null,
            $data['preferred_training_schedule'],
            $data['preferred_start_date'] ?? null,
            $data['has_previous_tesda_training'] ?? 0,
            $data['previous_tesa_course'] ?? '',
            $data['reason_for_applying'],
            $data['emergency_contact_name'],
            $data['emergency_contact_relationship'],
            $data['emergency_contact_number']
        ];
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }
}

// =============================================================================
// SECTION 5: DATABASE SETUP AND MANAGEMENT (setup_database.php - Core Logic)
// =============================================================================

/**
 * Database Setup and Management System
 * Handles database creation, table setup, and system integration checks
 */

class DatabaseSetup {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $stmt = $this->conn->prepare("SELECT 1 as test");
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get list of all tables in database
     */
    public function getTables() {
        $stmt = $this->conn->prepare("SHOW TABLES");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Check essential system tables
     */
    public function checkEssentialTables() {
        $tables = $this->getTables();
        $essential_tables = [
            'users' => 'User authentication and management',
            'student' => 'Student information',
            'pre_enrollment_applications' => 'Pre-enrollment applications',
            'scholarship_applications' => 'Scholarship applications',
            'training_modules' => 'Training modules',
            'user_access_assignments' => 'Access management',
            'module_access_permissions' => 'Module permissions'
        ];
        
        $results = [];
        foreach ($essential_tables as $table => $description) {
            $results[$table] = [
                'description' => $description,
                'exists' => in_array($table, $tables)
            ];
        }
        
        return $results;
    }
    
    /**
     * Test basic database operations
     */
    public function testDatabaseOperations() {
        try {
            // Test basic query
            $stmt = $this->conn->prepare("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'tesda_auto_mechanic'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Test insert capability
            $this->conn->beginTransaction();
            $stmt = $this->conn->prepare("CREATE TABLE IF NOT EXISTS setup_test (id INT AUTO_INCREMENT PRIMARY KEY, test_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            $stmt->execute();
            $stmt = $this->conn->prepare("INSERT INTO setup_test () VALUES ()");
            $stmt->execute();
            $this->conn->rollBack();
            
            return [
                'success' => true,
                'table_count' => $result['table_count']
            ];
        } catch(PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check system file integration
     */
    public function checkSystemFiles() {
        $system_files = [
            'pre_enrollment.php' => 'Pre-enrollment system',
            'scholarship_application.php' => 'Scholarship application system',
            'login.php' => 'Login system',
            'student/learning_modules.php' => 'Student learning modules',
            'admin/access_management.php' => 'Admin access management'
        ];
        
        $results = [];
        foreach ($system_files as $file => $description) {
            $results[$file] = [
                'description' => $description,
                'exists' => file_exists($file)
            ];
        }
        
        return $results;
    }
}

// =============================================================================
// SECTION 6: UTILITY FUNCTIONS AND HELPERS
// =============================================================================

/**
 * Utility functions for the TESDA system
 */

class SystemUtils {
    /**
     * Generate CSRF token for form protection
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Log system events
     */
    public static function logEvent($conn, $userId, $eventType, $resourceType, $action, $status, $details = null) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO access_logs (user_id, access_type, resource_type, access_action, access_timestamp, access_status, failure_reason)
                VALUES (?, ?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([$userId, $eventType, $resourceType, $action, $status, $details]);
        } catch (PDOException $e) {
            error_log("Failed to log event: " . $e->getMessage());
        }
    }
    
    /**
     * Check if user has permission for specific resource
     */
    public static function checkPermission($conn, $userId, $resourceType, $action) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as has_permission 
            FROM user_access_assignments uaa
            JOIN module_access_permissions map ON uaa.permission_id = map.permission_id
            WHERE uaa.user_id = ? AND map.resource_type = ? AND map.access_action = ?
            AND uaa.status = 'Active' AND uaa.expires_at > NOW()
        ");
        $stmt->execute([$userId, $resourceType, $action]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['has_permission'] > 0;
    }
}

// =============================================================================
// SECTION 7: SYSTEM CONFIGURATION CONSTANTS
// =============================================================================

/**
 * System-wide configuration constants
 */
define('TESDA_SYSTEM_NAME', 'TESDA Auto Mechanic Training Centre');
define('TESDA_SYSTEM_VERSION', '1.0.0');
define('TESDA_DB_NAME', 'tesda_auto_mechanic');
define('TESDA_SESSION_TIMEOUT', 3600); // 1 hour
define('TESDA_MAX_LOGIN_ATTEMPTS', 5);
define('TESDA_2FA_CODE_LIFETIME', 300); // 5 minutes

// User types
define('USER_TYPE_ADMIN', 'admin');
define('USER_TYPE_STUDENT', 'student');
define('USER_TYPE_TRAINEE', 'trainee');
define('USER_TYPE_INSTRUCTOR', 'instructor');
define('USER_TYPE_INSTRUCTIONAL_UNIT', 'instructional_unit');
define('USER_TYPE_SUPPORT_STAFF', 'support_staff');

// Application statuses
define('STATUS_PENDING', 'Pending');
define('STATUS_APPROVED', 'Approved');
define('STATUS_REJECTED', 'Rejected');
define('STATUS_ACTIVE', 'Active');
define('STATUS_INACTIVE', 'Inactive');

// =============================================================================
// SECTION 8: INITIALIZATION AND BOOTSTRAP
// =============================================================================

/**
 * System initialization
 */
function initializeSystem() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Generate CSRF token
    SystemUtils::generateCSRFToken();
    
    // Set default timezone
    date_default_timezone_set('Asia/Manila');
    
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn === null) {
        die("Database connection failed. Please check your configuration.");
    }
    
    return $conn;
}

/**
 * Security headers
 */
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// =============================================================================
// END OF CONSOLIDATED SOURCE CODE
// =============================================================================

/**
 * Usage Instructions:
 * 
 * 1. Database Setup:
 *    - Ensure MySQL server is running
 *    - Import the SQL schema file: tesda_auto_mechanic_integrated_system.sql
 *    - Update database credentials in the Database class if needed
 * 
 * 2. System Access:
 *    - Main entry point: index.php
 *    - Login portal: login/index.php
 *    - Pre-enrollment: pre_enrollment.php
 *    - Database setup: setup_database.php
 * 
 * 3. User Types:
 *    - Admin: Full system administration
 *    - Student: Access courses and materials
 *    - Trainee: Training modules and assessments
 *    - Instructor: Manage courses and student progress
 *    - Instructional Unit: Oversee training programs
 *    - Support Staff: Administrative functions
 * 
 * 4. Security Features:
 *    - Two-factor authentication (2FA)
 *    - CSRF protection
 *    - Password hashing with PHP's password_hash()
 *    - Session management
 *    - Input sanitization
 * 
 * 5. Main Features:
 *    - User authentication and authorization
 *    - Pre-enrollment application system
 *    - Scholarship application processing
 *    - Training module management
 *    - Access control and permissions
 *    - Comprehensive logging system
 */

?>
