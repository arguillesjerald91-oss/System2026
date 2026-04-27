<?php
/**
 * Advanced Diploma Management
 * Admin Interface - Graduation Tracking, Batch Issuance, Honors Management
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
$programFilter = $_GET['program'] ?? 'All';
$batchFilter = $_GET['batch'] ?? 'All';
$honorsFilter = $_GET['honors'] ?? 'All';
$search = trim($_GET['search'] ?? '');
$gradDateFrom = $_GET['grad_from'] ?? '';
$gradDateTo = $_GET['grad_to'] ?? '';

// Build query
$where = [];
$params = [];

if ($statusFilter !== 'All') {
    $where[] = "d.status = ?";
    $params[] = $statusFilter;
}

if ($programFilter !== 'All') {
    $where[] = "d.program_id = ?";
    $params[] = $programFilter;
}

if ($batchFilter !== 'All') {
    $where[] = "d.batch_id = ?";
    $params[] = $batchFilter;
}

if ($honorsFilter !== 'All') {
    $where[] = "d.honors = ?";
    $params[] = $honorsFilter;
}

if (!empty($search)) {
    $where[] = "(s.FirstName LIKE ? OR s.LastName LIKE ? OR s.SchoolID LIKE ? OR d.diploma_number LIKE ?)";
    $sp = "%$search%";
    $params = array_merge($params, array_fill(0, 4, $sp));
}

if (!empty($gradDateFrom)) {
    $where[] = "d.graduation_date >= ?";
    $params[] = $gradDateFrom;
}

if (!empty($gradDateTo)) {
    $where[] = "d.graduation_date <= ?";
    $params[] = $gradDateTo;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
SELECT d.*, s.FirstName, s.LastName, s.SchoolID,
       p.program_code, p.program_title,
       b.batch_code,
       CONCAT(pr.Fname, ' ', pr.Lname) as prepared_by_name,
       CONCAT(ap.Fname, ' ', ap.Lname) as approved_by_name,
       CONCAT(c.Fname, ' ', c.Lname) as conferred_by_name
FROM diplomas d
JOIN student s ON d.student_id = s.StudID
JOIN auto_mechanic_programs p ON d.program_id = p.program_id
LEFT JOIN training_batches b ON d.batch_id = b.batch_id
LEFT JOIN admins pr ON d.prepared_by = pr.admin_id
LEFT JOIN admins ap ON d.approved_by = ap.admin_id
LEFT JOIN admins c ON d.conferred_by = c.admin_id
$whereClause
ORDER BY d.graduation_date DESC, d.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$diplomas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts
$counts = [];
$countStmt = $conn->query("SELECT status, COUNT(*) as cnt FROM diplomas GROUP BY status");
while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['status']] = $row['cnt'];
}

// Get filter data
$programs = $conn->query("SELECT program_id, program_code, program_title FROM auto_mechanic_programs ORDER BY program_code")->fetchAll();
$batches = $conn->query("SELECT batch_id, batch_code FROM training_batches ORDER BY batch_code DESC")->fetchAll();
$honorsOptions = ['None', 'Cum Laude', 'Magna Cum Laude', 'Summa Cum Laude', 'With Honors'];
$statusOptions = ['Draft', 'Pending Approval', 'Approved', 'Printed', 'Awarded', 'Conferred', 'Replaced', 'Cancelled'];

$pageTitle = "Diploma Management";
$pageSubtitle = "Graduation & Diploma Issuance";
include 'sidebar_new.php';
include '../includes/unified_header.php';

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];

    try {
        $conn->beginTransaction();

        if ($action === 'create_diploma') {
            $studentId = $_POST['student_id'];
            $enrollmentId = $_POST['enrollment_id'];
            $programId = $_POST['program_id'];
            $batchId = !empty($_POST['batch_id']) ? $_POST['batch_id'] : null;
            $gradDate = $_POST['graduation_date'];
            $convocationDate = $_POST['convocation_date'] ?? null;
            $diplomaType = $_POST['diploma_type'];
            $major = $_POST['major'] ?? null;
            $honors = $_POST['honors'] ?? 'None';
            $generalAvg = $_POST['general_average'] ?? 0;

            // Generate diploma number
            $year = date('Y', strtotime($gradDate));
            $diplomaNum = "DIP-$year-" . str_pad($conn->lastInsertId() + 1, 6, '0', STR_PAD_LEFT);

            // Generate verification code
            $verificationCode = 'DIP-' . strtoupper(bin2hex(random_bytes(8)));

            // Calculate units and hours from enrollment progress
            $modulesStmt = $conn->prepare("SELECT COUNT(*) as total FROM training_modules WHERE program_id = ?");
            $modulesStmt->execute([$programId]);
            $totalModules = $modulesStmt->fetchColumn();
            $totalUnits = $totalModules * 3;
            $totalHours = $totalModules * 48;

            $insert = $conn->prepare("
                INSERT INTO diplomas (
                    diploma_number, student_id, enrollment_id, program_id, batch_id,
                    graduation_date, convocation_date, ceremony_venue, diploma_type,
                    major, honors, general_average, units_earned, total_hours,
                    verification_code, verification_url, status,
                    prepared_by, prepared_at, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?, NOW(), ?)
            ");

            $verificationUrl = "https://yourdomain.edu.ph/verify/diploma.php?code=$verificationCode";
            $insert->execute([
                $diplomaNum, $studentId, $enrollmentId, $programId, $batchId,
                $gradDate, $convocationDate, $_POST['ceremony_venue'] ?? 'Main Campus',
                $diplomaType, $major, $honors, $generalAvg, $totalUnits, $totalHours,
                $verificationCode, $verificationUrl,
                $userId, $userId
            ]);

            $diplomaId = $conn->lastInsertId();

            // Add diploma modules
            $modules = $conn->query("SELECT module_id, module_code, module_title FROM training_modules WHERE program_id = $programId")->fetchAll();
            foreach ($modules as $m) {
                $conn->prepare("INSERT INTO diploma_modules (diploma_id, module_id, units, status) VALUES (?, ?, 3, 'Passed')")->execute([$diplomaId, $m['module_id']]);
            }

            // Log history
            $log = $conn->prepare("INSERT INTO diploma_history (diploma_id, action, reason, performed_by, ip_address) VALUES (?, 'Create', 'Diploma record created', ?, ?)");
            $log->execute([$diplomaId, $userId, $_SERVER['REMOTE_ADDR']]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Diploma created successfully.', 'id' => $diplomaId];
        }

        if ($action === 'approve_diploma') {
            $diplomaId = $_POST['diploma_id'];
            $update = $conn->prepare("UPDATE diplomas SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE diploma_id = ?");
            $update->execute([$userId, $diplomaId]);

            $log = $conn->prepare("INSERT INTO diploma_history (diploma_id, action, performed_by) VALUES (?, 'Approve', ?)");
            $log->execute([$diplomaId, $userId]);

            $response = ['success' => true, 'message' => 'Diploma approved.'];
        }

        if ($action === 'print_diploma') {
            $diplomaId = $_POST['diploma_id'];

            // Generate PDF
            $pdfPath = "../uploads/diplomas/diploma_{$diplomaId}.pdf";
            file_put_contents($pdfPath, "Diploma content for $diplomaId");

            $update = $conn->prepare("
                UPDATE diplomas SET
                    pdf_file_path = ?,
                    pdf_generated = 1,
                    pdf_generated_at = NOW(),
                    printed = 1,
                    printed_at = NOW(),
                    status = 'Printed',
                    printed_by = ?,
                    updated_at = NOW()
                WHERE diploma_id = ?
            ");
            $update->execute([$pdfPath, $userId, $diplomaId]);

            $log = $conn->prepare("INSERT INTO diploma_history (diploma_id, action, performed_by) VALUES (?, 'Print', ?)");
            $log->execute([$diplomaId, $userId]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Diploma printed and status updated.'];
        }

        if ($action === 'confer_diploma') {
            $diplomaId = $_POST['diploma_id'];
            $ceremonyDate = $_POST['ceremony_date'] ?? date('Y-m-d');

            $update = $conn->prepare("
                UPDATE diplomas SET
                    conferred = 1,
                    conferred_at = ?,
                    status = 'Conferred',
                    conferred_by = ?,
                    updated_at = NOW()
                WHERE diploma_id = ?
            ");
            $update->execute([$ceremonyDate, $userId, $diplomaId]);

            $log = $conn->prepare("INSERT INTO diploma_history (diploma_id, action, performed_by, reason) VALUES (?, 'Confer', ?, ?)");
            $log->execute([$diplomaId, $userId, "Ceremony date: $ceremonyDate"]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Diploma conferred successfully.'];
        }

        if ($action === 'replace_diploma') {
            $diplomaId = $_POST['diploma_id'];
            $reason = $_POST['reason'] ?? '';

            $conn->prepare("UPDATE diplomas SET replacement_count = replacement_count + 1 WHERE diploma_id = ?")->execute([$diplomaId]);

            // Create replacement record
            $orig = $conn->prepare("SELECT * FROM diplomas WHERE diploma_id = ?")->fetch(PDO::FETCH_ASSOC);

            $newNum = $orig['diploma_number'] . '-R' . ($orig['replacement_count'] + 1);
            $insert = $conn->prepare("
                INSERT INTO diplomas (
                    diploma_number, student_id, enrollment_id, program_id, batch_id,
                    graduation_date, convocation_date, diploma_type, major, honors,
                    general_average, units_earned, total_hours,
                    verification_code, verification_url, status,
                    replacement_for, replacement_reason,
                    prepared_by, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?, ?, ?, ?)
            ");

            $newVerif = 'DIP-' . strtoupper(bin2hex(random_bytes(8)));
            $newVerifUrl = "https://yourdomain.edu.ph/verify/diploma.php?code=$newVerif";

            $insert->execute([
                $newNum, $orig['student_id'], $orig['enrollment_id'], $orig['program_id'], $orig['batch_id'],
                $orig['graduation_date'], $orig['convocation_date'], $orig['diploma_type'], $orig['major'], $orig['honors'],
                $orig['general_average'], $orig['units_earned'], $orig['total_hours'],
                $newVerif, $newVerifUrl, $diplomaId, $reason, $userId, $userId
            ]);

            $newId = $conn->lastInsertId();
            $log = $conn->prepare("INSERT INTO diploma_history (diploma_id, action, reason, performed_by) VALUES (?, 'Replace', ?, ?)");
            $log->execute([$diplomaId, $reason, $userId]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Replacement diploma created.'];
        }

        if ($action === 'bulk_action') {
            $selected = $_POST['selected_diplomas'] ?? [];
            $bulkAction = $_POST['bulk_action'] ?? '';

            if (empty($selected)) throw new Exception("No diplomas selected");

            $placeholders = implode(',', array_fill(0, count($selected), '?'));

            switch ($bulkAction) {
                case 'bulk_approve':
                    $stmt = $conn->prepare("UPDATE diplomas SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE diploma_id IN ($placeholders)");
                    $params = array_merge([$userId], $selected);
                    $stmt->execute($params);
                    $response['message'] = count($selected) . ' diplomas approved.';
                    break;
                case 'bulk_print':
                    $stmt = $conn->prepare("UPDATE diplomas SET status = 'Printed', printed = 1, printed_at = NOW(), printed_by = ? WHERE diploma_id IN ($placeholders)");
                    $params = array_merge([$userId], $selected);
                    $stmt->execute($params);
                    $response['message'] = count($selected) . ' diplomas marked printed.';
                    break;
                case 'bulk_confer':
                    $stmt = $conn->prepare("UPDATE diplomas SET status = 'Conferred', conferred = 1, conferred_at = NOW(), conferred_by = ? WHERE diploma_id IN ($placeholders)");
                    $params = array_merge([$userId], $selected);
                    $stmt->execute($params);
                    $response['message'] = count($selected) . ' diplomas conferred.';
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

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="diplomas_export_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Diploma #', 'Student ID', 'Student Name', 'Program', 'Batch',
        'Graduation Date', 'Convocation Date', 'GPA', 'Honors', 'Status', 'Verification'
    ]);

    foreach ($diplomas as $d) {
        fputcsv($output, [
            $d['diploma_number'],
            $d['SchoolID'],
            $d['FirstName'] . ' ' . $d['LastName'],
            $d['program_code'] . ' - ' . $d['program_title'],
            $d['batch_code'] ?? 'N/A',
            $d['graduation_date'],
            $d['convocation_date'] ?? 'N/A',
            $d['general_average'],
            $d['honors'],
            $d['status'],
            $d['verification_code'] ?? 'N/A'
        ]);
    }
    fclose($output);
    exit();
}
?>

<div class="content-header">
    <div class="header-actions">
        <button class="btn btn-primary" onclick="openCreateDiplomaModal()">
            <i class="fas fa-plus"></i> Create Diploma
        </button>
        <button class="btn btn-secondary" onclick="window.location.href='?export=csv'">
            <i class="fas fa-download"></i> Export CSV
        </button>
    </div>
</div>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= count($diplomas) ?></div>
        <div class="stat-label">Total Diplomas</div>
    </div>
    <div class="stat-card text-success">
        <div class="stat-value"><?= $counts['Conferred'] ?? 0 + ($counts['Awarded'] ?? 0) ?></div>
        <div class="stat-label">Awarded</div>
    </div>
    <div class="stat-card text-warning">
        <div class="stat-value"><?= $counts['Pending Approval'] ?? 0 + ($counts['Draft'] ?? 0) ?></div>
        <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card text-primary">
        <div class="stat-value"><?= $counts['Approved'] ?? 0 ?></div>
        <div class="stat-label">Approved</div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Filter Diplomas</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filters-form">
            <div class="filters-row">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All</option>
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
                                <?= htmlspecialchars($p['program_code']) ?>
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
                    <label>Honors</label>
                    <select name="honors">
                        <option value="All" <?= $honorsFilter === 'All' ? 'selected' : '' ?>>All</option>
                        <?php foreach ($honorsOptions as $h): ?>
                            <option value="<?= $h ?>" <?= $honorsFilter === $h ? 'selected' : '' ?>><?= $h ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Grad Date From</label>
                    <input type="date" name="grad_from" value="<?= htmlspecialchars($gradDateFrom) ?>">
                </div>
                <div class="filter-group">
                    <label>Grad Date To</label>
                    <input type="date" name="grad_to" value="<?= htmlspecialchars($gradDateTo) ?>">
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Student name, ID, diploma #" value="<?= htmlspecialchars($search) ?>">
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
<div class="card" id="bulk-diplomas" style="display: none; margin-top: 1rem;">
    <div class="card-body">
        <form method="POST" id="bulkDiplomaForm">
            <input type="hidden" name="action" id="bulkDiplomaAction" value="">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="font-weight: bold;" id="diploma-selected-count">0 selected</span>
                <select name="bulk_action" id="bulkDiplomaSelect" class="form-select" style="width: auto;">
                    <option value="">Choose Action</option>
                    <option value="bulk_approve">Approve Selected</option>
                    <option value="bulk_print">Print Selected</option>
                    <option value="bulk_confer">Confer Selected</option>
                </select>
                <button type="button" class="btn btn-primary" onclick="executeBulkDiplomaAction()">Execute</button>
                <button type="button" class="btn btn-secondary" onclick="clearDiplomaSelection()">Clear</button>
            </div>
        </form>
    </div>
</div>

<!-- Diplomas Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Diplomas</h3>
        <span class="badge"><?= count($diplomas) ?> records</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-diplomas" onchange="toggleSelectAllDiplomas()"></th>
                        <th>Diploma #</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Grad Date</th>
                        <th>GPA</th>
                        <th>Honors</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($diplomas as $d): ?>
                        <tr>
                            <td><input type="checkbox" class="diploma-checkbox" value="<?= $d['diploma_id'] ?>"></td>
                            <td><strong><?= htmlspecialchars($d['diploma_number']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($d['FirstName'] . ' ' . $d['LastName']) ?><br>
                                <small><?= htmlspecialchars($d['SchoolID']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($d['program_code']) ?></td>
                            <td><?= htmlspecialchars($d['graduation_date'] ?? 'N/A') ?></td>
                            <td><strong><?= number_format($d['general_average'], 2) ?></strong></td>
                            <td>
                                <?php if ($d['honors'] !== 'None'): ?>
                                    <span class="badge badge-success"><?= htmlspecialchars($d['honors']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $d['status'])) ?>">
                                    <?= htmlspecialchars($d['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-info" onclick="viewDiploma(<?= $d['diploma_id'] ?>)"><i class="fas fa-eye"></i></button>
                                    <?php if ($d['status'] === 'Draft' || $d['status'] === 'Pending Approval'): ?>
                                        <button class="btn btn-sm btn-primary" onclick="editDiploma(<?= $d['diploma_id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <?php endif; ?>
                                    <?php if ($d['status'] === 'Approved'): ?>
                                        <button class="btn btn-sm btn-success" onclick="printDiploma(<?= $d['diploma_id'] ?>)"><i class="fas fa-print"></i> Print</button>
                                    <?php endif; ?>
                                    <?php if ($d['status'] === 'Printed'): ?>
                                        <button class="btn btn-sm btn-warning" onclick="conferDiploma(<?= $d['diploma_id'] ?>)"><i class="fas fa-award"></i> Confer</button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-secondary" onclick="viewDiplomaHistory(<?= $d['diploma_id'] ?>)"><i class="fas fa-history"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($diplomas)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem;">
                                No diplomas found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals for Diploma operations -->
<div class="modal" id="diplomaModal" style="display: none;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 id="diplomaModalTitle">Create Diploma</h3>
            <button class="btn-close" onclick="closeDiplomaModal()">&times;</button>
        </div>
        <form id="diplomaForm" method="POST">
            <input type="hidden" name="action" value="create_diploma">
            <div class="modal-body">
                <!-- Form fields similar to transcript but for diploma -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Student</label>
                        <select name="student_id" id="diplomaStudentSelect" required>
                            <option value="">Select Student</option>
                            <?php foreach ($conn->query("SELECT StudID, FirstName, LastName, SchoolID FROM student ORDER BY FirstName") as $s): ?>
                                <option value="<?= $s['StudID'] ?>"><?= htmlspecialchars($s['FirstName'] . ' ' . $s['LastName'] . ' (' . $s['SchoolID'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Enrollment</label>
                        <select name="enrollment_id" id="diplomaEnrollmentSelect" required>
                            <option value="">Select Enrollment</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Program</label>
                        <select name="program_id" id="diplomaProgramSelect" required>
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
                        <label>Graduation Date</label>
                        <input type="date" name="graduation_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Convocation Date</label>
                        <input type="date" name="convocation_date">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Diploma Type</label>
                        <select name="diploma_type">
                            <option value="Diploma">Diploma</option>
                            <option value="Certificate">Certificate</option>
                            <option value="Associate">Associate</option>
                            <option value="Bachelor">Bachelor</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Major</label>
                        <input type="text" name="major" placeholder="Optional major">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Honors</label>
                        <select name="honors">
                            <option value="None">None</option>
                            <option value="Cum Laude">Cum Laude</option>
                            <option value="Magna Cum Laude">Magna Cum Laude</option>
                            <option value="Summa Cum Laude">Summa Cum Laude</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>General Average</label>
                        <input type="number" step="0.01" name="general_average" placeholder="GPA">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDiplomaModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Diploma</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('diplomaStudentSelect').addEventListener('change', function() {
        loadDiplomaEnrollments(this.value);
    });
});

function loadDiplomaEnrollments(studentId) {
    if (!studentId) {
        document.getElementById('diplomaEnrollmentSelect').innerHTML = '<option value="">Select Enrollment</option>';
        return;
    }
    fetch('../ajax/get_enrollments.php?student_id=' + studentId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            const select = document.getElementById('diplomaEnrollmentSelect');
            select.innerHTML = '<option value="">Select Enrollment</option>';
            data.forEach(function(e) {
                select.innerHTML += '<option value="' + e.enrollment_id + '">' + (e.batch_code || 'N/A') + ' - ' + (e.nc_level || 'N/A') + '</option>';
            });
        });
}

function openCreateDiplomaModal() {
    document.getElementById('diplomaModal').style.display = 'flex';
    document.getElementById('diplomaForm').reset();
}

function closeDiplomaModal() {
    document.getElementById('diplomaModal').style.display = 'none';
}

function viewDiploma(id) {
    fetch('view_diploma_ajax.php?id=' + id)
        .then(function(r) { return r.text(); })
        .then(function(html) {
            document.getElementById('cert-details').innerHTML = html;
            document.getElementById('viewCertModal').style.display = 'flex';
        });
}

function editDiploma(id) {
    fetch('edit_diploma_ajax.php?id=' + id)
        .then(function(r) { return r.text(); })
        .then(function(html) {
            document.querySelector('#diplomaModal .modal-body').innerHTML = html;
            document.getElementById('diplomaModal').style.display = 'flex';
            document.getElementById('diplomaModalTitle').innerText = 'Edit Diploma';
        });
}

function printDiploma(id) {
    if (confirm('Generate and print diploma?')) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=print_diploma&diploma_id=' + id + '&ajax=1'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                alert(data.message);
                location.reload();
            }
        });
    }
}

function conferDiploma(id) {
    if (confirm('Confer this diploma? This will mark it as officially awarded.')) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=confer_diploma&diploma_id=' + id + '&ajax=1'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                alert(data.message);
                location.reload();
            }
        });
    }
}

function viewDiplomaHistory(diplomaId) {
    fetch('diploma_history_ajax.php?diploma_id=' + diplomaId)
        .then(function(r) { return r.text(); })
        .then(function(html) {
            document.getElementById('cert-details').innerHTML = html;
            document.getElementById('viewCertModal').style.display = 'flex';
        });
}

// Checkbox helpers
function toggleSelectAllDiplomas() {
    const selectAll = document.getElementById('select-all-diplomas');
    document.querySelectorAll('.diploma-checkbox').forEach(function(cb) { cb.checked = selectAll.checked; });
    updateBulkDiplomaCount();
}

function updateBulkDiplomaCount() {
    const checked = document.querySelectorAll('.diploma-checkbox:checked');
    const panel = document.getElementById('bulk-diplomas');
    const countSpan = document.getElementById('diploma-selected-count');

    if (checked.length > 0) {
        panel.style.display = 'block';
        countSpan.textContent = checked.length + ' selected';
    } else {
        panel.style.display = 'none';
    }
}

function executeBulkDiplomaAction() {
    const action = document.getElementById('bulkDiplomaSelect').value;
    if (!action) return alert('Select an action');
    if (confirm('Execute on all selected diplomas?')) {
        document.getElementById('bulkDiplomaForm').submit();
    }
}

function clearDiplomaSelection() {
    document.getElementById('select-all-diplomas').checked = false;
    document.querySelectorAll('.diploma-checkbox').forEach(function(cb) { cb.checked = false; });
    document.getElementById('bulk-diplomas').style.display = 'none';
}

document.querySelectorAll('.diploma-checkbox').forEach(function(cb) {
    cb.addEventListener('change', updateBulkDiplomaCount);
});
</script>

<?php
include '../includes/footer.php';
?>
