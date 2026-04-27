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

$message = '';
$messageType = '';

// Create module contents table if not exists
$conn->exec("CREATE TABLE IF NOT EXISTS module_contents (
    content_id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content_type ENUM('Video','PDF','Link','Quiz','Assignment','Activity') NOT NULL,
    content_url VARCHAR(500),
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    duration_mins INT DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_published TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
)");

$uploadDir = __DIR__ . '/module_files/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Update module details
        if ($_POST['action'] === 'update_module' && isset($_POST['module_id'])) {
            $moduleId = intval($_POST['module_id']);
            $title = trim($_POST['module_title'] ?? '');
            $description = trim($_POST['module_description'] ?? '');
            $duration = intval($_POST['duration_mins'] ?? 30);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE learning_modules SET module_title = ?, module_description = ?, duration_mins = ?, is_active = ? WHERE module_id = ?");
            $stmt->execute([$title, $description, $duration, $isActive, $moduleId]);
            $message = 'Module updated successfully';
            $messageType = 'success';
        }
        
        // Add content (Video, PDF, Link, Quiz, Assignment)
        if ($_POST['action'] === 'add_content' && isset($_POST['module_id'])) {
            $moduleId = intval($_POST['module_id']);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $contentType = $_POST['content_type'] ?? 'Video';
            $contentUrl = trim($_POST['content_url'] ?? '');
            $duration = intval($_POST['duration_mins'] ?? 0);
            
            if ($title) {
                $filePath = '';
                $fileName = '';
                
                // Handle file upload
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $fileName = basename($_FILES['file']['name']);
                    $newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
                    if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $newFileName)) {
                        $filePath = 'module_files/' . $newFileName;
                    }
                }
                
                $stmt = $conn->prepare("SELECT MAX(sort_order) FROM module_contents WHERE module_id = ?");
                $stmt->execute([$moduleId]);
                $maxOrder = $stmt->fetchColumn() ?: 0;
                
                $stmt = $conn->prepare("INSERT INTO module_contents (module_id, title, description, content_type, content_url, file_path, file_name, duration_mins, sort_order, is_published, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())");
                $stmt->execute([$moduleId, $title, $description, $contentType, $contentUrl, $filePath, $fileName, $duration, $maxOrder + 1, $userId]);
                $message = 'Content added successfully';
                $messageType = 'success';
            }
        }
        
        // Toggle content publish status
        if ($_POST['action'] === 'toggle_publish' && isset($_POST['content_id'])) {
            $contentId = intval($_POST['content_id']);
            $stmt = $conn->prepare("UPDATE module_contents SET is_published = NOT is_published WHERE content_id = ?");
            $stmt->execute([$contentId]);
            $message = 'Content status updated';
            $messageType = 'success';
        }
        
        // Delete content
        if ($_POST['action'] === 'delete_content' && isset($_POST['content_id'])) {
            $contentId = intval($_POST['content_id']);
            $stmt = $conn->prepare("SELECT file_path FROM module_contents WHERE content_id = ?");
            $stmt->execute([$contentId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($file && $file['file_path'] && file_exists(__DIR__ . '/' . $file['file_path'])) {
                unlink(__DIR__ . '/' . $file['file_path']);
            }
            $stmt = $conn->prepare("DELETE FROM module_contents WHERE content_id = ?");
            $stmt->execute([$contentId]);
            $message = 'Content deleted';
            $messageType = 'success';
        }
    }
}

// Get programs for NC levels
$stmt = $conn->query("SELECT program_id, program_code, program_title, program_level FROM auto_mechanic_programs WHERE program_status = 'Active' ORDER BY program_level, program_title");
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get modules grouped by program
$stmt = $conn->query("SELECT lm.module_id, lm.module_title, lm.module_description, lm.module_type, lm.duration_mins, lm.is_active, lm.sort_order,
    p.program_id, p.program_title, p.program_level
    FROM learning_modules lm 
    LEFT JOIN auto_mechanic_programs p ON lm.unit_id = p.program_id OR p.program_id IS NULL
    WHERE lm.is_active = 1
    ORDER BY p.program_level, lm.sort_order");
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group modules by program level
$modulesByLevel = [];
foreach ($modules as $mod) {
    $level = $mod['program_level'] ?? 'General';
    $modulesByLevel[$level][] = $mod;
}

// Get selected module details with contents
$selectedModuleId = $_GET['module_id'] ?? ($modules[0]['module_id'] ?? 0);
$selectedModule = null;
$moduleContents = [];
$enrolledStudents = [];
$moduleStats = [];

if ($selectedModuleId) {
    $stmt = $conn->prepare("SELECT * FROM learning_modules WHERE module_id = ?");
    $stmt->execute([$selectedModuleId]);
    $selectedModule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedModule) {
        $stmt = $conn->prepare("SELECT * FROM module_contents WHERE module_id = ? ORDER BY sort_order");
        $stmt->execute([$selectedModuleId]);
        $moduleContents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get enrolled students count
        $stmt = $conn->query("SELECT COUNT(DISTINCT spe.student_id) as enrolled
            FROM student_module_progress smp 
            JOIN student_program_enrollments spe ON smp.enrollment_id = spe.enrollment_id
            WHERE smp.module_id = $selectedModuleId");
        $moduleStats = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

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
<title>Training Modules - TESDA Auto Mechanic</title>
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
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: var(--card); padding: 20px; border-radius: 12px; border: 1px solid var(--border); }
.stat-value { font-size: 24px; font-weight: 700; }
.stat-label { font-size: 12px; color: var(--muted); margin-top: 4px; }
.card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); margin-bottom: 24px; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.card-title { font-size: 16px; font-weight: 600; }
.card-body { padding: 20px 24px; }
.alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
.alert-success { background: #d1fae5; color: #059669; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-warning { background: #fed7aa; color: #d97706; }
.badge-blue { background: #dbeafe; color: #2563eb; }
.badge-purple { background: #ede9fe; color: #7c3aed; }
.badge-green { background: #d1fae5; color: #059669; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); }
.table th { font-size: 12px; color: var(--muted); text-transform: uppercase; font-weight: 600; background: #f8fafc; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; font-family: inherit; }
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: white; border-radius: 16px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.modal-title { font-size: 18px; font-weight: 600; }
.modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--muted); }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }
.module-list { display: flex; flex-direction: column; gap: 8px; }
.module-item { display: flex; align-items: center; padding: 14px 16px; border-radius: 10px; background: #f8fafc; cursor: pointer; transition: all 0.2s; }
.module-item:hover, .module-item.active { background: #e0e7ff; border-left: 3px solid var(--primary); }
.module-item-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 18px; }
.module-item-title { font-weight: 500; font-size: 14px; }
.module-item-meta { font-size: 12px; color: var(--muted); }
.content-item { display: flex; align-items: center; padding: 16px; border-radius: 12px; margin-bottom: 12px; background: #f8fafc; border-left: 4px solid var(--primary); }
.content-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 16px; font-size: 20px; }
.content-info { flex: 1; }
.content-title { font-weight: 600; margin-bottom: 4px; }
.content-meta { font-size: 12px; color: var(--muted); }
.content-actions { display: flex; gap: 8px; }
.empty-state { text-align: center; padding: 40px; color: var(--muted); }
.level-badge { display: inline-block; padding: 4px 12px; background: var(--primary); color: white; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 16px; }
.grid-2 { display: grid; grid-template-columns: 280px 1fr; gap: 24px; }
@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .grid-2 { grid-template-columns: 1fr; } }
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
             <a href="instructor_dashboard.php" class="nav-item <?= $currentPage == 'instructor_dashboard.php' ? 'active' : '' ?>"><span>🏠</span> Dashboard</a>
             <a href="my_modules.php" class="nav-item <?= $currentPage == 'my_modules.php' ? 'active' : '' ?>"><span>📚</span> My Modules</a>
             <a href="learning_materials.php" class="nav-item <?= $currentPage == 'learning_materials.php' ? 'active' : '' ?>"><span>📂</span> Materials</a>
             <a href="quizzes.php" class="nav-item <?= $currentPage == 'quizzes.php' ? 'active' : '' ?>"><span>❓</span> Quizzes</a>
             <a href="assignments.php" class="nav-item <?= $currentPage == 'assignments.php' ? 'active' : '' ?>"><span>📝</span> Assignments</a>
             <a href="my_students.php" class="nav-item <?= $currentPage == 'my_students.php' ? 'active' : '' ?>"><span>👥</span> My Students</a>
             <a href="assessments.php" class="nav-item <?= $currentPage == 'assessments.php' ? 'active' : '' ?>"><span>📋</span> Assessments</a>
             <a href="reports.php" class="nav-item <?= $currentPage == 'reports.php' ? 'active' : '' ?>"><span>📊</span> Reports</a>
         </div>
     </nav>
     <div class="sidebar-footer">
         <div class="user-profile">
             <div class="user-avatar">👤</div>
             <div class="user-info">
                 <h4><?= htmlspecialchars($fullName) ?></h4>
                 <p>Trainer</p>
             </div>
         </div>
     </div>
 </aside>

<main class="main-content">
    <div class="top-bar">
        <div><h1 class="page-title">Training Modules</h1><p class="page-subtitle">Manage module content by NC Level</p></div>
        <a href="../logout.php" class="btn btn-outline">Logout</a>
    </div>
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="grid-2">
            <!-- Module List by NC Level -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Modules by NC Level</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($modulesByLevel)): ?>
                        <div class="empty-state">No modules available.</div>
                        <?php else: ?>
                        <?php foreach ($modulesByLevel as $level => $levelModules): ?>
                        <div class="level-badge"><?= htmlspecialchars($level) ?></div>
                        <div class="module-list" style="margin-bottom: 20px;">
                            <?php foreach ($levelModules as $mod): ?>
                            <a href="?module_id=<?= $mod['module_id'] ?>" class="module-item <?= $selectedModuleId == $mod['module_id'] ? 'active' : '' ?>">
                                <div class="module-item-icon" style="background: #dbeafe; color: #2563eb;">
                                    <?= $mod['module_type'] === 'Video' ? '🎬' : ($mod['module_type'] === 'PDF' ? '📄' : ($mod['module_type'] === 'Quiz' ? '❓' : '📚')) ?>
                                </div>
                                <div>
                                    <div class="module-item-title"><?= htmlspecialchars($mod['module_title']) ?></div>
                                    <div class="module-item-meta"><?= $mod['duration_mins'] ?> mins</div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Module Details and Content -->
            <div>
                <?php if ($selectedModule): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= count($moduleContents) ?></div>
                        <div class="stat-label">Contents</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $moduleStats['enrolled'] ?? 0 ?></div>
                        <div class="stat-label">Enrolled</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= array_sum(array_column(array_filter($moduleContents, fn($c) => $c['is_published']), 'is_published')) ?></div>
                        <div class="stat-label">Published</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= array_sum(array_column($moduleContents, 'duration_mins')) ?></div>
                        <div class="stat-label">Total Mins</div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?= htmlspecialchars($selectedModule['module_title']) ?></h3>
                        <button class="btn btn-sm btn-primary" onclick="openModal('addContentModal')">+ Add Content</button>
                    </div>
                    <div class="card-body">
                        <p style="margin-bottom: 16px; color: var(--muted);"><?= htmlspecialchars($selectedModule['module_description']) ?></p>
                        
                        <?php if (empty($moduleContents)): ?>
                        <div class="empty-state">No content added yet. Click "+ Add Content" to add videos, PDFs, links, quizzes, or assignments.</div>
<?php else: 
                            function getContentStyle($type) {
                                if ($type === 'Video') return array('#ede9fe', '#7c3aed', '🎬', 'purple');
                                if ($type === 'PDF') return array('#dbeafe', '#2563eb', '📄', 'blue');
                                if ($type === 'Link') return array('#fed7aa', '#d97706', '🔗', 'warning');
                                return array('#d1fae5', '#059669', '📋', 'success');
                            }
                            foreach ($moduleContents as $content): 
                                list($bg, $cl, $ic, $bd) = getContentStyle($content['content_type']);
                        ?>
                        <div class="content-item">
                            <div class="content-icon" style="background: <?= $bg ?>; color: <?= $cl ?>;">
                                <?= $ic ?>
                            </div>
                            <div class="content-info">
                                <div class="content-title"><?= htmlspecialchars($content['title']) ?></div>
                                <div class="content-meta">
                                    <span class="badge badge-<?= $bd ?>"><?= $content['content_type'] ?></span>
                                    <?= $content['duration_mins'] ? $content['duration_mins'] . ' mins' : '' ?>
                                    <?php if ($content['content_url']): ?> • <a href="<?= htmlspecialchars($content['content_url']) ?>" target="_blank">Link</a><?php endif; ?>
                                    <?php if ($content['file_name']): ?> • <a href="<?= htmlspecialchars($content['file_path']) ?>" target="_blank">Download</a><?php endif; ?>
                                </div>
                            </div>
                            <div class="content-actions">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_publish">
                                    <input type="hidden" name="content_id" value="<?= $content['content_id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $content['is_published'] ? 'btn-success' : 'btn-outline' ?>">
                                        <?= $content['is_published'] ? 'Published' : 'Draft' ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this content?')">
                                    <input type="hidden" name="action" value="delete_content">
                                    <input type="hidden" name="content_id" value="<?= $content['content_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Student View Preview -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Student Access Preview</h3>
                    </div>
                    <div class="card-body">
                        <p style="color: var(--muted); margin-bottom: 16px;">Students enrolled in this module's NC Level will be able to access published content.</p>
                        <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
                            <div class="stat-card">
                                <div class="stat-value"><?= $moduleStats['enrolled'] ?? 0 ?></div>
                                <div class="stat-label">Currently Enrolled</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?= array_sum(array_map(fn($c) => $c['is_published'], $moduleContents)) ?></div>
                                <div class="stat-label">Items Available</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">Select a module to view and manage content</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Add Content Modal -->
<div id="addContentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add Module Content</h3>
            <button class="modal-close" onclick="closeModal('addContentModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_content">
                <input type="hidden" name="module_id" value="<?= $selectedModuleId ?>">
                
                <div class="form-group">
                    <label>Content Title *</label>
                    <input type="text" name="title" required placeholder="e.g., Introduction to Engine Repair">
                </div>
                
                <div class="form-group">
                    <label>Content Type *</label>
                    <select name="content_type" onchange="toggleContentFields()">
                        <option value="Video">Video (Upload or URL)</option>
                        <option value="PDF">PDF Document</option>
                        <option value="Link">External Link (YouTube, etc.)</option>
                        <option value="Quiz">Quiz</option>
                        <option value="Assignment">Assignment</option>
                        <option value="Activity">Activity/Workshop</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2" placeholder="Brief description of this content"></textarea>
                </div>
                
                <div class="form-group" id="urlField">
                    <label>Content URL (for Video/Link)</label>
                    <input type="url" name="content_url" placeholder="https://youtube.com/watch?v=... or https://...">
                </div>
                
                <div class="form-group" id="fileField">
                    <label>Upload File (PDF/Video for Video type)</label>
                    <input type="file" name="file" accept=".pdf,.mp4,.avi,.mov,.mkv">
                    <small style="color: var(--muted);">Max size: 100MB. Supported: PDF, MP4, AVI, MOV, MKV</small>
                </div>
                
                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration_mins" value="0" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addContentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Content</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function toggleContentFields() {
    const type = document.querySelector('[name="content_type"]').value;
    const urlField = document.getElementById('urlField');
    const fileField = document.getElementById('fileField');
    
    if (type === 'Link') {
        urlField.style.display = 'block';
        fileField.style.display = 'none';
    } else if (type === 'Video') {
        urlField.style.display = 'block';
        fileField.style.display = 'block';
    } else if (type === 'PDF') {
        urlField.style.display = 'none';
        fileField.style.display = 'block';
    } else {
        urlField.style.display = 'none';
        fileField.style.display = 'none';
    }
}
toggleContentFields();
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>
</body>
</html>