<?php
/**
 * Add New Module
 */
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if (!in_array($userType, ['admin', 'support_staff', 'instructional_unit'])) {
    header("Location: ../login.php");
    exit();
}

$errors = [];
$success = false;

$nc_levels = ['NC I', 'NC II', 'NC III', 'NC IV'];
$module_types = ['Theory', 'Practical', 'Assessment', 'Demo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $module_title = trim($_POST['module_title'] ?? '');
    $module_description = trim($_POST['module_description'] ?? '');
    $module_type = $_POST['module_type'] ?? 'Theory';
    $nc_level = $_POST['nc_level'] ?? 'NC I';
    $duration_mins = (int)($_POST['duration_mins'] ?? 60);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = (int)($_POST['sort_order'] ?? 1);
    
    if (empty($module_title)) {
        $errors[] = "Module title is required";
    }
    if ($duration_mins < 1) {
        $errors[] = "Duration must be at least 1 minute";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO learning_modules 
                (module_title, module_description, module_type, nc_level, duration_mins, is_active, sort_order, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$module_title, $module_description, $module_type, $nc_level, $duration_mins, $is_active, $sort_order]);
            
            $success = true;
            header("Location: lms_modules.php?success=1");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

$pageTitle = "Add Module";
$pageSubtitle = "Create New Learning Module";
$currentPage = "lms_modules.php";

include 'sidebar_new.php';
?>

<div style="max-width: 800px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Create New Module</h3>
            <a href="lms_modules.php" class="btn" style="padding: 8px 16px; background: #f1f5f9; color: #374151; border-radius: 6px; text-decoration: none;">
                Cancel
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
                <div style="display: grid; gap: 20px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Module Title *</label>
                        <input type="text" name="module_title" required value="<?= htmlspecialchars($_POST['module_title'] ?? '') ?>" 
                            style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;"
                            placeholder="e.g., Engine Repair and Diagnosis">
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Description</label>
                        <textarea name="module_description" rows="4" 
                            style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px; resize: vertical;"
                            placeholder="Describe what students will learn in this module..."><?= htmlspecialchars($_POST['module_description'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">NC Level</label>
                            <select name="nc_level" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                                <?php foreach ($nc_levels as $level): ?>
                                <option value="<?= $level ?>" <?= ($_POST['nc_level'] ?? 'NC I') === $level ? 'selected' : '' ?>><?= $level ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Module Type</label>
                            <select name="module_type" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                                <?php foreach ($module_types as $type): ?>
                                <option value="<?= $type ?>" <?= ($_POST['module_type'] ?? 'Theory') === $type ? 'selected' : '' ?>><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Duration (minutes)</label>
                            <input type="number" name="duration_mins" value="<?= $_POST['duration_mins'] ?? 60 ?>" min="1"
                                style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                        </div>
                        
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Sort Order</label>
                            <input type="number" name="sort_order" value="<?= $_POST['sort_order'] ?? 1 ?>" min="1"
                                style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px;">
                        <input type="checkbox" name="is_active" id="is_active" value="1" <?= !isset($_POST['is_active']) || $_POST['is_active'] ? 'checked' : '' ?>
                        <label for="is_active" style="font-weight: 600; color: #374151;">Active</label>
                        <span style="color: #64748b; font-size: 13px;">(Module will be visible to students)</span>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn" style="padding: 14px 28px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 15px; cursor: pointer;">
                        Create Module
                    </button>
                    <a href="lms_modules.php" class="btn" style="padding: 14px 28px; background: #f1f5f9; color: #374151; border-radius: 8px; text-decoration: none;">
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