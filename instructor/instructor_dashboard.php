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

if ($userType !== 'trainer') {
    header("Location: ../login.php");
    exit();
}

// Get trainer details
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$trainer = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($trainer['first_name'] ?? '') . ' ' . ($trainer['last_name'] ?? ''));

// Get modules assigned to this trainer
$stmt = $conn->prepare("SELECT lm.* FROM learning_modules lm 
    JOIN module_access_permissions map ON lm.module_id = map.module_id 
    WHERE map.user_id = ? AND map.user_type IN ('trainer', 'instructor') AND map.access_status = 'Active'
    ORDER BY lm.sort_order");
$stmt->execute([$userId]);
$myModules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active modules if none assigned
if (empty($myModules)) {
    $stmt = $conn->query("SELECT * FROM learning_modules WHERE is_active = 1 ORDER BY sort_order");
    $myModules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get students count per module
$moduleStudents = [];
foreach ($myModules as $mod) {
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT spe.student_id) FROM student_module_progress smp 
        JOIN student_program_enrollments spe ON smp.enrollment_id = spe.enrollment_id 
        WHERE smp.module_id = ?");
    $stmt->execute([$mod['module_id']]);
    $moduleStudents[$mod['module_id']] = $stmt->fetchColumn();
}

// Get pending assessments/competencies
$stmt = $conn->query("SELECT COUNT(*) FROM competency_assessments WHERE assessment_status IN ('Not Started', 'In Progress')");
$pendingAssessments = $stmt->fetchColumn();

// Get recent students
$stmt = $conn->query("SELECT u.user_id, u.first_name, u.last_name, u.email, smp.last_access_date 
    FROM student_module_progress smp 
    JOIN student_program_enrollments spe ON smp.enrollment_id = spe.enrollment_id
    JOIN users u ON spe.student_id = u.user_id 
    WHERE u.user_type = 'student' AND smp.last_access_date IS NOT NULL
    ORDER BY smp.last_access_date DESC LIMIT 10");
$recentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stmt = $conn->query("SELECT COUNT(*) FROM users WHERE user_type = 'student' AND status = 'active'");
$totalStudents = $stmt->fetchColumn();
$stmt = $conn->query("SELECT COUNT(*) FROM learning_modules WHERE is_active = 1");
$totalModules = $stmt->fetchColumn();
// Get current page for active sidebar highlighting
$currentPage = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Trainer Dashboard - TESDA Auto Mechanic</title>
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
.grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
.card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); margin-bottom: 24px; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.card-title { font-size: 16px; font-weight: 600; }
.card-body { padding: 20px 24px; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-warning { background: #fed7aa; color: #d97706; }
.badge-blue { background: #dbeafe; color: #2563eb; }
.badge-purple { background: #ede9fe; color: #7c3aed; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); }
.table th { font-size: 12px; color: var(--muted); text-transform: uppercase; font-weight: 600; background: #f8fafc; }
.table tr:hover { background: #f8fafc; }
.module-item { display: flex; align-items: center; padding: 16px; border-radius: 12px; margin-bottom: 12px; background: #f8fafc; }
.module-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 16px; font-size: 22px; }
.module-info { flex: 1; }
.module-name { font-weight: 600; margin-bottom: 4px; }
.module-meta { font-size: 12px; color: var(--muted); }
.empty-state { text-align: center; padding: 40px; color: var(--muted); }
.quick-action { display: block; width: 100%; padding: 14px; background: var(--primary); color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600; margin-bottom: 12px; }
.quick-action:hover { background: var(--primary-dark); }
.quick-action.secondary { background: #f1f5f9; color: var(--foreground); }
.quick-action.secondary:hover { background: #e2e8f0; }
@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .grid-2 { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .sidebar { width: 60px; } .main-content { margin-left: 60px; } .stats-grid { grid-template-columns: 1fr; } }
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
        <div><h1 class="page-title">Trainer Dashboard</h1><p class="page-subtitle">Welcome back, <?= htmlspecialchars($fullName) ?>!</p></div>
        <a href="../logout.php" class="btn btn-outline">Logout</a>
    </div>
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Enrolled Students</div>
                <div class="stat-value"><?= $totalStudents ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Training Modules</div>
                <div class="stat-value"><?= count($myModules) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending Assessments</div>
                <div class="stat-value"><?= $pendingAssessments ?></div>
            </div>
        </div>
        
        <div class="grid-2">
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">My Training Modules</h3>
                        <a href="my_modules.php" class="btn btn-sm btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($myModules)): ?>
                        <div class="empty-state">No modules assigned yet.</div>
                        <?php else: ?>
                        <?php foreach (array_slice($myModules, 0, 5) as $mod): ?>
                        <div class="module-item">
                            <div class="module-icon" style="background: #dbeafe; color: #2563eb;">
                                <?= $mod['module_type'] === 'Video' ? '🎬' : ($mod['module_type'] === 'PDF' ? '📄' : ($mod['module_type'] === 'Quiz' ? '❓' : '📚')) ?>
                            </div>
                            <div class="module-info">
                                <div class="module-name"><?= htmlspecialchars($mod['module_title']) ?></div>
                                <div class="module-meta"><?= $mod['module_type'] ?> • <?= $mod['duration_mins'] ?> mins • <?= $moduleStudents[$mod['module_id']] ?? 0 ?> students</div>
                            </div>
                            <span class="badge badge-<?= $mod['is_active'] ? 'success' : 'warning' ?>"><?= $mod['is_active'] ? 'Active' : 'Inactive' ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Student Activity</h3>
                        <a href="my_students.php" class="btn btn-sm btn-outline">View All</a>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($recentStudents)): ?>
                        <div class="empty-state">No recent student activity.</div>
                        <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Email</th>
                                    <th>Last Active</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentStudents as $student): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                    <td><?= htmlspecialchars($student['email'] ?? '-') ?></td>
                                    <td><?= $student['last_access_date'] ? date('M d, Y g:i A', strtotime($student['last_access_date'])) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <a href="my_modules.php" class="quick-action">📚 Manage Modules</a>
                        <a href="assessments.php" class="quick-action">📋 View Assessments</a>
                        <a href="my_students.php" class="quick-action">👥 Check Students</a>
                        <a href="reports.php" class="quick-action secondary">📊 Generate Reports</a>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Assessments Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="module-item" style="margin-bottom: 12px;">
                            <div class="module-info">
                                <div class="module-name">Pending Reviews</div>
                                <div class="module-meta"><?= $pendingAssessments ?> assessments waiting</div>
                            </div>
                            <span class="badge badge-warning"><?= $pendingAssessments ?></span>
                        </div>
                        <div class="module-item" style="margin-bottom: 0;">
                            <div class="module-info">
                                <div class="module-name">Completed</div>
                                <div class="module-meta">0 this week</div>
                            </div>
                            <span class="badge badge-success">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>