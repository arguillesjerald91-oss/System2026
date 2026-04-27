<?php
/**
 * Admin Interface for Managing NC Level Subjects
 * Allows staff to assign learning modules to specific NC levels
 */

session_start();
include '../db.php';

// Check if user is logged in and is admin/staff
if (!isset($_SESSION['userId']) || !in_array($_SESSION['userRole'], ['admin', 'staff'])) {
    header('Location: ../login.php');
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$userId = $_SESSION['userId'];
$userRole = $_SESSION['userRole'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_mapping') {
                $ncLevel = $_POST['nc_level'];
                $moduleId = $_POST['module_id'];
                $isRequired = isset($_POST['is_required']) ? 1 : 0;
                $sortOrder = $_POST['sort_order'] ?? 0;
                
                $stmt = $conn->prepare("INSERT INTO nc_level_subjects (nc_level, module_id, is_required, sort_order, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$ncLevel, $moduleId, $isRequired, $sortOrder, $userId]);
                
                $_SESSION['success'] = "Subject added to $ncLevel successfully!";
                
            } elseif ($_POST['action'] === 'remove_mapping') {
                $mappingId = $_POST['mapping_id'];
                
                $stmt = $conn->prepare("DELETE FROM nc_level_subjects WHERE mapping_id = ?");
                $stmt->execute([$mappingId]);
                
                $_SESSION['success'] = "Subject removed from NC level!";
                
            } elseif ($_POST['action'] === 'update_order') {
                $mappingId = $_POST['mapping_id'];
                $sortOrder = $_POST['sort_order'];
                
                $stmt = $conn->prepare("UPDATE nc_level_subjects SET sort_order = ? WHERE mapping_id = ?");
                $stmt->execute([$sortOrder, $mappingId]);
                
                $_SESSION['success'] = "Subject order updated!";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header('Location: manage_nc_subjects.php');
        exit;
    }
}

// Get data for display
$ncLevels = ['NC I', 'NC II', 'NC III', 'NC IV'];

// Get all available learning modules
$allModules = $conn->query("
    SELECT module_id, module_title, module_type, nc_level, is_active 
    FROM learning_modules 
    ORDER BY module_title ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get current mappings grouped by NC level
$mappingsByLevel = [];
foreach ($ncLevels as $ncLevel) {
    $stmt = $conn->prepare("
        SELECT nls.*, lm.module_title, lm.module_type, lm.duration_mins
        FROM nc_level_subjects nls
        JOIN learning_modules lm ON nls.module_id = lm.module_id
        WHERE nls.nc_level = ?
        ORDER BY nls.sort_order ASC, lm.module_title ASC
    ");
    $stmt->execute([$ncLevel]);
    $mappingsByLevel[$ncLevel] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics
$stats = [];
foreach ($ncLevels as $ncLevel) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(is_required) as required FROM nc_level_subjects WHERE nc_level = ?");
    $stmt->execute([$ncLevel]);
    $stats[$ncLevel] = $stmt->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = "Manage NC Level Subjects";
include 'header.php';
?>

<style>
.nc-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.nc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.nc-level-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    overflow: hidden;
}

.nc-level-header {
    padding: 20px;
    color: white;
    font-weight: bold;
    font-size: 18px;
}

.nc-i { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.nc-ii { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.nc-iii { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.nc-iv { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

.nc-level-content {
    padding: 20px;
}

.subject-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    border-left: 4px solid #2563eb;
}

.subject-item.required {
    border-left-color: #dc2626;
}

.subject-title {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 5px;
}

.subject-meta {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 10px;
}

.subject-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-danger { background: #dc2626; color: white; }
.btn-primary { background: #2563eb; color: white; }
.btn-success { background: #10b981; color: white; }

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #2563eb;
}

.stat-label {
    color: #6b7280;
    font-size: 14px;
    margin-top: 5px;
}

.add-subject-form {
    background: #f0f9ff;
    border: 2px dashed #2563eb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #374151;
}

.form-group select, .form-group input {
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #dc2626;
}

.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-required {
    background: #dc2626;
    color: white;
}

.badge-elective {
    background: #6b7280;
    color: white;
}
</style>

<div class="nc-container">
    <h1 style="margin-bottom: 10px;">Manage NC Level Subjects</h1>
    <p style="color: #6b7280; margin-bottom: 30px;">Assign learning modules to specific NC levels and manage their order</p>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-row">
        <?php foreach ($ncLevels as $ncLevel): ?>
            <div class="stat-card">
                <div class="stat-number"><?= $stats[$ncLevel]['total'] ?? 0 ?></div>
                <div class="stat-label"><?= $ncLevel ?> Subjects</div>
                <div style="font-size: 12px; color: #dc2626; margin-top: 5px;">
                    <?= $stats[$ncLevel]['required'] ?? 0 ?> Required
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Add Subject Form -->
    <div class="add-subject-form">
        <h3 style="margin-top: 0;">Add Subject to NC Level</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_mapping">
            <div class="form-row">
                <div class="form-group">
                    <label for="nc_level">NC Level</label>
                    <select name="nc_level" id="nc_level" required>
                        <option value="">Select NC Level</option>
                        <?php foreach ($ncLevels as $ncLevel): ?>
                            <option value="<?= $ncLevel ?>"><?= $ncLevel ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="module_id">Learning Module</label>
                    <select name="module_id" id="module_id" required>
                        <option value="">Select Module</option>
                        <?php foreach ($allModules as $module): ?>
                            <option value="<?= $module['module_id'] ?>">
                                <?= htmlspecialchars($module['module_title']) ?> 
                                (<?= $module['module_type'] ?> - <?= $module['duration_mins'] ?> mins)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sort_order">Display Order</label>
                    <input type="number" name="sort_order" id="sort_order" value="1" min="1">
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <label style="display: flex; align-items: center; gap: 5px;">
                    <input type="checkbox" name="is_required" value="1">
                    <span>Mark as Required Subject</span>
                </label>
                <button type="submit" class="btn-sm btn-primary">Add Subject</button>
            </div>
        </form>
    </div>

    <!-- NC Level Grid -->
    <div class="nc-grid">
        <?php foreach ($ncLevels as $ncLevel): ?>
            <div class="nc-level-card">
                <div class="nc-level-header nc-<?= strtolower(str_replace(' ', '', $ncLevel)) ?>">
                    <?= $ncLevel ?>
                    <div style="font-size: 14px; opacity: 0.9; margin-top: 5px;">
                        <?= count($mappingsByLevel[$ncLevel]) ?> subjects assigned
                    </div>
                </div>
                <div class="nc-level-content">
                    <?php if (empty($mappingsByLevel[$ncLevel])): ?>
                        <p style="text-align: center; color: #6b7280; padding: 20px;">
                            No subjects assigned to <?= $ncLevel ?> yet.
                        </p>
                    <?php else: ?>
                        <?php foreach ($mappingsByLevel[$ncLevel] as $mapping): ?>
                            <div class="subject-item <?= $mapping['is_required'] ? 'required' : '' ?>">
                                <div class="subject-title">
                                    <?= htmlspecialchars($mapping['module_title']) ?>
                                    <?php if ($mapping['is_required']): ?>
                                        <span class="badge badge-required">Required</span>
                                    <?php else: ?>
                                        <span class="badge badge-elective">Elective</span>
                                    <?php endif; ?>
                                </div>
                                <div class="subject-meta">
                                    <?= htmlspecialchars($mapping['module_type']) ?> &bull; 
                                    <?= $mapping['duration_mins'] ?> mins &bull; 
                                    Order: <?= $mapping['sort_order'] ?>
                                </div>
                                <div class="subject-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_order">
                                        <input type="hidden" name="mapping_id" value="<?= $mapping['mapping_id'] ?>">
                                        <input type="number" name="sort_order" value="<?= $mapping['sort_order'] ?>" 
                                               style="width: 60px; padding: 4px;" min="1">
                                        <button type="submit" class="btn-sm btn-success">Update</button>
                                    </form>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Remove this subject from <?= $ncLevel ?>?')">
                                        <input type="hidden" name="action" value="remove_mapping">
                                        <input type="hidden" name="mapping_id" value="<?= $mapping['mapping_id'] ?>">
                                        <button type="submit" class="btn-sm btn-danger">Remove</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
