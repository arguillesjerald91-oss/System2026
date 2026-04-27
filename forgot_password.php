<?php
session_start();
include 'db.php';
$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        $sql = "SELECT UserID, Username, Email FROM users WHERE Email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // Insert into password_resets
            $conn->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            $sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$email, $token, $expires]);

            // Reset link (for now, just show on screen)
   
            $resetLink = "http://localhost/student_portal/reset_password.php?token=" . $token;


            $success = "A password reset link has been generated.<br>
                        <a href='$resetLink'>Click here to reset password</a>";
        } else {
            $error = "No account found with that email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="password/forgot.css">
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-box">
                <img src="images/image.png" width="35" height="35" alt="Logo">
            </div>
            <h1>TESDA Training Portal</h1>
            <p>Reset your password to regain access to the Student Portal.</p>
        </div>
        
        <div class="right-panel">
            <h2>Forgot Password</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Enter your Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your registered email" required>
                </div>
                
                <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <button type="submit" class="btn-login">Send Reset Link</button>
            </form>

            <div class="create-account">
                <p>Remembered your password? <a href="index.php">Login here</a></p>
            </div>
        </div>
    </div>
</body>
</html>
