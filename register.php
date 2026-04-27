<?php
session_start();
include __DIR__ . '/db.php';
$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = trim($_POST['student_id']);
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $course     = trim($_POST['course']);
    $year_level = trim($_POST['year_level']);
    $username   = trim($_POST['username']);
    $password   = trim($_POST['password']);
    $confirm    = trim($_POST['confirm_password']);

    // Validation
    if (empty($student_id) || empty($first_name) || empty($last_name) || empty($email) || 
        empty($username) || empty($password) || empty($confirm)) {
        $error = "All required fields must be filled.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // First, insert into student table with all student information - detect columns dynamically
        $colsStmt = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student' ORDER BY ORDINAL_POSITION");
        $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
        $firstNameCol = in_array('FirstName', $columns) ? 'FirstName' : (in_array('FName', $columns) ? 'FName' : 'FirstName');
        $lastNameCol = in_array('LastName', $columns) ? 'LastName' : (in_array('LName', $columns) ? 'LName' : 'LastName');
        $emailCol = in_array('EmailAddr', $columns) ? 'EmailAddr' : (in_array('Email', $columns) ? 'Email' : 'EmailAddr');
        
        // Build dynamic INSERT
        $insertCols = ['StudID', $firstNameCol, $lastNameCol, $emailCol];
        $insertVals = [$student_id, $first_name, $last_name, $email];
        if (in_array('PhoneNo', $columns)) { $insertCols[] = 'PhoneNo'; $insertVals[] = $phone; }
        if (in_array('Phone', $columns)) { $insertCols[] = 'Phone'; $insertVals[] = $phone; }
        if (in_array('Course', $columns)) { $insertCols[] = 'Course'; $insertVals[] = $course; }
        if (in_array('YearLvl', $columns)) { $insertCols[] = 'YearLvl'; $insertVals[] = $year_level; }
        
        $sqlStudent = "INSERT INTO student (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', array_fill(0, count($insertVals), '?')) . ")";
        $stmtStudent = $conn->prepare($sqlStudent);
        
        if ($stmtStudent->execute($insertVals)) {
            // Then, insert into users table with username, password, and email
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $sqlUser = "INSERT INTO users (Username, Password, Email, Role, Fname, Lname, created_at) 
                       VALUES (?, ?, ?, 'student', ?, ?, NOW())";
            $stmtUser = $conn->prepare($sqlUser);
            
            if ($stmtUser->execute([$username, $hashedPassword, $email, $first_name, $last_name])) {
                // Insert a system log entry
                $logMessage = "$first_name $last_name (ID: $student_id) registered for the $course program ($year_level).";
                $logSql = "INSERT INTO system_logs (log_type, message, created_at) VALUES (?, ?, NOW())";
                $logStmt = $conn->prepare($logSql);
                $logStmt->execute(['Student Registration', $logMessage]);

                $success = true;
            } else {
                $error = "Failed to create user account: " . $stmtUser->errorInfo()[2];
            }
        } else {
            $error = "Failed to save student details: " . $stmtStudent->errorInfo()[2];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register - Student Portal</title>
  <link rel="stylesheet" href="register/register.css">
</head>
<body>
<div class="container">
  <div class="left-panel">
      <div class="logo">
        <div class="logo-box">
            <img src="images/image.png" width="35" height="35" alt="Logo">
        </div>
      <h1>TESDA Training Portal</h1>
      <p>Create your student account to access the portal.</p>
  </div>

  <div class="right-panel">
    <h2>Student Registration</h2>

    <?php if (isset($error)): ?>
      <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Multi-step form -->
    <form method="POST" action="" id="registerForm">
      
      <!-- STEP 1 -->
      <div class="form-step active">
        <div class="form-group"><label>Student ID</label><input type="text" name="student_id" required></div>
        <div class="form-group"><label>First Name</label><input type="text" name="first_name" required></div>
        <div class="form-group"><label>Last Name</label><input type="text" name="last_name" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
        <div class="form-group"><label>Phone</label><input type="text" name="phone"></div>
        <div class="form-group"><label><label>Course</label>
  <select name="course" required>
    <option value="">-- Select Course --</option>
    <option value="BSIT">BSIT - Bachelor of Science in Information Technology</option>
    <option value="BSBA">BSBA - Bachelor of Science in Business Administration</option>
    <option value="BEED">BEED - Bachelor of Elementary Education</option>
    <option value="BSED">BSED - Bachelor of Secondary Education</option>
    <option value="BSHM">BSHM - Bachelor of Science in Hospitality Management</option>
    <option value="BSCRIM">BSCRIM - Bachelor of Science in Criminology</option>
  </select>
</div>
        <div class="form-group">
  <label>Year Level</label>
  <select name="year_level" required>
    <option value="">-- Select Year Level --</option>
    <option value="1st Year">1st Year</option>
    <option value="2nd Year">2nd Year</option>
    <option value="3rd Year">3rd Year</option>
    <option value="4th Year">4th Year</option>
  </select>
</div>

        <button type="button" class="btn-login next-btn">Next</button>
      </div>

      <!-- STEP 2 -->
      <div class="form-step">
        <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
        <div class="form-group"><label>Password</label><input type="password" id="password" name="password" required></div>
        <div class="form-group"><label>Confirm Password</label><input type="password" id="confirm_password" name="confirm_password" required></div>
        <button type="button" class="btn-login back-btn">Back</button>
        <button type="submit" class="btn-login">Register</button>
      </div>

    </form>

    <div class="create-account">
      <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
  </div>
</div>


<?php if (isset($success) && $success): ?>
<div id="successModal" class="modal" style="display:flex;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div class="modal-content">
    <h3>✅ Registration Successful!</h3>
    <p>Your account has been created successfully. You can now login with your credentials.</p>
    <button id="modalOkBtn" class="btn-login">OK</button>
  </div>
</div>
<script>
  document.getElementById('modalOkBtn').addEventListener('click', function() {
    window.location.href = "login.php";
  });
</script>
<?php endif; ?>

<script src="register/register.js"></script>
</body>
</html>
