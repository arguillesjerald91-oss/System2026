<?php
/**
 * Student Portal - My Transcripts
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

// Redirect if not logged in as student
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];

// Only students or trainees
if (!in_array($userType, ['student', 'trainee'])) {
    header("Location: ../login.php");
    exit();
}

// Get student record
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
$userName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));

if (!$isEnrolled) {
    $message = "You are not enrolled. Please contact admin/staff to enroll you.";
    $messageType = 'warning';
}

if (!$student) {
    die('Student record not found');
}

$studentId = $student['StudID'];

// Get all transcripts for this student
$transcripts = $conn->prepare("
    SELECT t.*, p.program_code, p.program_title, b.batch_code
    FROM transcripts t
    JOIN auto_mechanic_programs p ON t.program_id = p.program_id
    LEFT JOIN training_batches b ON t.batch_id = b.batch_id
    WHERE t.student_id = ?
    ORDER BY t.issue_date DESC, t.transcript_id DESC
");
$transcripts->execute([$studentId]);
$transcripts = $transcripts->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "My Transcripts";
$pageSubtitle = "Official Academic Records - " . $studentNcLevel;
$currentPage = 'transcripts.php';
include 'sidebar_student.php';
?>

<div class="content-header">
    <h2><i class="fas fa-file-alt"></i> <?= htmlspecialchars($pageTitle) ?></h2>
    <p class="text-muted"><?= htmlspecialchars($pageSubtitle) ?> - <strong><?= htmlspecialchars($studentNcLevel) ?></strong></p>
</div>

<?php if (!$isEnrolled): ?>
<div style="padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; background: #fef3c7; color: #d97706;">
    <i class="fas fa-exclamation-triangle"></i> <strong>Not Enrolled</strong> - You are not currently enrolled. Please contact the admin/staff to enroll you.
</div>
<?php endif; ?>

<!-- Stats Summary -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= count($transcripts) ?></div>
        <div class="stat-label">Total Transcripts</div>
    </div>
    <div class="stat-card text-success">
        <div class="stat-value">
            <?= count(array_filter($transcripts, fn($t) => $t['status'] === 'Issued' || $t['status'] === 'Delivered')) ?>
        </div>
        <div class="stat-label">Issued</div>
    </div>
    <div class="stat-card text-warning">
        <div class="stat-value">
            <?= count(array_filter($transcripts, fn($t) => $t['status'] === 'Draft' || $t['status'] === 'Pending Approval')) ?>
        </div>
        <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card text-primary">
        <div class="stat-value">
            <?= count(array_filter($transcripts, fn($t) => $t['status'] === 'Archived')) ?>
        </div>
        <div class="stat-label">Archived</div>
    </div>
</div>

<!-- Request New Transcript Button -->
<div style="margin: 1.5rem 0;">
    <button class="btn btn-primary" onclick="openRequestModal()">
        <i class="fas fa-plus"></i> Request New Transcript
    </button>
</div>

<!-- Transcripts List -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">My Transcripts</h3>
        <span class="badge"><?= count($transcripts) ?> records</span>
    </div>
    <div class="card-body">
        <?php if (empty($transracts)): // Typo: should be transcripts ?>
            <div class="empty-state">
                <i class="fas fa-file-alt" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                <p>You don't have any transcripts yet.</p>
                <button class="btn btn-primary" onclick="openRequestModal()">Request Transcript</button>
            </div>
        <?php else: ?>
            <div class="records-grid">
                <?php foreach ($transcripts as $t): ?>
                <div class="record-card" style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <h4 style="margin: 0 0 0.5rem 0; color: #1e3a8a;">
                                <?= htmlspecialchars($t['transcript_number']) ?>
                            </h4>
                            <div class="text-muted" style="font-size: 0.9rem;">
                                <?= htmlspecialchars($t['program_code'] . ' - ' . $t['program_title']) ?>
                            </div>
                            <div><?= htmlspecialchars($t['batch_code'] ?? 'No Batch') ?></div>
                        </div>
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $t['status'])) ?>">
                            <?= htmlspecialchars($t['status']) ?>
                        </span>
                    </div>

                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>GPA:</span>
                            <strong style="color: #2563eb; font-size: 1.1rem;"><?= number_format($t['gpa'], 2) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Units:</span>
                            <span><?= $t['total_units'] ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Issue Date:</span>
                            <span><?= htmlspecialchars($t['issue_date']) ?></span>
                        </div>
                    </div>

                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                        <?php if ($t['pdf_generated'] && $t['pdf_file_path']): ?>
                            <a href="<?= htmlspecialchars($t['pdf_file_path']) ?>" class="btn btn-sm btn-primary" target="_blank">
                                <i class="fas fa-download"></i> Download
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-secondary" onclick="viewDetails(<?= $t['transcript_id'] ?>)">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                        <?php if ($t['verification_code']): ?>
                            <button class="btn btn-sm btn-outline" onclick="verifyDocument('<?= htmlspecialchars($t['verification_code']) ?>')">
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

<!-- Request Transcript Modal -->
<div class="modal" id="requestModal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Request New Transcript</h3>
            <button class="btn-close" onclick="closeRequestModal()">&times;</button>
        </div>
        <form id="requestForm" method="POST" action="create_document_request.php">
            <div class="modal-body">
                <div class="form-group">
                    <label>Document Type</label>
                    <select name="document_type" required>
                        <option value="Official Transcript">Official Transcript</option>
                        <option value="Transcript Copy">Transcript Copy</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Purpose</label>
                    <select name="purpose">
                        <option value="Employment">Employment</option>
                        <option value="Further Studies">Further Studies</option>
                        <option value="Scholarship">Scholarship Application</option>
                        <option value="Personal Copy">Personal Copy</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Number of Copies</label>
                    <input type="number" name="copies" value="1" min="1" max="5">
                </div>
                <div class="form-group">
                    <label>Collection Method</label>
                    <select name="collection_method">
                        <option value="Pickup">Pickup at Registrar</option>
                        <option value="Mail">Mail Delivery</option>
                        <option value="Digital Download">Digital Download</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Urgent?</label>
                    <select name="urgent">
                        <option value="0">No</option>
                        <option value="1">Yes (additional fee may apply)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Additional Notes</label>
                    <textarea name="details" rows="3" placeholder="Any special instructions..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRequestModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRequestModal() {
    document.getElementById('requestModal').style.display = 'flex';
}

function closeRequestModal() {
    document.getElementById('requestModal').style.display = 'none';
}

function viewDetails(id) {
    window.open('view_transcript.php?id=' + id, '_blank', 'width=800,height=600');
}

function verifyDocument(code) {
    window.open('../verify/transcript.php?code=' + code, '_blank');
}
</script>

</div>
</div>
</main>
</div>

<?php include '../includes/footer.php'; ?>
