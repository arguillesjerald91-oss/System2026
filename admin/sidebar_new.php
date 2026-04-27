<?php
/**
 * Consistent Sidebar for All Admin Pages
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
$userName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if (empty($userName)) $userName = 'Admin';
?>
<style>
* { box-sizing: border-box; }
:root {
    --primary-dark: #1e40af;
    --primary: #2563eb;
    --accent: #60a5fa;
    --bg: #f1f5f9;
    --fg: #1e293b;
    --muted: #64748b;
    --border: #e2e8f0;
    --white: #ffffff;
}
body { margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }

/* Sidebar Styles */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 260px;
    height: 100vh;
    background: linear-gradient(180deg, var(--primary-dark) 0%, #1e3a8a 100%);
    color: var(--white);
    display: flex;
    flex-direction: column;
    z-index: 1000;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}
.sidebar-header {
    padding: 24px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 18px;
    font-weight: 700;
}
.sidebar-logo span { font-size: 28px; }
.sidebar-subtitle {
    font-size: 11px;
    opacity: 0.7;
    margin-top: 4px;
}
.sidebar-nav {
    flex: 1;
    padding: 20px 0;
    overflow-y: auto;
}
.nav-section {
    padding: 0 12px;
    margin-bottom: 24px;
}
.nav-section-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.6;
    padding: 0 12px;
    margin-bottom: 10px;
}
.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 10px;
    color: var(--white);
    text-decoration: none;
    margin: 4px 8px;
    font-size: 14px;
    transition: all 0.2s;
}
.nav-item:hover {
    background: rgba(255,255,255,0.15);
}
.nav-item.active {
    background: rgba(255,255,255,0.2);
    border-left: 3px solid var(--accent);
}
.nav-item span:first-child {
    font-size: 18px;
    width: 24px;
    text-align: center;
}
.sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
}
.user-profile {
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-avatar {
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.user-info h4 {
    font-size: 14px;
    font-weight: 600;
    margin: 0;
}
.user-info p {
    font-size: 12px;
    opacity: 0.7;
    margin: 0;
    text-transform: capitalize;
}

/* Main Content Wrapper */
.main-wrapper {
    display: flex;
    min-height: 100vh;
}
.main-content {
    margin-left: 260px;
    flex: 1;
    background: var(--bg);
    min-height: 100vh;
}

/* Page Header */
.page-header {
    background: var(--white);
    padding: 16px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 50;
}
.page-title-section h1 {
    font-size: 24px;
    font-weight: 600;
    color: var(--fg);
    margin: 0;
}
.page-title-section p {
    font-size: 14px;
    color: var(--muted);
    margin: 4px 0 0 0;
}
.header-actions {
    display: flex;
    gap: 12px;
}
.btn {
    padding: 10px 20px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-primary {
    background: var(--primary);
    color: var(--white);
}
.btn-outline {
    background: var(--white);
    border: 1px solid var(--border);
    color: var(--fg);
}
.btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

/* Page Content */
.page-content {
    padding: 30px 40px;
}

/* Common Components */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: var(--white);
    padding: 24px;
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.stat-label {
    font-size: 13px;
    color: var(--muted);
    font-weight: 500;
    margin-bottom: 8px;
}
.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--fg);
}

.card {
    background: var(--white);
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
}
.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--fg);
}
.card-body {
    padding: 20px 24px;
}

.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.badge-blue { background: #dbeafe; color: #2563eb; }
.badge-green { background: #d1fae5; color: #059669; }
.badge-orange { background: #fed7aa; color: #d97706; }
.badge-red { background: #fee2e2; color: #dc2626; }
.badge-purple { background: #ede9fe; color: #7c3aed; }
</style>

<div class="main-wrapper">
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <span>🔧</span> TESDA
        </div>
        <p class="sidebar-subtitle">Auto Mechanic Training</p>
    </div>
    
    <nav class="sidebar-nav">
        <?php if (in_array($userType, ['admin'])): ?>
        <div class="nav-section">
            <p class="nav-section-title">Dashboard</p>
            <a href="admin_dashboard.php" class="nav-item <?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>">
                <span>📊</span> Dashboard
            </a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Modules</p>
            <a href="pre_enrollment_management.php" class="nav-item <?= $currentPage === 'pre_enrollment_management.php' ? 'active' : '' ?>">
                <span>📝</span> Pre-Enrollment
            </a>
            <a href="scholarship_qualification.php" class="nav-item <?= $currentPage === 'scholarship_qualification.php' ? 'active' : '' ?>">
                <span>🎓</span> Scholarship
            </a>
            <a href="competency_evaluation.php" class="nav-item <?= $currentPage === 'competency_evaluation.php' ? 'active' : '' ?>">
                <span>📊</span> Competency
            </a>
            <a href="lms_modules.php" class="nav-item <?= $currentPage === 'lms_modules.php' ? 'active' : '' ?>">
                <span>📚</span> LMS
            </a>
            <a href="tracer_survey.php" class="nav-item <?= $currentPage === 'tracer_survey.php' ? 'active' : '' ?>">
                <span>📋</span> TRACER Survey
            </a>
            <a href="reports_analytics.php" class="nav-item <?= $currentPage === 'reports_analytics.php' ? 'active' : '' ?>">
                <span>📈</span> Reports
            </a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Management</p>
            <a href="manage_applicants.php" class="nav-item <?= $currentPage === 'manage_applicants.php' ? 'active' : '' ?>">
                <span>📋</span> Applicants
            </a>
            <a href="manage_students.php" class="nav-item <?= $currentPage === 'manage_students.php' ? 'active' : '' ?>">
                <span>👥</span> Users
            </a>
            <a href="manage_transcripts.php" class="nav-item <?= $currentPage === 'manage_transcripts.php' ? 'active' : '' ?>">
                <span>📄</span> Transcripts (TOR)
            </a>
            <a href="manage_certificates.php" class="nav-item <?= $currentPage === 'manage_certificates.php' ? 'active' : '' ?>">
                <span>🏆</span> Certificates
            </a>
            <a href="manage_diplomas.php" class="nav-item <?= $currentPage === 'manage_diplomas.php' ? 'active' : '' ?>">
                <span>🎓</span> Diplomas
            </a>
            <a href="manage_documents.php" class="nav-item <?= $currentPage === 'manage_documents.php' ? 'active' : '' ?>">
                <span>📁</span> Document Repository
            </a>
            <a href="reports_documents.php" class="nav-item <?= $currentPage === 'reports_documents.php' ? 'active' : '' ?>">
                <span>📈</span> Reports & Analytics
            </a>
            <a href="../index.php" class="nav-item">
                <span>🌐</span> Public Portal
            </a>
        </div>
        
        <?php elseif (in_array($userType, ['support_staff'])): ?>
        <div class="nav-section">
            <p class="nav-section-title">Dashboard</p>
            <a href="admin_dashboard.php" class="nav-item <?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>">
                <span>📊</span> Dashboard
            </a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Operations</p>
            <a href="pre_enrollment_management.php" class="nav-item <?= $currentPage === 'pre_enrollment_management.php' ? 'active' : '' ?>">
                <span>📝</span> Pre-Enrollment
            </a>
            <a href="scholarship_qualification.php" class="nav-item <?= $currentPage === 'scholarship_qualification.php' ? 'active' : '' ?>">
                <span>🎓</span> Scholarship
            </a>
            <a href="manage_applicants.php" class="nav-item <?= $currentPage === 'manage_applicants.php' ? 'active' : '' ?>">
                <span>📋</span> Applicants
            </a>
            <a href="staff_document_requests.php" class="nav-item <?= $currentPage === 'staff_document_requests.php' ? 'active' : '' ?>">
                <span>📂</span> Document Requests
            </a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Quick Links</p>
            <a href="../student/trainee_portal.php" class="nav-item" target="_blank">
                <span>📚</span> Trainee Portal
            </a>
            <a href="../index.php" class="nav-item">
                <span>🌐</span> Public Portal
            </a>
        </div>
        
        <?php elseif (in_array($userType, ['instructional_unit'])): ?>
        <div class="nav-section">
            <p class="nav-section-title">Dashboard</p>
            <a href="admin_dashboard.php" class="nav-item <?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>">
                <span>📊</span> Dashboard
            </a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Instruction</p>
            <a href="competency_evaluation.php" class="nav-item <?= $currentPage === 'competency_evaluation.php' ? 'active' : '' ?>">
                <span>📊</span> Competency
            </a>
            <a href="lms_modules.php" class="nav-item <?= $currentPage === 'lms_modules.php' ? 'active' : '' ?>">
                <span>📚</span> LMS
            </a>
            <a href="reports_analytics.php" class="nav-item <?= $currentPage === 'reports_analytics.php' ? 'active' : '' ?>">
                <span>📈</span> Reports
            </a>
            <a href="manage_certificates.php" class="nav-item <?= $currentPage === 'manage_certificates.php' ? 'active' : '' ?>">
                <span>🏆</span> Certificates
            </a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Resources</p>
            <a href="../instructor/quizzes.php" class="nav-item" target="_blank">
                <span>❓</span> Quizzes
            </a>
            <a href="../instructor/assignments.php" class="nav-item" target="_blank">
                <span>📝</span> Assignments
            </a>
        </div>
        
        <?php elseif (in_array($userType, ['instructor'])): ?>
        <div class="nav-section">
            <p class="nav-section-title">My Dashboard</p>
            <a href="instructor_dashboard_new.php" class="nav-item <?= $currentPage === 'instructor_dashboard_new.php' ? 'active' : '' ?>">
                <span>📊</span> Dashboard
            </a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Teaching</p>
            <a href="my_modules.php" class="nav-item <?= $currentPage === 'my_modules.php' ? 'active' : '' ?>">
                <span>📚</span> My Modules
            </a>
            <a href="my_students.php" class="nav-item <?= $currentPage === 'my_students.php' ? 'active' : '' ?>">
                <span>👥</span> My Students
            </a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Content</p>
            <a href="quizzes.php" class="nav-item <?= $currentPage === 'quizzes.php' ? 'active' : '' ?>">
                <span>❓</span> Quizzes
            </a>
            <a href="assignments.php" class="nav-item <?= $currentPage === 'assignments.php' ? 'active' : '' ?>">
                <span>📝</span> Assignments
            </a>
            <a href="learning_materials.php" class="nav-item <?= $currentPage === 'learning_materials.php' ? 'active' : '' ?>">
                <span>📄</span> Materials
            </a>
        </div>
        
        <?php else: ?>
        <div class="nav-section">
            <p class="nav-section-title">Dashboard</p>
            <a href="admin_dashboard.php" class="nav-item <?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>">
                <span>📊</span> Dashboard
            </a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Training</p>
            <a href="lms_modules.php" class="nav-item <?= $currentPage === 'lms_modules.php' ? 'active' : '' ?>">
                <span>📚</span> LMS
            </a>
            <a href="reports_analytics.php" class="nav-item <?= $currentPage === 'reports_analytics.php' ? 'active' : '' ?>">
                <span>📈</span> Reports
            </a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Management</p>
            <a href="manage_students.php" class="nav-item <?= $currentPage === 'manage_students.php' ? 'active' : '' ?>">
                <span>👥</span> Users
            </a>
        </div>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">👤</div>
            <div class="user-info">
                <h4><?= htmlspecialchars($userName) ?></h4>
                <p><?= htmlspecialchars($userType ?: 'Admin') ?></p>
            </div>
        </div>
    </div>
</aside>

<main class="main-content">
    <div class="page-header">
        <div class="page-title-section">
            <h1><?= $pageTitle ?? 'Dashboard' ?></h1>
            <p><?= $pageSubtitle ?? 'TESDA Auto Mechanic Training Centre' ?></p>
        </div>
        <div class="header-actions">
            <button class="btn btn-outline">🔔</button>
            <a href="../logout.php" class="btn btn-primary">Logout</a>
        </div>
    </div>
    
    <div class="page-content">
<!-- Content -->