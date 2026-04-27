<?php
/**
 * Advanced Certificates Management
 * Admin Interface - NC Certificates, Competency Certificates, Completion Certificates
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

// Authorization
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if (!in_array($userType, ['admin', 'support_staff', 'instructional_unit', 'registrar'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'] ?? $_SESSION['userId'] ?? 1;

// Filters
$statusFilter = $_GET['status'] ?? 'All';
$certType = $_GET['type'] ?? 'All';
$ncLevel = $_GET['nc_level'] ?? 'All';
$programFilter = $_GET['program'] ?? 'All';
$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$expiryFilter = $_GET['expiry'] ?? 'All';

// Build query
$where = [];
$params = [];

if ($statusFilter !== 'All') {
    $where[] = "c.status = ?";
    $params[] = $statusFilter;
}

if ($certType !== 'All') {
    $where[] = "c.certificate_type = ?";
    $params[] = $certType;
}

if ($ncLevel !== 'All') {
    $where[] = "c.nc_level = ?";
    $params[] = $ncLevel;
}

if ($programFilter !== 'All') {
    $where[] = "c.program_id = ?";
    $params[] = $programFilter;
}

if (!empty($search)) {
    $where[] = "(s.FirstName LIKE ? OR s.LastName LIKE ? OR s.SchoolID LIKE ? OR c.certificate_number LIKE ? OR c.verification_code LIKE ?)";
    $sp = "%$search%";
    $params = array_merge($params, array_fill(0, 5, $sp));
}

if (!empty($dateFrom)) {
    $where[] = "c.issue_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[] = "c.issue_date <= ?";
    $params[] = $dateTo;
}

if ($expiryFilter === 'Expiring Soon') {
    $where[] = "c.valid_until BETWEEN DATE_ADD(NOW(), INTERVAL 30 DAY) AND DATE_ADD(NOW(), INTERVAL 90 DAY)";
} elseif ($expiryFilter === 'Expired') {
    $where[] = "c.valid_until < CURDATE()";
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
SELECT c.*, s.FirstName, s.LastName, s.SchoolID,
       p.program_code, p.program_title,
       CONCAT(pr.Fname, ' ', pr.Lname) as prepared_by_name,
       CONCAT(ap.Fname, ' ', ap.Lname) as approved_by_name
FROM certificates c
JOIN student s ON c.student_id = s.StudID
JOIN auto_mechanic_programs p ON c.program_id = p.program_id
LEFT JOIN admins pr ON c.prepared_by = pr.admin_id
LEFT JOIN admins ap ON c.approved_by = ap.admin_id
$whereClause
ORDER BY c.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts
$counts = [];
$countStmt = $conn->query("SELECT status, COUNT(*) as cnt FROM certificates GROUP BY status");
while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['status']] = $row['cnt'];
}

// Lists for filters
$programs = $conn->query("SELECT program_id, program_code, program_title FROM auto_mechanic_programs ORDER BY program_code")->fetchAll();
$ncLevels = ['NC I', 'NC II', 'NC III', 'NC IV', 'Diploma', 'Special'];
$certTypes = ['Certificate of Completion', 'Competency Certificate', 'NC Certificate', 'Skill Certificate', 'Completion', 'Achievement', 'Custom'];
$statusOptions = ['Draft', 'Pending', 'Approved', 'Issued', 'Active', 'Expired', 'Revoked', 'Cancelled'];

$pageTitle = "Certificates Management";
$pageSubtitle = "NC Certificates, Competency & Completion Certificates";
include 'sidebar_new.php';
include '../includes/unified_header.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];

    try {
        $conn->beginTransaction();

        if ($action === 'create_certificate') {
            $studentId = $_POST['student_id'];
            $enrollmentId = $_POST['enrollment_id'];
            $certType = $_POST['certificate_type'];
            $ncLevel = $_POST['nc_level'] ?? null;
            $programId = $_POST['program_id'] ?? null;
            $issueDate = $_POST['issue_date'];
            $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
            $title = $_POST['title'] ?? null;
            $description = $_POST['description'] ?? null;
            $honors = $_POST['honors'] ?? null;

            // Generate certificate number
            $prefix = strtoupper(substr(str_replace(' ', '', $certType), 0, 3));
            $year = date('Y');
            $certNum = "$prefix-$year-" . str_pad($conn->lastInsertId() + 1, 6, '0', STR_PAD_LEFT);

            // Generate verification code
            $verificationCode = 'CERT-' . strtoupper(bin2hex(random_bytes(8)));

            $insert = $conn->prepare("
                INSERT INTO certificates (
                    certificate_number, student_id, enrollment_id, certificate_type,
                    nc_level, program_id, issue_date, valid_from, valid_until,
                    title, description, honors,
                    verification_code, verification_url, status,
                    prepared_by, prepared_at, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?, NOW(), ?)
            ");

            $verificationUrl = "https://yourdomain.edu.ph/verify/certificate.php?code=$verificationCode";
            $insert->execute([
                $certNum, $studentId, $enrollmentId, $certType,
                $ncLevel, $programId, $issueDate, $issueDate, $validUntil,
                $title, $description, $honors,
                $verificationCode, $verificationUrl,
                $userId, $userId
            ]);

            $certId = $conn->lastInsertId();

            // Add course modules to certificate
            if (isset($_POST['modules']) && is_array($_POST['modules'])) {
                foreach ($_POST['modules'] as $moduleId) {
                    $moduleInfo = $conn->prepare("SELECT module_code, module_title FROM training_modules WHERE module_id = ?");
                    $moduleInfo->execute([$moduleId]);
                    $module = $moduleInfo->fetch(PDO::FETCH_ASSOC);

                    $compInsert = $conn->prepare("
                        INSERT INTO certificate_competencies (
                            certificate_id, module_id, competency_code, competency_title, completed_date
                        ) VALUES (?, ?, ?, ?, ?)
                    ");
                    $compInsert->execute([$certId, $moduleId, $module['module_code'], $module['module_title'], date('Y-m-d')]);
                }
            }

            // Log history
            $log = $conn->prepare("INSERT INTO certificate_history (certificate_id, action, reason, performed_by, ip_address) VALUES (?, 'Create', 'Certificate created', ?, ?)");
            $log->execute([$certId, $userId, $_SERVER['REMOTE_ADDR']]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Certificate created successfully.', 'id' => $certId];
        }

        if ($action === 'issue_certificate') {
            $certId = $_POST['certificate_id'];

            // Generate PDF
            $pdfPath = "../uploads/certificates/certificate_{$certId}.pdf";
            file_put_contents($pdfPath, "Certificate content for $certId");

            $update = $conn->prepare("
                UPDATE certificates SET
                    pdf_file_path = ?,
                    pdf_generated = 1,
                    pdf_generated_at = NOW(),
                    status = 'Issued',
                    issued_by = ?,
                    issued_at = NOW(),
                    updated_at = NOW()
                WHERE certificate_id = ?
            ");
            $update->execute([$pdfPath, $userId, $certId]);

            $log = $conn->prepare("INSERT INTO certificate_history (certificate_id, action, performed_by, ip_address) VALUES (?, 'Issue', ?, ?)");
            $log->execute([$certId, $userId, $_SERVER['REMOTE_ADDR']]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Certificate issued successfully.'];
        }

        if ($action === 'revoke_certificate') {
            $certId = $_POST['certificate_id'];
            $reason = $_POST['reason'] ?? '';

            if (empty($reason)) {
                throw new Exception("Revocation reason required");
            }

            $update = $conn->prepare("
                UPDATE certificates SET
                    status = 'Revoked',
                    revocation_reason = ?,
                    revoked_by = ?,
                    revoked_at = NOW(),
                    updated_at = NOW()
                WHERE certificate_id = ?
            ");
            $update->execute([$reason, $userId, $certId]);

            $log = $conn->prepare("INSERT INTO certificate_history (certificate_id, action, reason, performed_by) VALUES (?, 'Revoke', ?, ?)");
            $log->execute([$certId, $reason, $userId]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Certificate revoked.'];
        }

        if ($action === 'renew_certificate') {
            $certId = $_POST['certificate_id'];
            $newValidUntil = $_POST['new_valid_until'];

            // Get current cert
            $curr = $conn->prepare("SELECT * FROM certificates WHERE certificate_id = ?");
            $curr->execute([$certId]);
            $current = $curr->fetch(PDO::FETCH_ASSOC);

            // Create new certificate record (replacement)
            $replacementNum = $current['certificate_number'] . '-RENEW-' . date('Y');
            $insert = $conn->prepare("
                INSERT INTO certificates (
                    certificate_number, student_id, enrollment_id, certificate_type,
                    nc_level, program_id, issue_date, valid_from, valid_until,
                    verification_code, verification_url, status,
                    replacement_for, prepared_by, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Issued', ?, ?, ?)
            ");

            $newVerifCode = 'CERT-' . strtoupper(bin2hex(random_bytes(8)));
            $newVerifUrl = "https://yourdomain.edu.ph/verify/certificate.php?code=$newVerifCode";
            $insert->execute([
                $replacementNum, $current['student_id'], $current['enrollment_id'], $current['certificate_type'],
                $current['nc_level'], $current['program_id'], date('Y-m-d'), date('Y-m-d'), $newValidUntil,
                $newVerifCode, $newVerifUrl, $certId, $userId, $userId
            ]);

            // Update old cert as replaced
            $update = $conn->prepare("UPDATE certificates SET status = 'Expired', updated_at = NOW() WHERE certificate_id = ?");
            $update->execute([$certId]);

            $log = $conn->prepare("INSERT INTO certificate_history (certificate_id, action, reason, performed_by) VALUES (?, 'Renew', 'Certificate renewed', ?)");
            $log->execute([$certId, $userId]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Certificate renewed successfully.'];
        }

        if ($action === 'approve_certificate') {
            $certId = $_POST['certificate_id'];
            $update = $conn->prepare("UPDATE certificates SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE certificate_id = ?");
            $update->execute([$userId, $certId]);

            $log = $conn->prepare("INSERT INTO certificate_history (certificate_id, action, performed_by) VALUES (?, 'Approve', ?)");
            $log->execute([$certId, $userId]);

            $response = ['success' => true, 'message' => 'Certificate approved.'];
        }

        if ($action === 'bulk_action') {
            $selected = $_POST['selected_certs'] ?? [];
            $bulkAction = $_POST['bulk_action'] ?? '';

            if (empty($selected)) {
                throw new Exception("No certificates selected");
            }

            $placeholders = implode(',', array_fill(0, count($selected), '?'));

            switch ($bulkAction) {
                case 'bulk_issue':
                    $stmt = $conn->prepare("UPDATE certificates SET status = 'Issued', issued_by = ?, issued_at = NOW() WHERE certificate_id IN ($placeholders)");
                    $params = array_merge([$userId], $selected);
                    $stmt->execute($params);
                    $response['message'] = count($selected) . ' certificates issued.';
                    break;

                case 'bulk_approve':
                    $stmt = $conn->prepare("UPDATE certificates SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE certificate_id IN ($placeholders)");
                    $params = array_merge([$userId], $selected);
                    $stmt->execute($params);
                    $response['message'] = count($selected) . ' certificates approved.';
                    break;

                case 'bulk_revoke':
                    $stmt = $conn->prepare("UPDATE certificates SET status = 'Revoked', revoked_by = ?, revoked_at = NOW() WHERE certificate_id IN ($placeholders)");
                    $params = array_merge([$userId], $selected);
                    $stmt->execute($params);
                    $response['message'] = count($selected) . ' certificates revoked.';
                    break;

                case 'bulk_archive':
                    $stmt = $conn->prepare("UPDATE certificates SET status = 'Archived' WHERE certificate_id IN ($placeholders)");
                    $stmt->execute($selected);
                    $response['message'] = count($selected) . ' certificates archived.';
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

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="certificates_export_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Certificate #', 'Student ID', 'Student Name', 'Type', 'NC Level',
        'Issue Date', 'Valid Until', 'Status', 'Verification Code', 'Honors'
    ]);

    foreach ($certificates as $c) {
        fputcsv($output, [
            $c['certificate_number'],
            $c['SchoolID'],
            $c['FirstName'] . ' ' . $c['LastName'],
            $c['certificate_type'],
            $c['nc_level'] ?? 'N/A',
            $c['issue_date'],
            $c['valid_until'] ?? 'N/A',
            $c['status'],
            $c['verification_code'] ?? 'N/A',
            $c['honors'] ?? 'N/A'
        ]);
    }
    fclose($output);
    exit();
}
?>

<div class="content-header">
    <div class="header-actions">
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Issue Certificate
        </button>
        <button class="btn btn-secondary" onclick="window.location.href='?export=csv'">
            <i class="fas fa-download"></i> Export CSV
        </button>
    </div>
</div>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= count($certificates) ?></div>
        <div class="stat-label">Total Certificates</div>
    </div>
    <div class="stat-card text-success">
        <div class="stat-value"><?= $counts['Issued'] ?? 0 ?></div>
        <div class="stat-label">Issued</div>
    </div>
    <div class="stat-card text-warning">
        <div class="stat-value"><?= $counts['Pending'] ?? 0 + ($counts['Draft'] ?? 0) ?></div>
        <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card text-danger">
        <div class="stat-value"><?= $counts['Revoked'] ?? 0 + ($counts['Expired'] ?? 0) ?></div>
        <div class="stat-label">Revoked/Expired</div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Filter Certificates</h3>
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
                    <label>Type</label>
                    <select name="type">
                        <option value="All" <?= $certType === 'All' ? 'selected' : '' ?>>All Types</option>
                        <?php foreach ($certTypes as $t): ?>
                            <option value="<?= $t ?>" <?= $certType === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>NC Level</label>
                    <select name="nc_level">
                        <option value="All" <?= $ncLevel === 'All' ? 'selected' : '' ?>>All Levels</option>
                        <?php foreach ($ncLevels as $nl): ?>
                            <option value="<?= $nl ?>" <?= $ncLevel === $nl ? 'selected' : '' ?>><?= $nl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Program</label>
                    <select name="program">
                        <option value="All">All Programs</option>
                        <?php foreach ($programs as $p): ?>
                            <option value="<?= $p['program_id'] ?>" <?= $programFilter == $p['program_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['program_code']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Expiry</label>
                    <select name="expiry">
                        <option value="All" <?= $expiryFilter === 'All' ? 'selected' : '' ?>>All</option>
                        <option value="Expiring Soon" <?= $expiryFilter === 'Expiring Soon' ? 'selected' : '' ?>>Expiring Soon</option>
                        <option value="Expired" <?= $expiryFilter === 'Expired' ? 'selected' : '' ?>>Expired</option>
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
                    <input type="text" name="search" placeholder="Student name, ID, cert #" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-group filter-submit">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="?" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Actions -->
<div class="card" id="bulk-certs" style="display: none; margin-top: 1rem;">
    <div class="card-body">
        <form method="POST" id="bulkCertForm">
            <input type="hidden" name="action" id="bulkCertAction" value="">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="font-weight: bold;" id="cert-selected-count">0 selected</span>
                <select name="bulk_action" id="bulkCertSelect" class="form-select" style="width: auto;">
                    <option value="">Choose Action</option>
                    <option value="bulk_issue">Issue Selected</option>
                    <option value="bulk_approve">Approve Selected</option>
                    <option value="bulk_revoke">Revoke Selected</option>
                    <option value="bulk_archive">Archive Selected</option>
                </select>
                <button type="button" class="btn btn-primary" onclick="executeBulkCertAction()">Execute</button>
                <button type="button" class="btn btn-secondary" onclick="clearCertSelection()">Clear</button>
            </div>
        </form>
    </div>
</div>

<!-- Certificates Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Certificates</h3>
        <span class="badge"><?= count($certificates) ?> records</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-certs" onchange="toggleSelectAllCerts()"></th>
                        <th>Certificate #</th>
                        <th>Student</th>
                        <th>Type</th>
                        <th>NC Level</th>
                        <th>Issue Date</th>
                        <th>Valid Until</th>
                        <th>Status</th>
                        <th>Verification</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($certificates as $c): ?>
                        <tr>
                            <td><input type="checkbox" class="cert-checkbox" value="<?= $c['certificate_id'] ?>"></td>
                            <td><strong><?= htmlspecialchars($c['certificate_number']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($c['FirstName'] . ' ' . $c['LastName']) ?><br>
                                <small><?= htmlspecialchars($c['SchoolID']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($c['certificate_type']) ?></td>
                            <td><?= htmlspecialchars($c['nc_level'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($c['issue_date']) ?></td>
                            <td><?= htmlspecialchars($c['valid_until'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $c['status'])) ?>">
                                    <?= htmlspecialchars($c['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($c['verification_code'])): ?>
                                    <span class="text-success" title="<?= htmlspecialchars($c['verification_code']) ?>">
                                        <i class="fas fa-check-circle"></i> Enabled
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-info" onclick="viewCertificate(<?= $c['certificate_id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($c['status'] === 'Draft' || $c['status'] === 'Pending'): ?>
                                        <button class="btn btn-sm btn-primary" onclick="editCertificate(<?= $c['certificate_id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($c['status'] === 'Draft' || $c['status'] === 'Pending' || $c['status'] === 'Approved'): ?>
                                        <button class="btn btn-sm btn-success" onclick="issueCertificate(<?= $c['certificate_id'] ?>)">
                                            <i class="fas fa-file-pdf"></i> Issue
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($c['status'] === 'Issued' || $c['status'] === 'Active'): ?>
                                        <button class="btn btn-sm btn-warning" onclick="renewCertificate(<?= $c['certificate_id'] ?>)">
                                            <i class="fas fa-sync"></i> Renew
                                        </button>
                                    <?php endif; ?>
                                    <?php if (in_array($c['status'], ['Issued', 'Active', 'Approved'])): ?>
                                        <button class="btn btn-sm btn-danger" onclick="revokeCertificate(<?= $c['certificate_id'] ?>)">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-secondary" onclick="viewHistory(<?= $c['certificate_id'] ?>)">
                                        <i class="fas fa-history"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($certificates)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 2rem;">
                                No certificates found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit Certificate Modal -->
<div class="modal" id="certificateModal" style="display: none;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 id="certModalTitle">Issue Certificate</h3>
            <button class="btn-close" onclick="closeCertModal()">&times;</button>
        </div>
        <form id="certificateForm" method="POST">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Student</label>
                        <select name="student_id" id="certStudentSelect" required>
                            <option value="">Select Student</option>
                            <?php foreach ($conn->query("SELECT StudID, FirstName, LastName, SchoolID FROM student ORDER BY FirstName") as $s): ?>
                                <option value="<?= $s['StudID'] ?>">
                                    <?= htmlspecialchars($s['FirstName'] . ' ' . $s['LastName'] . ' (' . $s['SchoolID'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Enrollment</label>
                        <select name="enrollment_id" id="certEnrollmentSelect" required>
                            <option value="">Select Enrollment</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Certificate Type</label>
                        <select name="certificate_type" id="certTypeSelect" required>
                            <option value="">Select Type</option>
                            <?php foreach ($certTypes as $ct): ?>
                                <option value="<?= $ct ?>"><?= $ct ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>NC Level</label>
                        <select name="nc_level" id="ncLevelSelect">
                            <option value="">None</option>
                            <?php foreach ($ncLevels as $nl): ?>
                                <option value="<?= $nl ?>"><?= $nl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Program</label>
                        <select name="program_id">
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= $p['program_id'] ?>"><?= htmlspecialchars($p['program_code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Issue Date</label>
                        <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Valid Until</label>
                        <input type="date" name="valid_until" placeholder="Leave blank for lifetime validity">
                    </div>
                    <div class="form-group">
                        <label>Honors</label>
                        <input type="text" name="honors" placeholder="e.g. Cum Laude">
                    </div>
                </div>

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" placeholder="Certificate title">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Detailed description..."></textarea>
                </div>

                <!-- Modules/Competencies -->
                <div class="modules-section" style="margin-top: 1.5rem;">
                    <h4>Included Competencies</h4>
                    <p class="text-muted">Select the modules/competencies this certificate covers</p>
                    <div class="modules-grid">
                        <?php
                        $allModules = $conn->query("SELECT module_id, module_code, module_title FROM training_modules ORDER BY module_code")->fetchAll();
                        foreach ($allModules as $m):
                        ?>
                            <label style="display: block; margin-bottom: 0.5rem;">
                                <input type="checkbox" name="modules[]" value="<?= $m['module_id'] ?>">
                                <?= htmlspecialchars($m['module_code'] . ' - ' . $m['module_title']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCertModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Certificate</button>
            </div>
        </form>
    </div>
</div>

<!-- View Certificate Modal -->
<div class="modal" id="viewCertModal" style="display: none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3>Certificate Details</h3>
            <button class="btn-close" onclick="closeViewCertModal()">&times;</button>
        </div>
        <div class="modal-body" id="cert-details"></div>
    </div>
</div>

<!-- Renew Certificate Modal -->
<div class="modal" id="renewCertModal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Renew Certificate</h3>
            <button class="btn-close" onclick="closeRenewModal()">&times;</button>
        </div>
        <form id="renewForm" method="POST">
            <input type="hidden" name="action" value="renew_certificate">
            <input type="hidden" name="certificate_id" id="renewCertId">
            <div class="modal-body">
                <div class="form-group">
                    <label>New Valid Until Date</label>
                    <input type="date" name="new_valid_until" required>
                </div>
                <div class="form-group">
                    <label>Reason for Renewal</label>
                    <textarea name="renewal_reason" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRenewModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Renew Certificate</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('certStudentSelect').addEventListener('change', function() {
        loadCertEnrollments(this.value);
    });
});

function loadCertEnrollments(studentId) {
    if (!studentId) {
        document.getElementById('certEnrollmentSelect').innerHTML = '<option value="">Select Enrollment</option>';
        return;
    }

    fetch('../ajax/get_enrollments.php?student_id=' + studentId)
        .then(response => response.json())
        .then(function(data) {
            const select = document.getElementById('certEnrollmentSelect');
            select.innerHTML = '<option value="">Select Enrollment</option>';
            data.forEach(function(enrollment) {
                select.innerHTML += '<option value="' + enrollment.enrollment_id + '">' + (enrollment.batch_code || 'N/A') + ' - ' + (enrollment.nc_level || 'N/A') + '</option>';
            });
        });
}

function openCreateModal() {
    document.getElementById('certificateModal').style.display = 'flex';
    document.getElementById('certModalTitle').innerText = 'Issue Certificate';
    document.getElementById('certificateForm').reset();
}

function closeCertModal() {
    document.getElementById('certificateModal').style.display = 'none';
}

function closeViewCertModal() {
    document.getElementById('viewCertModal').style.display = 'none';
}

function closeRenewModal() {
    document.getElementById('renewCertModal').style.display = 'none';
}

function viewCertificate(id) {
    fetch('view_certificate_ajax.php?id=' + id)
        .then(function(r) { return r.text(); })
        .then(function(html) {
            document.getElementById('cert-details').innerHTML = html;
            document.getElementById('viewCertModal').style.display = 'flex';
        });
}

function editCertificate(id) {
    fetch('edit_certificate_ajax.php?id=' + id)
        .then(function(r) { return r.text(); })
        .then(function(html) {
            document.querySelector('#certificateModal .modal-body').innerHTML = html;
            document.getElementById('certificateModal').style.display = 'flex';
            document.getElementById('certModalTitle').innerText = 'Edit Certificate';
        });
}

function issueCertificate(id) {
    if (confirm('Issue this certificate? PDF will be generated.')) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=issue_certificate&certificate_id=' + id + '&ajax=1'
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

function revokeCertificate(id) {
    const reason = prompt('Reason for revocation:');
    if (reason) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=revoke_certificate&certificate_id=' + id + '&reason=' + encodeURIComponent(reason) + '&ajax=1'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Certificate revoked');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function renewCertificate(id) {
    document.getElementById('renewCertId').value = id;
    document.getElementById('renewCertModal').style.display = 'flex';
}

function viewHistory(certId) {
    fetch('certificate_history_ajax.php?certificate_id=' + certId)
        .then(function(r) { return r.text(); })
        .then(function(html) {
            document.getElementById('cert-details').innerHTML = html;
            document.getElementById('viewCertModal').style.display = 'flex';
        });
}

function toggleSelectAllCerts() {
    const selectAll = document.getElementById('select-all-certs');
    document.querySelectorAll('.cert-checkbox').forEach(cb => cb.checked = selectAll.checked);
    updateBulkCertCount();
}

function updateBulkCertCount() {
    const checked = document.querySelectorAll('.cert-checkbox:checked');
    const panel = document.getElementById('bulk-certs');
    const countSpan = document.getElementById('cert-selected-count');

    if (checked.length > 0) {
        panel.style.display = 'block';
        countSpan.textContent = checked.length + ' selected';
    } else {
        panel.style.display = 'none';
    }
}

function executeBulkCertAction() {
    const action = document.getElementById('bulkCertSelect').value;
    if (!action) return alert('Select an action');

    if (confirm('Execute on all selected certificates?')) {
        document.getElementById('bulkCertForm').submit();
    }
}

function clearCertSelection() {
    document.getElementById('select-all-certs').checked = false;
    document.querySelectorAll('.cert-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('bulk-certs').style.display = 'none';
}

document.querySelectorAll('.cert-checkbox').forEach(cb => {
    cb.addEventListener('change', updateBulkCertCount);
});
</script>

<?php
include '../includes/footer.php';
?>
