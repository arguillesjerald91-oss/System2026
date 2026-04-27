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
if (!in_array($userType, ['admin', 'instructional_unit'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_GET['id'] ?? 0;
if (!$userId) {
    header("Location: manage_students.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $user_type = $_POST['user_type'] ?? 'trainee';
    $status = $_POST['status'] ?? 'active';
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($email)) $errors[] = "Email is required";
    
    if (empty($errors)) {
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, user_type = ?, status = ?, password = ? WHERE user_id = ?");
            $stmt->execute([$email, $first_name, $last_name, $user_type, $status, $hashed_password, $userId]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, user_type = ?, status = ? WHERE user_id = ?");
            $stmt->execute([$email, $first_name, $last_name, $user_type, $status, $userId]);
        }
        
        header("Location: manage_students.php?updated=1");
        exit();
    }
}

$pageTitle = "Edit User";
$pageSubtitle = "Update User Account";
$currentPage = "manage_students.php";

include 'sidebar_new.php';
?>

<div style="max-width: 600px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Edit User: <?= htmlspecialchars($user['username']) ?></h3>
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
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Username</label>
                        <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled 
                            style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px; background: #f9fafb;">
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Email *</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                            style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" 
                                style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" 
                                style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                        </div>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">User Type</label>
                        <select name="user_type" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                            <option value="trainee" <?= ($user['user_type'] ?? 'trainee') === 'trainee' ? 'selected' : '' ?>>Trainee/Student</option>
                            <option value="instructor" <?= ($user['user_type'] ?? '') === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                            <option value="support_staff" <?= ($user['user_type'] ?? '') === 'support_staff' ? 'selected' : '' ?>>Support Staff</option>
                            <option value="admin" <?= ($user['user_type'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
                            <option value="instructional_unit" <?= ($user['user_type'] ?? '') === 'instructional_unit' ? 'selected' : '' ?>>Instructional Unit</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Status</label>
                        <select name="status" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                            <option value="active" <?= ($user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">New Password (leave blank to keep current)</label>
                        <input type="password" name="new_password" placeholder="Enter new password to change" 
                            style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn" style="padding: 14px 28px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 15px; cursor: pointer;">
                        Save Changes
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