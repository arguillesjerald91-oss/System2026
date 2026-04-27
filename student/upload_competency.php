<?php
/**
 * Advanced Competency Upload Page
 * Enhanced with role-based access, NC level filtering, and advanced features
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

$message = '';
$messageType = '';

$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'video/mp4', 'video/quicktime'];
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'mp4', 'mov'];
$maxFileSize = 50 * 1024 * 1024;

$conn->exec("CREATE TABLE IF NOT EXISTS `competency_submissions` (
    `submission_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `competency_id` INT NOT NULL,
    `unit_code` VARCHAR(50),
    `evidence_file` VARCHAR(255),
    `evidence_name` VARCHAR(255),
    `description` TEXT,
    `status` ENUM('Pending','Submitted','Under Review','Approved','Rejected') DEFAULT 'Submitted',
    `reviewer_notes` TEXT,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `reviewed_by` INT,
    `reviewed_at` TIMESTAMP NULL,
    INDEX idx_student (student_id),
    INDEX idx_status (status)
)");

$conn->exec("CREATE TABLE IF NOT EXISTS `competency_units` (
    `unit_id` INT AUTO_INCREMENT PRIMARY KEY,
    `unit_code` VARCHAR(20) NOT NULL,
    `unit_title` VARCHAR(200) NOT NULL,
    `nc_level` VARCHAR(10) DEFAULT NULL,
    `nctype` ENUM('Basic Competencies','Common Competencies','Core Competencies','Elective Competencies') DEFAULT 'Core Competencies',
    `competency_level` INT DEFAULT 1,
    `description` TEXT,
    INDEX idx_nc_level (nc_level)
)");

$isEnrolled = false;
$studentNcLevel = 'NC I';
$enrollment = null;

if ($conn !== null) {
    try {
        $ncStmt = $conn->prepare("
            SELECT spe.*, p.program_name
            FROM student_program_enrollments spe
            LEFT JOIN programs p ON spe.program_id = p.program_id
            WHERE spe.student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1)
            AND spe.enrollment_status = 'Active'
            ORDER BY spe.enrollment_id DESC LIMIT 1
        ");
        $ncStmt->execute([$userId]);
        $enrollment = $ncStmt->fetch(PDO::FETCH_ASSOC);
        if ($enrollment) {
            $isEnrolled = true;
            $studentNcLevel = $enrollment['nc_level'] ?? 'NC I';
        }
    } catch (Exception $e) {
        $studentNcLevel = 'NC I';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload_competency') {
        $competencyId = intval($_POST['competency_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if ($competencyId > 0) {
            $stmt = $conn->prepare("SELECT StudID FROM student WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $studentId = $stmt->fetchColumn();
            
            if ($studentId) {
                $filePath = '';
                $fileName = '';
                $fileError = '';
                
                if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['evidence']['tmp_name'];
                    $fileNameOrig = $_FILES['evidence']['name'];
                    $fileSize = $_FILES['evidence']['size'];
                    $fileType = $_FILES['evidence']['type'];
                    $fileNameCmps = explode('.', $fileNameOrig);
                    $fileExtension = strtolower(end($fileNameCmps));
                    
                    if (!in_array($fileExtension, $allowedExtensions)) {
                        $fileError = 'Invalid file type. Allowed: PDF, JPG, PNG, MP4, MOV';
                    } elseif ($fileSize > $maxFileSize) {
                        $fileError = 'File too large. Maximum size: 50MB';
                    } else {
                        $uploadDir = __DIR__ . '/uploads/competencies/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        $newFileName = uniqid('comp_') . '_' . time() . '.' . $fileExtension;
                        $destPath = $uploadDir . $newFileName;
                        
                        if (move_uploaded_file($fileTmpPath, $destPath)) {
                            $filePath = 'uploads/competencies/' . $newFileName;
                            $fileName = $fileNameOrig;
                        } else {
                            $fileError = 'Failed to upload file';
                        }
                    }
                } elseif (isset($_FILES['evidence']) && $_FILES['evidence']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $fileError = 'File upload error code: ' . $_FILES['evidence']['error'];
                }
                
                if ($fileError) {
                    $message = $fileError;
                    $messageType = 'error';
                } elseif ($filePath || $description) {
                    $stmt = $conn->prepare("SELECT unit_code FROM learning_modules WHERE module_id = ? LIMIT 1");
                    $stmt->execute([$competencyId]);
                    $unitCode = $stmt->fetchColumn() ?: '';
                    
                    $stmt = $conn->prepare("INSERT INTO competency_submissions (student_id, competency_id, unit_code, evidence_file, evidence_name, description, status) VALUES (?, ?, ?, ?, ?, ?, 'Submitted')");
                    $stmt->execute([$studentId, $competencyId, $unitCode, $filePath, $fileName, $description]);
                    
                    $message = "Competency evidence submitted successfully!";
                    $messageType = 'success';
                }
            }
        }
    } elseif ($action === 'delete_submission') {
        $submissionId = intval($_POST['submission_id'] ?? 0);
        if ($submissionId > 0) {
            $stmt = $conn->prepare("SELECT student_id, evidence_file FROM competency_submissions WHERE submission_id = ?");
            $stmt->execute([$submissionId]);
            $sub = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sub) {
                $stmt = $conn->prepare("SELECT StudID FROM student WHERE user_id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $studentId = $stmt->fetchColumn();
                
                if ($sub['student_id'] == $studentId && $sub['status'] === 'Pending') {
                    if ($sub['evidence_file'] && file_exists(__DIR__ . '/../' . $sub['evidence_file'])) {
                        unlink(__DIR__ . '/../' . $sub['evidence_file']);
                    }
                    $stmt = $conn->prepare("DELETE FROM competency_submissions WHERE submission_id = ?");
                    $stmt->execute([$submissionId]);
                    $message = "Submission deleted successfully.";
                    $messageType = 'success';
                }
            }
        }
    }
}

$filterStatus = $_GET['status'] ?? 'all';
$competencies = [];
try {
    $compStmt = $conn->prepare("
        SELECT module_id, module_code, module_title, module_description, module_type,
               (SELECT COUNT(*) FROM competency_submissions cs 
                WHERE cs.competency_id = learning_modules.module_id 
                AND cs.student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1)
                AND cs.status = 'Approved') as passed_count
        FROM learning_modules 
        WHERE module_type IN ('Competency', 'Unit of Competency')
        AND (nc_level = ? OR nc_level LIKE ? OR nc_level IS NULL OR nc_level = '')
        ORDER BY module_title
    ");
    $compStmt->execute([$userId, $studentNcLevel, $studentNcLevel . '%']);
    $competencies = $compStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $compCategories = [];
    foreach ($competencies as $comp) {
        $compCategories[] = $comp['module_type'] ?? 'Core Competencies';
    }
    $compCategories = array_unique($compCategories);
} catch (Exception $e) {
    $competencies = [];
}

$submissions = [];
try {
    $subSql = "SELECT cs.*, lm.module_title, lm.module_code, lm.module_type 
               FROM competency_submissions cs 
               LEFT JOIN learning_modules lm ON cs.competency_id = lm.module_id 
               WHERE cs.student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1)";
    
    if ($filterStatus !== 'all') {
        $subSql .= " AND cs.status = '" . ucfirst($filterStatus) . "'";
    }
    $subSql .= " ORDER BY cs.submitted_at DESC";
    
    $stmt = $conn->prepare($subSql);
    $stmt->execute([$userId]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $submissions = [];
}

$stats = [
    'total' => count($submissions),
    'approved' => count(array_filter($submissions, fn($s) => $s['status'] === 'Approved')),
    'pending' => count(array_filter($submissions, fn($s) => $s['status'] === 'Pending' || $s['status'] === 'Submitted')),
    'rejected' => count(array_filter($submissions, fn($s) => $s['status'] === 'Rejected'))
];

$pageTitle = "Competency Evidence";
$pageSubtitle = "Upload & Track Your Competency Evidence";
include 'sidebar_student.php';
?>

<style>
.drop-zone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f8fafc;
}
.drop-zone:hover, .drop-zone.dragover {
    border-color: #3b82f6;
    background: #eff6ff;
}
.drop-zone i { font-size: 48px; color: #94a3b8; margin-bottom: 15px; }
.file-preview {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f1f5f9;
    border-radius: 8px;
    margin-top: 10px;
}
.file-preview i { color: #3b82f6; }
.progress-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    border: 1px solid #e2e8f0;
}
.progress-ring {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: conic-gradient(#3b82f6 var(--progress), #e2e8f0 0);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}
.progress-ring-inner {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    color: #1e293b;
}
.filter-btn {
    padding: 8px 16px;
    border-radius: 20px;
    border: none;
    background: #f1f5f9;
    color: #64748b;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
}
.filter-btn:hover { background: #e2e8f0; }
.filter-btn.active { background: #3b82f6; color: white; }
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.status-approved { background: #d1fae5; color: #059669; }
.status-pending, .status-submitted { background: #fef3c7; color: #d97706; }
.status-rejected { background: #fee2e2; color: #dc2626; }
.status-under-review { background: #dbeafe; color: #2563eb; }
.competency-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}
.competency-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.competency-card.passed {
    border-left: 4px solid #10b981;
}
.competency-card.pending {
    border-left: 4px solid #f59e0b;
}
.guidelines-box {
    background: #f0f9ff;
    border-radius: 12px;
    padding: 20px;
    border: 1px solid #bae6fd;
}
.guidelines-box h4 { color: #0369a1; margin-bottom: 15px; }
.guidelines-box ul { margin: 0; padding-left: 20px; color: #075985; }
.guidelines-box li { margin-bottom: 8px; }
</style>

<div class="content-header">
    <h2><i class="fas fa-award"></i> <?= htmlspecialchars($pageTitle) ?></h2>
    <p class="text-muted"><?= htmlspecialchars($pageSubtitle) ?> - <strong><?= htmlspecialchars($studentNcLevel) ?></strong></p>
</div>

<?php if ($message): ?>
<div style="padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; <?= $messageType === 'error' ? 'background: #fee2e2; color: #dc2626;' : 'background: #d1fae5; color: #059671;' ?>">
    <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if (!$isEnrolled): ?>
<div style="padding: 20px; border-radius: 12px; margin-bottom: 25px; background: #fef3c7; color: #d97706;">
    <i class="fas fa-exclamation-triangle"></i> <strong>Not Enrolled</strong> - You are not currently enrolled in any training program. Please contact the admin or staff to enroll you.
</div>
<?php endif; ?>

<?php if ($isEnrolled): ?>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
    <div class="progress-card">
        <div class="progress-ring" style="--progress: <?= $stats['total'] > 0 ? ($stats['approved'] / $stats['total'] * 100) : 0 ?>%;">
            <div class="progress-ring-inner"><?= $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100) : 0 ?>%</div>
        </div>
        <p style="text-align: center; margin-top: 10px; color: #64748b;">Completion Rate</p>
    </div>
    <div class="progress-card">
        <div style="text-align: center;">
            <div style="font-size: 36px; font-weight: bold; color: #10b981;"><?= $stats['approved'] ?></div>
            <div style="color: #64748b;">Approved</div>
        </div>
    </div>
    <div class="progress-card">
        <div style="text-align: center;">
            <div style="font-size: 36px; font-weight: bold; color: #f59e0b;"><?= $stats['pending'] ?></div>
            <div style="color: #64748b;">Pending Review</div>
        </div>
    </div>
    <div class="progress-card">
        <div style="text-align: center;">
            <div style="font-size: 36px; font-weight: bold; color: #dc2626;"><?= $stats['rejected'] ?></div>
            <div style="color: #64748b;">Rejected</div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
    <div>
        <div class="card" style="padding: 20px;">
            <h3 style="margin-bottom: 15px;"><i class="fas fa-upload"></i> Submit Evidence</h3>
            
            <div class="guidelines-box" style="margin-bottom: 20px;">
                <h4><i class="fas fa-info-circle"></i> Upload Guidelines</h4>
                <ul>
                    <li>Accepted formats: PDF, JPG, PNG, MP4, MOV</li>
                    <li>Maximum file size: 50MB</li>
                    <li>Ensure evidence is clear and readable</li>
                    <li>Include your name and date in photos/videos</li>
                </ul>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload_competency">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Select Competency Unit</label>
                    <select name="competency_id" id="competencySelect" style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;" required>
                        <option value="">-- Select Competency --</option>
                        <?php foreach ($competencies as $comp): ?>
                        <option value="<?= $comp['module_id'] ?>">
                            <?= htmlspecialchars($comp['module_code'] ?? '') ?> - <?= htmlspecialchars($comp['module_title']) ?>
                            <?= $comp['passed_count'] > 0 ? ' ✓' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Evidence File</label>
                    <div class="drop-zone" id="dropZone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop files here or click to browse</p>
                        <p style="font-size: 12px; color: #94a3b8;">PDF, JPG, PNG, MP4, MOV (max 50MB)</p>
                        <input type="file" name="evidence" id="fileInput" accept=".pdf,.jpg,.jpeg,.png,.mp4,.mov" style="display: none;">
                    </div>
                    <div id="filePreview" class="file-preview" style="display: none;">
                        <i class="fas fa-file"></i>
                        <span id="fileName"></span>
                        <button type="button" onclick="removeFile()" style="margin-left: auto; background: none; border: none; color: #dc2626; cursor: pointer;"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Description / Notes</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;" placeholder="Describe your competency evidence..."></textarea>
                </div>
                
                <button type="submit" class="btn" style="width: 100%; padding: 14px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px;">
                    <i class="fas fa-paper-plane"></i> Submit Evidence
                </button>
            </form>
        </div>
    </div>
    
    <div>
        <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;"><i class="fas fa-history"></i> Submission History</h3>
                <div style="display: flex; gap: 8px;">
                    <a href="?status=all" class="filter-btn <?= $filterStatus === 'all' ? 'active' : '' ?>">All</a>
                    <a href="?status=approved" class="filter-btn <?= $filterStatus === 'approved' ? 'active' : '' ?>">Approved</a>
                    <a href="?status=pending" class="filter-btn <?= $filterStatus === 'pending' ? 'active' : '' ?>">Pending</a>
                    <a href="?status=rejected" class="filter-btn <?= $filterStatus === 'rejected' ? 'active' : '' ?>">Rejected</a>
                </div>
            </div>
            
            <?php if (empty($submissions)): ?>
            <div style="text-align: center; padding: 40px; color: #64748b;">
                <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 15px;"></i>
                <p>No submissions found</p>
            </div>
            <?php else: ?>
            
            <?php foreach ($submissions as $sub): ?>
            <?php 
            $statusClass = match($sub['status']) {
                'Approved' => 'passed',
                'Pending', 'Submitted' => 'pending',
                'Rejected' => 'rejected',
                default => ''
            };
            $badgeClass = match($sub['status']) {
                'Approved' => 'status-approved',
                'Pending', 'Submitted' => 'status-pending',
                'Under Review' => 'status-under-review',
                'Rejected' => 'status-rejected',
                default => 'status-pending'
            };
            ?>
            <div class="competency-card <?= $statusClass ?>">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div style="flex: 1;">
                        <h4 style="margin: 0 0 5px 0; font-size: 16px;">
                            <?= htmlspecialchars($sub['module_code'] ?? '') ?> - <?= htmlspecialchars($sub['module_title'] ?? 'N/A') ?>
                        </h4>
                        <p style="margin: 0 0 10px 0; color: #64748b; font-size: 13px;">
                            <?= htmlspecialchars($sub['description'] ?? 'No description') ?>
                        </p>
                        <div style="display: flex; gap: 15px; align-items: center; font-size: 12px; color: #94a3b8;">
                            <span><i class="fas fa-calendar"></i> <?= date('M d, Y h:i A', strtotime($sub['submitted_at'])) ?></span>
                            <?php if ($sub['evidence_file']): ?>
                            <a href="../<?= htmlspecialchars($sub['evidence_file']) ?>" target="_blank" style="color: #3b82f6; text-decoration: none;">
                                <i class="fas fa-eye"></i> View Evidence
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php if ($sub['reviewer_notes']): ?>
                        <div style="margin-top: 10px; padding: 10px; background: #fef3c7; border-radius: 8px; font-size: 13px;">
                            <strong><i class="fas fa-comment"></i> Review Notes:</strong> <?= htmlspecialchars($sub['reviewer_notes']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <span class="status-badge <?= $badgeClass ?>">
                        <?php if ($sub['status'] === 'Approved'): ?><i class="fas fa-check-circle"></i>
                        <?php elseif ($sub['status'] === 'Rejected'): ?><i class="fas fa-times-circle"></i>
                        <?php elseif ($sub['status'] === 'Under Review'): ?><i class="fas fa-search"></i>
                        <?php else: ?><i class="fas fa-clock"></i><?php endif; ?>
                        <?= htmlspecialchars($sub['status']) ?>
                    </span>
                </div>
                <?php if ($sub['status'] === 'Rejected'): ?>
                <div style="margin-top: 15px;">
                    <a href="?resubmit=<?= $sub['submission_id'] ?>" style="display: inline-block; padding: 8px 16px; background: #f59e0b; color: white; border-radius: 6px; text-decoration: none; font-size: 13px;">
                        <i class="fas fa-redo"></i> Resubmit
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const filePreview = document.getElementById('filePreview');
const fileName = document.getElementById('fileName');

dropZone.addEventListener('click', () => fileInput.click());

dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('dragover');
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('dragover');
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        showFilePreview();
    }
});

fileInput.addEventListener('change', showFilePreview);

function showFilePreview() {
    if (fileInput.files.length) {
        const file = fileInput.files[0];
        fileName.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
        filePreview.style.display = 'flex';
        dropZone.style.display = 'none';
    }
}

function removeFile() {
    fileInput.value = '';
    filePreview.style.display = 'none';
    dropZone.style.display = 'block';
}

<?php if (isset($_GET['resubmit'])): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('competencySelect').focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
<?php endif; ?>
</script>
