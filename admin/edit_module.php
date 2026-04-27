<?php
/**
 * Edit Module
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

$moduleId = $_GET['id'] ?? 0;

if (!$moduleId) {
    header("Location: lms_modules.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM learning_modules WHERE module_id = ?");
$stmt->execute([$moduleId]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    die("Module not found");
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
                UPDATE learning_modules 
                SET module_title = ?, module_description = ?, module_type = ?, nc_level = ?, 
                    duration_mins = ?, is_active = ?, sort_order = ?
                WHERE module_id = ?
            ");
            $stmt->execute([$module_title, $module_description, $module_type, $nc_level, $duration_mins, $is_active, $sort_order, $moduleId]);
            
            $success = true;
            header("Location: view_module.php?id=" . $moduleId . "&saved=1");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

$pageTitle = "Edit Module";
$pageSubtitle = "Update Learning Module";
$currentPage = "lms_modules.php";

include 'sidebar_new.php';
?>

<div style="max-width: 800px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Edit Module: <?= htmlspecialchars($module['module_title']) ?></h3>
            <div style="display: flex; gap: 12px;">
                <a href="view_module.php?id=<?= $moduleId ?>" class="btn" style="padding: 8px 16px; background: #f1f5f9; color: #374151; border-radius: 6px; text-decoration: none;">
                    View Details
                </a>
                <a href="lms_modules.php" class="btn" style="padding: 8px 16px; background: #f1f5f9; color: #374151; border-radius: 6px; text-decoration: none;">
                    Back to Modules
                </a>
            </div>
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
                        <input type="text" name="module_title" required value="<?= htmlspecialchars($module['module_title']) ?>" 
                            style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Description</label>
                        <textarea name="module_description" rows="4" 
                            style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px; resize: vertical;"><?= htmlspecialchars($module['module_description'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">NC Level</label>
                            <select name="nc_level" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                                <?php foreach ($nc_levels as $level): ?>
                                <option value="<?= $level ?>" <?= ($module['nc_level'] ?? 'NC I') === $level ? 'selected' : '' ?>><?= $level ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Module Type</label>
                            <select name="module_type" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                                <?php foreach ($module_types as $type): ?>
                                <option value="<?= $type ?>" <?= ($module['module_type'] ?? 'Theory') === $type ? 'selected' : '' ?>><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Duration (minutes)</label>
                            <input type="number" name="duration_mins" value="<?= $module['duration_mins'] ?>" min="1"
                                style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                        </div>
                        
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">Sort Order</label>
                            <input type="number" name="sort_order" value="<?= $module['sort_order'] ?? 1 ?>" min="1"
                                style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 15px;">
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px;">
                        <input type="checkbox" name="is_active" id="is_active" value="1" <?= ($module['is_active'] ?? 1) ? 'checked' : '' ?>
                        <label for="is_active" style="font-weight: 600; color: #374151;">Active</label>
                        <span style="color: #64748b; font-size: 13px;">(Module will be visible to students)</span>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn" style="padding: 14px 28px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 15px; cursor: pointer;">
                        Save Changes
                    </button>
                    <a href="view_module.php?id=<?= $moduleId ?>" class="btn" style="padding: 14px 28px; background: #f1f5f9; color: #374151; border-radius: 8px; text-decoration: none;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Danger Zone -->
    <div class="card" style="margin-top: 30px; border: 1px solid #fee2e2;">
        <div class="card-header" style="background: #fef2f2;">
            <h3 class="card-title" style="color: #dc2626;">Danger Zone</h3>
        </div>
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-weight: 600; margin-bottom: 4px;">Delete Module</div>
                    <div style="font-size: 13px; color: #64748b;">This action cannot be undone. All associated progress will be lost.</div>
                </div>
                <a href="delete_module.php?id=<?= $moduleId ?>" class="btn" onclick="return confirm('Are you sure you want to delete this module?');" 
                   style="padding: 10px 20px; background: #dc2626; color: white; border-radius: 8px; text-decoration: none;">
                    Delete Module
                </a>
            </div>
        </div>
    </div>
</div>

</main>
</div>

</body>
</html>