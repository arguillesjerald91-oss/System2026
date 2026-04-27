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

// Get date range filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Overall Stats
$stmt = $conn->query("SELECT 
    (SELECT COUNT(*) FROM users WHERE user_type = 'student') as total_students,
    (SELECT COUNT(*) FROM users WHERE user_type = 'student' AND status = 'active') as active_students,
    (SELECT COUNT(*) FROM learning_modules WHERE is_active = 1) as total_modules,
    (SELECT COUNT(DISTINCT student_id) FROM student_program_enrollments WHERE enrollment_status = 'Completed') as completed_students
    ");
$overallStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Enrollment trends
$stmt = $conn->query("SELECT 
    DATE_FORMAT(enrollment_date, '%Y-%m') as month,
    COUNT(*) as enrollments
    FROM student_program_enrollments 
    WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(enrollment_date, '%Y-%m')
    ORDER BY month");
$enrollmentTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Module completion stats
$stmt = $conn->query("SELECT lm.module_title, lm.module_type,
    COUNT(smp.module_id) as enrolled,
    SUM(CASE WHEN smp.status = 'Completed' THEN 1 ELSE 0 END) as completed,
    AVG(smp.progress_percentage) as avg_progress
    FROM learning_modules lm
    LEFT JOIN student_module_progress smp ON lm.module_id = smp.module_id
    GROUP BY lm.module_id
    ORDER BY completed DESC
    LIMIT 10");
$moduleStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Assessment stats
$stmt = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN assessment_status = 'Passed' THEN 1 ELSE 0 END) as passed,
    SUM(CASE WHEN assessment_status = 'Failed' THEN 1 ELSE 0 END) as failed,
    AVG(final_score) as avg_score
    FROM competency_assessments");
$assessmentStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent activity
$stmt = $conn->query("SELECT 
    'enrollment' as type,
    spe.enrollment_id as id,
    u.first_name,
    u.last_name,
    'Enrolled in program' as action,
    spe.enrollment_date as date
    FROM student_program_enrollments spe
    JOIN users u ON spe.student_id = u.user_id
    ORDER BY spe.enrollment_date DESC
    LIMIT 10");
$recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pass rate calculation
$passRate = 0;
if ($assessmentStats['total'] > 0) {
    $passRate = round(($assessmentStats['passed'] / $assessmentStats['total']) * 100);
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
<title>Reports - TESDA Auto Mechanic</title>
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
.btn-outline { background: white; border: 1px solid var(--border); color: var(--foreground); }
.container { padding: 30px 40px; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: var(--card); padding: 24px; border-radius: 12px; border: 1px solid var(--border); }
.stat-value { font-size: 32px; font-weight: 700; }
.stat-label { font-size: 13px; color: var(--muted); margin-top: 4px; }
.stats-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); margin-bottom: 24px; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.card-title { font-size: 16px; font-weight: 600; }
.card-body { padding: 20px 24px; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-warning { background: #fed7aa; color: #d97706; }
.badge-blue { background: #dbeafe; color: #2563eb; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); }
.table th { font-size: 12px; color: var(--muted); text-transform: uppercase; font-weight: 600; background: #f8fafc; }
.filter-bar { display: flex; gap: 12px; align-items: center; }
.filter-bar input, .filter-bar select { padding: 8px 14px; border: 1px solid var(--border); border-radius: 8px; }
.chart-placeholder { height: 200px; background: #f8fafc; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--muted); }
.bar-chart { display: flex; align-items: flex-end; gap: 8px; height: 150px; padding: 20px; }
.bar { flex: 1; background: var(--primary); border-radius: 4px 4px 0 0; position: relative; }
.bar-label { position: absolute; bottom: -25px; left: 50%; transform: translateX(-50%); font-size: 10px; color: var(--muted); white-space: nowrap; }
.bar-value { position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 11px; font-weight: 600; }
.pie-chart { width: 200px; height: 200px; border-radius: 50%; background: conic-gradient(var(--success) 0% <?= $passRate ?>%, var(--danger) <?= $passRate ?>% 100%); margin: 0 auto; position: relative; }
.pie-center { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 140px; height: 140px; background: white; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .stats-grid-3 { grid-template-columns: 1fr; } }
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
        <div><h1 class="page-title">Reports & Analytics</h1><p class="page-subtitle">Training center performance metrics</p></div>
        <div style="display: flex; gap: 12px;">
            <a href="javascript:window.print()" class="btn btn-outline">Print Report</a>
            <a href="../logout.php" class="btn btn-primary">Logout</a>
        </div>
    </div>
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $overallStats['total_students'] ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--success);"><?= $overallStats['active_students'] ?></div>
                <div class="stat-label">Active Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $overallStats['total_modules'] ?></div>
                <div class="stat-label">Training Modules</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--primary);"><?= $overallStats['completed_students'] ?></div>
                <div class="stat-label">Graduated</div>
            </div>
        </div>
        
        <div class="stats-grid-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Assessment Pass Rate</h3>
                </div>
                <div class="card-body" style="text-align: center;">
                    <div class="pie-chart">
                        <div class="pie-center">
                            <div style="font-size: 32px; font-weight: 700;"><?= $passRate ?>%</div>
                            <div style="font-size: 12px; color: var(--muted);">Pass Rate</div>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: center; gap: 24px; margin-top: 20px;">
                        <div><span class="badge badge-success"><?= $assessmentStats['passed'] ?> Passed</span></div>
                        <div><span class="badge badge-warning"><?= $assessmentStats['failed'] ?> Failed</span></div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Average Score</h3>
                </div>
                <div class="card-body" style="text-align: center;">
                    <div style="font-size: 64px; font-weight: 700; color: var(--primary);"><?= round($assessmentStats['avg_score'] ?? 0) ?>%</div>
                    <div style="color: var(--muted);">Overall Average</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Completion Rate</h3>
                </div>
                <div class="card-body" style="text-align: center;">
                    <?php 
                    $completionRate = 0;
                    if ($overallStats['total_students'] > 0) {
                        $completionRate = round(($overallStats['completed_students'] / $overallStats['total_students']) * 100);
                    }
                    ?>
                    <div style="font-size: 64px; font-weight: 700; color: var(--success);"><?= $completionRate ?>%</div>
                    <div style="color: var(--muted);">Students Completed</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Module Performance</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($moduleStats)): ?>
                <div style="padding: 40px; text-align: center; color: var(--muted);">No module data available.</div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Type</th>
                            <th>Enrolled</th>
                            <th>Completed</th>
                            <th>Avg Progress</th>
                            <th>Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($moduleStats as $mod): ?>
                        <?php 
                        $compRate = 0;
                        if ($mod['enrolled'] > 0) {
                            $compRate = round(($mod['completed'] / $mod['enrolled']) * 100);
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($mod['module_title']) ?></td>
                            <td><span class="badge badge-blue"><?= $mod['module_type'] ?></span></td>
                            <td><?= $mod['enrolled'] ?></td>
                            <td><?= $mod['completed'] ?></td>
                            <td><?= round($mod['avg_progress'] ?? 0) ?>%</td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="flex: 1; height: 8px; background: #e2e8f0; border-radius: 4px;">
                                        <div style="width: <?= $compRate ?>%; height: 100%; background: var(--success); border-radius: 4px;"></div>
                                    </div>
                                    <span style="font-size: 12px; font-weight: 600;"><?= $compRate ?>%</span>
                                </div>
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
                <h3 class="card-title">Recent Activity</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recentActivity)): ?>
                <div style="padding: 40px; text-align: center; color: var(--muted);">No recent activity.</div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Activity</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentActivity as $activity): ?>
                        <tr>
                            <td><?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?></td>
                            <td><?= htmlspecialchars($activity['action']) ?></td>
                            <td><?= date('M d, Y', strtotime($activity['date'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
</body>
</html>