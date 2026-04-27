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

if ($userType !== 'instructional_unit') {
    header("Location: ../login.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

$stmt = $conn->query("SELECT COUNT(*) FROM users WHERE user_type = 'instructor'");
$instructorCount = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Instructional Unit Dashboard - TESDA</title>
<style>
:root { --primary: #2563eb; --primary-dark: #1e40af; --success: #10b981; --warning: #f59e0b; --background: #f1f5f9; --foreground: #1e293b; --card: #ffffff; --muted: #64748b; --border: #e2e8f0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', -apple-system, sans-serif; background: var(--background); min-height: 100vh; }
.sidebar { position: fixed; left: 0; width: 260px; height: 100vh; background: linear-gradient(180deg, var(--primary-dark), #1e3a8a); color: white; display: flex; flex-direction: column; }
.sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.sidebar-logo { display: flex; align-items: center; gap: 12px; font-size: 18px; font-weight: 700; }
.sidebar-logo span { font-size: 28px; }
.sidebar-subtitle { font-size: 11px; opacity: 0.7; margin-top: 4px; }
.sidebar-nav { flex: 1; padding: 20px 0; }
.nav-section { padding: 0 12px; margin-bottom: 20px; }
.nav-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.6; padding: 0 12px; margin-bottom: 8px; }
.nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px; color: white; text-decoration: none; margin: 2px 8px; font-size: 14px; }
.nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.15); }
.sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
.user-profile { display: flex; align-items: center; gap: 12px; }
.user-avatar { width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.main-content { margin-left: 260px; }
.top-bar { background: white; padding: 16px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); }
.page-title { font-size: 24px; font-weight: 600; }
.page-subtitle { font-size: 14px; color: var(--muted); }
.btn { padding: 10px 20px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
.btn-primary { background: var(--primary); color: white; }
.container { padding: 30px 40px; }
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
.stat-card { background: var(--card); padding: 24px; border-radius: 16px; border: 1px solid var(--border); }
.stat-label { font-size: 13px; color: var(--muted); }
.stat-value { font-size: 28px; font-weight: 700; }
.grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
.card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); }
.card-title { font-size: 16px; font-weight: 600; }
.card-body { padding: 20px 24px; }
.program-item { display: flex; align-items: center; padding: 16px; border-radius: 12px; margin-bottom: 12px; background: #f8fafc; }
.program-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 16px; font-size: 22px; }
.program-info { flex: 1; }
.program-name { font-weight: 600; margin-bottom: 4px; }
.program-desc { font-size: 12px; color: var(--muted); }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d1fae5; color: #059669; }
.action-btn { display: block; width: 100%; padding: 14px; background: var(--primary); color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600; margin-bottom: 10px; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
.table th { font-size: 12px; color: var(--muted); }
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><span>🔧</span> TESDA</div>
        <p class="sidebar-subtitle">Instructional Unit</p>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <p class="nav-section-title">Menu</p>
            <a href="dashboard.php" class="nav-item active"><span>🏠</span> Dashboard</a>
            <a href="#" class="nav-item"><span>📚</span> Programs</a>
            <a href="#" class="nav-item"><span>📋</span> Curriculum</a>
        </div>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">👤</div>
            <div><h4><?= htmlspecialchars($fullName) ?></h4><p style="font-size:12px;opacity:0.7">Instructional Unit</p></div>
        </div>
    </div>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div><h1 class="page-title">Instructional Unit Dashboard</h1><p class="page-subtitle">Curriculum & Program Management</p></div>
        <a href="../logout.php" class="btn btn-primary">Logout</a>
    </div>
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">Training Programs</div><div class="stat-value">4</div></div>
            <div class="stat-card"><div class="stat-label">Instructors</div><div class="stat-value"><?= $instructorCount ?></div></div>
            <div class="stat-card"><div class="stat-label">Modules</div><div class="stat-value">12</div></div>
        </div>
        <div class="grid-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Training Programs</h3></div>
                <div class="card-body">
                    <div class="program-item"><div class="program-icon" style="background:#dbeafe;color:#2563eb">🚗</div><div class="program-info"><div class="program-name">Automotive Mechanic NC I</div><div class="program-desc">8 Competencies • 264 Hours</div></div><span class="badge badge-success">Active</span></div>
                    <div class="program-item"><div class="program-icon" style="background:#d1fae5;color:#10b981">🚙</div><div class="program-info"><div class="program-name">Automotive Mechanic NC II</div><div class="program-desc">6 Competencies • 320 Hours</div></div><span class="badge badge-success">Active</span></div>
                    <div class="program-item"><div class="program-icon" style="background:#fed7aa;color:#f59e0b">🔧</div><div class="program-info"><div class="program-name">Diesel Mechanic</div><div class="program-desc">5 Competencies • 240 Hours</div></div><span class="badge badge-success">Active</span></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Quick Actions</h3></div>
                <div class="card-body">
                    <a href="#" class="action-btn">Add Program</a>
                    <a href="#" class="action-btn">Update Curriculum</a>
                    <a href="#" class="action-btn" style="background:#f1f5f9;color:#1e293b">View Reports</a>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>