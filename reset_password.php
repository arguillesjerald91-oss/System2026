<?php
session_start();
include 'db.php';
$database = new Database();
$conn = $database->getConnection();

// ✅ Check token here
if (!isset($_GET['token'])) {
    die("Invalid request");
}

$token = $_GET['token'];
$sql = "SELECT * FROM password_resets WHERE token = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$token]);
$resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resetRequest) {
    die("Invalid token");
}

// ✅ Handle form submission after token check
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newPassword = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $sql = "UPDATE users SET Password = ? WHERE Email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$hashedPassword, $resetRequest['email']]);

        $conn->prepare("DELETE FROM password_resets WHERE email = ?")
             ->execute([$resetRequest['email']]);

        $success = "Password has been reset successfully. <a href='index.php'>Login here</a>";
    }
}
?>
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="password/forgot.css">
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-box">
                <img src="images/image.png" width="35" height="35" alt="Logo">
            </div>
            <h1>TESDA Training Portal</h1>
            <p>Create your new password to continue using the Student Portal.</p>
        </div>
        
        <div class="right-panel">
            <h2>Reset Password</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter new password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password" required>
                </div>
                
                <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <button type="submit" class="btn-login">Reset Password</button>
            </form>
        </div>
    </div>

    <?php if (isset($success) && $success): ?>
<div id="successModal" class="modal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div class="modal-content">
    <h3>Password Reset Successful!</h3>
    <p>You can now login with your new password.</p>
    <button id="modalOkBtn">OK</button>
  </div>
</div>
<script>
  // Show modal after success
  document.getElementById('successModal').style.display = 'flex';
  document.getElementById('modalOkBtn').addEventListener('click', function() {
    window.location.href = "index.php"; // Redirect to login
  });
</script>
<?php endif; ?>

<script src="register/register.js"></script>
</body>
</html>
