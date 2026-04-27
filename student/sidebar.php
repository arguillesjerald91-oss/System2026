<?php
/**
 * Trainee Portal Sidebar - Consistent Design with Enrollment-Based Permissions
 */
$currentPage = basename($_SERVER['PHP_SELF']); 
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? 'trainee';
$userName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if (empty($userName)) $userName = 'Trainee';
$userType = ($userType === 'student') ? 'trainee' : $userType;

// Check enrollment status
$userId = $_SESSION['userId'] ?? null;
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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
:root { --primary: #2563eb; --primary-dark: #1e40af; --bg: #f1f5f9; --fg: #1e293b; --card: #ffffff; --muted: #64748b; --border: #e2e8f0; }
body { margin: 0; padding: 0; font-family: 'Segoe UI', -apple-system, sans-serif; background: var(--bg); color: var(--fg); }
.sidebar { position: fixed; left: 0; top: 0; width: 260px; height: 100vh; background: linear-gradient(180deg, var(--primary-dark), #1e3a8a); color: white; display: flex; flex-direction: column; z-index: 1000; overflow-y: auto; }
.sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.sidebar-logo { display: flex; align-items: center; gap: 12px; font-size: 18px; font-weight: 700; }
.sidebar-logo span { font-size: 28px; }
.sidebar-subtitle { font-size: 11px; opacity: 0.7; margin-top: 4px; }
.sidebar-nav { flex: 1; padding: 20px 0; }
.nav-section { padding: 0 12px; margin-bottom: 24px; }
.nav-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.6; padding: 0 12px; margin-bottom: 10px; }
.nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px; color: white; text-decoration: none; margin: 4px 8px; font-size: 14px; transition: 0.2s; }
.nav-item:hover { background: rgba(255,255,255,0.15); }
.nav-item.active { background: rgba(255,255,255,0.2); border-left: 3px solid #60a5fa; }
.nav-item.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
.nav-item.locked { opacity: 0.6; cursor: pointer; }
.nav-item .badge { margin-left: auto; background: #ef4444; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px; }
.nav-item span.icon { font-size: 18px; width: 24px; text-align: center; }
.sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
.user-profile { display: flex; align-items: center; gap: 12px; }
.user-avatar { width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.user-info h4 { font-size: 14px; font-weight: 600; margin: 0; }
.user-info p { font-size: 12px; opacity: 0.7; margin: 0; }
.enrollment-notice { margin: 8px 12px; padding: 10px 12px; background: rgba(239, 68, 68, 0.2); border-radius: 8px; font-size: 12px; border: 1px solid rgba(239, 68, 68, 0.4); }
.enrollment-notice a { color: #fca5a5; text-decoration: underline; }
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><span>🔧</span> TESDA</div>
        <p class="sidebar-subtitle">Trainee Portal</p>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <p class="nav-section-title">Dashboard</p>
            <a href="student_dashboard.php" class="nav-item <?= ($currentPage == 'student_dashboard.php') ? 'active' : '' ?>"><span class="icon">🏠</span> Overview</a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Learning</p>
            <a href="learning_modules.php" class="nav-item <?= ($currentPage == 'learning_modules.php' || $currentPage == 'module_lesson.php') ? 'active' : '' ?>"><span class="icon">📚</span> My Modules</a>
            <a href="../instructor/learning_materials.php" class="nav-item <?= ($currentPage == 'learning_materials.php') ? 'active' : '' ?>"><span class="icon">📂</span> Materials</a>
            <a href="../instructor/quizzes.php" class="nav-item <?= ($currentPage == 'quizzes.php') ? 'active' : '' ?>"><span class="icon">❓</span> Quizzes</a>
            <a href="take_quiz.php" class="nav-item <?= ($currentPage == 'take_quiz.php') ? 'active' : '' ?>"><span class="icon">✏️</span> Take Quiz</a>
            <a href="../instructor/assignments.php" class="nav-item <?= ($currentPage == 'assignments.php') ? 'active' : '' ?>"><span class="icon">📝</span> Assignments</a>
            <a href="upload_competency.php" class="nav-item <?= ($currentPage == 'upload_competency.php') ? 'active' : '' ?>"><span class="icon">📤</span> Upload Competency</a>
            <a href="my_competencies.php" class="nav-item <?= ($currentPage == 'my_competencies.php') ? 'active' : '' ?>"><span class="icon">📋</span> Assessments</a>
            <a href="my_grades.php" class="nav-item <?= ($currentPage == 'my_grades.php') ? 'active' : '' ?>"><span class="icon">📊</span> Grades</a>
            <a href="notices.php" class="nav-item <?= ($currentPage == 'notices.php') ? 'active' : '' ?>"><span class="icon">🔔</span> Notifications</a>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Documents</p>
            <?php if ($isEnrolled): ?>
            <a href="documents.php" class="nav-item <?= ($currentPage == 'documents.php') ? 'active' : '' ?>"><span class="icon">📄</span> My Documents</a>
            <a href="request_document.php" class="nav-item <?= ($currentPage == 'request_document.php') ? 'active' : '' ?>"><span class="icon">📝</span> Request Document</a>
            <a href="transcripts.php" class="nav-item <?= ($currentPage == 'transcripts.php') ? 'active' : '' ?>"><span class="icon">🎓</span> Form 137</a>
            <?php else: ?>
            <a href="my_application.php" class="nav-item locked"><span class="icon">📄</span> My Documents <span class="badge">Locked</span></a>
            <a href="my_application.php" class="nav-item locked"><span class="icon">📝</span> Request Document <span class="badge">Locked</span></a>
            <a href="my_application.php" class="nav-item locked"><span class="icon">🎓</span> Form 137 <span class="badge">Locked</span></a>
            <?php endif; ?>
        </div>
        
        <div class="nav-section">
            <p class="nav-section-title">Other</p>
            <a href="schedule.php" class="nav-item <?= ($currentPage == 'schedule.php') ? 'active' : '' ?>"><span class="icon">📅</span> Schedule</a>
            <a href="result.php" class="nav-item <?= ($currentPage == 'result.php') ? 'active' : '' ?>"><span class="icon">📈</span> Results</a>
            <a href="notices.php" class="nav-item <?= ($currentPage == 'notices.php') ? 'active' : '' ?>"><span class="icon">🔔</span> Notices</a>
        </div>
        
        <?php if (!$isEnrolled): ?>
        <div class="nav-section">
            <div class="enrollment-notice">
                <strong>Not Enrolled?</strong><br>
                Complete your enrollment to access documents and grades.<br>
                <a href="my_application.php">View Application Status</a>
            </div>
        </div>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">👤</div>
            <div class="user-info">
                <h4><?= htmlspecialchars($userName) ?></h4>
                <p><?= htmlspecialchars(ucfirst($userType)) ?></p>
                <?php if ($isEnrolled): ?>
                <p style="color: #86efac; font-size: 11px;">✓ Enrolled</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</aside>