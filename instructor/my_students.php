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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_note' && isset($_POST['student_id'])) {
            $studentId = intval($_POST['student_id']);
            $note = trim($_POST['note'] ?? '');
            
            $stmt = $conn->prepare("INSERT INTO student_module_progress (enrollment_id, module_id, status, instructor_notes) 
                SELECT spe.enrollment_id, lm.module_id, 'In Progress', ?
                FROM student_program_enrollments spe 
                JOIN learning_modules lm ON 1=1
                WHERE spe.student_id = ? LIMIT 1
                ON DUPLICATE KEY UPDATE instructor_notes = CONCAT(instructor_notes, '\n', ?)");
            $stmt->execute([$note, $studentId, $note]);
            $message = 'Note added successfully';
            $messageType = 'success';
        }
        
        if ($_POST['action'] === 'filter') {
            $_SESSION['student_filter'] = $_POST['filter_status'] ?? 'all';
            $_SESSION['search'] = $_POST['search'] ?? '';
        }
    }
}

$filterStatus = $_SESSION['student_filter'] ?? 'all';
$searchTerm = $_SESSION['search'] ?? '';

// Get students with enrollments
$whereClause = "WHERE u.user_type = 'student' AND u.status = 'active'";
if ($filterStatus !== 'all') {
    $whereClause .= " AND spe.enrollment_status = '$filterStatus'";
}
if ($searchTerm) {
    $whereClause .= " AND (u.first_name LIKE '%$searchTerm%' OR u.last_name LIKE '%$searchTerm%' OR u.email LIKE '%$searchTerm%')";
}

$query = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.created_at,
    spe.enrollment_id, spe.enrollment_status, spe.enrollment_date, spe.final_grade,
    (SELECT MAX(last_access_date) FROM student_module_progress WHERE enrollment_id = spe.enrollment_id) as last_active
    FROM users u 
    JOIN student_program_enrollments spe ON u.user_id = spe.student_id
    $whereClause
    ORDER BY last_active DESC, u.last_name ASC";

$stmt = $conn->query($query);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected student details
$selectedStudentId = $_GET['student_id'] ?? 0;
$studentDetails = null;
$studentProgress = [];
$studentAssessments = [];

if ($selectedStudentId) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$selectedStudentId]);
    $studentDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($studentDetails) {
        $stmt = $conn->query("SELECT smp.*, lm.module_title, lm.module_type, lm.duration_mins
            FROM student_module_progress smp 
            JOIN learning_modules lm ON smp.module_id = lm.module_id
            JOIN student_program_enrollments spe ON smp.enrollment_id = spe.enrollment_id
            WHERE spe.student_id = $selectedStudentId
            ORDER BY smp.last_access_date DESC");
        $studentProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $conn->query("SELECT ca.*, cu.unit_code, cu.unit_title
            FROM competency_assessments ca 
            LEFT JOIN competency_units cu ON ca.unit_id = cu.unit_id
            WHERE ca.user_id = $selectedStudentId
            ORDER BY ca.assessment_date DESC");
        $studentAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Stats
$stmt = $conn->query("SELECT 
    COUNT(DISTINCT student_id) as total_students,
    SUM(CASE WHEN enrollment_status = 'Active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN enrollment_status = 'Completed' THEN 1 ELSE 0 END) as completed
    FROM student_program_enrollments");
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
<title>My Students - TESDA Auto Mechanic</title>
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
.table tr:hover { background: #f8fafc; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; font-family: inherit; }
.search-box { display: flex; gap: 12px; margin-bottom: 20px; }
.search-box input { flex: 1; padding: 10px 16px; border: 1px solid var(--border); border-radius: 8px; }
.filter-select { padding: 10px 16px; border: 1px solid var(--border); border-radius: 8px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.profile-header { display: flex; align-items: center; gap: 20px; margin-bottom: 24px; }
.profile-avatar { width: 80px; height: 80px; background: #dbeafe; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; }
.profile-info h2 { font-size: 20px; font-weight: 600; margin-bottom: 4px; }
.profile-info p { color: var(--muted); }
.progress-bar { height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
.progress-bar-fill { height: 100%; background: var(--success); border-radius: 4px; }
.empty-state { text-align: center; padding: 40px; color: var(--muted); }
.student-row { display: flex; align-items: center; padding: 14px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.2s; }
.student-row:hover { background: #f8fafc; }
.student-row.active { background: #e0e7ff; }
.student-name { font-weight: 500; }
.student-email { font-size: 12px; color: var(--muted); }
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
        <div><h1 class="page-title">My Students</h1><p class="page-subtitle">Manage enrolled students</p></div>
        <a href="../logout.php" class="btn btn-outline">Logout</a>
    </div>
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_students'] ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--success);"><?= $stats['active'] ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--primary);"><?= $stats['completed'] ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        
        <?php if ($selectedStudentId && $studentDetails): ?>
        <div class="grid-2">
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Student Details</h3>
                        <a href="my_students.php" class="btn btn-sm btn-outline">Close</a>
                    </div>
                    <div class="card-body">
                        <div class="profile-header">
                            <div class="profile-avatar">👤</div>
                            <div class="profile-info">
                                <h2><?= htmlspecialchars($studentDetails['first_name'] . ' ' . $studentDetails['last_name']) ?></h2>
                                <p><?= htmlspecialchars($studentDetails['email'] ?? '') ?></p>
                                <p><?= htmlspecialchars($studentDetails['phone'] ?? 'No phone') ?></p>
                            </div>
                        </div>
                        
                        <h4 style="margin-bottom: 12px;">Enrolled Modules (<?= count($studentProgress) ?>)</h4>
                        <?php if (empty($studentProgress)): ?>
                        <div class="empty-state">No module progress yet.</div>
                        <?php else: ?>
                        <?php foreach ($studentProgress as $prog): ?>
                        <div style="margin-bottom: 16px; padding: 12px; background: #f8fafc; border-radius: 8px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <strong><?= htmlspecialchars($prog['module_title']) ?></strong>
                                <span class="badge badge-<?= $prog['status'] === 'Completed' ? 'success' : ($prog['status'] === 'In Progress' ? 'warning' : 'blue') ?>"><?= $prog['status'] ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-bar-fill" style="width: <?= $prog['progress_percentage'] ?>%"></div>
                            </div>
                            <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                                <?= $prog['progress_percentage'] ?>% complete • <?= $prog['final_score'] ?? '-' ?> score
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Competency Assessments</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($studentAssessments)): ?>
                        <div class="empty-state">No assessments yet.</div>
                        <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Unit</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentAssessments as $assess): ?>
                                <tr>
                                    <td><?= htmlspecialchars($assess['unit_code'] ?? 'N/A') ?></td>
                                    <td><?= $assess['final_score'] ?? '-' ?></td>
                                    <td>
                                        <span class="badge badge-<?= $assess['assessment_status'] === 'Passed' ? 'success' : ($assess['assessment_status'] === 'Failed' ? 'warning' : 'blue') ?>">
                                            <?= $assess['assessment_status'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Add Note</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_note">
                            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                            <div class="form-group">
                                <textarea name="note" rows="3" placeholder="Add a note for this student..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Note</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Students</h3>
                <form method="POST" class="search-box" style="margin: 0;">
                    <input type="hidden" name="action" value="filter">
                    <input type="text" name="search" placeholder="Search students..." value="<?= htmlspecialchars($searchTerm) ?>">
                    <select name="filter_status" class="filter-select">
                        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="Active" <?= $filterStatus === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Completed" <?= $filterStatus === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Dropped" <?= $filterStatus === 'Dropped' ? 'selected' : '' ?>>Dropped</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($students)): ?>
                <div class="empty-state">No students found.</div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Enrolled</th>
                            <th>Last Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td style="font-weight: 500;"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                            <td><?= htmlspecialchars($student['email'] ?? '-') ?></td>
                            <td>
                                <span class="badge badge-<?= $student['enrollment_status'] === 'Active' ? 'success' : ($student['enrollment_status'] === 'Completed' ? 'blue' : 'warning') ?>">
                                    <?= $student['enrollment_status'] ?? 'Active' ?>
                                </span>
                            </td>
                            <td><?= $student['enrollment_date'] ? date('M d, Y', strtotime($student['enrollment_date'])) : '-' ?></td>
                            <td><?= $student['last_active'] ? date('M d, Y', strtotime($student['last_active'])) : '-' ?></td>
                            <td>
                                <a href="?student_id=<?= $student['user_id'] ?>" class="btn btn-sm btn-primary">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>