<?php
/**
 * Student Portal - My Diplomas
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];
if (!in_array($userType, ['student', 'trainee'])) {
    header("Location: ../login.php");
    exit();
}

$studentStmt = $conn->prepare("SELECT * FROM student WHERE user_id = ?");
$studentStmt->execute([$userId]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);
if (!$student) die('Student not found');
$studentId = $student['StudID'];

// Check enrollment status and get NC level
$enrollStmt = $conn->prepare("SELECT nc_level FROM student_program_enrollments WHERE student_id = ? AND enrollment_status = 'Active' LIMIT 1");
$enrollStmt->execute([$studentId]);
$ncLevel = $enrollStmt->fetchColumn() ?: 'NC I';
$isEnrolled = !empty($ncLevel);
$studentNcLevel = $ncLevel;

if (!$isEnrolled) {
    $message = "You are not enrolled. Please contact admin/staff to enroll you.";
    $messageType = 'warning';
}

// Get diplomas
$diplomasStmt = $conn->prepare("
    SELECT d.*, p.program_code, p.program_title, b.batch_code
    FROM diplomas d
    JOIN auto_mechanic_programs p ON d.program_id = p.program_id
    LEFT JOIN training_batches b ON d.batch_id = b.batch_id
    WHERE d.student_id = ?
    ORDER BY d.graduation_date DESC
");
$diplomasStmt->execute([$studentId]);
$diplomas = $diplomasStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "My Diplomas";
$pageSubtitle = "Graduation Documents - " . $studentNcLevel;
$currentPage = 'diplomas.php';
include 'sidebar_student.php';
?>

<div class="content-header">
    <h2><i class="fas fa-award"></i> <?= htmlspecialchars($pageTitle) ?></h2>
    <p class="text-muted"><?= htmlspecialchars($pageSubtitle) ?> - <strong><?= htmlspecialchars($studentNcLevel) ?></strong></p>
</div>

<?php if (!$isEnrolled): ?>
<div style="padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; background: #fef3c7; color: #d97706;">
    <i class="fas fa-exclamation-triangle"></i> <strong>Not Enrolled</strong> - You are not currently enrolled. Please contact the admin/staff to enroll you.
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= count($diplomas) ?></div>
        <div class="stat-label">Total Diplomas</div>
    </div>
    <div class="stat-card text-success">
        <div class="stat-value"><?= count(array_filter($diplomas, fn($d) => $d['conferred'])) ?></div>
        <div class="stat-label">Awarded</div>
    </div>
    <div class="stat-card text-warning">
        <div class="stat-value"><?= count(array_filter($diplomas, fn($d) => $d['status'] === 'Printed')) ?></div>
        <div class="stat-label">Ready for Pickup</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">My Diplomas</h3>
        <span class="badge"><?= count($diplomas) ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($diplomas)): ?>
            <div class="empty-state" style="text-align: center; padding: 3rem;">
                <i class="fas fa-award" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                <p>No diplomas issued yet.</p>
                <p class="text-muted">Upon successful completion of your program, your diploma will appear here.</p>
            </div>
        <?php else: ?>
            <div class="records-grid">
                <?php foreach ($diplomas as $d): ?>
                <div class="record-card" style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <h4 style="margin: 0 0 0.5rem 0; color: #1e3a8a;">
                                <?= htmlspecialchars($d['diploma_number']) ?>
                            </h4>
                            <p style="margin: 0.5rem 0;">
                                <?= htmlspecialchars($d['program_code'] . ' - ' . $d['program_title']) ?>
                            </p>
                            <p style="margin: 0.25rem 0;">
                                Honors: <strong><?= htmlspecialchars($d['honors']) ?></strong>
                            </p>
                        </div>
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $d['status'])) ?>">
                            <?= htmlspecialchars($d['status']) ?>
                        </span>
                    </div>

                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>GPA:</span>
                            <strong><?= number_format($d['general_average'], 2) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Units Earned:</span>
                            <span><?= $d['units_earned'] ?></span>
                        </div>
                        <?php if ($d['convocation_date']): ?>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Convocation:</span>
                            <span><?= htmlspecialchars($d['convocation_date']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($d['conferred']): ?>
                        <div style="display: flex; justify-content: space-between; color: #10b981;">
                            <span><i class="fas fa-check-circle"></i> Awarded on</span>
                            <span><?= htmlspecialchars($d['conferred_at']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                        <?php if ($d['pdf_generated'] && $d['pdf_file_path'] && file_exists($d['pdf_file_path'])): ?>
                            <a href="<?= htmlspecialchars($d['pdf_file_path']) ?>" class="btn btn-sm btn-primary" target="_blank">
                                <i class="fas fa-download"></i> Download
                            </a>
                        <?php endif; ?>
                        <?php if ($d['verification_code']): ?>
                            <button class="btn btn-sm btn-secondary" onclick="verifyDiploma('<?= htmlspecialchars($d['verification_code']) ?>')">
                                <i class="fas fa-check-circle"></i> Verify
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Request Diploma Replacement (only for conferred diplomas) -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Request Diploma Replacement</h3>
    </div>
    <div class="card-body">
        <p>Lost or damaged your diploma? You can request a replacement.</p>
        <button class="btn btn-warning" onclick="requestReplacement()">
            <i class="fas fa-exchange-alt"></i> Request Replacement
        </button>
    </div>
</div>

<script>
function verifyDiploma(code) {
    window.open('../verify/diploma.php?code=' + code, '_blank', 'width=600,height=500');
}

function requestReplacement() {
    window.location.href = 'request_document.php?type=Diploma Replacement';
}
</script>

</div>

</main>
</div>

<?php include '../includes/footer.php'; ?>
