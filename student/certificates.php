<?php
/**
 * Student Portal - My Certificates
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

// Get certificates (if table exists)
$certificates = [];
$certTableExists = false;
try {
    $checkCertTable = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'certificates' LIMIT 1");
    $certTableExists = (bool)$checkCertTable->fetchColumn();
} catch (Exception $e) {
    $certTableExists = false;
}

if ($certTableExists) {
    try {
        $certsStmt = $conn->prepare("
            SELECT c.*, p.program_code, p.program_title,
                   CONCAT(pr.Fname, ' ', pr.Lname) as prepared_by_name
            FROM certificates c
            JOIN auto_mechanic_programs p ON c.program_id = p.program_id
            LEFT JOIN admins pr ON c.prepared_by = pr.admin_id
            WHERE c.student_id = ?
            ORDER BY c.issue_date DESC
        ");
        $certsStmt->execute([$studentId]);
        $certificates = $certsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $certificates = [];
    }
}

// Get competencies/certificates from enrollment too (if table exists)
$achievements = [];
$competencyTableExists = false;
try {
    $checkTable = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competency_achievements' LIMIT 1");
    $competencyTableExists = (bool)$checkTable->fetchColumn();
} catch (Exception $e) {
    $competencyTableExists = false;
}

if ($competencyTableExists) {
    try {
        $enrollmentCerts = $conn->prepare("
            SELECT 
                ca.*,
                s.module_id,
                GROUP_CONCAT(DISTINCT m.module_code SEPARATOR ', ') as module_codes
            FROM competency_achievements ca
            JOIN student_program_enrollments spe ON ca.enrollment_id = spe.enrollment_id
            LEFT JOIN training_modules m ON ca.competency_id = m.competency_unit_id
            WHERE spe.student_id = ?
            GROUP BY ca.achievement_id
            ORDER BY ca.certificate_date DESC
        ");
        $enrollmentCerts->execute([$studentId]);
        $achievements = $enrollmentCerts->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $achievements = [];
    }
}

$pageTitle = "My Certificates";
$pageSubtitle = "Competency Certificates & Achievements - " . $studentNcLevel;
$currentPage = 'certificates.php';
include 'sidebar_student.php';
?>

<div class="content-header">
    <h2><i class="fas fa-certificate"></i> <?= htmlspecialchars($pageTitle) ?></h2>
    <p class="text-muted"><?= htmlspecialchars($pageSubtitle) ?> - <strong><?= htmlspecialchars($studentNcLevel) ?></strong></p>
</div>

<?php if (!$isEnrolled): ?>
<div style="padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; background: #fef3c7; color: #d97706;">
    <i class="fas fa-exclamation-triangle"></i> <strong>Not Enrolled</strong> - You are not currently enrolled. Please contact the admin/staff to enroll you.
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= count($certificates) ?></div>
        <div class="stat-label">Certificates Issued</div>
    </div>
    <div class="stat-card text-success">
        <div class="stat-value"><?= count(array_filter($certificates, fn($c) => $c['status'] === 'Issued' || $c['status'] === 'Active')) ?></div>
        <div class="stat-label">Active</div>
    </div>
    <div class="stat-card text-warning">
        <div class="stat-value"><?= count($achievements) ?></div>
        <div class="stat-label">Competency Units</div>
    </div>
</div>

<!-- Certificates -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Issued Certificates</h3>
        <span class="badge"><?= count($certificates) ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($certificates)): ?>
            <div class="empty-state" style="text-align: center; padding: 3rem;">
                <i class="fas fa-certificate" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                <p>No certificates issued yet.</p>
                <p class="text-muted">Certificates are generated automatically upon completion of competency assessments.</p>
            </div>
        <?php else: ?>
            <div class="records-grid">
                <?php foreach ($certificates as $c): ?>
                <div class="record-card" style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; position: relative;">
                    <div style="position: absolute; top: 1rem; right: 1rem;">
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $c['status'])) ?>">
                            <?= htmlspecialchars($c['status']) ?>
                        </span>
                    </div>
                    <h4 style="margin: 0 0 0.5rem 0; color: #1e3a8a;">
                        <?= htmlspecialchars($c['certificate_type']) ?>
                    </h4>
                    <p style="margin: 0.5rem 0;">
                        <?= htmlspecialchars($c['program_code'] . ' - ' . $c['program_title']) ?>
                    </p>
                    <?php if ($c['nc_level']): ?>
                        <p style="margin: 0.25rem 0;">
                            <strong>NC Level:</strong> <?= htmlspecialchars($c['nc_level']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($c['honors']): ?>
                        <p style="margin: 0.25rem 0; color: #f59e0b;">
                            <strong>Honors:</strong> <?= htmlspecialchars($c['honors']) ?>
                        </p>
                    <?php endif; ?>
                    <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #e5e7eb; font-size: 0.9rem; color: #6b7280;">
                        Issued: <?= htmlspecialchars($c['issue_date']) ?><br>
                        Prepared by: <?= htmlspecialchars($c['prepared_by_name'] ?? 'System') ?>
                    </div>
                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                        <?php if ($c['pdf_generated'] && $c['pdf_file_path'] && file_exists($c['pdf_file_path'])): ?>
                            <a href="<?= htmlspecialchars($c['pdf_file_path']) ?>" class="btn btn-sm btn-primary" target="_blank">
                                <i class="fas fa-download"></i> Download
                            </a>
                        <?php endif; ?>
                        <?php if ($c['verification_code']): ?>
                            <button class="btn btn-sm btn-secondary" onclick="verifyCert('<?= htmlspecialchars($c['verification_code']) ?>')">
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

<!-- Competency Achievements -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Competency Units Completed</h3>
        <span class="badge"><?= count($achievements) ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($achievements)): ?>
            <p class="text-muted">No competency achievements recorded.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Competency/Task</th>
                        <th>Date Achieved</th>
                        <th>Certificate Number</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($achievements as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['competency_title'] ?? 'Competency #' . $a['competency_id']) ?></td>
                            <td><?= htmlspecialchars($a['certificate_date']) ?></td>
                            <td><?= htmlspecialchars($a['certificate_number'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge status-approved">
                                    <?= htmlspecialchars($a['certificate_issued'] ? 'Issued' : 'Pending') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function verifyCert(code) {
    window.open('../verify/certificate.php?code=' + code, '_blank', 'width=600,height=500');
}
</script>

</div>

</main>
</div>

<?php include '../includes/footer.php'; ?>
