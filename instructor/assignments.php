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

// Create tables if not exist (removed DROP TABLE to preserve data)
$conn->exec("CREATE TABLE IF NOT EXISTS `assignments` (
    `assignment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_id` INT DEFAULT 0,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `due_date` DATETIME,
    `max_score` INT DEFAULT 100,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
)");

// Create assignment_submissions table if not exists
$conn->exec("CREATE TABLE IF NOT EXISTS `assignment_submissions` (
    `submission_id` INT AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `file_path` VARCHAR(255),
    `file_name` VARCHAR(255),
    `submission_text` TEXT,
    `score` INT,
    `feedback` TEXT,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP(),
    `graded_by` INT,
    `graded_at` TIMESTAMP NULL
)");

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_assignment' && ($userType === 'trainer' || $userType === 'instructor' || $userType === 'admin')) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $dueDate = $_POST['due_date'] ?? '';
        $maxScore = intval($_POST['max_score'] ?? 100);
        $ncLevel = $_POST['nc_level'] ?? 'NC I';
        
        if ($title) {
            $stmt = $conn->prepare("INSERT INTO assignments (title, description, due_date, max_score, created_by, nc_level) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $dueDate ?: null, $maxScore, $userId, $ncLevel]);
            $message = 'Assignment created successfully';
            $messageType = 'success';
        }
    }
    
    if ($_POST['action'] === 'submit_assignment' && $userType === 'student') {
        $assignmentId = intval($_POST['assignment_id'] ?? 0);
        $text = trim($_POST['submission_text'] ?? '');
        
        if ($assignmentId && $text) {
            $stmt = $conn->prepare("SELECT submission_id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
            $stmt->execute([$assignmentId, $userId]);
            
            if ($stmt->fetch()) {
                $stmt = $conn->prepare("UPDATE assignment_submissions SET submission_text = ?, submitted_at = NOW() WHERE assignment_id = ? AND student_id = ?");
                $stmt->execute([$text, $assignmentId, $userId]);
            } else {
                $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, submission_text) VALUES (?, ?, ?)");
                $stmt->execute([$assignmentId, $userId, $text]);
            }
            $message = 'Assignment submitted';
            $messageType = 'success';
        }
    }
    
    if ($_POST['action'] === 'grade' && ($userType === 'trainer' || $userType === 'instructor' || $userType === 'admin')) {
        $submissionId = intval($_POST['submission_id'] ?? 0);
        $score = intval($_POST['score'] ?? 0);
        $feedback = trim($_POST['feedback'] ?? '');
        
        if ($submissionId > 0) {
            $stmt = $conn->prepare("UPDATE assignment_submissions SET score = ?, feedback = ?, graded_by = ?, graded_at = NOW() WHERE submission_id = ?");
            $stmt->execute([$score, $feedback, $userId, $submissionId]);
            $message = 'Graded successfully';
            $messageType = 'success';
        }
    }
}

// Get assignments - filter by trainee's enrolled modules and NC level for student users
if ($userType === 'trainee' || $userType === 'student') {
    // Get trainee's NC level (first get student ID from user_id)
    $ncLevelStmt = $conn->prepare("
        SELECT spe.nc_level 
        FROM student_program_enrollments spe
        WHERE spe.student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1) 
        AND spe.enrollment_status = 'Active'
        ORDER BY spe.enrollment_id DESC LIMIT 1
    ");
    $ncLevelStmt->execute([$userId]);
    $studentNcLevel = $ncLevelStmt->fetchColumn() ?: 'NC I';
    
    // Get the student ID for this user
    $studStmt = $conn->prepare("SELECT StudID FROM student WHERE user_id = ? LIMIT 1");
    $studStmt->execute([$userId]);
    $studentId = $studStmt->fetchColumn() ?: 0;
    
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
        $stmt = $conn->prepare("
            SELECT a.*, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.assignment_id AND student_id = ?) as submission_count,
                (SELECT score FROM assignment_submissions WHERE assignment_id = a.assignment_id AND student_id = ? ORDER BY submitted_at DESC LIMIT 1) as last_score,
                CASE WHEN a.due_date < NOW() THEN 'Overdue' ELSE 'Active' END as status_label
            FROM assignments a 
            LEFT JOIN users u ON a.created_by = u.user_id
            WHERE (a.module_id IN ($placeholders) OR a.nc_level = ?)
            ORDER BY a.due_date ASC, a.created_at DESC
        ");
        $params = array_merge($enrolledModuleIds, [$userId, $userId, $studentNcLevel]);
        $stmt->execute($params);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // If no enrolled modules, still show NC level-specific assignments
        $stmt = $conn->prepare("
            SELECT a.*, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.assignment_id AND student_id = ?) as submission_count,
                (SELECT score FROM assignment_submissions WHERE assignment_id = a.assignment_id AND student_id = ? ORDER BY submitted_at DESC LIMIT 1) as last_score,
                CASE WHEN a.due_date < NOW() THEN 'Overdue' ELSE 'Active' END as status_label
            FROM assignments a 
            LEFT JOIN users u ON a.created_by = u.user_id
            WHERE a.nc_level = ?
            ORDER BY a.due_date ASC, a.created_at DESC
        ");
        $stmt->execute([$userId, $userId, $studentNcLevel]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Trainers/admins see all assignments
    $stmt = $conn->query("SELECT a.*, u.first_name, u.last_name,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.assignment_id) as submission_count
        FROM assignments a 
        LEFT JOIN users u ON a.created_by = u.user_id
        ORDER BY a.due_date ASC, a.created_at DESC");
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get selected assignment
$selectedId = $_GET['assignment_id'] ?? 0;
$selectedAssignment = null;
$submissions = [];

if ($selectedId > 0) {
    $stmt = $conn->prepare("SELECT * FROM assignments WHERE assignment_id = ?");
    $stmt->execute([$selectedId]);
    $selectedAssignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedAssignment) {
        $stmt = $conn->query("SELECT s.*, u.first_name, u.last_name, u.email
            FROM assignment_submissions s
            JOIN users u ON s.student_id = u.user_id
            WHERE s.assignment_id = $selectedId
            ORDER BY s.submitted_at DESC");
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Stats
$stmt = $conn->query("SELECT 
    (SELECT COUNT(*) FROM assignments) as total_assignments,
    (SELECT COUNT(*) FROM assignment_submissions) as total_submissions,
    (SELECT AVG(score) FROM assignment_submissions WHERE score IS NOT NULL) as avg_score");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
$currentPage = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assignments - TESDA Auto Mechanic</title>
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
.btn-outline { background: white; border: 1px solid var(--border); color: var(--foreground); }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.container { padding: 30px 40px; }
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: var(--card); padding: 20px; border-radius: 12px; border: 1px solid var(--border); text-align: center; }
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
.badge-warning { background: #fed7aa; color: #d97706; }
.badge-blue { background: #dbeafe; color: #2563eb; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); }
.table th { font-size: 12px; color: var(--muted); text-transform: uppercase; font-weight: 600; background: #f8fafc; }
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
.assignment-item { display: flex; align-items: center; padding: 16px; border-radius: 12px; margin-bottom: 12px; background: #f8fafc; }
.empty-state { text-align: center; padding: 40px; color: var(--muted); }
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
             <a href="../student/student_dashboard.php" class="nav-item <?= $currentPage == 'student_dashboard.php' ? 'active' : '' ?>"><span>🏠</span> Dashboard</a>
             <a href="learning_materials.php" class="nav-item <?= $currentPage == 'learning_materials.php' ? 'active' : '' ?>"><span>📚</span> Materials</a>
             <a href="quizzes.php" class="nav-item <?= $currentPage == 'quizzes.php' ? 'active' : '' ?>"><span>❓</span> Quizzes</a>
             <a href="assignments.php" class="nav-item <?= $currentPage == 'assignments.php' ? 'active' : '' ?>"><span>📝</span> Assignments</a>
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
        <div><h1 class="page-title">Assignments</h1><p class="page-subtitle"><?= $userType === 'student' ? 'Submit assignments' : 'Manage assignments' ?></p></div>
        <a href="../logout.php" class="btn btn-outline">Logout</a>
    </div>
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?= $stats['total_assignments'] ?></div><div class="stat-label">Assignments</div></div>
            <div class="stat-card"><div class="stat-value"><?= $stats['total_submissions'] ?></div><div class="stat-label">Submissions</div></div>
            <div class="stat-card"><div class="stat-value"><?= round($stats['avg_score'] ?? 0) ?>%</div><div class="stat-label">Avg Score</div></div>
        </div>
        
        <?php if ($selectedAssignment): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= htmlspecialchars($selectedAssignment['title']) ?></h3>
                <a href="assignments.php" class="btn btn-sm btn-outline">Back</a>
            </div>
            <div class="card-body">
                <p><?= htmlspecialchars($selectedAssignment['description']) ?></p>
                <p style="margin-top:12px;"><strong>Due:</strong> <?= $selectedAssignment['due_date'] ? date('M d, Y g:i A', strtotime($selectedAssignment['due_date'])) : 'No due date' ?> | <strong>Max:</strong> <?= $selectedAssignment['max_score'] ?> pts</p>
                
                <?php if ($userType === 'student'): ?>
                <?php 
                $stmt = $conn->prepare("SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
                $stmt->execute([$selectedId, $userId]);
                $mySub = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <?php if ($mySub): ?>
                <div class="alert alert-success">Submitted! Score: <?= $mySub['score'] ?? 'Pending' ?>/<?= $selectedAssignment['max_score'] ?></div>
                <?php endif; ?>
                
                <form method="POST" style="margin-top:20px;">
                    <input type="hidden" name="action" value="submit_assignment">
                    <input type="hidden" name="assignment_id" value="<?= $selectedId ?>">
                    <div class="form-group"><label>Your Answer</label><textarea name="submission_text" rows="5"><?= htmlspecialchars($mySub['submission_text'] ?? '') ?></textarea></div>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </form>
                <?php else: ?>
                <table class="table">
                    <thead><tr><th>Student</th><th>Submitted</th><th>Text</th><th>Score</th></tr></thead>
                    <tbody>
                        <?php foreach ($submissions as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></td>
                            <td><?= $s['submitted_at'] ? date('M d, Y g:i A', strtotime($s['submitted_at'])) : '-' ?></td>
                            <td><?= htmlspecialchars(substr($s['submission_text'] ?? '', 0, 50)) ?>...</td>
                            <td>
                                <?php if ($s['score'] !== null): ?>
                                <span class="badge badge-<?= $s['score'] >= 70 ? 'success' : 'warning' ?>"><?= $s['score'] ?></span>
                                <?php else: ?>
                                <button class="btn btn-sm btn-primary" onclick="openGrade(<?= $s['submission_id'] ?>, '<?= $s['score'] ?? '' ?>')">Grade</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Assignments</h3>
                <?php if ($userType === 'trainer' || $userType === 'instructor' || $userType === 'admin'): ?>
                <button class="btn btn-sm btn-primary" onclick="openModal('createModal')">+ Create</button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($assignments)): ?>
                <div class="empty-state">No assignments yet.</div>
                <?php else: ?>
                <?php foreach ($assignments as $a): ?>
                <a href="?assignment_id=<?= $a['assignment_id'] ?>" class="assignment-item" style="display:block; text-decoration:none; color:inherit;">
                    <div style="flex:1;">
                        <div style="font-weight:600;"><?= htmlspecialchars($a['title']) ?></div>
                        <div style="font-size:12px; color:var(--muted);">Due: <?= $a['due_date'] ? date('M d, Y', strtotime($a['due_date'])) : 'No due date' ?> | <?= $a['submission_count'] ?> submissions | <span class="badge badge-purple"><?= $a['nc_level'] ?? 'NC I' ?></span></div>
                    </div>
                    <span class="badge badge-blue"><?= $a['max_score'] ?> pts</span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3 class="modal-title">Create Assignment</h3><button class="modal-close" onclick="closeModal('createModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create_assignment">
                <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="3"></textarea></div>
                <div class="form-group"><label>Due Date</label><input type="datetime-local" name="due_date"></div>
                <div class="form-group"><label>Max Score</label><input type="number" name="max_score" value="100"></div>
                <div class="form-group">
                    <label>NC Level *</label>
                    <select name="nc_level" required>
                        <option value="NC I">NC I - Automotive Servicing</option>
                        <option value="NC II">NC II - Automotive Servicing</option>
                        <option value="NC III">NC III - Automotive Servicing</option>
                        <option value="NC IV">NC IV - Automotive Servicing</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<div id="gradeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3 class="modal-title">Grade</h3><button class="modal-close" onclick="closeModal('gradeModal')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="grade">
                <input type="hidden" name="submission_id" id="gradeId">
                <div class="form-group"><label>Score</label><input type="number" name="score" id="gradeScore" min="0"></div>
                <div class="form-group"><label>Feedback</label><textarea name="feedback" id="gradeFeedback" rows="3"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('gradeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function openGrade(id, score) { document.getElementById('gradeId').value = id; document.getElementById('gradeScore').value = score || 0; openModal('gradeModal'); }
document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if(e.target === m) closeModal(m.id); }));
</script>
</body>
</html>