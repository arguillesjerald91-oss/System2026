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

// Get current page for active sidebar highlighting
$currentPage = basename(__FILE__);
$conn->exec("CREATE TABLE IF NOT EXISTS quizzes (
    quiz_id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    time_limit INT DEFAULT 30,
    passing_score INT DEFAULT 70,
    nc_level VARCHAR(20) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
)");

$conn->exec("CREATE TABLE IF NOT EXISTS quiz_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('Multiple Choice','True/False','Short Answer','Essay') NOT NULL,
    options JSON,
    correct_answer TEXT,
    points_value DECIMAL(5,2) DEFAULT 1,
    question_order INT DEFAULT 0
)");

$conn->exec("CREATE TABLE IF NOT EXISTS quiz_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    answers JSON,
    score DECIMAL(5,2),
    passed TINYINT(1) DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP()
)");

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_quiz' && ($userType === 'trainer' || $userType === 'instructor' || $userType === 'admin')) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $timeLimit = intval($_POST['time_limit'] ?? 30);
        $passingScore = intval($_POST['passing_score'] ?? 70);
        $ncLevel = $_POST['nc_level'] ?? 'NC I';
        
        if ($title) {
            $stmt = $conn->prepare("INSERT INTO quizzes (title, description, time_limit, passing_score, created_by, nc_level) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $timeLimit, $passingScore, $userId, $ncLevel]);
            $message = 'Quiz created successfully';
            $messageType = 'success';
        }
    }
    
    if ($_POST['action'] === 'add_question' && ($userType === 'trainer' || $userType === 'instructor' || $userType === 'admin')) {
        $quizId = intval($_POST['quiz_id'] ?? 0);
        $questionText = trim($_POST['question_text'] ?? '');
        $questionType = $_POST['question_type'] ?? 'Multiple Choice';
        $options = $_POST['options'] ?? [];
        $correctAnswer = trim($_POST['correct_answer'] ?? '');
        $points = floatval($_POST['points'] ?? 1);
        
        if ($quizId && $questionText) {
            $stmt = $conn->prepare("SELECT MAX(question_order) FROM quiz_questions WHERE quiz_id = ?");
            $stmt->execute([$quizId]);
            $maxOrder = $stmt->fetchColumn() ?: 0;
            
            $stmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer, points_value, question_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$quizId, $questionText, $questionType, json_encode($options), $correctAnswer, $points, $maxOrder + 1]);
            $message = 'Question added';
            $messageType = 'success';
        }
    }
    
    if ($_POST['action'] === 'submit_quiz' && $userType === 'student') {
        $quizId = intval($_POST['quiz_id'] ?? 0);
        $answers = $_POST['answers'] ?? [];
        
        if ($quizId && !empty($answers)) {
            // Calculate score
            $stmt = $conn->prepare("SELECT question_id, correct_answer, points_value FROM quiz_questions WHERE quiz_id = ?");
            $stmt->execute([$quizId]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalPoints = 0;
            $earnedPoints = 0;
            
            foreach ($questions as $q) {
                $totalPoints += $q['points_value'];
                $userAnswer = $answers[$q['question_id']] ?? '';
                if (strtolower(trim($userAnswer)) === strtolower(trim($q['correct_answer']))) {
                    $earnedPoints += $q['points_value'];
                }
            }
            
            $score = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
            $passed = $score >= 70;
            
            $stmt = $conn->prepare("INSERT INTO quiz_attempts (quiz_id, user_id, answers, score, passed) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$quizId, $userId, json_encode($answers), $score, $passed ? 1 : 0]);
            
            $_SESSION['last_score'] = $score;
            $_SESSION['last_passed'] = $passed;
        }
    }
    
    if ($_POST['action'] === 'delete_quiz' && ($userType === 'trainer' || $userType === 'instructor' || $userType === 'admin')) {
        $quizId = intval($_POST['quiz_id'] ?? 0);
        if ($quizId > 0) {
            $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?");
            $stmt->execute([$quizId]);
            $stmt = $conn->prepare("DELETE FROM quiz_attempts WHERE quiz_id = ?");
            $stmt->execute([$quizId]);
            $stmt = $conn->prepare("DELETE FROM quizzes WHERE quiz_id = ?");
            $stmt->execute([$quizId]);
            $message = 'Quiz deleted';
            $messageType = 'success';
        }
    }
}

// Get modules
$stmt = $conn->query("SELECT module_id, module_title FROM learning_modules WHERE is_active = 1 ORDER BY sort_order");
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get quizzes - filter by enrolled modules and NC level for trainees
if ($userType === 'trainee' || $userType === 'student') {
    // Get trainee's NC level
    $ncLevelStmt = $conn->prepare("
        SELECT nc_level 
        FROM student_program_enrollments 
        WHERE student_id = ? AND enrollment_status = 'Active'
        ORDER BY enrollment_id DESC LIMIT 1
    ");
    $ncLevelStmt->execute([$userId]);
    $studentNcLevel = $ncLevelStmt->fetchColumn() ?: 'NC I';
    
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
            SELECT q.*, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
                (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ?) as attempt_count,
                (SELECT score FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ? ORDER BY attempted_at DESC LIMIT 1) as last_score
            FROM quizzes q 
            LEFT JOIN users u ON q.created_by = u.user_id
            WHERE (q.module_id IN ($placeholders) OR q.nc_level = ?)
            ORDER BY q.created_at DESC
        ");
        $params = array_merge($enrolledModuleIds, [$userId, $userId, $studentNcLevel]);
        $stmt->execute($params);
        $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // If no enrolled modules, still show NC level-specific quizzes
        $stmt = $conn->prepare("
            SELECT q.*, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
                (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ?) as attempt_count,
                (SELECT score FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ? ORDER BY attempted_at DESC LIMIT 1) as last_score
            FROM quizzes q 
            LEFT JOIN users u ON q.created_by = u.user_id
            WHERE q.nc_level = ?
            ORDER BY q.created_at DESC
        ");
        $stmt->execute([$userId, $userId, $studentNcLevel]);
        $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Trainers/admins see all quizzes
    $stmt = $conn->query("SELECT q.*, u.first_name, u.last_name, 
        (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
        (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.quiz_id) as attempt_count
        FROM quizzes q 
        LEFT JOIN users u ON q.created_by = u.user_id
        ORDER BY q.created_at DESC");
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get selected quiz with questions
$selectedQuizId = $_GET['quiz_id'] ?? 0;
$selectedQuiz = null;
$quizQuestions = [];

if ($selectedQuizId > 0) {
    $stmt = $conn->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
    $stmt->execute([$selectedQuizId]);
    $selectedQuiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedQuiz) {
        $stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order");
        $stmt->execute([$selectedQuizId]);
        $quizQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get user's last attempt
$userLastAttempt = null;
if ($userType === 'student' && $selectedQuizId > 0) {
    $stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY attempted_at DESC LIMIT 1");
    $stmt->execute([$selectedQuizId, $userId]);
    $userLastAttempt = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Stats
$stmt = $conn->query("SELECT 
    (SELECT COUNT(*) FROM quizzes) as total_quizzes,
    (SELECT COUNT(*) FROM quiz_attempts) as total_attempts,
    (SELECT AVG(score) FROM quiz_attempts) as avg_score");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quizzes - TESDA Auto Mechanic</title>
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
.alert-error { background: #fee2e2; color: #dc2626; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-warning { background: #fed7aa; color: #d97706; }
.badge-blue { background: #dbeafe; color: #2563eb; }
.badge-purple { background: #ede9fe; color: #7c3aed; }
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
.quiz-item { display: flex; align-items: center; padding: 16px; border-radius: 12px; margin-bottom: 12px; background: #f8fafc; cursor: pointer; }
.quiz-info { flex: 1; }
.quiz-title { font-weight: 600; margin-bottom: 4px; }
.quiz-meta { font-size: 12px; color: var(--muted); }
.question-card { padding: 20px; border: 1px solid var(--border); border-radius: 12px; margin-bottom: 16px; }
.question-text { font-weight: 600; margin-bottom: 12px; }
.option-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 8px; }
.option-item input { width: auto; }
.result-box { text-align: center; padding: 40px; }
.result-score { font-size: 64px; font-weight: 700; color: var(--primary); }
.result-label { font-size: 14px; color: var(--muted); margin-top: 8px; }
.empty-state { text-align: center; padding: 40px; color: var(--muted); }
.answer-input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; margin-top: 8px; }
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
            <a href="../student/student_dashboard.php" class="nav-item"><span>🏠</span> Dashboard</a>
            <a href="learning_materials.php" class="nav-item"><span>📚</span> Materials</a>
            <a href="quizzes.php" class="nav-item active"><span>❓</span> Quizzes</a>
            <a href="assignments.php" class="nav-item"><span>📝</span> Assignments</a>
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
        <div><h1 class="page-title"><?= $userType === 'student' ? 'My Quizzes' : 'Quiz Management' ?></h1><p class="page-subtitle"><?= $userType === 'student' ? 'Take quizzes to test your knowledge' : 'Create and manage quizzes' ?></p></div>
        <a href="../logout.php" class="btn btn-outline">Logout</a>
    </div>
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['last_score'])): ?>
        <div class="result-box card">
            <div class="result-score"><?= round($_SESSION['last_score']) ?>%</div>
            <div class="result-label"><?= $_SESSION['last_passed'] ? 'PASSED' : 'FAILED' ?></div>
            <?php unset($_SESSION['last_score'], $_SESSION['last_passed']); ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_quizzes'] ?? 0 ?></div>
                <div class="stat-label">Total Quizzes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_attempts'] ?? 0 ?></div>
                <div class="stat-label">Total Attempts</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= round($stats['avg_score'] ?? 0) ?>%</div>
                <div class="stat-label">Average Score</div>
            </div>
        </div>
        
        <?php if ($selectedQuiz && $userType === 'student'): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= htmlspecialchars($selectedQuiz['title']) ?></h3>
                <a href="quizzes.php" class="btn btn-sm btn-outline">Back</a>
            </div>
            <div class="card-body">
                <?php if ($userLastAttempt): ?>
                <div class="alert alert-<?= $userLastAttempt['passed'] ? 'success' : 'error' ?>">
                    Last attempt: <?= round($userLastAttempt['score']) ?>% - <?= $userLastAttempt['passed'] ? 'PASSED' : 'FAILED' ?>
                </div>
                <?php endif; ?>
                
                <p style="margin-bottom: 20px;"><?= htmlspecialchars($selectedQuiz['description']) ?></p>
                <p><strong>Time Limit:</strong> <?= $selectedQuiz['time_limit'] ?> minutes | <strong>Passing Score:</strong> <?= $selectedQuiz['passing_score'] ?>%</p>
                
                <?php if (empty($quizQuestions)): ?>
                <div class="empty-state">No questions in this quiz yet.</div>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="submit_quiz">
                    <input type="hidden" name="quiz_id" value="<?= $selectedQuizId ?>">
                    
                    <?php foreach ($quizQuestions as $idx => $q): ?>
                    <div class="question-card">
                        <div class="question-text"><?= $idx + 1 ?>. <?= htmlspecialchars($q['question_text']) ?> (<?= $q['points_value'] ?> pts)</div>
                        
                        <?php if ($q['question_type'] === 'Multiple Choice'): ?>
                        <?php $options = json_decode($q['options'] ?? '[]', true) ?: ['A', 'B', 'C', 'D']; ?>
                        <?php foreach ($options as $opt): ?>
                        <label class="option-item">
                            <input type="radio" name="answers[<?= $q['question_id'] ?>]" value="<?= $opt ?>">
                            <?= $opt ?>
                        </label>
                        <?php endforeach; ?>
                        <?php elseif ($q['question_type'] === 'True/False'): ?>
                        <label class="option-item"><input type="radio" name="answers[<?= $q['question_id'] ?>]" value="True"> True</label>
                        <label class="option-item"><input type="radio" name="answers[<?= $q['question_id'] ?>]" value="False"> False</label>
                        <?php else: ?>
                        <input type="text" name="answers[<?= $q['question_id'] ?>]" class="answer-input" placeholder="Your answer">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <button type="submit" class="btn btn-primary">Submit Quiz</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <?php elseif ($selectedQuiz && ($userType === 'trainer' || $userType === 'instructor' || $userType === 'admin')): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= htmlspecialchars($selectedQuiz['title']) ?></h3>
                <div>
                    <button class="btn btn-sm btn-primary" onclick="openModal('addQuestionModal')">+ Add Question</button>
                    <a href="quizzes.php" class="btn btn-sm btn-outline">Back</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($quizQuestions)): ?>
                <div class="empty-state">No questions yet. Add questions to this quiz.</div>
                <?php else: ?>
                <table class="table">
                    <thead><tr><th>#</th><th>Question</th><th>Type</th><th>Points</th><th>Answer</th></tr></thead>
                    <tbody>
                        <?php foreach ($quizQuestions as $q): ?>
                        <tr>
                            <td><?= $q['question_order'] ?></td>
                            <td><?= htmlspecialchars($q['question_text']) ?></td>
                            <td><span class="badge badge-blue"><?= $q['question_type'] ?></span></td>
                            <td><?= $q['points_value'] ?></td>
                            <td><?= htmlspecialchars($q['correct_answer'] ?? '-') ?></td>
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
                <h3 class="card-title">All Quizzes</h3>
                <?php if ($userType === 'trainer' || $userType === 'instructor' || $userType === 'admin'): ?>
                <button class="btn btn-sm btn-primary" onclick="openModal('createQuizModal')">+ Create Quiz</button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($quizzes)): ?>
                <div class="empty-state">No quizzes created yet.</div>
                <?php else: ?>
                <?php foreach ($quizzes as $quiz): ?>
                <a href="?quiz_id=<?= $quiz['quiz_id'] ?>" class="quiz-item" style="display: block; text-decoration: none; color: inherit;">
                    <div class="quiz-info">
                        <div class="quiz-title"><?= htmlspecialchars($quiz['title']) ?></div>
                        <div class="quiz-meta"><?= $quiz['question_count'] ?> questions • <?= $quiz['time_limit'] ?> mins • Pass: <?= $quiz['passing_score'] ?>% • <?= $quiz['attempt_count'] ?> attempts • <span class="badge badge-purple"><?= $quiz['nc_level'] ?? 'NC I' ?></span></div>
                    </div>
                    <span class="badge badge-<?= $quiz['question_count'] > 0 ? 'success' : 'warning' ?>"><?= $quiz['question_count'] > 0 ? 'Ready' : 'Empty' ?></span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Create Quiz Modal -->
<div id="createQuizModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Create New Quiz</h3>
            <button class="modal-close" onclick="closeModal('createQuizModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create_quiz">
                <div class="form-group">
                    <label>Quiz Title *</label>
                    <input type="text" name="title" required placeholder="Enter quiz title">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Quiz description"></textarea>
                </div>
                <div class="form-group">
                    <label>Time Limit (minutes)</label>
                    <input type="number" name="time_limit" value="30" min="5" max="180">
                </div>
                <div class="form-group">
                    <label>Passing Score (%)</label>
                    <input type="number" name="passing_score" value="70" min="50" max="100">
                </div>
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
                <button type="button" class="btn btn-outline" onclick="closeModal('createQuizModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Quiz</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Question Modal -->
<div id="addQuestionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add Question</h3>
            <button class="modal-close" onclick="closeModal('addQuestionModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_question">
                <input type="hidden" name="quiz_id" value="<?= $selectedQuizId ?>">
                <div class="form-group">
                    <label>Question Text *</label>
                    <textarea name="question_text" rows="2" required placeholder="Enter question"></textarea>
                </div>
                <div class="form-group">
                    <label>Question Type</label>
                    <select name="question_type" onchange="toggleOptions()">
                        <option value="Multiple Choice">Multiple Choice</option>
                        <option value="True/False">True/False</option>
                        <option value="Short Answer">Short Answer</option>
                        <option value="Essay">Essay</option>
                    </select>
                </div>
                <div class="form-group" id="optionsGroup">
                    <label>Options (comma separated)</label>
                    <input type="text" name="options" placeholder="A,B,C,D">
                </div>
                <div class="form-group">
                    <label>Correct Answer *</label>
                    <input type="text" name="correct_answer" required placeholder="Correct answer">
                </div>
                <div class="form-group">
                    <label>Points</label>
                    <input type="number" name="points" value="1" min="1" step="0.5">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addQuestionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Question</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function toggleOptions() {
    const type = document.querySelector('[name="question_type"]').value;
    document.getElementById('optionsGroup').style.display = (type === 'Multiple Choice') ? 'block' : 'none';
}
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>
</body>
</html>