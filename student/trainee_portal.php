<?php
/**
 * Comprehensive Trainee Portal - NC Level-Based Access
 * Provides access to quizzes, materials, assignments, and assessments
 */

session_start();
include __DIR__ . '/../db.php';
$database = new Database();
$conn = $database->getConnection();

// Authentication check
if (!isset($_SESSION['userId'])) {
    header('Location: ../login.php');
    exit();
}

$userId = $_SESSION['userId'];
$userRole = $_SESSION['userRole'] ?? 'trainee';
$userType = ($userRole === 'student') ? 'trainee' : $userRole;

if (!in_array($userType, ['trainee', 'student'])) {
    header("Location: ../login.php");
    exit();
}

// Get trainee profile and NC level enrollment
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

// Get trainee's enrollment info with NC level
$stmt = $conn->prepare("
    SELECT spe.enrollment_id, spe.nc_level, spe.program_id, spe.batch_id,
           tb.batch_name, amp.program_title, amp.tesda_qualification_code
    FROM student_program_enrollments spe
    JOIN training_batches tb ON spe.batch_id = tb.batch_id
    LEFT JOIN auto_mechanic_programs amp ON spe.program_id = amp.program_id
    WHERE spe.student_id = ? AND spe.enrollment_status = 'Active'
    ORDER BY spe.enrollment_id DESC LIMIT 1
");
$stmt->execute([$userId]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

$studentNcLevel = $enrollment['nc_level'] ?? 'Not Assigned';
$enrollmentId = $enrollment['enrollment_id'] ?? null;

// Get enrolled modules for this trainee
$enrolledModules = [];
if ($enrollmentId) {
    $stmt = $conn->prepare("
        SELECT DISTINCT lm.module_id, lm.module_title, lm.module_type
        FROM student_module_progress smp
        JOIN learning_modules lm ON smp.module_id = lm.module_id
        WHERE smp.enrollment_id = ?
        ORDER BY lm.module_title
    ");
    $stmt->execute([$enrollmentId]);
    $enrolledModules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$enrolledModuleIds = array_column($enrolledModules, 'module_id');

// Get accessible quizzes based on NC level and enrolled modules
$accessibleQuizzes = [];
if (!empty($enrolledModuleIds)) {
    $placeholders = implode(',', array_fill(0, count($enrolledModuleIds), '?'));
    $stmt = $conn->prepare("
        SELECT q.*, 
               (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
               (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ?) as attempt_count,
               (SELECT score FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ? ORDER BY attempted_at DESC LIMIT 1) as last_score
        FROM quizzes q 
        WHERE (q.module_id IN ($placeholders) OR q.nc_level = ?)
        ORDER BY q.created_at DESC
        LIMIT 10
    ");
    $params = array_merge($enrolledModuleIds, [$userId, $userId, $studentNcLevel]);
    $stmt->execute($params);
    $accessibleQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get accessible assignments based on NC level and enrolled modules
$accessibleAssignments = [];
if (!empty($enrolledModuleIds)) {
    $placeholders = implode(',', array_fill(0, count($enrolledModuleIds), '?'));
    $stmt = $conn->prepare("
        SELECT a.*, 
               (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.assignment_id AND student_id = ?) as submission_count,
               (SELECT score FROM assignment_submissions WHERE assignment_id = a.assignment_id AND student_id = ? ORDER BY submitted_at DESC LIMIT 1) as last_score
        FROM assignments a 
        WHERE (a.module_id IN ($placeholders) OR a.nc_level = ?)
        ORDER BY a.due_date ASC, a.created_at DESC
        LIMIT 10
    ");
    $params = array_merge($enrolledModuleIds, [$userId, $userId, $studentNcLevel]);
    $stmt->execute($params);
    $accessibleAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get accessible learning materials based on NC level and enrolled modules
$accessibleMaterials = [];
if (!empty($enrolledModuleIds)) {
    $placeholders = implode(',', array_fill(0, count($enrolledModuleIds), '?'));
    $stmt = $conn->prepare("
        SELECT lm.*, u.first_name, u.last_name
        FROM learning_materials lm 
        LEFT JOIN users u ON lm.uploaded_by = u.user_id 
        WHERE (lm.module_id IN ($placeholders) OR lm.nc_level = ?)
        ORDER BY lm.created_at DESC
        LIMIT 15
    ");
    $params = array_merge($enrolledModuleIds, [$studentNcLevel]);
    $stmt->execute($params);
    $accessibleMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get competency assessments
$competencyAssessments = [];
if ($studentNcLevel !== 'Not Assigned') {
    $stmt = $conn->prepare("
        SELECT ca.*, cu.unit_title, cu.competency_code
        FROM competency_assessments ca
        JOIN competency_units cu ON ca.unit_id = cu.unit_id
        WHERE ca.user_id = ? AND cu.nc_level = ?
        ORDER BY ca.assessment_date DESC
        LIMIT 10
    ");
    $stmt->execute([$userId, $studentNcLevel]);
    $competencyAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate statistics
$totalModules = count($enrolledModules);
$completedModules = 0;
if ($enrollmentId) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM student_module_progress WHERE enrollment_id = ? AND status = 'Completed'");
    $stmt->execute([$enrollmentId]);
    $completedModules = $stmt->fetchColumn();
}

$quizAttempts = count(array_filter($accessibleQuizzes, fn($q) => $q['attempt_count'] > 0));
$assignmentSubmissions = count(array_filter($accessibleAssignments, fn($a) => $a['submission_count'] > 0));

$currentPage = 'trainee_portal.php';
$pageTitle = "Trainee Portal";
$pageSubtitle = "Complete Learning Hub - $studentNcLevel";
include 'sidebar_student.php';
?>

<div class="page-content" style="visibility: visible; opacity: 1;">
    
    <!-- Welcome Banner -->
    <div class="welcome-banner bg-primary">
        <h2>Welcome, <?= htmlspecialchars(explode(' ', $fullName)[0]) ?>!</h2>
        <p class="subtitle"><?= htmlspecialchars($studentNcLevel) ?> - <?= htmlspecialchars($enrollment['program_title'] ?? 'TESDA Auto Mechanic Program') ?></p>
        <p class="meta">Batch: <?= htmlspecialchars($enrollment['batch_name'] ?? 'Not Assigned') ?></p>
    </div>

    <!-- Statistics Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Training Modules</div>
            <div class="stat-value"><?= $completedModules ?>/<?= $totalModules ?></div>
            <div class="stat-sub">Completed</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Quiz Attempts</div>
            <div class="stat-value"><?= $quizAttempts ?></div>
            <div class="stat-sub">Taken</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Assignments</div>
            <div class="stat-value"><?= $assignmentSubmissions ?></div>
            <div class="stat-sub">Submitted</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Materials</div>
            <div class="stat-value"><?= count($accessibleMaterials) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Available</div>
        </div>
    </div>

    <!-- Quick Access Grid -->
    <div class="grid-2">
        
        <!-- Learning Modules Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">My Learning Modules</h3>
                <a href="learning_modules.php" style="color: #2563eb; text-decoration: none; font-size: 14px;">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($enrolledModules)): ?>
                    <p style="color: #64748b; text-align: center; padding: 20px;">No modules enrolled yet</p>
                <?php else: ?>
                    <?php foreach (array_slice($enrolledModules, 0, 3) as $module): ?>
                    <div class="module-item">
                        <div class="module-icon" style="background: #dbeafe; color: #2563eb;">&#128218;</div>
                        <div class="module-info">
                            <div class="module-name"><?= htmlspecialchars($module['module_title']) ?></div>
                            <div class="module-desc"><?= htmlspecialchars($module['module_type'] ?? 'Module') ?></div>
                        </div>
                        <a href="learning_modules.php" class="btn" style="padding: 6px 12px; background: #2563eb; color: white; border: none; border-radius: 6px; text-decoration: none; font-size: 12px;">Continue</a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Available Quizzes Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Available Quizzes</h3>
                <a href="../instructor/quizzes.php" style="color: #2563eb; text-decoration: none; font-size: 14px;">Take Quiz</a>
            </div>
            <div class="card-body">
                <?php if (empty($accessibleQuizzes)): ?>
                    <p style="color: #64748b; text-align: center; padding: 20px;">No quizzes available for your level</p>
                <?php else: ?>
                    <?php foreach (array_slice($accessibleQuizzes, 0, 3) as $quiz): ?>
                    <div class="quiz-item" style="display: flex; align-items: center; padding: 12px; border-radius: 8px; background: #f8fafc; margin-bottom: 8px;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($quiz['title']) ?></div>
                            <div style="font-size: 12px; color: #64748b;"><?= $quiz['question_count'] ?> questions</div>
                        </div>
                        <?php if ($quiz['attempt_count'] > 0): ?>
                            <span class="badge badge-blue"><?= round($quiz['last_score']) ?>%</span>
                        <?php else: ?>
                            <a href="../instructor/quizzes.php?quiz_id=<?= $quiz['quiz_id'] ?>" class="btn btn-sm btn-primary">Take</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Assignments Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Assignments</h3>
                <a href="../instructor/assignments.php" style="color: #2563eb; text-decoration: none; font-size: 14px;">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($accessibleAssignments)): ?>
                    <p style="color: #64748b; text-align: center; padding: 20px;">No assignments assigned</p>
                <?php else: ?>
                    <?php foreach (array_slice($accessibleAssignments, 0, 3) as $assignment): ?>
                    <div class="assignment-item" style="display: flex; align-items: center; padding: 12px; border-radius: 8px; background: #f8fafc; margin-bottom: 8px;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($assignment['title']) ?></div>
                            <div style="font-size: 12px; color: #64748b;">Due: <?= $assignment['due_date'] ? date('M d', strtotime($assignment['due_date'])) : 'No due date' ?></div>
                        </div>
                        <?php if ($assignment['submission_count'] > 0): ?>
                            <span class="badge badge-green">Submitted</span>
                        <?php else: ?>
                            <a href="../instructor/assignments.php?assignment_id=<?= $assignment['assignment_id'] ?>" class="btn btn-sm btn-primary">Submit</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Learning Materials Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Learning Materials</h3>
                <a href="../instructor/learning_materials.php" style="color: #2563eb; text-decoration: none; font-size: 14px;">Browse</a>
            </div>
            <div class="card-body">
                <?php if (empty($accessibleMaterials)): ?>
                    <p style="color: #64748b; text-align: center; padding: 20px;">No materials available</p>
                <?php else: ?>
                    <?php foreach (array_slice($accessibleMaterials, 0, 3) as $material): ?>
                    <div class="material-item" style="display: flex; align-items: center; padding: 12px; border-radius: 8px; background: #f8fafc; margin-bottom: 8px;">
                        <div style="width: 32px; height: 32px; background: #dbeafe; color: #2563eb; border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 16px;">
                            <?= $material['material_type'] === 'Video' ? '&#127925;' : ($material['material_type'] === 'Document' ? '&#128196;' : '&#128221;') ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($material['title']) ?></div>
                            <div style="font-size: 12px; color: #64748b;"><?= $material['material_type'] ?></div>
                        </div>
                        <a href="<?= htmlspecialchars($material['file_path']) ?>" class="btn btn-sm btn-outline" download>Download</a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Competency Assessments Section -->
    <?php if (!empty($competencyAssessments)): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">My Competency Assessments</h3>
            <a href="my_competencies.php" style="color: #2563eb; text-decoration: none; font-size: 14px;">View All</a>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
                <?php foreach ($competencyAssessments as $assessment): ?>
                <div style="padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;">
                    <div style="font-weight: 600; margin-bottom: 8px;"><?= htmlspecialchars($assessment['unit_title']) ?></div>
                    <div style="font-size: 14px; color: #64748b; margin-bottom: 8px;"><?= htmlspecialchars($assessment['competency_code']) ?></div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="badge <?= $assessment['assessment_status'] === 'Passed' ? 'badge-green' : 'badge-blue' ?>">
                            <?= $assessment['assessment_status'] ?>
                        </span>
                        <?php if ($assessment['final_score']): ?>
                            <span style="font-weight: 600; color: #2563eb;"><?= $assessment['final_score'] ?>%</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions Panel -->
    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h3 class="card-title">Quick Actions</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <a href="learning_modules.php" class="action-btn" style="display: block; padding: 16px; background: #2563eb; color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600;">
                    &#128214; Browse Modules
                </a>
                <a href="../instructor/quizzes.php" class="action-btn" style="display: block; padding: 16px; background: #10b981; color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600;">
                    &#10067; Take Quizzes
                </a>
                <a href="../instructor/assignments.php" class="action-btn" style="display: block; padding: 16px; background: #f59e0b; color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600;">
                    &#128221; My Assignments
                </a>
                <a href="../instructor/learning_materials.php" class="action-btn" style="display: block; padding: 16px; background: #8b5cf6; color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600;">
                    &#128194; Download Materials
                </a>
                <a href="my_competencies.php" class="action-btn" style="display: block; padding: 16px; background: #ef4444; color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600;">
                    &#127891; My Competencies
                </a>
                <a href="my_grades.php" class="action-btn" style="display: block; padding: 16px; background: #6366f1; color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 600;">
                    &#127942; View Grades
                </a>
            </div>
        </div>
    </div>

</div>

<style>
.module-item, .quiz-item, .assignment-item, .material-item {
    transition: all 0.2s ease;
}

.module-item:hover, .quiz-item:hover, .assignment-item:hover, .material-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}

.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.badge-green { background: #d1fae5; color: #059669; }
.badge-blue { background: #dbeafe; color: #2563eb; }

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
    
    div[style*="grid-template-columns: repeat(2, 1fr)"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

</div>

</main>
</div>
