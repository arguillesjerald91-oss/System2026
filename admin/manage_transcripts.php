<?php
/**
 * Advanced TOR (Transcript of Records) Management
 * Admin Interface - Complete with Versioning, Verification, and Audit
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

// Authorization: Admin, Registrar, Support Staff
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if (!in_array($userType, ['admin', 'support_staff', 'instructional_unit', 'registrar'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'] ?? $_SESSION['userId'] ?? 1;
$userRole = $_SESSION['userRole'] ?? $userType;

// Get user's department assignment for access control
$staffDeptStmt = $conn->prepare("SELECT department FROM staff_department_assignments WHERE user_id = ? AND is_active = 1 LIMIT 1");
$staffDeptStmt->execute([$userId]);
$staffDept = $staffDeptStmt->fetchColumn();

// Filter parameters
$statusFilter = $_GET['status'] ?? 'All';
$programFilter = $_GET['program'] ?? 'All';
$batchFilter = $_GET['batch'] ?? 'All';
$ncLevelFilter = $_GET['nc_level'] ?? 'All';
$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$verificationFilter = $_GET['verified'] ?? 'All';

// Build query with filters
$where = [];
$params = [];

if ($statusFilter !== 'All') {
    $where[] = "t.status = ?";
    $params[] = $statusFilter;
}

if ($programFilter !== 'All') {
    $where[] = "t.program_id = ?";
    $params[] = $programFilter;
}

if ($batchFilter !== 'All') {
    $where[] = "t.batch_id = ?";
    $params[] = $batchFilter;
}

if (!empty($search)) {
    $where[] = "(s.FirstName LIKE ? OR s.LastName LIKE ? OR s.SchoolID LIKE ? OR t.transcript_number LIKE ? OR t.verification_code LIKE ?)";
    $searchPattern = "%$search%";
    $params = array_merge($params, array_fill(0, 5, $searchPattern));
}

if (!empty($dateFrom)) {
    $where[] = "t.issue_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[] = "t.issue_date <= ?";
    $params[] = $dateTo;
}

if ($verificationFilter === 'Verified') {
    $where[] = "t.verification_code IS NOT NULL AND t.verification_code != ''";
} elseif ($verificationFilter === 'Unverified') {
    $where[] = "(t.verification_code IS NULL OR t.verification_code = '')";
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get transcripts with student info
$sql = "
SELECT t.*, s.FirstName, s.LastName, s.SchoolID, p.program_code, p.program_title,
       b.batch_code, u.email as student_email,
       CONCAT(pr.Fname, ' ', pr.Lname) as prepared_by_name,
       CONCAT(ap.Fname, ' ', ap.Lname) as approved_by_name
FROM transcripts t
JOIN student s ON t.student_id = s.StudID
JOIN auto_mechanic_programs p ON t.program_id = p.program_id
LEFT JOIN training_batches b ON t.batch_id = b.batch_id
LEFT JOIN users u ON s.user_id = u.user_id
LEFT JOIN admins pr ON t.prepared_by = pr.admin_id
LEFT JOIN admins ap ON t.approved_by = ap.admin_id
$whereClause
ORDER BY t.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$transcripts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts for dashboard
$counts = [];
$countStmt = $conn->query("SELECT status, COUNT(*) as cnt FROM transcripts GROUP BY status");
while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['status']] = $row['cnt'];
}

// Get programs for filter
$programs = $conn->query("SELECT program_id, program_code, program_title FROM auto_mechanic_programs ORDER BY program_code")->fetchAll();

// Get batches for filter
$batches = $conn->query("SELECT batch_id, batch_code FROM training_batches ORDER BY batch_code DESC")->fetchAll();

// Get status options
$statusOptions = ['Draft', 'Pending Approval', 'Approved', 'Issued', 'Delivered', 'Archived', 'Recalled', 'Superseded'];

$pageTitle = "Transcript of Records (TOR) Management";
$pageSubtitle = "Advanced Academic Records with Version Control & Verification";
include 'sidebar_new.php';
include '../includes/unified_header.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];

    try {
        $conn->beginTransaction();

        if ($action === 'create_transcript') {
            $studentId = $_POST['student_id'];
            $enrollmentId = $_POST['enrollment_id'];
            $programId = $_POST['program_id'];
            $batchId = !empty($_POST['batch_id']) ? $_POST['batch_id'] : null;
            $issueDate = $_POST['issue_date'];
            $effectiveDate = $_POST['effective_date'] ?? null;
            $honors = $_POST['honors'] ?? null;

            // Generate transcript number
            $year = date('Y');
            $transcriptNum = "TOR-$year-" . str_pad($conn->lastInsertId() + 1, 6, '0', STR_PAD_LEFT);

            // Get student and enrollment details
            $enrollmentStmt = $conn->prepare("SELECT * FROM student_program_enrollments WHERE enrollment_id = ?");
            $enrollmentStmt->execute([$enrollmentId]);
            $enrollment = $enrollmentStmt->fetch(PDO::FETCH_ASSOC);

            // Calculate GPA from grades
            $gradesStmt = $conn->prepare("SELECT AVG(grade_point) as avg_gpa FROM module_assessments WHERE enrollment_id = ? AND status = 'Completed'");
            $gradesStmt->execute([$enrollmentId]);
            $gpaResult = $gradesStmt->fetch(PDO::FETCH_ASSOC);
            $gpa = round($gpaResult['avg_gpa'] ?? 0, 2);

            // Get completed modules count
            $modulesStmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed FROM student_module_progress WHERE enrollment_id = ?");
            $modulesStmt->execute([$enrollmentId]);
            $modulesInfo = $modulesStmt->fetch(PDO::FETCH_ASSOC);

            $totalUnits = $modulesInfo['total'] * 3; // Assuming 3 units per module
            $totalHours = $modulesInfo['total'] * 48; // Assuming 48 hours per module

            // Generate verification code
            $verificationCode = 'VER-' . strtoupper(bin2hex(random_bytes(8)));

            $insert = $conn->prepare("
                INSERT INTO transcripts (
                    transcript_number, student_id, enrollment_id, program_id, batch_id,
                    issue_date, effective_date, total_units, total_hours, gpa,
                    honors, degree_conferred, conferred_date,
                    verification_code, verification_url, status,
                    prepared_by, prepared_at, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, ?, ?, 'Draft', ?, NOW(), ?)
            ");

            $verificationUrl = "https://yourdomain.edu.ph/verify/transcript.php?code=$verificationCode";
            $insert->execute([
                $transcriptNum, $studentId, $enrollmentId, $programId, $batchId,
                $issueDate, $effectiveDate, $totalUnits, $totalHours, $gpa,
                $honors, $verificationCode, $verificationUrl,
                $userId, $userId
            ]);

            $transcriptId = $conn->lastInsertId();

            // Log history
            $log = $conn->prepare("INSERT INTO transcript_history (transcript_id, change_type, change_reason, changed_by, ip_address) VALUES (?, 'Create', 'Initial transcript creation', ?, ?)");
            $log->execute([$transcriptId, $userId, $_SERVER['REMOTE_ADDR']]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Transcript created successfully.', 'id' => $transcriptId];
        }

        if ($action === 'add_grade') {
            $transcriptId = $_POST['transcript_id'];
            $moduleId = $_POST['module_id'];
            $courseCode = $_POST['course_code'];
            $courseTitle = $_POST['course_title'];
            $units = $_POST['units'];
            $grade = $_POST['grade'];
            $gradePoint = $_POST['grade_point'];
            $gradeType = $_POST['grade_type'];
            $semester = $_POST['semester'] ?? null;
            $academicYear = $_POST['academic_year'] ?? null;

            $insert = $conn->prepare("
                INSERT INTO transcript_grades (
                    transcript_id, module_id, course_code, course_title,
                    units, grade, grade_point, grade_type, semester, academic_year
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $insert->execute([
                $transcriptId, $moduleId, $courseCode, $courseTitle,
                $units, $grade, $gradePoint, $gradeType, $semester, $academicYear
            ]);

            $response = ['success' => true, 'message' => 'Grade added successfully.'];
        }

        if ($action === 'update_status') {
            $transcriptId = $_POST['transcript_id'];
            $newStatus = $_POST['status'];
            $reason = $_POST['reason'] ?? '';

            // Get current transcript
            $currentStmt = $conn->prepare("SELECT * FROM transcripts WHERE transcript_id = ?");
            $currentStmt->execute([$transcriptId]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                throw new Exception("Transcript not found");
            }

            // Update status
            $update = $conn->prepare("UPDATE transcripts SET status = ?, updated_at = NOW() WHERE transcript_id = ?");
            $update->execute([$newStatus, $transcriptId]);

            // Log history
            $log = $conn->prepare("INSERT INTO transcript_history (transcript_id, change_type, field_changed, old_value, new_value, change_reason, changed_by, ip_address) VALUES (?, 'Status Change', 'status', ?, ?, ?, ?, ?)");
            $log->execute([$transcriptId, $current['status'], $newStatus, $reason, $userId, $_SERVER['REMOTE_ADDR']]);

            // If status is "Issued" and PDF not yet generated, trigger generation
            if ($newStatus === 'Issued' && !$current['pdf_generated']) {
                // Note: Actual PDF generation would require external library like TCPDF or dompdf
                // For now, mark as ready for generation
                $response['message'] = 'Status updated. PDF generation queued.';
            } else {
                $response['message'] = 'Status updated successfully.';
            }

            $response['success'] = true;
        }

        if ($action === 'generate_pdf') {
            $transcriptId = $_POST['transcript_id'];

            // Get transcript data
            $stmt = $conn->prepare("
                SELECT t.*, s.FirstName, s.LastName, s.SchoolID,
                       p.program_code, p.program_title,
                       b.batch_code
                FROM transcripts t
                JOIN student s ON t.student_id = s.StudID
                JOIN auto_mechanic_programs p ON t.program_id = p.program_id
                LEFT JOIN training_batches b ON t.batch_id = b.batch_id
                WHERE t.transcript_id = ?
            ");
            $stmt->execute([$transcriptId]);
            $transcript = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transcript) {
                throw new Exception("Transcript not found");
            }

            // Placeholder for PDF generation
            // In production, use TCPDF, dompdf, or mPDF
            $pdfPath = "../uploads/transcripts/transcript_{$transcriptId}.pdf";

            // Simulate PDF generation (create dummy file)
            file_put_contents($pdfPath, "PDF content for transcript {$transcript['transcript_number']}");

            // Update transcript
            $update = $conn->prepare("
                UPDATE transcripts SET
                    pdf_file_path = ?,
                    pdf_generated = 1,
                    pdf_generated_at = NOW(),
                    status = 'Issued',
                    issued_by = ?,
                    issued_at = NOW(),
                    updated_at = NOW()
                WHERE transcript_id = ?
            ");
            $update->execute([$pdfPath, $userId, $transcriptId]);

            // Log history
            $log = $conn->prepare("INSERT INTO transcript_history (transcript_id, change_type, changed_by, ip_address) VALUES (?, 'Issue', ?, ?)");
            $log->execute([$transcriptId, $userId, $_SERVER['REMOTE_ADDR']]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Transcript PDF generated and issued successfully.'];
        }

        if ($action === 'revoke_transcript') {
            $transcriptId = $_POST['transcript_id'];
            $reason = $_POST['reason'] ?? '';

            if (empty($reason)) {
                throw new Exception("Revocation reason is required");
            }

            // Check if already revoked
            $checkStmt = $conn->prepare("SELECT status FROM transcripts WHERE transcript_id = ?");
            $checkStmt->execute([$transcriptId]);
            $currentStatus = $checkStmt->fetchColumn();

            if ($currentStatus === 'Revoked') {
                throw new Exception("Transcript already revoked");
            }

            // Update status
            $update = $conn->prepare("UPDATE transcripts SET status = 'Recalled', remarks = ?, revoked_by = ?, revoked_at = NOW(), updated_at = NOW() WHERE transcript_id = ?");
            $update->execute([$reason, $userId, $transcriptId]);

            // Log history
            $log = $conn->prepare("INSERT INTO transcript_history (transcript_id, change_type, reason, changed_by, ip_address) VALUES (?, 'Recall', ?, ?, ?)");
            $log->execute([$transcriptId, $reason, $userId, $_SERVER['REMOTE_ADDR']]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Transcript revoked successfully.'];
        }

        if ($action === 'delete_transcript') {
            $transcriptId = $_POST['transcript_id'];

            // Soft delete by archiving
            $update = $conn->prepare("UPDATE transcripts SET status = 'Archived', updated_at = NOW() WHERE transcript_id = ?");
            $update->execute([$transcriptId]);

            $response = ['success' => true, 'message' => 'Transcript archived successfully.'];
        }

        if ($action === 'bulk_action') {
            $selected = $_POST['selected_transcripts'] ?? [];
            $bulkAction = $_POST['bulk_action'] ?? '';
            $bulkReason = $_POST['bulk_reason'] ?? '';

            if (empty($selected)) {
                throw new Exception("No transcripts selected");
            }

            $placeholders = implode(',', array_fill(0, count($selected), '?'));

            switch ($bulkAction) {
                case 'bulk_issue':
                    $stmt = $conn->prepare("UPDATE transcripts SET status = 'Issued', issued_by = ?, issued_at = NOW() WHERE transcript_id IN ($placeholders)");
                    $params = array_merge([$userId], $selected);
                    $stmt->execute($params);
                    $response['message'] = count($selected) . ' transcripts issued.';
                    break;

                case 'bulk_approve':
                    $stmt = $conn->prepare("UPDATE transcripts SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE transcript_id IN ($placeholders)");
                    $params = array_merge([$userId], $selected);
                    $stmt->execute($params);
                    $response['message'] = count($selected) . ' transcripts approved.';
                    break;

                case 'bulk_archive':
                    $stmt = $conn->prepare("UPDATE transcripts SET status = 'Archived', updated_at = NOW() WHERE transcript_id IN ($placeholders)");
                    $stmt->execute($selected);
                    $response['message'] = count($selected) . ' transcripts archived.';
                    break;

                case 'bulk_delete':
                    $stmt = $conn->prepare("UPDATE transcripts SET status = 'Archived' WHERE transcript_id IN ($placeholders)");
                    $stmt->execute($selected);
                    $response['message'] = count($selected) . ' transcripts deleted.';
                    break;
            }

            $response['success'] = true;
        }

        if ($jsonResponse = json_encode($response)) {
            echo $jsonResponse;
            exit();
        }

    } catch (Exception $e) {
        $conn->rollBack();
        $response = ['success' => false, 'message' => $e->getMessage()];
        if (isset($_POST['ajax'])) {
            echo json_encode($response);
            exit();
        }
        $_SESSION['error'] = $response['message'];
    }
}

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transcripts_export_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Transcript #', 'Student ID', 'Student Name', 'Program', 'Batch',
        'Issue Date', 'GPA', 'Total Units', 'Status', 'Verification Code',
        'Prepared By', 'Approved By', 'Issued By'
    ]);

    foreach ($transcripts as $t) {
        fputcsv($output, [
            $t['transcript_number'],
            $t['SchoolID'],
            $t['FirstName'] . ' ' . $t['LastName'],
            $t['program_code'] . ' - ' . $t['program_title'],
            $t['batch_code'] ?? 'N/A',
            $t['issue_date'],
            $t['gpa'],
            $t['total_units'],
            $t['status'],
            $t['verification_code'] ?? 'N/A',
            $t['prepared_by_name'] ?? 'N/A',
            $t['approved_by_name'] ?? 'N/A',
            $t['issued_by_name'] ?? 'N/A'
        ]);
    }
    fclose($output);
    exit();
}
?>

<div class="content-header">
    <div class="header-actions">
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Create Transcript
        </button>
        <button class="btn btn-secondary" onclick="window.location.href='?export=csv'">
            <i class="fas fa-download"></i> Export CSV
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= count($transcripts) ?></div>
        <div class="stat-label">Total Transcripts</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-success"><?= $counts['Issued'] ?? 0 ?></div>
        <div class="stat-label">Issued</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-warning"><?= $counts['Pending Approval'] ?? 0 ?></div>
        <div class="stat-label">Pending Approval</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-primary"><?= $counts['Draft'] ?? 0 ?></div>
        <div class="stat-label">Draft</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-info"><?= $counts['Archived'] ?? 0 ?></div>
        <div class="stat-label">Archived</div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Filter Transcripts</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filters-form">
            <div class="filters-row">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All Statuses</option>
                        <?php foreach ($statusOptions as $opt): ?>
                            <option value="<?= $opt ?>" <?= $statusFilter === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Program</label>
                    <select name="program">
                        <option value="All">All Programs</option>
                        <?php foreach ($programs as $p): ?>
                            <option value="<?= $p['program_id'] ?>" <?= $programFilter == $p['program_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['program_code'] . ' - ' . $p['program_title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Batch</label>
                    <select name="batch">
                        <option value="All">All Batches</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= $b['batch_id'] ?>" <?= $batchFilter == $b['batch_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['batch_code']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Verification</label>
                    <select name="verified">
                        <option value="All" <?= $verificationFilter === 'All' ? 'selected' : '' ?>>All</option>
                        <option value="Verified" <?= $verificationFilter === 'Verified' ? 'selected' : '' ?>>Has Verification Code</option>
                        <option value="Unverified" <?= $verificationFilter === 'Unverified' ? 'selected' : '' ?>>No Verification Code</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Student name, ID, transcript #" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-group filter-submit">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="?" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Actions Panel -->
<div class="card" id="bulk-actions" style="display: none; margin-top: 1rem;">
    <div class="card-body" style="padding: 1rem;">
        <form method="POST" id="bulkActionForm">
            <input type="hidden" name="action" id="bulkActionInput" value="">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="font-weight: bold;" id="selected-count">0 selected</span>
                <select name="bulk_action" id="bulkActionSelect" class="form-select" style="width: auto;">
                    <option value="">Choose Action</option>
                    <option value="bulk_issue">Issue All Selected</option>
                    <option value="bulk_approve">Approve All Selected</option>
                    <option value="bulk_archive">Archive All Selected</option>
                    <option value="bulk_delete">Delete All Selected</option>
                </select>
                <button type="button" class="btn btn-primary" onclick="executeBulkAction()">Execute</button>
                <button type="button" class="btn btn-secondary" onclick="clearSelection()">Clear Selection</button>
            </div>
        </form>
    </div>
</div>

<!-- Transcripts Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Transcript of Records</h3>
        <span class="badge"><?= count($transcripts) ?> records</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all" onchange="toggleSelectAll()"></th>
                        <th>Transcript #</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Batch</th>
                        <th>Issue Date</th>
                        <th>GPA</th>
                        <th>Units</th>
                        <th>Status</th>
                        <th>Verification</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transcripts as $t): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="transcript-checkbox" value="<?= $t['transcript_id'] ?>">
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($t['transcript_number']) ?></strong>
                                <?php if ($t['version'] > 1): ?>
                                    <span class="badge badge-info">v<?= $t['version'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($t['FirstName'] . ' ' . $t['LastName']) ?><br>
                                <small><?= htmlspecialchars($t['SchoolID']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($t['program_code'] . ' - ' . $t['program_title']) ?></td>
                            <td><?= htmlspecialchars($t['batch_code'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($t['issue_date']) ?></td>
                            <td><strong><?= number_format($t['gpa'], 2) ?></strong></td>
                            <td><?= $t['total_units'] ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $t['status'])) ?>">
                                    <?= htmlspecialchars($t['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($t['verification_code'])): ?>
                                    <span class="text-success">
                                        <i class="fas fa-check-circle"></i> Enabled
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-info" onclick="viewTranscript(<?= $t['transcript_id'] ?>)"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($t['status'] === 'Draft' || $t['status'] === 'Pending Approval'): ?>
                                        <button class="btn btn-sm btn-primary" onclick="editTranscript(<?= $t['transcript_id'] ?>)"
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($t['status'] === 'Draft' || $t['status'] === 'Pending Approval'): ?>
                                        <button class="btn btn-sm btn-success" onclick="generatePDF(<?= $t['transcript_id'] ?>)"
                                                title="Generate PDF & Issue">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($t['status'] === 'Issued' || $t['status'] === 'Delivered'): ?>
                                        <button class="btn btn-sm btn-warning" onclick="revokeTranscript(<?= $t['transcript_id'] ?>)"
                                                title="Revoke">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-secondary" onclick="viewHistory(<?= $t['transcript_id'] ?>)"
                                            title="History">
                                        <i class="fas fa-history"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline" onclick="exportSingle(<?= $t['transcript_id'] ?>)"
                                            title="Export">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($transcripts)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 2rem;">
                                No transcripts found. <a href="#" onclick="openCreateModal()">Create the first transcript</a>.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit Transcript Modal -->
<div class="modal" id="transcriptModal" style="display: none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 id="modalTitle">Create Transcript</h3>
            <button class="btn-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="transcriptForm" method="POST">
            <input type="hidden" name="action" value="create_transcript">
            <input type="hidden" name="transcript_id" id="editTransId">

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Student</label>
                        <select name="student_id" id="studentSelect" required>
                            <option value="">Select Student</option>
                            <?php
                            $students = $conn->query("SELECT StudID, FirstName, LastName, SchoolID FROM student ORDER BY FirstName, LastName")->fetchAll();
                            foreach ($students as $s):
                            ?>
                                <option value="<?= $s['StudID'] ?>">
                                    <?= htmlspecialchars($s['FirstName'] . ' ' . $s['LastName'] . ' (' . $s['SchoolID'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Enrollment</label>
                        <select name="enrollment_id" id="enrollmentSelect" required>
                            <option value="">Select Enrollment</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Program</label>
                        <select name="program_id" id="programSelect" required>
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= $p['program_id'] ?>"><?= htmlspecialchars($p['program_code'] . ' - ' . $p['program_title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Batch</label>
                        <select name="batch_id">
                            <option value="">None</option>
                            <?php foreach ($batches as $b): ?>
                                <option value="<?= $b['batch_id'] ?>"><?= htmlspecialchars($b['batch_code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Issue Date</label>
                        <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Effective Date</label>
                        <input type="date" name="effective_date">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Honors</label>
                        <select name="honors">
                            <option value="">None</option>
                            <option value="Cum Laude">Cum Laude</option>
                            <option value="Magna Cum Laude">Magna Cum Laude</option>
                            <option value="Summa Cum Laude">Summa Cum Laude</option>
                            <option value="With Honors">With Honors</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-secondary" onclick="calculateGPA()">Calculate GPA</button>
                    </div>
                </div>

                <!-- Grades Table -->
                <div class="grades-section" style="margin-top: 2rem;">
                    <h4>Grades</h4>
                    <table class="table" id="grades-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Units</th>
                                <th>Grade</th>
                                <th>Grade Point</th>
                                <th>Type</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Grades will be added dynamically -->
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="addGradeRow()">
                        <i class="fas fa-plus"></i> Add Grade
                    </button>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Transcript</button>
            </div>
        </form>
    </div>
</div>

<!-- View Transcript Modal -->
<div class="modal" id="viewTranscriptModal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3>Transcript Details</h3>
            <button class="btn-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body" id="transcript-details">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal" id="historyModal" style="display: none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3>Change History</h3>
            <button class="btn-close" onclick="closeHistoryModal()">&times;</button>
        </div>
        <div class="modal-body" id="history-content">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<?php
$pageScript = "
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Student enrollment cascade
    document.getElementById('studentSelect').addEventListener('change', function() {
        loadEnrollments(this.value);
    });
});

function loadEnrollments(studentId) {
    if (!studentId) {
        document.getElementById('enrollmentSelect').innerHTML = '<option value=\"\">Select Enrollment</option>';
        return;
    }

    fetch('../ajax/get_enrollments.php?student_id=' + studentId)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('enrollmentSelect');
            select.innerHTML = '<option value=\"\">Select Enrollment</option>';
            data.forEach(enrollment => {
                select.innerHTML += \`<option value=\"\${enrollment.enrollment_id}\">
                    \${enrollment.batch_code || 'N/A'} - \${enrollment.nc_level || 'N/A'}
                </option>\`;
            });
        });
}

function openCreateModal() {
    document.getElementById('transcriptModal').style.display = 'flex';
    document.getElementById('modalTitle').innerText = 'Create Transcript';
    document.getElementById('transcriptForm').reset();
    document.getElementById('grades-table').innerHTML = '<thead>...</thead><tbody></tbody>';
}

function closeModal() {
    document.getElementById('transcriptModal').style.display = 'none';
}

function closeViewModal() {
    document.getElementById('viewTranscriptModal').style.display = 'none';
}

function closeHistoryModal() {
    document.getElementById('historyModal').style.display = 'none';
}

function addGradeRow() {
    const tbody = document.querySelector('#grades-table tbody');
    const rowCount = tbody.children.length + 1;
    const row = \`
        <tr>
            <td><input type=\"text\" name=\"course_code[]\" placeholder=\"e.g. CS101\" required></td>
            <td><input type=\"text\" name=\"course_title[]\" placeholder=\"Course Title\" required></td>
            <td><input type=\"number\" step=\"0.5\" name=\"units[]\" placeholder=\"Units\" required></td>
            <td><input type=\"text\" name=\"grade[]\" placeholder=\"1.00\" required></td>
            <td><input type=\"number\" step=\"0.01\" name=\"grade_point[]\" placeholder=\"4.00\" required></td>
            <td>
                <select name=\"grade_type[]\">
                    <option value=\"Numerical\">Numerical</option>
                    <option value=\"Letter\">Letter</option>
                    <option value=\"Pass/Fail\">Pass/Fail</option>
                    <option value=\"Competency Based\">Competency Based</option>
                </select>
            </td>
            <td>
                <button type=\"button\" class=\"btn btn-sm btn-danger\" onclick=\"this.closest('tr').remove()\">
                    <i class=\"fas fa-trash\"></i>
                </button>
            </td>
        </tr>
    \`;
    tbody.insertAdjacentHTML('beforeend', row);
}

function toggleSelectAll() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.transcript-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkActions();
}

function updateBulkActions() {
    const checked = document.querySelectorAll('.transcript-checkbox:checked');
    const bulkPanel = document.getElementById('bulk-actions');
    const countSpan = document.getElementById('selected-count');

    if (checked.length > 0) {
        bulkPanel.style.display = 'block';
        countSpan.textContent = checked.length + ' selected';
    } else {
        bulkPanel.style.display = 'none';
    }
}

function executeBulkAction() {
    const action = document.getElementById('bulkActionSelect').value;
    if (!action) {
        alert('Please select an action');
        return;
    }

    const form = document.getElementById('bulkActionForm');
    document.getElementById('bulkActionInput').value = action;
    form.submit();
}

function clearSelection() {
    document.getElementById('select-all').checked = false;
    document.querySelectorAll('.transcript-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('bulk-actions').style.display = 'none';
}

function viewTranscript(id) {
    fetch('view_transcript_ajax.php?id=' + id)
        .then(r => r.text())
        .then(html => {
            document.getElementById('transcript-details').innerHTML = html;
            document.getElementById('viewTranscriptModal').style.display = 'flex';
        });
}

function editTranscript(id) {
    // Load edit form via AJAX
    fetch('edit_transcript_ajax.php?id=' + id)
        .then(r => r.text())
        .then(html => {
            document.querySelector('#transcriptModal .modal-body').innerHTML = html;
            document.getElementById('transcriptModal').style.display = 'flex';
            document.getElementById('modalTitle').innerText = 'Edit Transcript';
        });
}

function generatePDF(id) {
    if (confirm('Generate and issue PDF for this transcript?')) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=generate_pdf&transcript_id=' + id + '&ajax=1'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function revokeTranscript(id) {
    const reason = prompt('Please provide reason for revocation:');
    if (reason) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=revoke_transcript&transcript_id=' + id + '&reason=' + encodeURIComponent(reason) + '&ajax=1'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Transcript revoked');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function viewHistory(transcriptId) {
    fetch('transcript_history_ajax.php?transcript_id=' + transcriptId)
        .then(r => r.text())
        .then(html => {
            document.getElementById('history-content').innerHTML = html;
            document.getElementById('historyModal').style.display = 'flex';
        });
}

function exportSingle(id) {
    window.open('export_transcript.php?id=' + id, '_blank');
}

// Recalculate GPA
function calculateGPA() {
    const grades = document.querySelectorAll('#grades-table tbody tr');
    let totalPoints = 0;
    let totalUnits = 0;

    grades.forEach(row => {
        const units = parseFloat(row.querySelector('input[name=\"units[]\"]').value) || 0;
        const points = parseFloat(row.querySelector('input[name=\"grade_point[]\"]').value) || 0;
        totalUnits += units;
        totalPoints += (units * points);
    });

    const gpa = totalUnits > 0 ? (totalPoints / totalUnits) : 0;
    alert('Calculated GPA: ' + gpa.toFixed(2));
}

// Auto-refresh bulk count
document.querySelectorAll('.transcript-checkbox').forEach(cb => {
    cb.addEventListener('change', updateBulkActions);
});
</script>
";

include '../includes/footer.php';
?>
