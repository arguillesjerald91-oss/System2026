<?php
/**
 * Trainee Grades Page
 */

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
$userType = ($userType === 'student') ? 'trainee' : $userType;

if (!in_array($userType, ['trainee', 'student'])) {
    header("Location: ../login.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

// Check enrollment status
$enrollStmt = $conn->prepare("SELECT 1 FROM student_program_enrollments WHERE student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1) AND enrollment_status = 'Active' LIMIT 1");
$enrollStmt->execute([$userId]);
$isEnrolled = (bool)$enrollStmt->fetchColumn();

if (!$isEnrolled) {
    header("Location: my_application.php?error=not_enrolled");
    exit();
}

// Get student's NC level first
$ncStmt = $conn->prepare("
    SELECT nc_level FROM student_program_enrollments 
    WHERE student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1) 
    AND enrollment_status = 'Active' 
    LIMIT 1
");
$ncStmt->execute([$userId]);
$studentNcLevel = $ncStmt->fetchColumn() ?: 'NC I';

// Get trainee's module progress/grades filtered by NC level
$stmt = $conn->prepare("
    SELECT lm.module_id, lm.module_title, lm.module_type, lm.duration_mins, lm.nc_level,
           COALESCE(mp.progress_percentage, 0) as progress_percentage, 
           COALESCE(mp.final_score, 0) as final_score,
           COALESCE(mp.status, 'Not Started') as status, 
           mp.completion_date
    FROM learning_modules lm
    LEFT JOIN module_progress mp ON lm.module_id = mp.module_id AND mp.user_id = ?
    WHERE lm.nc_level = ? AND lm.is_active = 1
    ORDER BY lm.module_title
");
$stmt->execute([$userId, $studentNcLevel]);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$completedCount = count(array_filter($grades, fn($g) => $g['status'] === 'Completed'));
$inProgressCount = count(array_filter($grades, fn($g) => $g['status'] === 'In Progress'));
$avgScore = 0;
$scores = array_filter(array_column($grades, 'final_score'), fn($s) => $s !== null && $s !== '');
$avgScore = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;

$currentPage = 'my_grades.php';
$pageTitle = "My Grades";
$pageSubtitle = "Module scores & progress";
include 'sidebar_student.php';
?>

<div class="page-content">
    
<div class="welcome-banner bg-purple">
    <h2>My Grades</h2>
    <p class="subtitle">View your module progress and scores</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Modules Completed</div>
        <div class="stat-value"><?= $completedCount ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">In Progress</div>
        <div class="stat-value" style="color: #f59e0b;"><?= $inProgressCount ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Average Score</div>
        <div class="stat-value"><?= $avgScore ? $avgScore . '%' : '-' ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Module Grades</h3>
    </div>
    <div class="card-body">
        <?php if (empty($grades)): ?>
        <div style="text-align: center; padding: 40px; color: #64748b;">
            <p style="font-size: 18px; margin-bottom: 10px;">No grades yet</p>
            <p style="font-size: 14px;">Start working on your modules to see your progress and scores here.</p>
        </div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Type</th>
                    <th>Progress</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Completed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grades as $g): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($g['module_title']) ?></strong><br>
                        <small style="color: #64748b;"><?= htmlspecialchars($g['module_code'] ?? 'N/A') ?></small>
                    </td>
                    <td><?= htmlspecialchars($g['module_type'] ?? 'Module') ?></td>
                    <td>
                        <div class="progress-bar" style="background:#e5e7eb; height:8px; border-radius:4px; overflow:hidden; width:100px;">
                            <div class="progress-fill" style="background:#10b981; height:100%; width:<?= $g['progress_percentage'] ?>%"></div>
                        </div>
                        <small><?= $g['progress_percentage'] ?>%</small>
                    </td>
                    <td><strong><?= $g['final_score'] !== null ? $g['final_score'] . '%' : '-' ?></strong></td>
                    <td>
                        <span class="badge <?= $g['status'] === 'Completed' ? 'badge-green' : ($g['status'] === 'In Progress' ? 'badge-blue' : 'badge-red') ?>">
                            <?= htmlspecialchars($g['status']) ?>
                        </span>
                    </td>
                    <td><?= $g['completion_date'] ? date('M d, Y', strtotime($g['completion_date'])) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
