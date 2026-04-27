<?php
/**
 * Professional Login System with 2FA
 * TESDA Auto Mechanic Training Centre
 */

session_start();
include __DIR__ . '/../db.php';

$database = new Database();
$conn = $database->getConnection();

// Create 2FA tables if not exist
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS twofa_codes (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        code VARCHAR(10) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS twofa_enabled TINYINT(1) DEFAULT 0");
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS twofa_secret VARCHAR(50) DEFAULT NULL");
} catch (PDOException $e) {
    // Tables may already exist
}

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $redirects = [
        'admin' => '../admin/admin_dashboard.php',
        'student' => '../student/student_dashboard.php',
        'trainee' => '../student/student_dashboard.php',
        'instructor' => '../instructor/instructor_dashboard.php',
        'instructional_unit' => '../instructional_unit/dashboard.php',
        'support_staff' => '../support/dashboard.php'
    ];
    $userType = $_SESSION['user_type'];
    if (isset($redirects[$userType])) {
        header('Location: ' . $redirects[$userType]);
        exit;
    }
}

$error = '';
$success_message = '';
$step = 1;
$pending_user = null;

// 2FA Functions
function generateCode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendVerificationEmail($email, $code, $userName) {
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'login';
    
    if ($action == 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $userType = $_POST['user_type'] ?? 'student';
        
        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password";
        } else {
            try {
                $sql = "SELECT user_id, username, password, email, user_type, first_name, last_name, status, twofa_enabled 
                        FROM users 
                        WHERE (username = ? OR email = ?) AND user_type = ? AND status = 'active'";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([$username, $username, $userType]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password'])) {
                    if (!empty($user['twofa_enabled'])) {
                        // Need 2FA verification
                        $_SESSION['pending_user_id'] = $user['user_id'];
                        $_SESSION['pending_user_type'] = $user['user_type'];
                        $_SESSION['pending_user_email'] = $user['email'];
                        $_SESSION['pending_user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['pending_username'] = $user['username'];
                        
                        // Send 2FA code
                        $code = generateCode();
                        $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                        
                        $stmt = $conn->prepare("DELETE FROM twofa_codes WHERE user_id = ?");
                        $stmt->execute([$user['user_id']]);
                        
                        $stmt = $conn->prepare("INSERT INTO twofa_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
                        $stmt->execute([$user['user_id'], $code, $expires]);
                        
                        sendVerificationEmail($user['email'], $code, $user['first_name'] . ' ' . $user['last_name']);
                        
                        $step = 2;
                        $success_message = "Please enter the verification code sent to your email.";
                    } else {
                        // No 2FA, complete login
                        completeLogin($user, $conn);
                    }
                } else {
                    $error = "Invalid username or password";
                }
            } catch (PDOException $e) {
                $error = "Login error: " . $e->getMessage();
            }
        }
    } elseif ($action == 'verify_2fa') {
        $code = trim($_POST['twofa_code'] ?? '');
        $userId = $_SESSION['pending_user_id'] ?? null;
        
        if (!$userId || empty($code)) {
            $error = "Invalid verification request";
        } else {
            $stmt = $conn->prepare("
                SELECT code FROM twofa_codes 
                WHERE user_id = ? AND code = ? AND expires_at > NOW() AND used = 0
            ");
            $stmt->execute([$userId, $code]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $stmt = $conn->prepare("UPDATE twofa_codes SET used = 1 WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                completeLogin($user, $conn);
            } else {
                $error = "Invalid or expired verification code";
                $step = 2;
            }
        }
    } elseif ($action == 'resend_code') {
        $userId = $_SESSION['pending_user_id'] ?? null;
        if ($userId) {
            $code = generateCode();
            $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            
            $stmt = $conn->prepare("DELETE FROM twofa_codes WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $stmt = $conn->prepare("INSERT INTO twofa_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $code, $expires]);
            
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                sendVerificationEmail($user['email'], $code, $user['first_name']);
                $success_message = "A new verification code has been sent.";
            }
            $step = 2;
        }
    }
}

function completeLogin($user, $conn) {
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
    
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    
    unset($_SESSION['pending_user_id'], $_SESSION['pending_user_type']);
    
    $redirects = [
        'admin' => '../admin/admin_dashboard.php',
        'student' => '../student/student_dashboard.php',
        'trainee' => '../student/student_dashboard.php',
        'instructor' => '../instructor/instructor_dashboard.php',
        'instructional_unit' => '../instructional_unit/dashboard.php',
        'support_staff' => '../support/dashboard.php'
    ];
    $userType = $user['user_type'];
    header('Location: ' . ($redirects[$userType] ?? '../index.php'));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - TESDA Auto Mechanic Training Centre</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    margin: 0;
    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #06b6d4 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.login-wrapper {
    width: 100%;
    max-width: 440px;
}
.login-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
}
.login-header {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
    padding: 40px 30px 30px;
    text-align: center;
    color: white;
    position: relative;
}
.login-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
}
.logo-icon {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 36px;
    position: relative;
}
.login-header h1 {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 5px;
    position: relative;
}
.login-header p {
    opacity: 0.9;
    font-size: 14px;
    position: relative;
}
.login-body {
    padding: 30px;
}
.step-indicator {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 30px;
}
.step {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #e5e7eb;
    transition: all 0.3s;
}
.step.active {
    background: #3b82f6;
    transform: scale(1.3);
}
.step.completed {
    background: #10b981;
}
.error {
    background: #fef2f2;
    color: #dc2626;
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    border-left: 4px solid #dc2626;
}
.success {
    background: #f0fdf4;
    color: #16a34a;
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    border-left: 4px solid #16a34a;
}
.user-type-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 25px;
}
.user-type-tab {
    flex: 1;
    min-width: 65px;
    padding: 12px 6px;
    border: 2px solid #e5e7eb;
    background: white;
    border-radius: 10px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    transition: all 0.2s;
    text-align: center;
}
.user-type-tab:hover {
    border-color: #3b82f6;
    color: #3b82f6;
}
.user-type-tab.active {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
    border-color: #3b82f6;
    color: white;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}
.form-group input {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.2s;
}
.form-group input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}
.login-btn {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.35);
}
.code-input {
    font-size: 28px !important;
    text-align: center;
    letter-spacing: 12px;
    font-weight: bold;
}
.resend-link {
    text-align: center;
    margin-top: 20px;
}
.resend-link a, .resend-link button {
    color: #3b82f6;
    text-decoration: none;
    background: none;
    border: none;
    font-size: 14px;
    cursor: pointer;
}
.resend-link a:hover, .resend-link button:hover {
    text-decoration: underline;
}
.divider {
    display: flex;
    align-items: center;
    margin: 25px 0;
}
.divider::before, .divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e5e7eb;
}
.divider span {
    padding: 0 15px;
    color: #9ca3af;
    font-size: 12px;
}
.footer-links {
    text-align: center;
    font-size: 14px;
    color: #6b7280;
}
.footer-links a {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
}
.footer-links a:hover {
    text-decoration: underline;
}
.back-link {
    text-align: center;
    margin-top: 15px;
}
.back-link a {
    color: #6b7280;
    text-decoration: none;
    font-size: 14px;
}
.back-link a:hover {
    color: #3b82f6;
}
</style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <div class="logo-icon">⚙️</div>
            <h1>TESDA Auto Mechanic</h1>
            <p>Training Centre Portal</p>
        </div>
        
        <div class="login-body">
            <div class="step-indicator">
                <div class="step <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'completed' : '' ?>"></div>
                <div class="step <?= $step >= 2 ? 'active' : '' ?>"></div>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
            <div class="success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                
                <div class="user-type-tabs">
                    <button type="button" class="user-type-tab active" onclick="setUserType('student', this)">Student</button>
                    <button type="button" class="user-type-tab" onclick="setUserType('trainee', this)">Trainee</button>
                    <button type="button" class="user-type-tab" onclick="setUserType('instructor', this)">Instructor</button>
                    <button type="button" class="user-type-tab" onclick="setUserType('instructional_unit', this)">Unit</button>
                    <button type="button" class="user-type-tab" onclick="setUserType('support_staff', this)">Support</button>
                    <button type="button" class="user-type-tab" onclick="setUserType('admin', this)">Admin</button>
                </div>
                
                <input type="hidden" name="user_type" id="user_type" value="student">
                
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required placeholder="Enter username or email" autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter password" autocomplete="current-password">
                </div>
                
                <button type="submit" class="login-btn">Sign In</button>
            </form>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="verify_2fa">
                
                <div class="form-group">
                    <label for="twofa_code">Verification Code</label>
                    <input type="text" id="twofa_code" name="twofa_code" class="code-input" required placeholder="------" maxlength="6" autocomplete="one-time-code" inputmode="numeric">
                    <p style="font-size: 12px; color: #6b7280; margin-top: 10px;">Enter the 6-digit code sent to your email</p>
                </div>
                
                <button type="submit" class="login-btn">Verify Code</button>
                
                <div class="resend-link">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="resend_code">
                        <button type="submit">Resend Code</button>
                    </form>
                </div>
                
                <div class="back-link">
                    <a href="?step=1">← Back to Login</a>
                </div>
            </form>
            <?php endif; ?>
            
            <div class="divider"><span>OR</span></div>
            
            <div class="footer-links">
                <p>New applicant? <a href="../pre_enrollment.php">Apply for Enrollment</a></p>
                <p style="margin-top: 8px;"><a href="../forgot_password.php">Forgot Password?</a></p>
            </div>
        </div>
    </div>
</div>

<script>
function setUserType(type, element) {
    document.getElementById('user_type').value = type;
    document.querySelectorAll('.user-type-tab').forEach(tab => tab.classList.remove('active'));
    element.classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('twofa_code');
    if (codeInput) {
        codeInput.focus();
        codeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            if (this.value.length === 6) {
                document.querySelector('form').submit();
            }
        });
    } else {
        document.getElementById('username').focus();
    }
});
</script>
</body>
</html>