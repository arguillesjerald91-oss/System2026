<?php
/**
 * Enhanced My Assessments Page - Advanced Features & UI
 */
session_start();
include __DIR__ . '/../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['userId'])) {
    header('Location: ../login.php');
    exit();
}

$userId = $_SESSION['userId'];
$userRole = $_SESSION['userRole'] ?? 'trainee';
$userType = ($userRole === 'student') ? 'trainee' : $userRole;

$userName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));

// Get enrollment
$enrollment = null;
$studentNcLevel = 'NC I';
if ($conn !== null) {
    try {
        $stmt = $conn->prepare("
            SELECT spe.*, p.program_name
            FROM student_program_enrollments spe
            LEFT JOIN programs p ON spe.program_id = p.program_id
            WHERE spe.student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1)
            AND spe.enrollment_status = 'Active'
            ORDER BY spe.enrollment_id DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($enrollment) {
            $studentNcLevel = $enrollment['nc_level'] ?? 'NC I';
        }
    } catch (Exception $e) {
        $enrollment = null;
    }
}

// Get assessments
$assessments = [];
$completed = [];
$pending = [];
$inProgress = [];

if ($conn !== null) {
    try {
        // Get competency assessments with results
        $stmt = $conn->prepare("
            SELECT ca.*, lm.module_title, lm.competency_code
            FROM student_competency_assessments ca
            LEFT JOIN learning_modules lm ON ca.competency_id = lm.module_id
            WHERE ca.student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1)
            ORDER BY ca.assessment_date DESC
        ");
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $r) {
            if ($r['status'] === 'Passed' || $r['status'] === 'Completed') {
                $completed[] = $r;
            } elseif ($r['status'] === 'In Progress' || $r['status'] === 'Started') {
                $inProgress[] = $r;
            } else {
                $pending[] = $r;
            }
        }
    } catch (Exception $e) {
        // Table might not exist yet
        $completed = [];
        $inProgress = [];
        $pending = [];
    }
}

// Get module-based assessments filtered by NC level
$moduleAssessments = [];
if ($conn !== null && $enrollment) {
    try {
        // Get modules for student's NC level
        $stmt = $conn->prepare("
            SELECT lm.module_id, lm.module_title, lm.module_type, lm.nc_level, lm.competency_code,
                   COALESCE(mp.progress_percentage, 0) as progress_percentage,
                   COALESCE(mp.status, 'Not Started') as status,
                   mp.final_score, mp.completion_date
            FROM learning_modules lm
            LEFT JOIN module_progress mp ON lm.module_id = mp.module_id AND mp.user_id = ?
            WHERE lm.nc_level = ? AND lm.is_active = 1
            ORDER BY lm.module_type, lm.module_title
        ");
        $stmt->execute([$userId, $studentNcLevel]);
        $moduleAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $conn->prepare("
            SELECT lm.*, smp.progress_percentage, smp.status as progress_status, smp.final_score
            FROM learning_modules lm
            LEFT JOIN student_module_progress smp ON lm.module_id = smp.module_id 
                AND smp.enrollment_id = ?
            WHERE lm.is_active = 1 AND (lm.nc_level = ? OR lm.nc_level LIKE ?)
            ORDER BY lm.module_order ASC
        ");
        $stmt->execute([$enrollment['enrollment_id'] ?? 0, $studentNcLevel, $studentNcLevel . '%']);
        $moduleAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $moduleAssessments = [];
    }
}

// Calculate stats
$totalAssessments = count($completed) + count($inProgress) + count($pending);
$passRate = $totalAssessments > 0 ? round((count($completed) / $totalAssessments) * 100) : 0;

$pageTitle = "My Assessments";
$programName = $enrollment['program_name'] ?? 'Training Program';
$pageSubtitle = $enrollment ? $programName . ' - ' . $studentNcLevel : "Competency Assessments";
include 'sidebar_student.php';
?>

<style>
.assessment-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}
.assessment-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.assessment-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}
.status-passed { background: #d1fae5; color: #059669; }
.status-failed { background: #fee2e2; color: #dc2626; }
.status-pending { background: #fef3c7; color: #d97706; }
.status-progress { background: #dbeafe; color: #2563eb; }

.assessment-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}
.icon-passed { background: #d1fae5; color: #059669; }
.icon-failed { background: #fee2e2; color: #dc2626; }
.icon-pending { background: #f1f5f9; color: #64748b; }
.icon-progress { background: #dbeafe; color: #2563eb; }

.stat-highlight {
    background: linear-gradient(135deg, #2563eb, #1e40af);
    color: white;
    border-radius: 16px;
    padding: 25px;
    text-align: center;
}
.stat-highlight h3 {
    font-size: 36px;
    margin: 0;
}
</style>

<div class="content-header">
    <h2><i class="fas fa-clipboard-check"></i> <?= htmlspecialchars($pageTitle) ?></h2>
    <p class="text-muted"><?= htmlspecialchars($pageSubtitle) ?></p>
</div>

<?php if (!$enrollment): ?>
<div style="padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; background: #fef3c7; color: #d97706;">
    <i class="fas fa-exclamation-triangle"></i> <strong>Not Enrolled</strong> - You are not currently enrolled. Please contact the admin/staff to enroll you.
</div>
<?php endif; ?>

<!-- Assessment Stats -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
    <div class="stat-highlight">
        <h3><?= count($completed) ?></h3>
        <p>Passed</p>
    </div>
    <div class="stat-card" style="text-align: center; padding: 25px;">
        <div style="font-size: 32px; font-weight: bold; color: #f59e0b;"><?= count($inProgress) ?></div>
        <div style="font-size: 12px; color: #64748b;">In Progress</div>
    </div>
    <div class="stat-card" style="text-align: center; padding: 25px;">
        <div style="font-size: 32px; font-weight: bold; color: #64748b;"><?= count($pending) ?></div>
        <div style="font-size: 12px; color: #64748b;">Pending</div>
    </div>
    <div class="stat-card" style="text-align: center; padding: 25px;">
        <div style="font-size: 32px; font-weight: bold; color: #10b981;"><?= $passRate ?>%</div>
        <div style="font-size: 12px; color: #64748b;">Pass Rate</div>
    </div>
</div>

<!-- Passed Assessments -->
<?php if (!empty($completed)): ?>
<div class="card" style="padding: 25px; margin-bottom: 25px;">
    <h3 style="color: #10b981; margin-bottom: 20px;"><i class="fas fa-check-circle"></i> Passed Assessments</h3>
    <?php foreach ($completed as $assess): ?>
    <div class="assessment-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 15px;">
                <div class="assessment-icon icon-passed">
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h4 style="margin: 0 0 5px 0;"><?= htmlspecialchars($assess['module_title'] ?? $assess['title'] ?? 'Assessment') ?></h4>
                    <?php if (!empty($assess['score']) || !empty($assess['final_score'])): ?>
                    <div style="margin-top: 8px;">
                        <span style="font-weight: bold; color: #10b981;">Score: <?= $assess['score'] ?? $assess['final_score'] ?? 0 ?>%</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <span class="assessment-status status-passed">
                <i class="fas fa-check"></i> Passed
            </span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- In Progress -->
<?php if (!empty($inProgress)): ?>
<div class="card" style="padding: 25px; margin-bottom: 25px;">
    <h3 style="color: #2563eb; margin-bottom: 20px;"><i class="fas fa-spinner"></i> In Progress</h3>
    <?php foreach ($inProgress as $assess): ?>
    <div class="assessment-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 15px;">
                <div class="assessment-icon icon-progress">
                    <i class="fas fa-spinner"></i>
                </div>
                <div>
                    <h4 style="margin: 0 0 5px 0;"><?= htmlspecialchars($assess['module_title'] ?? $assess['title'] ?? 'Assessment') ?></h4>
                </div>
            </div>
            <span class="assessment-status status-progress">
                <i class="fas fa-play"></i> In Progress
            </span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Pending Assessments -->
<?php if (!empty($pending)): ?>
<div class="card" style="padding: 25px; margin-bottom: 25px;">
    <h3 style="color: #f59e0b; margin-bottom: 20px;"><i class="fas fa-clock"></i> Pending Assessments</h3>
    <?php foreach ($pending as $assess): ?>
    <div class="assessment-card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 15px;">
                <div class="assessment-icon icon-pending">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div>
                    <h4 style="margin: 0 0 5px 0;"><?= htmlspecialchars($assess['module_title'] ?? $assess['title'] ?? 'Assessment') ?></h4>
                </div>
            </div>
            <span class="assessment-status status-pending">
                <i class="fas fa-clock"></i> Pending
            </span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- No Assessments -->
<?php if (empty($completed) && empty($inProgress) && empty($pending) && empty($moduleAssessments)): ?>
<div class="card" style="padding: 50px; text-align: center;">
    <i class="fas fa-clipboard" style="font-size: 48px; color: #cbd5e1; margin-bottom: 20px;"></i>
    <h3>No Assessments Yet</h3>
    <p>Complete your learning modules to unlock assessments.</p>
    <a href="learning_modules.php" class="btn btn-primary" style="display: inline-block; margin-top: 15px; padding: 12px 24px; background: #2563eb; color: white; border-radius: 8px; text-decoration: none;">
        Go to Modules
    </a>
</div>
<?php endif; ?>

<!-- Module Progress Section -->
<?php if (!empty($moduleAssessments)): ?>
<div class="card" style="padding: 25px; margin-top: 25px;">
    <h3 style="margin-bottom: 20px;"><i class="fas fa-book"></i> Module Progress</h3>
    <table>
        <thead>
            <tr>
                <th>Module</th>
                <th>Progress</th>
                <th>Status</th>
                <th>Score</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($moduleAssessments as $mod): ?>
            <tr>
                <td style="text-align: left;"><?= htmlspecialchars($mod['module_title']) ?></td>
                <td>
                    <div style="width: 100px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                        <div style="width: <?= $mod['progress_percentage'] ?? 0 ?>%; height: 100%; background: #2563eb; border-radius: 3px;"></div>
                    </div>
                    <span style="font-size: 11px;"><?= $mod['progress_percentage'] ?? 0 ?>%</span>
                </td>
                <td>
                    <?php if (($mod['progress_status'] ?? '') === 'Completed' || ($mod['progress_percentage'] ?? 0) >= 100): ?>
                    <span class="badge badge-success">Completed</span>
                    <?php elseif (($mod['progress_percentage'] ?? 0) > 0): ?>
                    <span class="badge badge-warning">In Progress</span>
                    <?php else: ?>
                    <span class="badge badge-primary">Not Started</span>
                    <?php endif; ?>
                </td>
                <td><?= $mod['final_score'] ?? '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>