<?php 
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['userId'])) {
    header("Location: ../login.php");
    exit();
}
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if (!in_array($userType, ['admin'])) {
    header("Location: ../login.php");
    exit();
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $user_type = $_POST['user_type'] ?? 'trainee';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Username or email already exists";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, user_type, first_name, last_name, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$username, $hashed_password, $email, $user_type, $first_name, $last_name, $status]);
            
            if (in_array($user_type, ['trainee', 'student'])) {
                $userId = $conn->lastInsertId();
                $colsStmt = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student' ORDER BY ORDINAL_POSITION");
                $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
                $firstNameCol = in_array('FirstName', $columns) ? 'FirstName' : (in_array('FName', $columns) ? 'FName' : 'FirstName');
                $lastNameCol = in_array('LastName', $columns) ? 'LastName' : (in_array('LName', $columns) ? 'LName' : 'LastName');
                $emailCol = in_array('Email', $columns) ? 'Email' : (in_array('EmailAddr', $columns) ? 'EmailAddr' : 'Email');
                
                $insertCols = ['SchoolID', $firstNameCol, $lastNameCol, $emailCol, 'Status', 'EnrollmentDate', 'user_id'];
                $insertVals = ['TESDA-' . str_pad($userId, 4, '0', STR_PAD_LEFT), $first_name, $last_name, $email, 'Active', date('Y-m-d H:i:s'), $userId];
                $stmt = $conn->prepare("INSERT INTO student (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', array_fill(0, count($insertVals), '?')) . ")");
                $stmt->execute($insertVals);
            }
            
            header("Location: manage_students.php?success=1");
            exit();
        }
    }
}

$pageTitle = "Add User";
$pageSubtitle = "Create New User Account";
$currentPage = "manage_students.php";

include 'sidebar_new.php';
?>

<div style="max-width: 600px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Create New User</h3>
            <a href="manage_students.php" class="btn" style="padding: 8px 16px; background: #f1f5f9; color: #374151; border-radius: 6px; text-decoration: none;">
                Back to Users
            </a>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                <strong>Please fix the following errors:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div style="display: grid; gap: 16px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Username *</label>
                        <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                            style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Email *</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                            style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" 
                                style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" 
                                style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                        </div>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">User Type *</label>
                        <select name="user_type" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                            <option value="trainee" <?= ($_POST['user_type'] ?? 'trainee') === 'trainee' ? 'selected' : '' ?>>Trainee/Student</option>
                            <option value="instructor" <?= ($_POST['user_type'] ?? '') === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                            <option value="support_staff" <?= ($_POST['user_type'] ?? '') === 'support_staff' ? 'selected' : '' ?>>Support Staff</option>
                            <option value="admin" <?= ($_POST['user_type'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Status</label>
                        <select name="status" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                            <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Password *</label>
                            <input type="password" name="password" required 
                                style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374161;">Confirm Password *</label>
                            <input type="password" name="confirm_password" required 
                                style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn" style="padding: 14px 28px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 15px; cursor: pointer;">
                        Create User
                    </button>
                    <a href="manage_students.php" class="btn" style="padding: 14px 28px; background: #f1f5f9; color: #374151; border-radius: 8px; text-decoration: none;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

</main>
</div>

</body>
</html>