<?php
/**
 * Trainee Sidebar - Consistent Design
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
$userId = $_SESSION['user_id'] ?? $_SESSION['userId'] ?? null;
$userName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if (empty($userName)) $userName = 'Trainee';

// Normalize role for backward compatibility
$userType = ($userType === 'student') ? 'trainee' : $userType;

// Check enrollment status
$isEnrolled = false;
if ($userId) {
    try {
        include __DIR__ . '/db.php';
        $database = new Database();
        $conn = $database->getConnection();
        if ($conn !== null) {
            $stmt = $conn->prepare("
                SELECT 1 FROM student_program_enrollments 
                WHERE student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1) 
                AND enrollment_status = 'Active' 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $isEnrolled = (bool)$stmt->fetchColumn();
        }
    } catch (Exception $e) {
        $isEnrolled = false;
    }
}
?>
<link rel="stylesheet" href="../css/unified-portal.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
/* Reset and visibility */
* { visibility: visible !important; opacity: 1 !important; }
html, body { visibility: visible; opacity: 1; width: 100%; height: 100%; margin: 0; padding: 0; background: #f1f5f9; }
.portal-container { visibility: visible; opacity: 1; display: flex; width: 100%; min-height: 100vh; margin: 0; padding: 0; position: relative; }
/* Sidebar */
aside.sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 260px; background: linear-gradient(180deg, #1e40af, #1e3a8a); z-index: 1000; }
.sidebar-logo span { font-size: 28px; }
.nav-item i { width: 20px; text-align: center; }
/* Main content */
main.main { margin-left: 260px; flex: 1; min-width: 0; background: #f1f5f9; min-height: 100vh; width: calc(100% - 260px); position: relative; display: block; }
/* Page header */
.page-header { padding: 16px 20px; background: white; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
/* Page content - IMPORTANT for visibility */
.page-content { 
    visibility: visible; 
    opacity: 1; 
    display: block; 
    padding: 20px 25px; 
    width: 100%; 
    box-sizing: border-box; 
    position: relative; 
    z-index: 1; 
}
/* Content header */
.content-header {
    text-align: center;
    padding: 20px;
    margin-bottom: 30px;
}
.content-header h2 {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 8px;
}
/* Stats grid - full width */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
    width: 100%;
}
/* Container for centering */
.container {
    max-width: 100%;
    margin: 0;
    padding: 20px;
}
.container h2, .container h3 {
    text-align: center;
}
/* Card styling */
.card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    margin-bottom: 20px;
    padding: 20px;
}
/* Table styling */
table {
    width: 100%;
    border-collapse: collapse;
    margin: 0 auto;
}
table th, table td {
    text-align: center;
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
}
table th { background: #f8fafc; font-weight: 600; color: #374151; }
table tr:hover { background: #f8fafc; }
.stats-grid .stat-card {
    text-align: center;
    padding: 24px;
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
}
/* Stats grid */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; width: 100%; }
/* Enhanced buttons */
.btn { display: inline-block; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.2s ease; }
.btn-primary { background: #2563eb; color: white; }
.btn-primary:hover { background: #1d4ed8; }
.btn-success { background: #10b981; color: white; }
.btn-success:hover { background: #059669; }
.btn-warning { background: #f59e0b; color: white; }
.btn-warning:hover { background: #d97706; }
/* Card hover effects */
.card { transition: all 0.3s ease; }
.card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
/* Badge styles */
.badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.badge-primary { background: #dbeafe; color: #2563eb; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-warning { background: #fef3c7; color: #d97706; }
.badge-danger { background: #fee2e2; color: #dc2626; }
.stat-card { padding: 24px; background: white; border-radius: 16px; border: 1px solid #e2e8f0; }
/* Welcome banner */
.welcome-banner { padding: 40px; border-radius: 20px; color: white; margin-bottom: 30px; position: relative; }
.welcome-banner h2 { font-size: 32px; margin-bottom: 8px; }
.welcome-banner .subtitle { opacity: 0.9; font-size: 18px; }
.welcome-banner .meta { opacity: 0.8; font-size: 14px; margin-top: 8px; }
/* Grid layouts */
.grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; width: 100%; }
.card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 20px; }
</style>
<style>
.card-title { font-size: 16px; font-weight: 600; }
.card-body { padding: 20px 24px; }
.module-item { display: flex; align-items: center; padding: 14px; border-radius: 12px; margin-bottom: 12px; background: #f8fafc; }
.module-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 14px; font-size: 20px; }
.module-info { flex: 1; }
.module-name { font-weight: 600; margin-bottom: 4px; }
.module-desc { font-size: 12px; color: var(--muted); }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-blue { background: #dbeafe; color: #2563eb; }
.badge-green { background: #d1fae5; color: #059669; }
.badge-red { background: #fee2e2; color: #dc2626; }
.grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.action-btn { display: block; padding: 14px; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600; }
.welcome-banner { padding: 40px; border-radius: 20px; color: white; margin-bottom: 30px; position: relative; overflow: hidden; }
.welcome-banner h2 { font-size: 32px; margin-bottom: 8px; }
.welcome-banner .subtitle { opacity: 0.9; font-size: 18px; }
.welcome-banner .meta { opacity: 0.8; font-size: 14px; margin-top: 8px; }
.stat-sub { font-size: 12px; color: var(--muted); margin-top: 4px; }
.quick-access { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
.bg-primary { background: linear-gradient(135deg, #1e40af, #2563eb); visibility: visible; opacity: 1; }
.bg-success { background: linear-gradient(135deg, #059669, #10b981); visibility: visible; opacity: 1; }
.bg-purple { background: linear-gradient(135deg, #7c3aed, #a855f7); visibility: visible; opacity: 1; }
</style>

<div class="portal-container">
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><span>&#128295;</span> TESDA</div>
        <p class="sidebar-subtitle">Trainee Portal</p>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <p class="nav-section-title">Dashboard</p>
            <a href="trainee_portal.php" class="nav-item <?= $currentPage === 'trainee_portal.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> Trainee Portal</a>
            <a href="student_dashboard.php" class="nav-item <?= $currentPage === 'student_dashboard.php' ? 'active' : '' ?>"><i class="fas fa-chart-line"></i> Overview</a>
            <a href="my_application.php" class="nav-item <?= $currentPage === 'my_application.php' ? 'active' : '' ?>"><i class="fas fa-file-alt"></i> My Application</a>
        </div>
        <div class="nav-section">
            <p class="nav-section-title">Learning</p>
            <a href="learning_modules.php" class="nav-item <?= in_array($currentPage, ['learning_modules.php','module_lesson.php']) ? 'active' : '' ?>"><i class="fas fa-book"></i> My Modules</a>
            <a href="../instructor/learning_materials.php" class="nav-item"><i class="fas fa-folder-open"></i> Materials</a>
            <a href="../instructor/quizzes.php" class="nav-item"><i class="fas fa-question-circle"></i> Quizzes</a>
            <a href="take_quiz.php" class="nav-item"><i class="fas fa-pen"></i> Take Quiz</a>
            <a href="../instructor/assignments.php" class="nav-item"><i class="fas fa-file-alt"></i> Assignments</a>
            <a href="upload_competency.php" class="nav-item"><i class="fas fa-upload"></i> Upload Competency</a>
            <a href="notices.php" class="nav-item <?= $currentPage == 'notices.php' ? 'active' : '' ?>"><i class="fas fa-bell"></i> Notifications</a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Assessments</p>
            <a href="my_assessments.php" class="nav-item <?= $currentPage == 'my_assessments.php' ? 'active' : '' ?>"><i class="fas fa-clipboard-check"></i> My Assessments</a>
            <a href="my_competencies.php" class="nav-item <?= $currentPage == 'my_competencies.php' ? 'active' : '' ?>"><i class="fas fa-tasks"></i> Competencies</a>
            <a href="my_grades.php" class="nav-item <?= $currentPage == 'my_grades.php' ? 'active' : '' ?>"><i class="fas fa-star"></i> Grades</a>
        </div>
        <div class="nav-section">
            <p class="nav-section-title">Assessments</p>
            <a href="my_assessments.php" class="nav-item <?= $currentPage === 'my_assessments.php' ? 'active' : '' ?>"><i class="fas fa-clipboard-check"></i> My Assessments</a>
            <a href="my_competencies.php" class="nav-item <?= $currentPage == 'my_competencies.php' ? 'active' : '' ?>"><i class="fas fa-tasks"></i> Competencies</a>
            <a href="my_grades.php" class="nav-item <?= $currentPage == 'my_grades.php' ? 'active' : '' ?>"><i class="fas fa-star"></i> Grades</a>
        </div>
        <div class="nav-section">
            <p class="nav-section-title">Personal Records</p>
            <?php if ($isEnrolled): ?>
            <a href="transcripts.php" class="nav-item <?= $currentPage === 'transcripts.php' ? 'active' : '' ?>"><i class="fas fa-file"></i> Transcripts</a>
            <a href="certificates.php" class="nav-item <?= $currentPage === 'certificates.php' ? 'active' : '' ?>"><i class="fas fa-certificate"></i> Certificates</a>
            <a href="diplomas.php" class="nav-item <?= $currentPage === 'diplomas.php' ? 'active' : '' ?>"><i class="fas fa-graduation-cap"></i> Diplomas</a>
            <a href="documents.php" class="nav-item <?= $currentPage === 'documents.php' ? 'active' : '' ?>"><i class="fas fa-folder-open"></i> Documents</a>
            <a href="request_document.php" class="nav-item <?= $currentPage === 'request_document.php' ? 'active' : '' ?>"><i class="fas fa-paper-plane"></i> Request Document</a>
            <?php else: ?>
            <a href="my_application.php" class="nav-item"><i class="fas fa-file"></i> Transcripts <span style="background:#ef4444;color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:auto;">Locked</span></a>
            <a href="my_application.php" class="nav-item"><i class="fas fa-certificate"></i> Certificates <span style="background:#ef4444;color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:auto;">Locked</span></a>
            <a href="my_application.php" class="nav-item"><i class="fas fa-graduation-cap"></i> Diplomas <span style="background:#ef4444;color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:auto;">Locked</span></a>
            <a href="my_application.php" class="nav-item"><i class="fas fa-folder-open"></i> Documents <span style="background:#ef4444;color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:auto;">Locked</span></a>
            <a href="my_application.php" class="nav-item"><i class="fas fa-paper-plane"></i> Request Document <span style="background:#ef4444;color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:auto;">Locked</span></a>
            <?php endif; ?>
        </div>
        <div class="nav-section">
            <p class="nav-section-title">Account</p>
            <a href="schedule_new.php" class="nav-item <?= $currentPage == 'schedule_new.php' ? 'active' : '' ?>"><i class="fas fa-calendar"></i> Schedule</a>
            <a href="notices.php" class="nav-item <?= $currentPage == 'notices.php' ? 'active' : '' ?>"><i class="fas fa-bell"></i> Notices</a>
            <a href="payment.php" class="nav-item <?= $currentPage == 'payment.php' ? 'active' : '' ?>"><i class="fas fa-money-bill"></i> Payment</a>
            <a href="change_password.php" class="nav-item <?= $currentPage == 'change_password.php' ? 'active' : '' ?>"><i class="fas fa-key"></i> Change Password</a>
        </div>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar"><i class="fas fa-user"></i></div>
            <div class="user-info">
                <h4><?= htmlspecialchars($userName) ?></h4>
                <p><?= htmlspecialchars(ucfirst($userType)) ?></p>
            </div>
        </div>
    </div>
</aside>

<main class="main">
    <div class="page-header">
        <div class="page-title-section">
            <h1><?= $pageTitle ?? 'Dashboard' ?></h1>
            <p><?= $pageSubtitle ?? 'Student Portal' ?></p>
        </div>
        <div class="header-actions">
            <a href="../logout.php" class="btn btn-primary">Logout</a>
        </div>
    </div>
    
    <div class="page-content">