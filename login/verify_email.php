<?php
/**
 * Email Verification Handler
 * Handles email verification from the login flow
 */

session_start();
include __DIR__ . '/../db.php';
include __DIR__ . '/EmailVerification.php';

$database = new Database();
$conn = $database->getConnection();

$error = '';
$success = '';
$verified = false;

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $verifier = new EmailVerification($conn);
    $verification = $verifier->verifyToken($token, 'verification');
    
    if ($verification) {
        $verifier->markTokenUsed($token);
        $verifier->markEmailVerified($verification['user_id']);
        $verified = true;
        $success = 'Your email has been verified successfully! You can now log in.';
    } else {
        $error = 'Invalid or expired verification link. Please request a new verification email.';
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resend_verification'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $verifier = new EmailVerification($conn);
            $verifier->sendVerificationEmail($user, 'verification');
            $success = 'Verification email sent! Please check your inbox.';
        } else {
            $error = 'No account found with that email address';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Email - TESDA Auto Mechanic Training Centre</title>
<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f8f9fc;
    color: #2d2d2d;
}
.container {
    max-width: 500px;
    margin: 100px auto;
    padding: 20px;
}
.card {
    background: white;
    border-radius: 15px;
    padding: 40px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    text-align: center;
}
h1 { color: #1f2937; margin-bottom: 10px; }
p { color: #6b7280; margin-bottom: 20px; }
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
.btn {
    background: #2563eb;
    color: white;
    padding: 15px 30px;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.btn:hover { background: #1e4dcc; }
.form-group { margin-bottom: 20px; }
.form-group input {
    width: 100%;
    padding: 15px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 16px;
    box-sizing: border-box;
}
.icon {
    font-size: 60px;
    margin-bottom: 20px;
}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <?php if ($verified): ?>
            <div class="icon">✅</div>
            <h1>Email Verified!</h1>
            <div class="success"><?= htmlspecialchars($success) ?></div>
            <a href="index.php" class="btn">Go to Login</a>
        <?php else: ?>
            <div class="icon">📧</div>
            <h1>Verify Your Email</h1>
            
            <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <p>Enter your email address to resend the verification link.</p>
            
            <form method="POST">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Your email address" required>
                </div>
                <button type="submit" name="resend_verification" class="btn">Resend Verification Email</button>
            </form>
            
            <p style="margin-top: 20px;"><a href="index.php">Back to Login</a></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>