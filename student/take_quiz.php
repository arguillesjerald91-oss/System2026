<?php
/**
 * Advanced Take Quiz Page - Enhanced Features for Enrolled Trainees
 */
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
$userType = ($userType === 'student') ? 'trainee' : $userType;
$userName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));

if (!in_array($userType, ['trainee', 'student'])) {
    header("Location: ../login.php");
    exit();
}

$message = '';
$messageType = '';

// Get student's NC level from enrollment (set by admin/staff)
$studentNcLevel = 'NC I';
$enrollment = null;
$isEnrolled = false;
if ($conn !== null) {
    try {
        // First get the student ID from user_id
        $findStudStmt = $conn->prepare("SELECT StudID FROM student WHERE user_id = ? LIMIT 1");
        $findStudStmt->execute([$userId]);
        $studID = $findStudStmt->fetchColumn();
        
        if ($studID) {
            $ncStmt = $conn->prepare("
                SELECT spe.*
                FROM student_program_enrollments spe
                WHERE spe.student_id = ? AND spe.enrollment_status = 'Active'
                ORDER BY spe.enrollment_id DESC LIMIT 1
            ");
            $ncStmt->execute([$studID]);
            $enrollment = $ncStmt->fetch(PDO::FETCH_ASSOC);
            if ($enrollment) {
                $isEnrolled = true;
                $studentNcLevel = $enrollment['nc_level'] ?? 'NC I';
                error_log("DEBUG: Found enrollment - NC Level: $studentNcLevel for user $userId, student $studID");
            }
        }
    } catch (Exception $e) {
        error_log("ERROR getting enrollment: " . $e->getMessage());
        $studentNcLevel = 'NC I';
    }
}

// Get available quizzes for student's NC level - only show quizzes with questions created by admin/staff
$availableQuizzes = [];
if ($conn !== null) {
    try {
        $stmt = $conn->prepare("
            SELECT q.*,
                (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
                (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ?) as best_score,
                (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ?) as attempt_count,
                u.first_name as creator_first_name, u.last_name as creator_last_name, u.user_type as creator_role
            FROM quizzes q
            LEFT JOIN users u ON q.created_by = u.user_id
            WHERE q.is_active = 1 
            AND (q.nc_level = ? OR q.nc_level LIKE ? OR q.nc_level IS NULL OR q.nc_level = '')
            AND (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) >= 0
            ORDER BY q.created_at DESC
        ");
        $stmt->execute([$userId, $userId, $studentNcLevel, $studentNcLevel . '%']);
        $availableQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $availableQuizzes = [];
    }
}

// Handle quiz submission
$quiz_id = $_GET['quiz_id'] ?? 0;
if ($quiz_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finish_quiz') {
    $answers = $_POST['answers'] ?? [];
    $quizId = intval($_POST['quiz_id'] ?? 0);
    
    if ($quizId && !empty($answers)) {
        try {
            $stmt = $conn->prepare("SELECT question_id, correct_answer, points_value FROM quiz_questions WHERE quiz_id = ?");
            $stmt->execute([$quizId]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalPoints = 0;
            $earnedPoints = 0;
            
            foreach ($questions as $q) {
                $totalPoints += floatval($q['points_value'] ?? 1);
                $userAnswer = $answers[$q['question_id']] ?? '';
                if (strtolower(trim($userAnswer)) === strtolower(trim($q['correct_answer']))) {
                    $earnedPoints += floatval($q['points_value'] ?? 1);
                }
            }
            
            $score = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;
            $passed = $score >= 70;
            
            $stmt = $conn->prepare("INSERT INTO quiz_attempts (quiz_id, user_id, answers, score, passed, attempted_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$quizId, $userId, json_encode($answers), $score, $passed ? 1 : 0]);
            
            $message = "Quiz completed! Score: $score% - " . ($passed ? "PASSED!" : "Keep practicing!");
            $messageType = $passed ? 'success' : 'warning';
            
            // Refresh quizzes
            $stmt = $conn->prepare("
                SELECT q.*,
                    (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
                    (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ?) as best_score,
                    (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ?) as attempt_count
                FROM quizzes q
                WHERE q.is_active = 1 AND (q.nc_level = ? OR q.nc_level LIKE ? OR q.nc_level IS NULL OR q.nc_level = '')
                ORDER BY q.created_at DESC
            ");
            $stmt->execute([$userId, $userId, $studentNcLevel, $studentNcLevel . '%']);
            $availableQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get current quiz for taking
$currentQuiz = null;
$quizQuestions = [];
$showQuiz = false;
if ($quiz_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM quizzes WHERE quiz_id = ? AND (nc_level = ? OR nc_level LIKE ? OR nc_level IS NULL OR nc_level = '')");
    $stmt->execute([$quiz_id, $studentNcLevel, $studentNcLevel . '%']);
    $currentQuiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($currentQuiz) {
        $stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order");
        $stmt->execute([$quiz_id]);
        $quizQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $showQuiz = true;
    }
}

$pageTitle = "Take Quiz";
$pageSubtitle = "Test Your Knowledge - " . ($studentNcLevel ?? 'NC I');
if (!$isEnrolled) {
    $message = "You are not currently enrolled. Please contact the admin/staff to enroll you in a training program.";
    $messageType = 'warning';
}
include 'sidebar_student.php';
?>

<style>
.quiz-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 15px; transition: all 0.3s ease; }
.quiz-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
.quiz-info { display: flex; gap: 20px; margin-top: 10px; }
.quiz-stat { display: flex; align-items: center; gap: 5px; font-size: 13px; color: #64748b; }
.quiz-stat i { color: #3b82f6; }
.status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.status-passed { background: #d1fae5; color: #059669; }
.status-failed { background: #fee2e2; color: #dc2626; }
.status-pending { background: #f1f5f9; color: #64748b; }
.status-best { background: #dbeafe; color: #2563eb; }
.question-nav { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
.question-dot { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #64748b; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
.question-dot:hover { background: #e2e8f0; }
.question-dot.current { background: #2563eb; color: white; }
.question-dot.answered { background: #10b981; color: white; }
.quiz-timer { position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; padding: 10px 20px; border-radius: 12px; font-size: 18px; font-weight: bold; z-index: 1000; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
.option-card { display: flex; align-items: center; padding: 15px; margin: 8px 0; border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s ease; }
.option-card:hover { border-color: #3b82f6; background: #f8fafc; }
.option-card.selected { border-color: #2563eb; background: #dbeafe; }
.option-card input { margin-right: 12px; }
.progress-bar-quiz { height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-top: 10px; }
.progress-bar-quiz .fill { height: 100%; background: linear-gradient(90deg, #2563eb, #3b82f6); transition: width 0.3s ease; }
</style>

<div class="content-header">
    <h2><i class="fas fa-stopwatch"></i> <?= htmlspecialchars($pageTitle) ?></h2>
    <p class="text-muted"><?= htmlspecialchars($pageSubtitle) ?></p>
</div>

<?php if ($message): ?>
<div style="padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; <?= $messageType === 'error' ? 'background: #fee2e2; color: #dc2626;' : ($messageType === 'success' ? 'background: #d1fae5; color: #059671;' : 'background: #fef3c7; color: #d97706;') ?>">
    <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : ($messageType === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($showQuiz && $currentQuiz && !empty($quizQuestions)): ?>
<!-- Taking Quiz Mode -->
<?php $totalQuestions = count($quizQuestions); ?>
<div class="quiz-timer"><i class="fas fa-clock"></i> <span id="timer"><?= $currentQuiz['time_limit'] ?? 30 ?>:00</span></div>

<div class="card" style="padding: 25px; margin-bottom: 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3><?= htmlspecialchars($currentQuiz['title']) ?></h3>
            <p style="color: #64748b; margin: 5px 0;"><?= htmlspecialchars($currentQuiz['description'] ?? '') ?></p>
        </div>
        <div style="text-align: right;">
            <span class="status-badge status-pending"><i class="fas fa-layer-group"></i> Question 1 of <?= $totalQuestions ?></span>
        </div>
    </div>
    <div class="question-nav">
        <?php foreach ($quizQuestions as $idx => $q): ?>
        <a href="?quiz_id=<?= $quiz_id ?>&question=<?= $idx + 1 ?>" class="question-dot <?= $idx === 0 ? 'current' : '' ?>" style="text-decoration: none; color: inherit;"><?= $idx + 1 ?></a>
        <?php endforeach; ?>
    </div>
</div>

<form method="POST" action="" id="quizForm">
    <input type="hidden" name="action" value="finish_quiz">
    <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">
    
    <?php foreach ($quizQuestions as $idx => $q): ?>
    <div class="card" style="padding: 30px; margin-bottom: 20px; <?= $idx > 0 ? 'display:none;' : '' ?>" id="question_<?= $idx + 1 ?>">
        <h4 style="margin-bottom: 20px; font-size: 18px;"><span style="color: #2563eb;">Question <?= $idx + 1 ?></span><span style="color: #64748b; font-weight: normal;">/ <?= $totalQuestions ?></span></h4>
        <h3 style="font-size: 20px; margin-bottom: 25px;"><?= htmlspecialchars($q['question_text']) ?></h3>
        
        <?php 
        $optionsRaw = $q['options'] ?? null;
        $options = $optionsRaw ? json_decode($optionsRaw, true) : null;
        $qType = $q['question_type'] ?? 'Multiple Choice';
        
        // If no options in JSON, try parsing as pipe-separated string
        if (empty($options) && !empty($optionsRaw)) {
            $options = explode('|', $optionsRaw);
        }
        ?>
        
        <?php if ($qType === 'Multiple Choice' && !empty($options)): ?>
            <?php foreach ($options as $opt): ?>
            <label class="option-card">
                <input type="radio" name="answers[<?= $q['question_id'] ?>]" value="<?= htmlspecialchars($opt) ?>" <?= $idx === 0 ? 'required' : '' ?>>
                <?= htmlspecialchars($opt) ?>
            </label>
            <?php endforeach; ?>
        <?php elseif ($qType === 'True/False'): ?>
            <label class="option-card"><input type="radio" name="answers[<?= $q['question_id'] ?>]" value="True" <?= $idx === 0 ? 'required' : '' ?>> <i class="fas fa-check" style="color: #10b981; margin-right: 10px;"></i> True</label>
            <label class="option-card"><input type="radio" name="answers[<?= $q['question_id'] ?>]" value="False" <?= $idx === 0 ? 'required' : '' ?>> <i class="fas fa-times" style="color: #dc2626; margin-right: 10px;"></i> False</label>
        <?php else: ?>
            <input type="text" name="answers[<?= $q['question_id'] ?>]" placeholder="Type your answer..." style="width: 100%; padding: 15px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 16px;">
        <?php endif; ?>
        
        <div style="display: flex; gap: 15px; margin-top: 30px;">
            <?php if ($idx > 0): ?>
            <button type="button" onclick="showQuestion(<?= $idx ?>)" class="btn" style="padding: 12px 24px; background: #64748b; color: white; border: none; border-radius: 8px; cursor: pointer;"><i class="fas fa-arrow-left"></i> Previous</button>
            <?php endif; ?>
            <button type="submit" class="btn" style="padding: 12px 24px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer;"><?= $idx < $totalQuestions - 1 ? 'Next <i class="fas fa-arrow-right"></i>' : '<i class="fas fa-check"></i> Finish Quiz' ?></button>
        </div>
    </div>
    <?php endforeach; ?>
</form>

<?php else: ?>
<!-- Quiz List -->
<div class="card" style="padding: 25px;">
    <h3 style="margin-bottom: 20px;"><i class="fas fa-list"></i> Available Quizzes - <?= htmlspecialchars($studentNcLevel) ?></h3>
    
    <?php if (!$isEnrolled): ?>
    <div style="text-align: center; padding: 50px;">
        <i class="fas fa-user-clock" style="font-size: 64px; color: #cbd5e1; margin-bottom: 20px;"></i>
        <h3>Not Enrolled</h3>
        <p>You are not currently enrolled in any training program. Please contact the admin or staff to enroll you.</p>
    </div>
    <?php elseif (empty($availableQuizzes)): ?>
    <div style="text-align: center; padding: 50px;">
        <i class="fas fa-clipboard-list" style="font-size: 64px; color: #cbd5e1; margin-bottom: 20px;"></i>
        <h3>No Quizzes Available</h3>
        <p>Complete your learning modules or contact your instructor for quiz assignments.</p>
    </div>
    <?php else: ?>
    
    <?php foreach ($availableQuizzes as $quiz): ?>
    <?php 
    $bestScore = $quiz['best_score'] ?? 0;
    $attempts = $quiz['attempt_count'] ?? 0;
    $hasPassed = $bestScore >= ($quiz['passing_score'] ?? 70);
    $questionCount = $quiz['question_count'] ?? 0;
    ?>
    <div class="quiz-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="flex: 1;">
                <h4 style="margin: 0 0 10px 0; font-size: 18px;"><?= htmlspecialchars($quiz['title']) ?></h4>
                <p style="margin: 0 0 15px 0; color: #64748b; font-size: 14px;"><?= htmlspecialchars($quiz['description'] ?? 'No description') ?></p>
                <div style="display: flex; gap: 20px; font-size: 13px; color: #64748b;">
                    <span><i class="fas fa-list"></i> <?= $questionCount ?> Questions</span>
                    <span><i class="fas fa-clock"></i> <?= $quiz['time_limit'] ?? 30 ?> min</span>
                    <span><i class="fas fa-percentage"></i> Pass: <?= $quiz['passing_score'] ?? 70 ?>%</span>
                    <span><i class="fas fa-repeat"></i> <?= $attempts ?> attempts</span>
                </div>
            </div>
            <div style="text-align: right; min-width: 150px;">
                <?php if ($hasPassed): ?>
                <span class="status-badge status-passed" style="margin-bottom: 10px;"><i class="fas fa-check-circle"></i> Passed</span>
                <div style="font-size: 24px; font-weight: bold; color: #10b981;"><?= $bestScore ?>%</div>
                <a href="?quiz_id=<?= $quiz['quiz_id'] ?>" style="display: block; margin-top: 10px; padding: 8px 16px; background: #64748b; color: white; border-radius: 8px; text-decoration: none;">Retake</a>
                <?php elseif ($attempts > 0): ?>
                <span class="status-badge status-failed" style="margin-bottom: 10px;"><i class="fas fa-times-circle"></i> Not Passed</span>
                <div style="font-size: 24px; font-weight: bold; color: #dc2626; margin-bottom: 10px;"><?= $bestScore ?>%</div>
                <a href="?quiz_id=<?= $quiz['quiz_id'] ?>" style="display: block; padding: 8px 16px; background: #2563eb; color: white; border-radius: 8px; text-decoration: none;">Try Again</a>
                <?php else: ?>
                <a href="?quiz_id=<?= $quiz['quiz_id'] ?>" style="display: block; padding: 12px 24px; background: #2563eb; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;"><i class="fas fa-play"></i> Start Quiz</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
function showQuestion(n) {
    document.querySelectorAll('[id^="question_"]').forEach(function(el) { el.style.display = 'none'; });
    document.getElementById('question_' + n).style.display = 'block';
}

let timeLeft = <?= $showQuiz ? ($currentQuiz['time_limit'] ?? 30) * 60 : 0 ?>;
if (timeLeft > 0) {
    const timerEl = document.getElementById('timer');
    setInterval(function() {
        if (timeLeft <= 0) {
            document.getElementById('quizForm').submit();
            return;
        }
        const mins = Math.floor(timeLeft / 60);
        const secs = timeLeft % 60;
        timerEl.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
        if (timeLeft < 300) document.querySelector('.quiz-timer').style.background = 'linear-gradient(135deg, #dc2626, #b91c1c)';
        timeLeft--;
    }, 1000);
}

document.querySelectorAll('.option-card').forEach(function(card) {
    card.addEventListener('click', function() {
        const radio = this.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
        document.querySelectorAll('.option-card').forEach(function(c) { c.classList.remove('selected'); });
        this.classList.add('selected');
    });
});
</script>