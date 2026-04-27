<?php 
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['userId'])) {
    header("Location: ../login.php");
    exit();
}
$userId = $_SESSION['user_id'] ?? $_SESSION['userId'] ?? null;
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
$userType = ($userType === 'instructor') ? 'trainer' : $userType;

if ($userType !== 'trainer' && $userType !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_trainer' && $userType === 'admin') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            
            if ($firstName && $lastName && $email && $username) {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $message = 'Username or email already exists';
                    $messageType = 'error';
                } else {
                    $password = password_hash('trainer123', PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, user_type, first_name, last_name, phone, address, status, created_at) VALUES (?, ?, ?, 'trainer', ?, ?, ?, ?, 'active', NOW())");
                    $stmt->execute([$username, $password, $email, $firstName, $lastName, $phone, $address]);
                    $message = 'Trainer added successfully';
                    $messageType = 'success';
                }
            }
        }
        
        if ($_POST['action'] === 'update_status' && $userType === 'admin') {
            $trainerId = intval($_POST['trainer_id'] ?? 0);
            $newStatus = $_POST['status'] ?? 'active';
            if ($trainerId > 0) {
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ? AND user_type IN ('trainer', 'instructor')");
                $stmt->execute([$newStatus, $trainerId]);
                $message = 'Trainer status updated';
                $messageType = 'success';
            }
        }

        if ($_POST['action'] === 'assign_module' && $userType === 'admin') {
            $trainerId = intval($_POST['trainer_id'] ?? 0);
            $moduleId = intval($_POST['module_id'] ?? 0);
            if ($trainerId > 0 && $moduleId > 0) {
                $stmt = $conn->prepare("INSERT IGNORE INTO module_access_permissions (user_id, module_id, user_type, access_type, access_status, granted_date) VALUES (?, ?, 'trainer', 'View', 'Active', NOW())");
                $stmt->execute([$trainerId, $moduleId]);
                $message = 'Module assigned to trainer';
                $messageType = 'success';
            }
        }

        if ($_POST['action'] === 'remove_module' && $userType === 'admin') {
            $trainerId = intval($_POST['trainer_id'] ?? 0);
            $moduleId = intval($_POST['module_id'] ?? 0);
            if ($trainerId > 0 && $moduleId > 0) {
                $stmt = $conn->prepare("DELETE FROM module_access_permissions WHERE user_id = ? AND module_id = ?");
                $stmt->execute([$trainerId, $moduleId]);
                $message = 'Module removed from trainer';
                $messageType = 'success';
            }
        }
    }
}

// Get all trainers
$stmt = $conn->query("SELECT user_id, username, email, first_name, last_name, phone, address, status, created_at FROM users WHERE user_type IN ('trainer', 'instructor') ORDER BY last_name, first_name");
$trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all learning modules
$stmt = $conn->query("SELECT module_id, module_title, module_type, duration_mins, is_active FROM learning_modules ORDER BY sort_order");
$allModules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get trainer module assignments
$trainerModules = [];
$stmt = $conn->query("SELECT user_id, module_id FROM module_access_permissions WHERE user_type IN ('trainer', 'instructor') AND access_status = 'Active'");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $trainerModules[$row['user_id']][] = $row['module_id'];
}

// Get module details for each trainer
$trainerModuleDetails = [];
foreach ($trainers as $t) {
    $trainerModuleDetails[$t['user_id']] = [];
    if (isset($trainerModules[$t['user_id']])) {
        $moduleIds = implode(',', $trainerModules[$t['user_id']]);
        $stmt = $conn->query("SELECT module_id, module_title, module_type FROM learning_modules WHERE module_id IN ($moduleIds)");
        $trainerModuleDetails[$t['user_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get current trainer info
$currentUser = null;
if ($userType === 'trainer') {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
}
$fullName = $currentUser ? trim($currentUser['first_name'] . ' ' . $currentUser['last_name']) : 'Trainer';

// Stats
$stmt = $conn->query("SELECT COUNT(*) FROM users WHERE user_type IN ('trainer', 'instructor')");
$trainerCount = $stmt->fetchColumn();
$stmt = $conn->query("SELECT COUNT(*) FROM learning_modules WHERE is_active = 1");
$activeModulesCount = $stmt->fetchColumn();
$stmt = $conn->query("SELECT COUNT(*) FROM users WHERE user_type = 'student'");
$studentCount = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Trainers Management - TESDA Auto Mechanic</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { --primary: #2563eb; --primary-dark: #1e40af; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --background: #f1f5f9; --foreground: #1e293b; --card: #ffffff; --muted: #64748b; --border: #e2e8f0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', -apple-system, sans-serif; background: var(--background); min-height: 100vh; }
.sidebar { position: fixed; left: 0; width: 260px; height: 100vh; background: linear-gradient(180deg, var(--primary-dark), #1e3a8a); color: white; display: flex; flex-direction: column; z-index: 100; }
.sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.sidebar-logo { display: flex; align-items: center; gap: 12px; font-size: 18px; font-weight: 700; }
.sidebar-logo span { font-size: 28px; }
.sidebar-subtitle { font-size: 11px; opacity: 0.7; margin-top: 4px; }
.sidebar-nav { flex: 1; padding: 20px 0; overflow-y: auto; }
.nav-section { padding: 0 12px; margin-bottom: 20px; }
.nav-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.6; padding: 0 12px; margin-bottom: 8px; }
.nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px; color: white; text-decoration: none; margin: 2px 8px; font-size: 14px; transition: all 0.2s; }
.nav-item:hover { background: rgba(255,255,255,0.15); }
.nav-item.active { background: rgba(255,255,255,0.2); }
.sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
.user-profile { display: flex; align-items: center; gap: 12px; }
.user-avatar { width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.user-info h4 { font-size: 14px; font-weight: 600; }
.user-info p { font-size: 12px; opacity: 0.7; }
.main-content { margin-left: 260px; }
.top-bar { background: white; padding: 16px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
.page-title { font-size: 24px; font-weight: 600; }
.page-subtitle { font-size: 14px; color: var(--muted); }
.btn { padding: 10px 20px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: var(--primary-dark); }
.btn-success { background: var(--success); color: white; }
.btn-outline { background: white; border: 1px solid var(--border); color: var(--foreground); }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.container { padding: 30px 40px; }
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
.stat-card { background: var(--card); padding: 24px; border-radius: 16px; border: 1px solid var(--border); }
.stat-label { font-size: 13px; color: var(--muted); }
.stat-value { font-size: 28px; font-weight: 700; }
.card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); margin-bottom: 24px; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.card-title { font-size: 16px; font-weight: 600; }
.card-body { padding: 20px 24px; }
.alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
.alert-success { background: #d1fae5; color: #059669; }
.alert-error { background: #fee2e2; color: #dc2626; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-warning { background: #fed7aa; color: #d97706; }
.badge-danger { background: #fee2e2; color: #dc2626; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid var(--border); }
.table th { font-size: 12px; color: var(--muted); text-transform: uppercase; font-weight: 600; background: #f8fafc; }
.table tr:hover { background: #f8fafc; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; color: var(--foreground); }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; font-family: inherit; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: white; border-radius: 16px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.modal-title { font-size: 18px; font-weight: 600; }
.modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--muted); }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }
.module-tag { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #f1f5f9; border-radius: 6px; font-size: 12px; margin: 2px; }
.module-tag button { background: none; border: none; cursor: pointer; color: var(--muted); padding: 0; margin-left: 4px; }
.module-tag button:hover { color: var(--danger); }
.empty-state { text-align: center; padding: 40px; color: var(--muted); }
.actions { display: flex; gap: 8px; }
@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) { .sidebar { width: 60px; } .main-content { margin-left: 60px; } .stats-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><span>🔧</span> TESDA</div>
        <p class="sidebar-subtitle">Trainer Management</p>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <p class="nav-section-title">Menu</p>
            <a href="instructor_dashboard.php" class="nav-item"><span>🏠</span> Dashboard</a>
            <a href="trainers.php" class="nav-item active"><span>👥</span> Trainers</a>
            <?php if ($userType === 'admin'): ?>
            <a href="../admin/admin_dashboard.php" class="nav-item"><span>📊</span> Admin</a>
            <?php endif; ?>
        </div>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">👤</div>
            <div class="user-info">
                <h4><?= htmlspecialchars($fullName) ?></h4>
                <p><?= $userType === 'admin' ? 'Administrator' : 'Trainer' ?></p>
            </div>
        </div>
    </div>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div><h1 class="page-title">Trainers Management</h1><p class="page-subtitle">Manage all trainers and their module assignments</p></div>
        <div style="display: flex; gap: 12px;">
            <?php if ($userType === 'admin'): ?>
            <button class="btn btn-primary" onclick="openModal('addTrainerModal')">+ Add Trainer</button>
            <?php endif; ?>
            <a href="../logout.php" class="btn btn-outline">Logout</a>
        </div>
    </div>
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">Total Trainers</div><div class="stat-value"><?= $trainerCount ?></div></div>
            <div class="stat-card"><div class="stat-label">Active Modules</div><div class="stat-value"><?= $activeModulesCount ?></div></div>
            <div class="stat-card"><div class="stat-label">Enrolled Students</div><div class="stat-value"><?= $studentCount ?></div></div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Trainers</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($trainers)): ?>
                <div class="empty-state">No trainers found. Click "Add Trainer" to create one.</div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Trainer</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Assigned Modules</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <?php if ($userType === 'admin'): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trainers as $trainer): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;"><?= htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']) ?></div>
                                <div style="font-size: 12px; color: var(--muted);">@<?= htmlspecialchars($trainer['username']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($trainer['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($trainer['phone'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($trainerModuleDetails[$trainer['user_id']])): ?>
                                <div style="max-width: 200px; flex-wrap: wrap; display: flex;">
                                    <?php foreach ($trainerModuleDetails[$trainer['user_id']] as $mod): ?>
                                    <span class="module-tag">
                                        <?= htmlspecialchars(substr($mod['module_title'], 0, 20)) . (strlen($mod['module_title']) > 20 ? '...' : '') ?>
                                        <?php if ($userType === 'admin'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="remove_module">
                                            <input type="hidden" name="trainer_id" value="<?= $trainer['user_id'] ?>">
                                            <input type="hidden" name="module_id" value="<?= $mod['module_id'] ?>">
                                            <button type="submit" title="Remove module">×</button>
                                        </form>
                                        <?php endif; ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <span style="color: var(--muted); font-size: 12px;">No modules</span>
                                <?php endif; ?>
                                <?php if ($userType === 'admin'): ?>
                                <button class="btn btn-sm btn-outline" style="margin-top: 4px;" onclick="openAssignModal(<?= $trainer['user_id'] ?>, '<?= htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']) ?>')">+ Assign</button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $trainer['status'] === 'active' ? 'success' : ($trainer['status'] === 'inactive' ? 'warning' : 'danger') ?>">
                                    <?= htmlspecialchars(ucfirst($trainer['status'])) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($trainer['created_at'])) ?></td>
                            <?php if ($userType === 'admin'): ?>
                            <td>
                                <div class="actions">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="trainer_id" value="<?= $trainer['user_id'] ?>">
                                        <input type="hidden" name="status" value="<?= $trainer['status'] === 'active' ? 'inactive' : 'active' ?>">
                                        <button type="submit" class="btn btn-sm <?= $trainer['status'] === 'active' ? 'btn-outline' : 'btn-success' ?>">
                                            <?= $trainer['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Add Trainer Modal -->
<div id="addTrainerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Trainer</h3>
            <button class="modal-close" onclick="closeModal('addTrainerModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_trainer">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required placeholder="Enter first name">
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required placeholder="Enter last name">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required placeholder="trainer@tesda.gov.ph">
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" required placeholder="Unique username">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" placeholder="Contact number">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="2" placeholder="Full address"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addTrainerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Trainer</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Module Modal -->
<div id="assignModuleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Assign Module to <span id="assignTrainerName"></span></h3>
            <button class="modal-close" onclick="closeModal('assignModuleModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="assign_module">
                <input type="hidden" name="trainer_id" id="assignTrainerId">
                <div class="form-group">
                    <label>Select Module</label>
                    <select name="module_id" required>
                        <option value="">Choose a module...</option>
                        <?php foreach ($allModules as $mod): ?>
                        <option value="<?= $mod['module_id'] ?>"><?= htmlspecialchars($mod['module_title']) ?> (<?= $mod['module_type'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('assignModuleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Module</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function openAssignModal(trainerId, trainerName) {
    document.getElementById('assignTrainerId').value = trainerId;
    document.getElementById('assignTrainerName').textContent = trainerName;
    openModal('assignModuleModal');
}
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>
</body>
</html>