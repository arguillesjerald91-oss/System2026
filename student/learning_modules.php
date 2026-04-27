<?php
/**
 * Enhanced My Modules Page - Advanced Features & UI
 */
session_start();
include __DIR__ . '/../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['userId'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['userId'];
$userRole = $_SESSION['userRole'] ?? 'trainee';
$userType = ($userRole === 'student') ? 'trainee' : $userRole;

// Get user info
$userName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));

// Get enrollment info
$enrollment = null;
$studentNcLevel = 'NC I';
if ($conn !== null) {
    try {
        $stmt = $conn->prepare("SELECT spe.*, p.program_name, p.program_level
            FROM student_program_enrollments spe
            LEFT JOIN programs p ON spe.program_id = p.program_id
            WHERE spe.student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1) 
            AND spe.enrollment_status = 'Active' 
            ORDER BY spe.enrollment_id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($enrollment) {
            $studentNcLevel = $enrollment['nc_level'] ?? 'NC I';
        }
    } catch (Exception $e) {
        $enrollment = null;
    }
}

// Get modules with progress
$modules = [];
$inProgress = [];
$completed = [];
$notStarted = [];

if ($conn !== null && $enrollment) {
    try {
        // Get modules for student's NC level with progress
        $stmt = $conn->prepare("
            SELECT lm.*, smp.progress_percentage, smp.status as progress_status, smp.completion_date,
                   smp.final_score, smp.time_spent_minutes
            FROM learning_modules lm
            LEFT JOIN student_module_progress smp ON lm.module_id = smp.module_id 
                AND smp.enrollment_id = ?
            WHERE lm.is_active = 1 AND lm.nc_level = ?
            ORDER BY lm.module_order ASC, lm.module_id ASC
        ");
        $stmt->execute([$enrollment['enrollment_id'] ?? 0, $studentNcLevel]);
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Categorize modules
        foreach ($modules as $mod) {
            $status = $mod['progress_status'] ?? '';
            $progress = $mod['progress_percentage'] ?? 0;
            
            if ($status === 'Completed' || $progress >= 100) {
                $completed[] = $mod;
            } elseif ($progress > 0) {
                $inProgress[] = $mod;
            } else {
                $notStarted[] = $mod;
            }
        }
    } catch (Exception $e) {
        $modules = [];
    }
}

// Calculate stats
$totalModules = count($modules);
$completedCount = count($completed);
$inProgressCount = count($inProgress);
$progressPercent = $totalModules > 0 ? round(($completedCount / $totalModules) * 100) : 0;

// Get recent activity
$recentActivity = [];
if ($conn !== null) {
    try {
        $stmt = $conn->query("
            SELECT smp.*, lm.module_title
            FROM student_module_progress smp
            JOIN learning_modules lm ON smp.module_id = lm.module_id
            WHERE smp.enrollment_id = " . ($enrollment['enrollment_id'] ?? 0) . "
            ORDER BY smp.last_accessed DESC
            LIMIT 5
        ");
        $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $recentActivity = [];
    }
}

$pageTitle = "My Learning Modules";
$programName = $enrollment['program_name'] ?? 'Training Program';
$ncLevel = $enrollment['nc_level'] ?? '';
$pageSubtitle = $enrollment ? $programName . ' (' . $ncLevel . ')' : "Your Training Path";
include 'sidebar_student.php';
?>

<style>
.module-progress { height: 8px; border-radius: 4px; background: #e2e8f0; overflow: hidden; }
.module-progress-bar { height: 100%; border-radius: 4px; transition: width 0.3s ease; }
.progress-complete { background: linear-gradient(90deg, #10b981, #059669); }
.progress-in-progress { background: linear-gradient(90deg, #3b82f6, #2563eb); }
.progress-not-started { background: #e2e8f0; }

.module-card { 
    background: white; border-radius: 16px; border: 1px solid #e2e8f0; 
    padding: 20px; margin-bottom: 15px; transition: all 0.3s ease;
}
.module-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }

.module-icon { 
    width: 50px; height: 50px; border-radius: 12px; display: flex; 
    align-items: center; justify-content: center; font-size: 24px;
}
.module-icon.video { background: #dbeafe; color: #2563eb; }
.module-icon.document { background: #d1fae5; color: #059669; }
.module-icon.quiz { background: #fef3c7; color: #d97706; }
.module-icon.competency { background: #fce7f3; color: #db2777; }

.stat-circle {
    width: 80px; height: 80px; border-radius: 50%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #1e40af, #2563eb); color: white;
}
.stat-circle.completed { background: linear-gradient(135deg, #059669, #10b981); }
.stat-circle.in-progress { background: linear-gradient(135deg, #2563eb, #3b82f6); }

.activity-item {
    display: flex; align-items: center; padding: 12px; border-bottom: 1px solid #e2e8f0;
}
.activity-item:last-child { border-bottom: none; }
.activity-icon {
    width: 36px; height: 36px; border-radius: 50%; display: flex;
    align-items: center; justify-content: center; margin-right: 12px;
}
</style>

<div class="content-header">
    <h2><i class="fas fa-book"></i> <?= htmlspecialchars($pageTitle) ?></h2>
    <p class="text-muted"><?= htmlspecialchars($pageSubtitle) ?></p>
</div>

<!-- Progress Overview -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
    <div class="stat-card" style="text-align: center; padding: 25px;">
        <div class="stat-circle completed">
            <span style="font-size: 28px; font-weight: bold;"><?= $completedCount ?></span>
            <span style="font-size: 11px;">Completed</span>
        </div>
    </div>
    <div class="stat-card" style="text-align: center; padding: 25px;">
        <div class="stat-circle in-progress">
            <span style="font-size: 28px; font-weight: bold;"><?= $inProgressCount ?></span>
            <span style="font-size: 11px;">In Progress</span>
        </div>
    </div>
    <div class="stat-card" style="text-align: center; padding: 25px;">
        <div class="stat-circle">
            <span style="font-size: 28px; font-weight: bold;"><?= $totalModules - $completedCount - $inProgressCount ?></span>
            <span style="font-size: 11px;">Not Started</span>
        </div>
    </div>
    <div class="stat-card" style="text-align: center; padding: 25px;">
        <div style="font-size: 36px; font-weight: bold; color: #10b981;"><?= $progressPercent ?>%</div>
        <div style="font-size: 12px; color: #64748b;">Overall Progress</div>
    </div>
</div>

<!-- Overall Progress Bar -->
<div class="card" style="padding: 25px; margin-bottom: 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <h3>Overall Progress</h3>
        <span style="font-weight: bold; color: #10b981;"><?= $progressPercent ?>% Complete</span>
    </div>
    <div class="module-progress" style="height: 12px;">
        <div class="module-progress-bar progress-complete" style="width: <?= $progressPercent ?>%;"></div>
    </div>
    <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 12px; color: #64748b;">
        <span>0%</span>
        <span>25%</span>
        <span>50%</span>
        <span>75%</span>
        <span>100%</span>
    </div>
</div>

<!-- In Progress Modules -->
<?php if (!empty($inProgress)): ?>
<div class="card" style="padding: 25px; margin-bottom: 25px;">
    <h3 style="margin-bottom: 20px; color: #2563eb;"><i class="fas fa-play-circle"></i> Continue Learning</h3>
    <?php foreach ($inProgress as $mod): ?>
    <div class="module-card">
        <div style="display: flex; justify-content: space-between; align-items: start;">
            <div style="display: flex; gap: 15px;">
                <div class="module-icon video">
                    <i class="fas fa-play"></i>
                </div>
                <div>
                    <h4 style="margin: 0 0 5px 0;"><?= htmlspecialchars($mod['module_title']) ?></h4>
                    <p style="margin: 0; font-size: 13px; color: #64748b;"><?= htmlspecialchars($mod['module_description'] ?? '') ?></p>
                    <div style="margin-top: 10px;">
                        <span style="font-size: 12px; color: #3b82f6; font-weight: 600;"><?= $mod['progress_percentage'] ?? 0 ?>% Complete</span>
                        <?php if (!empty($mod['time_spent_minutes'])): ?>
                        <span style="font-size: 12px; color: #64748b; margin-left: 15px;">⏱ <?= $mod['time_spent_minutes'] ?> mins</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <a href="module_lesson.php?id=<?= $mod['module_id'] ?>" class="btn" style="padding: 10px 20px; background: #2563eb; color: white; border-radius: 8px; text-decoration: none;">
                Continue
            </a>
        </div>
        <div class="module-progress" style="margin-top: 15px;">
            <div class="module-progress-bar progress-in-progress" style="width: <?= $mod['progress_percentage'] ?? 0 ?>%;"></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Not Started Modules -->
<?php if (!empty($notStarted)): ?>
<div class="card" style="padding: 25px; margin-bottom: 25px;">
    <h3 style="margin-bottom: 20px; color: #64748b;"><i class="fas fa-lock"></i> Modules to Complete</h3>
    <?php foreach ($notStarted as $mod): ?>
    <div class="module-card" style="opacity: 0.8;">
        <div style="display: flex; justify-content: space-between; align-items: start;">
            <div style="display: flex; gap: 15px;">
                <div class="module-icon" style="background: #f1f5f9; color: #64748b;">
                    <i class="fas fa-book"></i>
                </div>
                <div>
                    <h4 style="margin: 0 0 5px 0;"><?= htmlspecialchars($mod['module_title']) ?></h4>
                    <p style="margin: 0; font-size: 13px; color: #64748b;"><?= htmlspecialchars($mod['module_description'] ?? '') ?></p>
                    <div style="margin-top: 10px;">
                        <span style="font-size: 12px; color: #94a3b8;">Not Started</span>
                        <?php if (!empty($mod['duration_mins'])): ?>
                        <span style="font-size: 12px; color: #64748b; margin-left: 15px;">⏱ <?= $mod['duration_mins'] ?> mins</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <a href="module_lesson.php?id=<?= $mod['module_id'] ?>" class="btn" style="padding: 10px 20px; background: #64748b; color: white; border-radius: 8px; text-decoration: none;">
                Start
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Completed Modules -->
<?php if (!empty($completed)): ?>
<div class="card" style="padding: 25px; margin-bottom: 25px;">
    <h3 style="margin-bottom: 20px; color: #10b981;"><i class="fas fa-check-circle"></i> Completed Modules</h3>
    <?php foreach ($completed as $mod): ?>
    <div class="module-card" style="opacity: 0.7; border-color: #10b981;">
        <div style="display: flex; justify-content: space-between; align-items: start;">
            <div style="display: flex; gap: 15px;">
                <div class="module-icon" style="background: #d1fae5; color: #059669;">
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h4 style="margin: 0 0 5px 0;"><?= htmlspecialchars($mod['module_title']) ?></h4>
                    <div style="margin-top: 10px;">
                        <?php if (!empty($mod['final_score'])): ?>
                        <span style="font-size: 12px; color: #10b981; font-weight: 600;">Score: <?= $mod['final_score'] ?>%</span>
                        <?php endif; ?>
                        <?php if (!empty($mod['completion_date'])): ?>
                        <span style="font-size: 12px; color: #64748b; margin-left: 15px;">Completed: <?= date('M d, Y', strtotime($mod['completion_date'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <a href="module_lesson.php?id=<?= $mod['module_id'] ?>" class="btn" style="padding: 10px 20px; background: #10b981; color: white; border-radius: 8px; text-decoration: none;">
                Review
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($modules)): ?>
<div class="card" style="padding: 50px; text-align: center;">
    <i class="fas fa-book-open" style="font-size: 48px; color: #cbd5e1; margin-bottom: 20px;"></i>
    <h3>No Modules Available</h3>
    <p>You don't have any learning modules assigned yet. Please contact your instructor or administrator.</p>
</div>
<?php endif; ?>

<style>
.btn { display: inline-block; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
</style>