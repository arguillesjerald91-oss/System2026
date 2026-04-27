<?php
/**
 * Comprehensive Login System for TESDA Auto Mechanic Training Centre
 * Supports: Admin, Trainees, Instructional Unit, Instructors, Support Staff
 */

session_start();
include __DIR__ . '/../db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    switch($_SESSION['user_type']) {
        case 'admin':
            header('Location: ../admin/admin_dashboard.php');
            exit;
        case 'student':
        case 'trainee':
            header('Location: ../student/student_dashboard.php');
            exit;
        case 'instructor':
            header('Location: ../instructor/instructor_dashboard.php');
            exit;
        case 'instructional_unit':
            header('Location: ../instructional_unit/dashboard.php');
            exit;
        case 'support_staff':
            header('Location: ../support/dashboard.php');
            exit;
        default:
            header('Location: ../index.php');
            exit;
    }
}

$database = new Database();
$conn = $database->getConnection();

$error = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $userType = $_POST['user_type'] ?? 'student';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        try {
            // Unified authentication using users table
            $sql = "SELECT user_id, username, password, email, user_type, first_name, last_name, status 
                    FROM users 
                    WHERE (username = ? OR email = ?) AND user_type = ? AND status = 'active'";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username, $username, $userType]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Successful login - set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
                $_SESSION['login_time'] = time();
                
                // Update last login
                $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $updateStmt->execute([$user['user_id']]);
                
                // Log successful login
                $logStmt = $conn->prepare("
                    INSERT INTO access_logs (user_id, access_type, resource_type, access_action, access_timestamp, access_status)
                    VALUES (?, 'Login', 'System', 'User Login', NOW(), 'Success')
                ");
                $logStmt->execute([$user['user_id']]);
                
                // Redirect based on user type
                switch($userType) {
                    case 'admin':
                        header('Location: ../admin/admin_dashboard.php');
                        exit;
                    case 'student':
                    case 'trainee':
                        header('Location: ../student/student_dashboard.php');
                        exit;
                    case 'instructor':
                        header('Location: ../instructor/instructor_dashboard.php');
                        exit;
                    case 'instructional_unit':
                        header('Location: ../instructional_unit/dashboard.php');
                        exit;
                    case 'support_staff':
                        header('Location: ../support/dashboard.php');
                        exit;
                    default:
                        header('Location: ../index.php');
                        exit;
                }
                
            } else {
                // Failed login - log attempt
                $logStmt = $conn->prepare("
                    INSERT INTO access_logs (access_type, resource_type, access_action, access_timestamp, access_status, failure_reason)
                    VALUES ('Login', 'System', 'User Login', NOW(), 'Failed', ?)
                ");
                $logStmt->execute(["Invalid credentials for $userType: $username"]);
                
                $error = "Invalid username or password";
            }
            
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            
            // Log database error
            $logStmt = $conn->prepare("
                INSERT INTO access_logs (access_type, resource_type, access_action, access_timestamp, access_status, failure_reason)
                VALUES ('Login', 'System', 'User Login', NOW(), 'Error', ?)
            ");
            $logStmt->execute([$e->getMessage()]);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Login to TESDA Auto Mechanic Training Centre portal for students, trainees, instructors, and staff">
<title>Login - TESDA Auto Mechanic Training Centre</title>
<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f8f9fc;
    color: #2d2d2d;
}
:root {
  --background: #f8f9fc;
  --foreground: #2d2d2d;
  --card: #ffffff;
  --card-foreground: #2d2d2d;
  --primary: #2563eb;
  --muted-foreground: #6b7280;
  --radius: 14px;
  --shadow-soft: 0 4px 15px rgba(0,0,0,0.08);
  --shadow-card: 0 8px 25px rgba(0,0,0,0.12);
}
header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(6px);
    border-bottom: 1px solid #ddd;
    padding: 20px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.logo {
    display: flex;
    align-items: center;
    gap: 12px;
}
.logo-box {
    width: 50px;
    height: 50px;
    border-radius: 50px;
    display: flex;
    justify-content: center;
    align-items: center;
}
header a {
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
}
.btn-primary {
    background: #2563eb;
    color: white;
}
.btn-primary:hover {
    background: #1e4dcc;
}
.container {
    max-width: 600px;
    margin: 60px auto;
    padding: 0 20px;
}
.login-container {
    background: white;
    border-radius: 15px;
    padding: 40px;
    box-shadow: var(--shadow-card);
}
.login-header {
    text-align: center;
    margin-bottom: 40px;
}
.login-header h2 {
    color: #1f2937;
    font-size: 32px;
    margin-bottom: 10px;
}
.login-header p {
    color: #6b7280;
}
.user-type-tabs {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 8px;
    margin-bottom: 30px;
    background: #f3f4f6;
    padding: 8px;
    border-radius: 10px;
}
.user-type-tab {
    padding: 10px 8px;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    color: #6b7280;
    transition: all 0.3s ease;
    text-align: center;
    font-size: 13px;
    line-height: 1.2;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.user-type-tab.active {
    background: white;
    color: #2563eb;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.user-type-tab:hover:not(.active) {
    background: rgba(255,255,255,0.5);
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
}
.form-group input {
    width: 100%;
    padding: 15px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 16px;
    transition: all 0.3s ease;
    box-sizing: border-box;
}
.form-group input.error {
    border-color: #dc2626;
    background: #fef2f2;
}
.form-group input.success {
    border-color: #16a34a;
    background: #f0fdf4;
}
.form-group input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}
.login-btn {
    width: 100%;
    padding: 15px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 20px;
    position: relative;
}
.login-btn:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
}
.login-btn.loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s ease-in-out infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.login-btn:hover {
    background: #1e4dcc;
    transform: translateY(-2px);
}
.forgot-password {
    text-align: center;
}
.forgot-password a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
}
.forgot-password a:hover {
    text-decoration: underline;
}
.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #dc2626;
}
.success {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #28a745;
}
.register-link {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}
.register-link p {
    color: #6b7280;
}
.register-link a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
}
.register-link a:hover {
    text-decoration: underline;
}
.user-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #6c757d;
}
.password-wrapper {
    position: relative;
}
.password-toggle {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 5px;
    font-size: 18px;
}
.password-toggle:hover {
    color: #2563eb;
}
.form-feedback {
    font-size: 12px;
    margin-top: 5px;
    display: none;
}
.form-feedback.error {
    color: #dc2626;
    display: block;
}
.form-feedback.success {
    color: #16a34a;
    display: block;
}
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
@media (max-width: 768px) {
    .container {
        margin: 40px auto;
        padding: 0 15px;
    }
    .login-container {
        padding: 30px 25px;
    }
    header {
        padding: 15px 20px;
        flex-direction: column;
        gap: 15px;
    }
    .user-type-tabs {
        grid-template-columns: repeat(2, 1fr);
        gap: 5px;
        padding: 5px;
    }
    .user-type-tab {
        font-size: 12px;
        padding: 8px 6px;
        min-height: 40px;
    }
}
</style>
</head>
<body>
<header role="banner">
    <div class="logo">
        <div class="logo-box">
            <img src="../images/image.png" width="35" height="35" alt="TESDA Auto Mechanic Training Centre Logo">
        </div>
        <strong>TESDA Auto Mechanic Training Centre</strong>
    </div>
    <nav>
        <a href="../index.php" class="btn-primary">Back to Home</a>
    </nav>
</header>

<main class="container" role="main">
    <section class="login-container" aria-labelledby="login-heading">
        <header class="login-header">
            <h2 id="login-heading">Welcome Back</h2>
            <p>Sign in to access your training portal</p>
        </header>
        
        <?php if (!empty($error)): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
        <div class="success">
            <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" aria-label="Login form" novalidate>
            <fieldset class="user-type-tabs" role="radiogroup" aria-labelledby="user-type-label">
                <legend id="user-type-label" class="sr-only">Select User Type</legend>
                <button type="button" class="user-type-tab active" onclick="switchUserType('student', this)" role="radio" aria-checked="true" aria-label="Student">
                    Student
                </button>
                <button type="button" class="user-type-tab" onclick="switchUserType('trainee', this)" role="radio" aria-checked="false" aria-label="Trainee">
                    Trainee
                </button>
                <button type="button" class="user-type-tab" onclick="switchUserType('instructor', this)" role="radio" aria-checked="false" aria-label="Instructor">
                    Instructor
                </button>
                <button type="button" class="user-type-tab" onclick="switchUserType('instructional_unit', this)" role="radio" aria-checked="false" aria-label="Instructional Unit">
                    Instructional Unit
                </button>
                <button type="button" class="user-type-tab" onclick="switchUserType('support_staff', this)" role="radio" aria-checked="false" aria-label="Support Staff">
                    Support Staff
                </button>
                <button type="button" class="user-type-tab" onclick="switchUserType('admin', this)" role="radio" aria-checked="false" aria-label="Admin">
                    Admin
                </button>
            </fieldset>
            
            <input type="hidden" name="user_type" id="user_type" value="student">
            
            <div class="user-info" id="user_info" role="status" aria-live="polite">
                <strong>Student Portal:</strong> Access your courses, grades, and training materials
            </div>
            
            <div class="form-group">
                <label for="username">Username / Email</label>
                <input type="text" id="username" name="username" required placeholder="Enter your username or email" autocomplete="username" aria-describedby="username-feedback">
                <div class="form-feedback" id="username-feedback" aria-live="polite"></div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required placeholder="Enter your password" autocomplete="current-password" aria-describedby="password-feedback">
                    <button type="button" class="password-toggle" id="password-toggle" aria-label="Toggle password visibility">
                        <span id="password-icon" aria-hidden="true">?</span>
                    </button>
                </div>
                <div class="form-feedback" id="password-feedback" aria-live="polite"></div>
            </div>
            
            <button type="submit" class="login-btn" id="login-btn">Sign In</button>
        </form>
        
        <div class="forgot-password">
            <a href="../forgot_password.php">Forgot your password?</a>
        </div>
        
        <div class="register-link">
            <p>New to TESDA Training Centre? <a href="../pre_enrollment.php">Apply for Pre-Enrollment</a></p>
        </div>
    </section>
</main>

<script>
function switchUserType(type, element) {
    // Update hidden input
    document.getElementById('user_type').value = type;
    
    // Update tab styles and accessibility
    const tabs = document.querySelectorAll('.user-type-tab');
    tabs.forEach(tab => {
        tab.classList.remove('active');
        tab.setAttribute('aria-checked', 'false');
    });
    element.classList.add('active');
    element.setAttribute('aria-checked', 'true');
    
    // Update user info and placeholder based on user type
    const usernameInput = document.getElementById('username');
    const userInfo = document.getElementById('user_info');
    
    switch(type) {
        case 'student':
            usernameInput.placeholder = 'Enter your student username or email';
            userInfo.innerHTML = '<strong>Student Portal:</strong> Access your courses, grades, and training materials';
            break;
        case 'trainee':
            usernameInput.placeholder = 'Enter your trainee username or email';
            userInfo.innerHTML = '<strong>Trainee Portal:</strong> Access your training modules and assessments';
            break;
        case 'instructor':
            usernameInput.placeholder = 'Enter your instructor username';
            userInfo.innerHTML = '<strong>Instructor Portal:</strong> Manage courses, assessments, and student progress';
            break;
        case 'instructional_unit':
            usernameInput.placeholder = 'Enter your instructional unit username';
            userInfo.innerHTML = '<strong>Instructional Unit Portal:</strong> Oversee training programs and curriculum management';
            break;
        case 'support_staff':
            usernameInput.placeholder = 'Enter your support staff username';
            userInfo.innerHTML = '<strong>Support Portal:</strong> Administrative and support functions';
            break;
        case 'admin':
            usernameInput.placeholder = 'Enter your admin username';
            userInfo.innerHTML = '<strong>Admin Portal:</strong> Full system administration and management';
            break;
    }
    
    // Clear any previous validation states
    clearValidationStates();
}

function clearValidationStates() {
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const usernameFeedback = document.getElementById('username-feedback');
    const passwordFeedback = document.getElementById('password-feedback');
    
    usernameInput.classList.remove('error', 'success');
    passwordInput.classList.remove('error', 'success');
    usernameFeedback.classList.remove('error', 'success');
    passwordFeedback.classList.remove('error', 'success');
    usernameFeedback.textContent = '';
    passwordFeedback.textContent = '';
}

function validateField(input, feedbackElement) {
    const value = input.value.trim();
    
    input.classList.remove('error', 'success');
    feedbackElement.classList.remove('error', 'success');
    feedbackElement.textContent = '';
    
    if (value.length === 0) {
        input.classList.add('error');
        feedbackElement.classList.add('error');
        feedbackElement.textContent = 'This field is required';
        return false;
    }
    
    if (input.id === 'username' && value.length < 3) {
        input.classList.add('error');
        feedbackElement.classList.add('error');
        feedbackElement.textContent = 'Username must be at least 3 characters';
        return false;
    }
    
    if (input.id === 'password' && value.length < 6) {
        input.classList.add('error');
        feedbackElement.classList.add('error');
        feedbackElement.textContent = 'Password must be at least 6 characters';
        return false;
    }
    
    input.classList.add('success');
    feedbackElement.classList.add('success');
    feedbackElement.textContent = 'Looks good!';
    return true;
}

// Password visibility toggle
document.getElementById('password-toggle').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const passwordIcon = document.getElementById('password-icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordIcon.textContent = '?';
    } else {
        passwordInput.type = 'password';
        passwordIcon.textContent = '?';
    }
});

// Real-time validation
document.getElementById('username').addEventListener('blur', function() {
    validateField(this, document.getElementById('username-feedback'));
});

document.getElementById('password').addEventListener('blur', function() {
    validateField(this, document.getElementById('password-feedback'));
});

// Enhanced form validation and submission
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    const loginBtn = document.getElementById('login-btn');
    const usernameFeedback = document.getElementById('username-feedback');
    const passwordFeedback = document.getElementById('password-feedback');
    
    // Validate fields
    const isUsernameValid = validateField(username, usernameFeedback);
    const isPasswordValid = validateField(password, passwordFeedback);
    
    if (!isUsernameValid || !isPasswordValid) {
        // Focus on first invalid field
        if (!isUsernameValid) {
            username.focus();
        } else {
            password.focus();
        }
        return false;
    }
    
    // Show loading state
    loginBtn.disabled = true;
    loginBtn.classList.add('loading');
    loginBtn.textContent = 'Signing in...';
    
    // Submit form after a short delay for better UX
    setTimeout(() => {
        this.submit();
    }, 500);
});

// Auto-focus username field
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('username').focus();
});
</script>
</body>
</html>
