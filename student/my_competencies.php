<?php
/**
 * Trainee Competencies Page
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

if (!in_array($userType, ['trainee', 'student'])) {
    header("Location: ../login.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

// Get student's NC level from enrollment
$studentNcLevel = 'NC I';
$isEnrolled = false;
try {
    $ncStmt = $conn->prepare("
        SELECT nc_level FROM student_program_enrollments 
        WHERE student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1) 
        AND enrollment_status = 'Active' 
        LIMIT 1
    ");
    $ncStmt->execute([$userId]);
    $ncLevel = $ncStmt->fetchColumn();
    if ($ncLevel) {
        $isEnrolled = true;
        $studentNcLevel = $ncLevel;
    }
} catch (Exception $e) {
    $studentNcLevel = 'NC I';
}

// Get trainee's competency assessments (filtered by NC level from enrollment)
$assessments = [];
try {
    $stmt = $conn->prepare("
        SELECT ca.assess_id, ca.unit_id, ca.assessment_status, ca.final_score, ca.assessment_date,
               cu.unit_title, cu.unit_code, cu.nctype AS competency_category
        FROM competency_assessments ca
        JOIN competency_units cu ON ca.unit_id = cu.unit_id
        WHERE ca.user_id = ?
        ORDER BY ca.assessment_date DESC
    ");
    $stmt->execute([$userId]);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $assessments = [];
}

$passedCount = count(array_filter($assessments, fn($a) => $a['assessment_status'] === 'Passed'));
$failedCount = count(array_filter($assessments, fn($a) => $a['assessment_status'] === 'Failed'));
$pendingCount = count(array_filter($assessments, fn($a) => $a['assessment_status'] === 'Pending' || $a['assessment_status'] === 'Not Started' || $a['assessment_status'] === 'In Progress'));

$currentPage = 'my_competencies.php';
$pageTitle = "My Assessments";
$pageSubtitle = "Competency evaluation results";
include 'sidebar_student.php';
?>

<div class="page-content">
    
<div class="welcome-banner bg-success">
    <h2>My Assessments</h2>
    <p class="subtitle">Track your competency evaluation results - <strong><?= htmlspecialchars($studentNcLevel) ?></strong></p>
</div>

<?php if (!$isEnrolled): ?>
<div style="padding: 15px 20px; margin: 20px; background: #fef3c7; border-radius: 8px; color: #d97706;">
    <i class="fas fa-exclamation-triangle"></i> <strong>Not Enrolled</strong> - You are not currently enrolled. Please contact the admin/staff to enroll you.
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Assessments</div>
        <div class="stat-value"><?= count($assessments) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Passed</div>
        <div class="stat-value" style="color: #10b981;"><?= $passedCount ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending</div>
        <div class="stat-value" style="color: #f59e0b;"><?= $pendingCount ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Assessment History</h3>
    </div>
    <div class="card-body">
        <?php if (empty($assessments)): ?>
            <div style="text-align: center; padding: 40px; color: #64748b;">
                <p style="font-size: 18px; margin-bottom: 10px;">No assessments yet</p>
                <p style="font-size: 14px;">Complete your learning modules to take competency assessments.</p>
            </div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Unit Code</th>
                    <th>Competency</th>
                    <th>Category</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assessments as $asm): ?>
                <tr>
                    <td><?= htmlspecialchars($asm['unit_code']) ?></td>
                    <td><?= htmlspecialchars($asm['unit_title']) ?></td>
                    <td><?= htmlspecialchars($asm['competency_category']) ?></td>
                    <td><?= $asm['final_score'] !== null ? $asm['final_score'] . '%' : '-' ?></td>
                    <td>
                        <span class="badge <?= $asm['assessment_status'] === 'Passed' ? 'badge-green' : ($asm['assessment_status'] === 'Failed' ? 'badge-red' : 'badge-blue') ?>">
                            <?= htmlspecialchars($asm['assessment_status']) ?>
                        </span>
                    </td>
                    <td><?= $asm['assessment_date'] ? date('M d, Y', strtotime($asm['assessment_date'])) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
