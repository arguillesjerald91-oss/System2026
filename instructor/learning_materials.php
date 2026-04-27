<?php 
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if ($conn === null) {
    die("Database connection unavailable. Please try again later.");
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['userId'])) {
    header("Location: ../login.php");
    exit();
}
$userId = $_SESSION['user_id'] ?? $_SESSION['userId'] ?? null;
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
$userType = ($userType === 'instructor') ? 'trainer' : $userType;

if ($userType !== 'trainer' && $userType !== 'admin' && $userType !== 'student') {
    header("Location: ../login.php");
    exit();
}

$message = '';
$messageType = '';

// Check enrollment status for students/trainees
if ($userType === 'student') {
    $enrollStmt = $conn->prepare("SELECT 1 FROM student_program_enrollments WHERE student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1) AND enrollment_status = 'Active' LIMIT 1");
    $enrollStmt->execute([$userId]);
    $isEnrolled = (bool)$enrollStmt->fetchColumn();
    
    if (!$isEnrolled) {
        header("Location: ../student/my_application.php?error=not_enrolled");
        exit();
    }
}

// Create learning_materials table if not exists
$conn->exec("CREATE TABLE IF NOT EXISTS learning_materials (
    material_id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    material_type ENUM('Video','Document','Presentation','Image','Archive') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
)");

// Create uploads directory
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle file uploads (trainer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_material' && ($userType === 'trainer' || $userType === 'instructor' || $userType === 'admin')) {
        $moduleId = intval($_POST['module_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $materialType = $_POST['material_type'] ?? 'Document';
        $ncLevel = $_POST['nc_level'] ?? 'NC I';
        
        if ($title && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $fileName = basename($_FILES['file']['name']);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileName);
            $targetPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
                // Create table if not exists
                $conn->exec("CREATE TABLE IF NOT EXISTS learning_materials (
                    material_id INT AUTO_INCREMENT PRIMARY KEY,
                    module_id INT DEFAULT 0,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    material_type ENUM('Video','Document','Presentation','Image','Archive') NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    file_path VARCHAR(255) NOT NULL,
                    file_size INT,
                    uploaded_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
                )");
                
                $stmt = $conn->prepare("INSERT INTO learning_materials (module_id, title, description, material_type, file_name, file_path, file_size, uploaded_by, nc_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$moduleId, $title, $description, $materialType, $fileName, 'uploads/' . $newFileName, $_FILES['file']['size'], $userId, $ncLevel]);
                $message = 'Material uploaded successfully';
                $messageType = 'success';
            }
        }
    }
    
    if ($_POST['action'] === 'delete_material' && ($userType === 'trainer' || $userType === 'instructor' || $userType === 'admin')) {
        $materialId = intval($_POST['material_id'] ?? 0);
        if ($materialId > 0) {
            $stmt = $conn->prepare("SELECT file_path FROM learning_materials WHERE material_id = ?");
            $stmt->execute([$materialId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($file && file_exists(__DIR__ . '/' . $file['file_path'])) {
                unlink(__DIR__ . '/' . $file['file_path']);
            }
            $stmt = $conn->prepare("DELETE FROM learning_materials WHERE material_id = ?");
            $stmt->execute([$materialId]);
            $message = 'Material deleted';
            $messageType = 'success';
        }
    }
}

// Get modules
$stmt = $conn->query("SELECT module_id, module_title, module_type FROM learning_modules WHERE is_active = 1 ORDER BY sort_order");
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get materials
if ($userType === 'trainee' || $userType === 'student') {
    // Get trainee's NC level
    $ncLevelStmt = $conn->prepare("
        SELECT nc_level 
        FROM student_program_enrollments 
        WHERE student_id = ? AND enrollment_status = 'Active'
        ORDER BY enrollment_id DESC LIMIT 1
    ");
    $ncLevelStmt->execute([$userId]);
    $studentNcLevel = $ncLevelStmt->fetchColumn() ?: 'NC I';
    
    // Get modules this trainee is enrolled in
    $enrolledModulesStmt = $conn->prepare("
        SELECT DISTINCT module_id 
        FROM student_module_progress 
        WHERE enrollment_id IN (
            SELECT enrollment_id 
            FROM student_program_enrollments 
            WHERE student_id = ? AND enrollment_status = 'Active'
        )
    ");
    $enrolledModulesStmt->execute([$userId]);
    $enrolledModuleIds = $enrolledModulesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($enrolledModuleIds)) {
        $placeholders = implode(',', array_fill(0, count($enrolledModuleIds), '?'));
        $query = "SELECT lm.*, u.first_name, u.last_name 
                  FROM learning_materials lm 
                  LEFT JOIN users u ON lm.uploaded_by = u.user_id 
                  WHERE (lm.module_id IN ($placeholders) OR lm.nc_level = ?)
                  ORDER BY lm.created_at DESC";
        $params = array_merge($enrolledModuleIds, [$studentNcLevel]);
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // If no enrolled modules, still show NC level-specific materials
        $query = "SELECT lm.*, u.first_name, u.last_name 
                  FROM learning_materials lm 
                  LEFT JOIN users u ON lm.uploaded_by = u.user_id 
                  WHERE lm.nc_level = ?
                  ORDER BY lm.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$studentNcLevel]);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Trainers/admins see all materials
    $query = "SELECT lm.*, u.first_name, u.last_name 
              FROM learning_materials lm 
              LEFT JOIN users u ON lm.uploaded_by = u.user_id 
              ORDER BY lm.created_at DESC";
    $stmt = $conn->query($query);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Stats
$stmt = $conn->query("SELECT 
    (SELECT COUNT(*) FROM learning_materials) as total_materials,
    (SELECT SUM(file_size) FROM learning_materials) as total_size");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get trainer info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$trainer = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($trainer['first_name'] ?? '') . ' ' . ($trainer['last_name'] ?? ''));
$currentPage = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Learning Materials - TESDA Auto Mechanic</title>
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
.btn-success { background: var(--success); color: white; }
.btn-danger { background: var(--danger); color: white; }
.btn-outline { background: white; border: 1px solid var(--border); color: var(--foreground); }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.container { padding: 30px 40px; }
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: var(--card); padding: 20px; border-radius: 12px; border: 1px solid var(--border); }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 12px; color: var(--muted); margin-top: 4px; }
.card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); margin-bottom: 24px; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.card-title { font-size: 16px; font-weight: 600; }
.card-body { padding: 20px 24px; }
.alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
.alert-success { background: #d1fae5; color: #059669; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-blue { background: #dbeafe; color: #2563eb; }
.badge-purple { background: #ede9fe; color: #7c3aed; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); }
.table th { font-size: 12px; color: var(--muted); text-transform: uppercase; font-weight: 600; background: #f8fafc; }
.table tr:hover { background: #f8fafc; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; font-family: inherit; }
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: white; border-radius: 16px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.modal-title { font-size: 18px; font-weight: 600; }
.modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--muted); }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }
.material-item { display: flex; align-items: center; padding: 16px; border-radius: 12px; margin-bottom: 12px; background: #f8fafc; }
.material-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 16px; font-size: 24px; }
.material-info { flex: 1; }
.material-title { font-weight: 600; margin-bottom: 4px; }
.material-meta { font-size: 12px; color: var(--muted); }
.empty-state { text-align: center; padding: 40px; color: var(--muted); }
.filter-tabs { display: flex; gap: 12px; margin-bottom: 20px; }
.filter-tabs a { padding: 8px 16px; border-radius: 20px; text-decoration: none; font-size: 14px; }
.filter-tabs a.active { background: var(--primary); color: white; }
.file-link { display: flex; align-items: center; gap: 8px; padding: 10px; border-radius: 8px; background: var(--primary); color: white; text-decoration: none; font-size: 14px; }
.file-link:hover { background: var(--primary-dark); }
@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><span>🔧</span> TESDA</div>
        <p class="sidebar-subtitle">Trainer Portal</p>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <p class="nav-section-title">Menu</p>
            <?php if ($userType === 'student'): ?>
            <a href="../student/student_dashboard.php" class="nav-item"><span>🏠</span> Dashboard</a>
            <a href="learning_materials.php" class="nav-item active"><span>📚</span> Materials</a>
            <a href="quizzes.php" class="nav-item"><span>❓</span> Quizzes</a>
            <a href="assignments.php" class="nav-item"><span>📝</span> Assignments</a>
             <?php else: ?>
             <a href="instructor_dashboard.php" class="nav-item <?= $currentPage == 'instructor_dashboard.php' ? 'active' : '' ?>"><span>🏠</span> Dashboard</a>
             <a href="my_modules.php" class="nav-item <?= $currentPage == 'my_modules.php' ? 'active' : '' ?>"><span>📚</span> My Modules</a>
             <a href="learning_materials.php" class="nav-item <?= $currentPage == 'learning_materials.php' ? 'active' : '' ?>"><span>📂</span> Materials</a>
             <a href="quizzes.php" class="nav-item <?= $currentPage == 'quizzes.php' ? 'active' : '' ?>"><span>❓</span> Quizzes</a>
             <a href="assignments.php" class="nav-item <?= $currentPage == 'assignments.php' ? 'active' : '' ?>"><span>📝</span> Assignments</a>
             <a href="my_students.php" class="nav-item <?= $currentPage == 'my_students.php' ? 'active' : '' ?>"><span>👥</span> My Students</a>
             <a href="assessments.php" class="nav-item <?= $currentPage == 'assessments.php' ? 'active' : '' ?>"><span>📋</span> Assessments</a>
             <a href="reports.php" class="nav-item <?= $currentPage == 'reports.php' ? 'active' : '' ?>"><span>📊</span> Reports</a>
             <?php endif; ?>
        </div>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">👤</div>
            <div class="user-info">
                <h4><?= htmlspecialchars($fullName) ?></h4>
                <p><?= $userType === 'student' ? 'Student' : 'Trainer' ?></p>
            </div>
        </div>
    </div>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div><h1 class="page-title">Learning Materials</h1><p class="page-subtitle">Training materials and resources</p></div>
        <div style="display: flex; gap: 12px;">
            <?php if ($userType === 'trainer' || $userType === 'instructor' || $userType === 'admin'): ?>
            <button class="btn btn-primary" onclick="openModal('uploadModal')">+ Upload Material</button>
            <?php endif; ?>
            <a href="../logout.php" class="btn btn-outline">Logout</a>
        </div>
    </div>
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($userType === 'student'): ?>
        <div class="filter-tabs">
            <a href="learning_materials.php" class="filter-tabs <?= !$selectedModuleId ? 'active' : '' ?>">All</a>
            <?php foreach ($modules as $mod): ?>
            <a href="?module_id=<?= $mod['module_id'] ?>" class="<?= $selectedModuleId == $mod['module_id'] ? 'active' : '' ?>"><?= htmlspecialchars($mod['module_title']) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_materials'] ?? 0 ?></div>
                <div class="stat-label">Total Materials</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= round(($stats['total_size'] ?? 0) / 1024 / 1024, 2) ?> MB</div>
                <div class="stat-label">Total Storage Used</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($modules) ?></div>
                <div class="stat-label">Available Modules</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Materials</h3>
            </div>
            <div class="card-body">
                <?php if (empty($materials)): ?>
                <div class="empty-state">No materials uploaded yet.</div>
                <?php else: ?>
                <?php foreach ($materials as $mat): ?>
                <div class="material-item">
                    <div class="material-icon" style="background: <?= $mat['material_type'] === 'Video' ? '#ede9fe' : ($mat['material_type'] === 'Presentation' ? '#fed7aa' : '#dbeafe') ?>; color: <?= $mat['material_type'] === 'Video' ? '#7c3aed' : ($mat['material_type'] === 'Presentation' ? '#d97706' : '#2563eb') ?>;">
                        <?= $mat['material_type'] === 'Video' ? '🎬' : ($mat['material_type'] === 'Presentation' ? '📊' : ($mat['material_type'] === 'Image' ? '🖼️' : '📄')) ?>
                    </div>
                    <div class="material-info">
                        <div class="material-title"><?= htmlspecialchars($mat['title']) ?></div>
                        <div class="material-meta"><?= $mat['material_type'] ?> • <?= round($mat['file_size'] / 1024, 1) ?> KB • <?= htmlspecialchars($mat['first_name'] . ' ' . $mat['last_name']) ?> • <?= date('M d, Y', strtotime($mat['created_at'])) ?> • <span class="badge badge-purple"><?= $mat['nc_level'] ?? 'NC I' ?></span></div>
                    </div>
                    <?php if ($userType === 'trainer' || $userType === 'instructor' || $userType === 'admin'): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="delete_material">
                        <input type="hidden" name="material_id" value="<?= $mat['material_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this material?')">Delete</button>
                    </form>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($mat['file_path']) ?>" class="file-link" download>Download</a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Upload Modal -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Upload Learning Material</h3>
            <button class="modal-close" onclick="closeModal('uploadModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="upload_material">
                <div class="form-group">
                    <label>Module (Optional)</label>
                    <select name="module_id">
                        <option value="0">General / All Modules</option>
                        <?php foreach ($modules as $mod): ?>
                        <option value="<?= $mod['module_id'] ?>"><?= htmlspecialchars($mod['module_title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" required placeholder="Material title">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2" placeholder="Brief description"></textarea>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="material_type">
                        <option value="Document">Document (PDF, Word)</option>
                        <option value="Video">Video</option>
                        <option value="Presentation">Presentation</option>
                        <option value="Image">Image</option>
                        <option value="Archive">Archive (Zip)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>NC Level *</label>
                    <select name="nc_level" required>
                        <option value="NC I">NC I - Automotive Servicing</option>
                        <option value="NC II">NC II - Automotive Servicing</option>
                        <option value="NC III">NC III - Automotive Servicing</option>
                        <option value="NC IV">NC IV - Automotive Servicing</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>File *</label>
                    <input type="file" name="file" required accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.jpg,.jpeg,.png,.mp4,.avi,.mov">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('uploadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>
</body>
</html>