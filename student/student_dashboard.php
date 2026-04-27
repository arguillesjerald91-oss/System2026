<?php
/**
 * Trainee Dashboard - Role-Based Features
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

// Normalize 'student' to 'trainee' for backward compatibility
$userType = ($userType === 'student') ? 'trainee' : $userType;

if (!in_array($userType, ['trainee', 'student'])) {
    header("Location: ../login.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$email = $user['email'] ?? '';

// Get trainee's enrollment with NC level
$isEnrolled = false;
$studentNcLevel = 'NC I';
$stmt = $conn->prepare("
    SELECT spe.enrollment_id, spe.batch_id, spe.nc_level, tb.batch_name, amp.program_title, amp.tesda_qualification_code
    FROM student_program_enrollments spe
    LEFT JOIN training_batches tb ON spe.batch_id = tb.batch_id
    LEFT JOIN auto_mechanic_programs amp ON tb.program_id = amp.program_id
    WHERE spe.student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1)
    AND spe.enrollment_status = 'Active'
    LIMIT 1
");
$stmt->execute([$userId]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
if ($enrollment) {
    $isEnrolled = true;
    $studentNcLevel = $enrollment['nc_level'] ?? 'NC I';
}

// Get trainee's learning progress - directly from NC level subjects
$myProgress = [];
if ($studentNcLevel) {
    $stmt = $conn->prepare("
        SELECT lm.module_id, lm.module_title, lm.module_type, 
               COALESCE(mp.progress_percent, 0) as progress_percentage, 
               COALESCE(mp.status, 'Not Started') as status
        FROM learning_modules lm
        LEFT JOIN module_progress mp ON lm.module_id = mp.module_id AND mp.user_id = ?
        WHERE lm.nc_level = ? AND lm.is_active = 1
        ORDER BY lm.module_id ASC
    ");
    $stmt->execute([$userId, $studentNcLevel]);
    $myProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get enrolled modules count
$enrolledModulesCount = 0;
if ($studentNcLevel) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM learning_modules WHERE nc_level = ? AND is_active = 1");
    $stmt->execute([$studentNcLevel]);
    $enrolledModulesCount = $stmt->fetchColumn();
}

// Get completed modules count
$completedCount = 0;
if ($studentNcLevel) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM module_progress mp 
        JOIN learning_modules lm ON mp.module_id = lm.module_id 
        WHERE mp.user_id = ? AND lm.nc_level = ? AND mp.status = 'Completed'
    ");
    $stmt->execute([$userId, $studentNcLevel]);
    $completedCount = $stmt->fetchColumn();
}

// Get competency status
$myCompetencies = [];
$stmt = $conn->prepare("SELECT cu.unit_title, ca.assessment_status, ca.final_score 
    FROM competency_assessments ca 
    JOIN competency_units cu ON ca.unit_id = cu.unit_id 
    WHERE ca.user_id = ? AND ca.assessment_status != 'Passed'
    ORDER BY ca.assessment_date DESC LIMIT 5");
$stmt->execute([$userId]);
$myCompetencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPage = 'student_dashboard.php';
$pageTitle = "Trainee Portal";
$pageSubtitle = "My Training & Learning";
include 'sidebar_student.php';
?>

<div class="welcome-banner bg-primary">
    <h2>Welcome, <?= htmlspecialchars(explode(' ', $fullName)[0]) ?>!</h2>
    <p class="subtitle">TESDA Auto Mechanic Training Programme - <strong><?= htmlspecialchars($studentNcLevel) ?></strong></p>
    <p class="meta">Competency-Based Modular Training</p>
</div>

<?php if (!$isEnrolled): ?>
<div style="padding: 15px 20px; margin: 0 25px 20px 25px; background: #fef3c7; border-radius: 8px; color: #d97706;">
    <i class="fas fa-exclamation-triangle"></i> <strong>Not Enrolled</strong> - You are not currently enrolled in any training program. Please contact the admin/staff to enroll you.
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Enrollment Status</div>
        <div class="stat-value" style="color: #10b981;"><?= $enrollment ? 'Active' : 'Pending' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Modules Enrolled</div>
        <div class="stat-value"><?= $enrolledModulesCount ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Modules Completed</div>
        <div class="stat-value"><?= $completedCount ?>/<?= $enrolledModulesCount ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Competencies Passed</div>
        <div class="stat-value"><?= count(array_filter($myCompetencies, fn($c) => $c['assessment_status'] === 'Passed')) ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">My Learning Modules</h3>
            <a href="learning_modules.php" style="color: #2563eb; text-decoration: none; font-size: 14px;">View All →</a>
        </div>
        <div class="card-body">
            <?php if (empty($myProgress)): ?>
                <?php 
                // Get available modules for trainee
                $stmt = $conn->query("SELECT module_id, module_title, module_type, duration_mins, nc_level FROM learning_modules WHERE is_active = 1 ORDER BY sort_order LIMIT 5");
                $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php foreach ($modules as $mod): ?>
                <div class="module-item">
                    <div class="module-icon" style="background: #dbeafe; color: #2563eb;">📚</div>
                 <div class="module-info">
                         <div class="module-name"><?= htmlspecialchars($mod['module_title']) ?></div>
                         <div class="module-desc"><?= ($mod['module_type'] ?? 'Module') ?> • <?= ($mod['duration_mins'] ?? 0) ?> mins</div>
                     </div>
                     <?php if ($enrollment): ?>
                     <form method="POST" style="display:inline;">
                         <input type="hidden" name="module_id" value="<?= $mod['module_id'] ?>">
                         <button type="submit" class="btn" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer;">Start</button>
                     </form>
                     <?php else: ?>
                     <a href="student_dashboard.php?error=enrollment_required" class="btn" style="padding: 8px 16px; background: #f59e0b; color: white; border-radius: 8px; text-decoration:none;">Enroll First</a>
                     <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($myProgress as $prog): 
                    $progStatus = $prog['progress_status'] ?? $prog['status'] ?? 'Not Started';
                    $progPercent = $prog['progress_percent'] ?? $prog['progress_percentage'] ?? 0;
                ?>
                <div class="module-item">
                    <div class="module-icon" style="background: <?= $progStatus === 'Completed' ? '#d1fae5' : '#dbeafe' ?>; color: <?= $progStatus === 'Completed' ? '#10b981' : '#2563eb' ?>;"><?= $progStatus === 'Completed' ? '✓' : '📚' ?></div>
                    <div class="module-info">
                        <div class="module-name"><?= htmlspecialchars($prog['module_title']) ?></div>
                        <div class="module-desc"><?= $progPercent ?>% Complete</div>
                    </div>
                    <span class="badge <?= $progStatus === 'Completed' ? 'badge-green' : 'badge-blue' ?>"><?= $progStatus ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div>
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header"><h3 class="card-title">Quick Actions</h3></div>
            <div class="card-body">
                <div class="grid-2">
                <a href="learning_modules.php" class="action-btn" style="display: block; padding: 14px; background: #2563eb; color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600;">📚 Browse Modules</a>
                <a href="../instructor/assignments.php" class="action-btn" style="display: block; padding: 14px; background: #10b981; color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600;">📝 My Assignments</a>
                <a href="../instructor/quizzes.php" class="action-btn" style="display: block; padding: 14px; background: #f59e0b; color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600;">❓ Take Quizzes</a>
                <a href="../instructor/learning_materials.php" class="action-btn" style="display: block; padding: 14px; background: #8b5cf6; color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600;">📂 Download Materials</a>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3 class="card-title">My Competencies</h3></div>
            <div class="card-body">
                <?php if (empty($myCompetencies)): ?>
                    <p style="color: #64748b; text-align: center; padding: 20px;">No assessments yet</p>
                <?php else: ?>
                    <?php foreach ($myCompetencies as $comp): ?>
                    <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                        <span style="font-size: 14px;"><?= htmlspecialchars($comp['unit_title']) ?></span>
                        <span class="badge <?= $comp['assessment_status'] === 'Passed' ? 'badge-green' : 'badge-blue' ?>"><?= $comp['assessment_status'] ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</main>
</div>