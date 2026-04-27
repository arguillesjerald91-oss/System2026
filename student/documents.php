<?php
/**
 * Student Documents Center
 * View all documents available to the student
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$userType = $_SESSION['user_type'];
if (!in_array($userType, ['student', 'trainee'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
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

// Get documents for this student
$docStmt = $conn->prepare("
    SELECT d.*, dc.category_name
    FROM documents d
    LEFT JOIN document_categories dc ON d.category_id = dc.category_id
    WHERE d.student_id = ? AND d.status = 'Approved'
    ORDER BY d.created_at DESC
");
$docStmt->execute([$studentId]);
$documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "My Documents";
$pageSubtitle = "Repository of all your uploaded and issued documents - " . $studentNcLevel;
$currentPage = 'documents.php';
include 'sidebar_student.php';
?>

<div class="content-header">
    <h2><i class="fas fa-folder-open"></i> <?= htmlspecialchars($pageTitle) ?></h2>
    <p class="text-muted"><?= htmlspecialchars($pageSubtitle) ?> - <strong><?= htmlspecialchars($studentNcLevel) ?></strong></p>
</div>

<?php if (!$isEnrolled): ?>
<div style="padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; background: #fef3c7; color: #d97706;">
    <i class="fas fa-exclamation-triangle"></i> <strong>Not Enrolled</strong> - You are not currently enrolled. Please contact the admin/staff to enroll you.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Available Documents</h3>
        <a href="request_document.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Request New Document
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($documents)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                <p>No documents in your repository yet.</p>
                <a href="request_document.php" class="btn btn-primary">Request a Document</a>
            </div>
        <?php else: ?>
            <div class="records-grid">
                <?php foreach ($documents as $doc): ?>
                <div class="record-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <h4 style="margin:0; color: #1e3a8a;"><?= htmlspecialchars($doc['title']) ?></h4>
                        <span class="badge badge-<?= $doc['confidentiality_level'] === 'Public' ? 'success' : 'warning' ?>">
                            <?= htmlspecialchars($doc['confidentiality_level']) ?>
                        </span>
                    </div>
                    <p class="text-muted" style="font-size: 0.9rem;">
                        <?= htmlspecialchars($doc['category_name'] ?? 'Document') ?> • 
                        <?= date('M j, Y', strtotime($doc['created_at'])) ?>
                    </p>
                    <?php if ($doc['description']): ?>
                        <p style="font-size: 0.9rem; color: #4b5563;"><?= htmlspecialchars(substr($doc['description'], 0, 100)) ?>...</p>
                    <?php endif; ?>
                    <div style="margin-top: 1rem;">
                        <?php if ($doc['file_path'] && file_exists($doc['file_path'])): ?>
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" class="btn btn-sm btn-primary" target="_blank">
                                <i class="fas fa-download"></i> Download
                            </a>
                        <?php endif; ?>
                        <span style="font-size: 0.85rem; color: #6b7280;">
                            Downloaded <?= number_format($doc['access_count']) ?> times
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>

<?php include '../includes/footer.php'; ?>
