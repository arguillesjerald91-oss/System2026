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

if ($userType !== 'support_staff') {
    header("Location: ../login.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

$stmt = $conn->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Support Staff Dashboard - TESDA</title>
<style>
:root { --primary: #2563eb; --primary-dark: #1e40af; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --background: #f1f5f9; --foreground: #1e293b; --card: #ffffff; --muted: #64748b; --border: #e2e8f0; }
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
.ticket-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--border); }
.ticket-item:last-child { border-bottom: none; }
.ticket-info h4 { font-size: 14px; margin-bottom: 4px; }
.ticket-info p { font-size: 12px; color: var(--muted); }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-warning { background: #fed7aa; color: #d97706; }
.badge-danger { background: #fee2e2; color: #dc2626; }
.action-btn { display: block; width: 100%; padding: 14px; background: var(--primary); color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600; margin-bottom: 10px; }
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><span>🔧</span> TESDA</div>
        <p class="sidebar-subtitle">Support Portal</p>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <p class="nav-section-title">Menu</p>
            <a href="dashboard.php" class="nav-item active"><span>🏠</span> Dashboard</a>
            <a href="#" class="nav-item"><span>🎫</span> Tickets</a>
            <a href="#" class="nav-item"><span>👥</span> Users</a>
        </div>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">👤</div>
            <div><h4><?= htmlspecialchars($fullName) ?></h4><p style="font-size:12px;opacity:0.7">Support Staff</p></div>
        </div>
    </div>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div><h1 class="page-title">Support Dashboard</h1><p class="page-subtitle">Administrative & Support Functions</p></div>
        <a href="../logout.php" class="btn btn-primary">Logout</a>
    </div>
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">Total Users</div><div class="stat-value"><?= $totalUsers ?></div></div>
            <div class="stat-card"><div class="stat-label">Open Tickets</div><div class="stat-value">5</div></div>
            <div class="stat-card"><div class="stat-label">Pending Enrollments</div><div class="stat-value">8</div></div>
        </div>
        <div class="grid-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Support Tickets</h3></div>
                <div class="card-body">
                    <div class="ticket-item"><div class="ticket-info"><h4>Login Issues</h4><p>Juan Dela Cruz • 2 hours ago</p></div><span class="badge badge-warning">Pending</span></div>
                    <div class="ticket-item"><div class="ticket-info"><h4>Password Reset</h4><p>Maria Santos • 5 hours ago</p></div><span class="badge badge-success">Resolved</span></div>
                    <div class="ticket-item"><div class="ticket-info"><h4>Account Access</h4><p>Pedro Garcia • 1 day ago</p></div><span class="badge badge-danger">Urgent</span></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Quick Actions</h3></div>
                <div class="card-body">
                    <a href="#" class="action-btn">Create User</a>
                    <a href="#" class="action-btn">Process Enrollment</a>
                    <a href="#" class="action-btn" style="background:#f1f5f9;color:#1e293b">Generate Report</a>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>