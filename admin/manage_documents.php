<?php
/**
 * Advanced Documents Management System
 * Centralized repository with version control, permissions, and audit trails
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

// Authorization: Admin and authorized staff only
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if (!in_array($userType, ['admin', 'support_staff', 'instructional_unit', 'registrar'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'] ?? $_SESSION['userId'] ?? 1;
$userRole = $_SESSION['userRole'] ?? $userType;

// Get user's department for data filtering
$staffDeptStmt = $conn->prepare("SELECT department FROM staff_department_assignments WHERE user_id = ? AND is_active = 1 LIMIT 1");
$staffDeptStmt->execute([$userId]);
$staffDepartment = $staffDeptStmt->fetchColumn();

// Filters
$docType = $_GET['type'] ?? 'All';
$categoryFilter = $_GET['category'] ?? 'All';
$statusFilter = $_GET['status'] ?? 'All';
$confidentialFilter = $_GET['confidential'] ?? 'All';
$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$studentFilter = $_GET['student'] ?? '';

$where = [];
$params = [];

if ($docType !== 'All') {
    $where[] = "d.document_type = ?";
    $params[] = $docType;
}

if ($categoryFilter !== 'All') {
    $where[] = "d.category_id = ?";
    $params[] = $categoryFilter;
}

if ($statusFilter !== 'All') {
    $where[] = "d.status = ?";
    $params[] = $statusFilter;
}

if ($confidentialFilter !== 'All') {
    $where[] = "d.confidentiality_level = ?";
    $params[] = $confidentialFilter;
}

if (!empty($search)) {
    $where[] = "(d.title LIKE ? OR d.document_number LIKE ? OR d.tags LIKE ?)";
    $sp = "%$search%";
    $params = array_merge($params, array_fill(0, 3, $sp));
}

if (!empty($dateFrom)) {
    $where[] = "d.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $where[] = "d.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

if (!empty($studentFilter)) {
    $where[] = "(s.FirstName LIKE ? OR s.LastName LIKE ? OR s.SchoolID LIKE ?)";
    $sp = "%$studentFilter%";
    $params = array_merge($params, array_fill(0, 3, $sp));
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// For staff with department restrictions, filter by related documents only
if ($staffDepartment && $userRole !== 'admin') {
    $deptMapping = [
        'Registrar' => ['TOR', 'Certificate', 'Diploma', 'Transcript'],
        'Certification' => ['Certificate'],
        'Academic' => ['Certificate', 'Diploma'],
        'Admission' => ['ID', 'Registration'],
        'Finance' => ['Scholarship', 'Registration'],
        'Records' => ['TOR', 'Transcript', 'Diploma']
    ];

    if (isset($deptMapping[$staffDepartment])) {
        $allowedTypes = $deptMapping[$staffDepartment];
        $placeholders = implode(',', array_fill(0, count($allowedTypes), '?'));
        $params = array_merge($allowedTypes, $params);
        if (strpos($whereClause, 'd.document_type') === false) {
            $typeCondition = "d.document_type IN ($placeholders)";
            $whereClause = $whereClause ? "WHERE $typeCondition AND " . substr($whereClause, 6) : "WHERE $typeCondition";
        }
    }
}

$sql = "
SELECT d.*, dc.category_name, dc.category_code,
       CONCAT(u1.first_name, ' ', u1.last_name) as created_by_name,
       CONCAT(u2.first_name, ' ', u2.last_name) as student_name,
       p.program_code,
       (SELECT COUNT(*) FROM document_versions WHERE document_id = d.document_id) as version_count,
       (SELECT COUNT(*) FROM document_permissions WHERE document_id = d.document_id AND is_active = 1) as permission_count
FROM documents d
LEFT JOIN document_categories dc ON d.category_id = dc.category_id
LEFT JOIN student s ON d.student_id = s.StudID
LEFT JOIN users u1 ON d.created_by = u1.user_id
LEFT JOIN users u2 ON s.user_id = u2.user_id
LEFT JOIN auto_mechanic_programs p ON d.program_id = p.program_id
$whereClause
ORDER BY d.created_at DESC
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching documents: " . $e->getMessage();
    $documents = [];
}

// Get categories for filter
$categories = $conn->query("SELECT category_id, category_name, category_code FROM document_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll();

// Counts
$counts = [];
$countStmt = $conn->query("SELECT status, COUNT(*) as cnt FROM documents GROUP BY status");
while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['status']] = $row['cnt'];
}

// Confidence levels
$confLevels = ['Public', 'Internal', 'Confidential', 'Restricted'];
$allDocTypes = ['TOR', 'Certificate', 'Diploma', 'Transcript', 'ID', 'Registration', 'Scholarship', 'Other'];
$statusOptions = ['Draft', 'Pending', 'Approved', 'Rejected', 'Expired', 'Archived', 'Revoked'];

$pageTitle = "Documents Management";
$pageSubtitle = "Central Document Repository with Version Control & Access Management";
include 'sidebar_new.php';
include '../includes/unified_header.php';

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];

    try {
        $conn->beginTransaction();

        if ($action === 'upload_document') {
            // Handle file upload
            $uploadDir = '../uploads/documents/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $title = $_POST['title'];
            $description = $_POST['description'] ?? '';
            $categoryId = $_POST['category_id'];
            $docType = $_POST['document_type'];
            $studentId = !empty($_POST['student_id']) ? $_POST['student_id'] : null;
            $confidential = $_POST['confidentiality_level'];
            $tags = isset($_POST['tags']) ? json_encode($_POST['tags']) : '[]';

            // Generate document number
            $year = date('Y');
            $docNum = "DOC-$year-" . date('His') . rand(100,999);

            if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['document_file'];
                $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $fileSize = $file['size'];
                    $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
                    $mimeType = mime_content_type($filePath);
                    $fileHash = hash_file('sha256', $filePath);

                    // Generate verification code if needed
                    $verificationCode = null;
                    $verificationUrl = null;
                    if ($confidential === 'Public' || $docType === 'Certificate' || $docType === 'Diploma') {
                        $verificationCode = strtoupper(bin2hex(random_bytes(8)));
                        $verificationUrl = "https://yourdomain.edu.ph/verify/document.php?code=$verificationCode";
                    }

                    $insert = $conn->prepare("
                        INSERT INTO documents (
                            document_number, title, description, category_id, document_type,
                            file_path, file_name, file_size, file_extension, mime_type, file_hash,
                            student_id, issue_date, expiry_date, status,
                            verification_code, verification_url, confidentiality_level,
                            tags, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?)
                    ");

                    $issueDate = date('Y-m-d');
                    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

                    $insert->execute([
                        $docNum, $title, $description, $categoryId, $docType,
                        $filePath, $file['name'], $fileSize, $fileExt, $mimeType, $fileHash,
                        $studentId, $issueDate, $expiryDate,
                        $verificationCode, $verificationUrl, $confidential, $tags,
                        $userId
                    ]);

                    $docId = $conn->lastInsertId();

                    // Also create initial version
                    $conn->prepare("
                        INSERT INTO document_versions (document_id, version_number, file_path, file_name, file_size, file_hash, created_by)
                        VALUES (?, 1, ?, ?, ?, ?, ?)
                    ")->execute([$docId, $filePath, $fileName, $fileSize, $fileHash, $userId]);

                    $conn->commit();
                    $response = ['success' => true, 'message' => 'Document uploaded successfully.', 'id' => $docId];
                } else {
                    throw new Exception("Failed to move uploaded file.");
                }
            } else {
                throw new Exception("File upload error: " . $_FILES['document_file']['error']);
            }
        }

        if ($action === 'update_document') {
            $docId = $_POST['document_id'];
            $title = $_POST['title'];
            $description = $_POST['description'] ?? '';
            $status = $_POST['status'];
            $confidential = $_POST['confidentiality_level'];

            $update = $conn->prepare("
                UPDATE documents SET
                    title = ?, description = ?, status = ?, confidentiality_level = ?,
                    tags = ?, updated_at = NOW()
                WHERE document_id = ?
            ");
            $update->execute([$title, $description, $status, $confidential, $tags, $docId]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Document updated successfully.'];
        }

        if ($action === 'delete_document') {
            $docId = $_POST['document_id'];
            // Soft delete by archiving
            $conn->prepare("UPDATE documents SET status = 'Archived', updated_at = NOW() WHERE document_id = ?")->execute([$docId]);
            $response = ['success' => true, 'message' => 'Document archived.'];
        }

        if ($action === 'share_document') {
            $docId = $_POST['document_id'];
            $shareWith = $_POST['share_with_user_id'];
            $accessLevel = $_POST['access_level'];
            $reason = $_POST['reason'] ?? '';

            // Check existing permission
            $existing = $conn->prepare("SELECT permission_id FROM document_permissions WHERE document_id = ? AND user_id = ? AND is_active = 1");
            $existing->execute([$docId, $shareWith]);
            if ($existing->fetch()) {
                throw new Exception("User already has permission to this document.");
            }

            $grant = $conn->prepare("
                INSERT INTO document_permissions (document_id, user_id, access_level, granted_by, reason)
                VALUES (?, ?, ?, ?, ?)
            ");
            $grant->execute([$docId, $shareWith, $accessLevel, $userId, $reason]);

            $conn->commit();
            $response = ['success' => true, 'message' => 'Document shared successfully.'];
        }

        if ($action === 'bulk_action') {
            $selected = $_POST['selected_docs'] ?? [];
            $bulkAction = $_POST['bulk_action'] ?? '';

            if (empty($selected)) throw new Exception("No documents selected");

            $placeholders = implode(',', array_fill(0, count($selected), '?'));

            switch ($bulkAction) {
                case 'bulk_approve':
                    $stmt = $conn->prepare("UPDATE documents SET status = 'Approved', updated_at = NOW() WHERE document_id IN ($placeholders)");
                    $stmt->execute($selected);
                    $response['message'] = count($selected) . ' documents approved.';
                    break;
                case 'bulk_archive':
                    $stmt = $conn->prepare("UPDATE documents SET status = 'Archived' WHERE document_id IN ($placeholders)");
                    $stmt->execute($selected);
                    $response['message'] = count($selected) . ' documents archived.';
                    break;
                case 'bulk_restrict':
                    $stmt = $conn->prepare("UPDATE documents SET confidentiality_level = 'Restricted' WHERE document_id IN ($placeholders)");
                    $stmt->execute($selected);
                    $response['message'] = count($selected) . ' documents restricted.';
                    break;
                case 'bulk_delete':
                    foreach ($selected as $docId) {
                        $filePath = $conn->prepare("SELECT file_path FROM documents WHERE document_id = ?");
                        $filePath->execute([$docId]);
                        $path = $filePath->fetchColumn();
                        if ($path && file_exists($path)) unlink($path);
                        $conn->prepare("DELETE FROM documents WHERE document_id = ?")->execute([$docId]);
                    }
                    $response['message'] = count($selected) . ' documents permanently deleted.';
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
    header('Content-Disposition: attachment; filename="documents_export_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Document #', 'Title', 'Type', 'Category', 'Student', 'Confidentiality',
        'Status', 'Created At', 'Created By', 'File Size', 'Downloads'
    ]);

    foreach ($documents as $d) {
        fputcsv($output, [
            $d['document_number'],
            $d['title'],
            $d['document_type'],
            $d['category_name'] ?? 'N/A',
            $d['student_name'] ?? 'N/A',
            $d['confidentiality_level'],
            $d['status'],
            $d['created_at'],
            $d['created_by_name'] ?? 'System',
            $d['file_size'],
            $d['access_count']
        ]);
    }
    fclose($output);
    exit();
}
?>

<div class="content-header">
    <div class="header-actions">
        <button class="btn btn-primary" onclick="openUploadModal()">
            <i class="fas fa-upload"></i> Upload Document
        </button>
        <button class="btn btn-secondary" onclick="window.location.href='?export=csv'">
            <i class="fas fa-download"></i> Export CSV
        </button>
    </div>
</div>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= count($documents) ?></div>
        <div class="stat-label">Total Documents</div>
    </div>
    <div class="stat-card text-success">
        <div class="stat-value"><?= $counts['Approved'] ?? 0 ?></div>
        <div class="stat-label">Approved</div>
    </div>
    <div class="stat-card text-warning">
        <div class="stat-value"><?= $counts['Pending'] ?? 0 ?></div>
        <div class="stat-label">Pending Review</div>
    </div>
    <div class="stat-card text-primary">
        <div class="stat-value"><?= array_sum($counts) ?></div>
        <div class="stat-label">Total Downloads</div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Filter Documents</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filters-form">
            <div class="filters-row">
                <div class="filter-group">
                    <label>Type</label>
                    <select name="type">
                        <option value="All" <?= $docType === 'All' ? 'selected' : '' ?>>All Types</option>
                        <?php foreach ($allDocTypes as $t): ?>
                            <option value="<?= $t ?>" <?= $docType === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="All">All Categories</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['category_id'] ?>" <?= $categoryFilter == $c['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All Statuses</option>
                        <?php foreach ($statusOptions as $s): ?>
                            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Confidentiality</label>
                    <select name="confidential">
                        <option value="All" <?= $confidentialFilter === 'All' ? 'selected' : '' ?>>All Levels</option>
                        <?php foreach ($confLevels as $l): ?>
                            <option value="<?= $l ?>" <?= $confidentialFilter === $l ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
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
                    <input type="text" name="search" placeholder="Title, #, tags" value="<?= htmlspecialchars($search) ?>">
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
<div class="card" id="bulk-docs" style="display: none; margin-top: 1rem;">
    <div class="card-body">
        <form method="POST" id="bulkDocForm">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="font-weight: bold;" id="doc-selected-count">0 selected</span>
                <select name="bulk_action" class="form-select" style="width: auto;">
                    <option value="">Choose Action</option>
                    <option value="bulk_approve">Approve Selected</option>
                    <option value="bulk_archive">Archive Selected</option>
                    <option value="bulk_restrict">Set as Restricted</option>
                    <option value="bulk_delete">Permanently Delete</option>
                </select>
                <button type="button" class="btn btn-primary" onclick="executeBulkDocAction()">Execute</button>
                <button type="button" class="btn btn-secondary" onclick="clearDocSelection()">Clear</button>
            </div>
        </form>
    </div>
</div>

<!-- Documents Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Document Repository</h3>
        <span class="badge"><?= count($documents) ?> documents</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-docs" onchange="toggleSelectAllDocs()"></th>
                        <th>Document #</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Student</th>
                        <th>Confidentiality</th>
                        <th>Status</th>
                        <th>Versions</th>
                        <th>Downloads</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $d): ?>
                        <tr>
                            <td><input type="checkbox" class="doc-checkbox" value="<?= $d['document_id'] ?>"></td>
                            <td><strong><?= htmlspecialchars($d['document_number']) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($d['title']) ?></strong>
                                <?php if ($d['version_count'] > 1): ?>
                                    <span class="badge badge-info">v<?= $d['version_count'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($d['document_type']) ?></td>
                            <td><?= htmlspecialchars($d['category_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($d['student_name']): ?>
                                    <?= htmlspecialchars($d['student_name']) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $d['confidentiality_level'] === 'Public' ? 'success' : ($d['confidentiality_level'] === 'Restricted' ? 'danger' : 'warning') ?>">
                                    <?= htmlspecialchars($d['confidentiality_level']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $d['status'])) ?>">
                                    <?= htmlspecialchars($d['status']) ?>
                                </span>
                            </td>
                            <td><?= $d['version_count'] ?></td>
                            <td><?= number_format($d['access_count']) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($d['file_path'] && file_exists($d['file_path'])): ?>
                                        <a href="<?= htmlspecialchars($d['file_path']) ?>" class="btn btn-sm btn-info" target="_blank" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-secondary" onclick="viewVersions(<?= $d['document_id'] ?>)" title="Versions">
                                        <i class="fas fa-history"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline" onclick="viewAuditLog(<?= $d['document_id'] ?>)" title="Audit Log">
                                        <i class="fas fa-shield-alt"></i>
                                    </button>
                                    <?php if ($d['status'] === 'Draft' || $d['status'] === 'Pending'): ?>
                                        <button class="btn btn-sm btn-primary" onclick="editDocument(<?= $d['document_id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-warning" onclick="shareDocument(<?= $d['document_id'] ?>)">
                                        <i class="fas fa-share"></i>
                                    </button>
                                    <?php if ($d['status'] !== 'Archived'): ?>
                                        <button class="btn btn-sm btn-danger" onclick="deleteDocument(<?= $d['document_id'] ?>)"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 2rem;">
                                No documents found. <button class="btn btn-sm btn-primary" onclick="openUploadModal()">Upload first document</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal" id="uploadModal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Upload Document</h3>
            <button class="btn-close" onclick="closeUploadModal()">&times;</button>
        </div>
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_document">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Document Type *</label>
                        <select name="document_type" required>
                            <?php foreach ($allDocTypes as $t): ?>
                                <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>File *</label>
                    <input type="file" name="document_file" required>
                    <small class="text-muted">Max size: 10MB. Supported: PDF, DOC, DOCX, JPG, PNG</small>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Confidentiality Level</label>
                        <select name="confidentiality_level">
                            <?php foreach ($confLevels as $l): ?>
                                <option value="<?= $l ?>"><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Expiry Date (Optional)</label>
                        <input type="date" name="expiry_date">
                    </div>
                </div>
                <div class="form-group">
                    <label>Associated Student (Optional)</label>
                    <select name="student_id">
                        <option value="">None</option>
                        <?php foreach ($conn->query("SELECT StudID, FirstName, LastName, SchoolID FROM student ORDER BY FirstName") as $s): ?>
                            <option value="<?= $s['StudID'] ?>"><?= htmlspecialchars($s['FirstName'] . ' ' . $s['LastName'] . ' (' . $s['SchoolID'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tags (comma-separated)</label>
                    <input type="text" name="tags" placeholder="e.g. transcript,2024,approved">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload Document</button>
            </div>
        </form>
    </div>
</div>

<!-- Share Modal -->
<div class="modal" id="shareModal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Share Document</h3>
            <button class="btn-close" onclick="closeShareModal()">&times;</button>
        </div>
        <form id="shareForm" method="POST">
            <input type="hidden" name="action" value="share_document">
            <input type="hidden" name="document_id" id="shareDocId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Share With User</label>
                    <select name="share_with_user_id" required>
                        <option value="">Select User</option>
                        <?php
                        $users = $conn->query("SELECT user_id, CONCAT(first_name, ' ', last_name) as name, user_type FROM users WHERE status = 'active' ORDER BY user_type, name")->fetchAll();
                        foreach ($users as $u):
                        ?>
                            <option value="<?= $u['user_id'] ?>">
                                <?= htmlspecialchars($u['name'] . ' (' . $u['user_type'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Access Level</label>
                    <select name="access_level" required>
                        <option value="View">View Only</option>
                        <option value="Download">Download</option>
                        <option value="Edit">Edit</option>
                        <option value="Admin">Full Access</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <textarea name="reason" rows="2" placeholder="Why share this document?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeShareModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Share</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUploadModal() {
    document.getElementById('uploadModal').style.display = 'flex';
}

function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}

function closeShareModal() {
    document.getElementById('shareModal').style.display = 'none';
}

function shareDocument(docId) {
    document.getElementById('shareDocId').value = docId;
    document.getElementById('shareModal').style.display = 'flex';
}

function editDocument(docId) {
    // Load edit form via AJAX (similar patterns)
    alert('Edit document: ' + docId);
}

function deleteDocument(docId) {
    if (confirm('Permanently delete this document? This cannot be undone.')) {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_document&document_id=' + docId + '&ajax=1'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                alert('Document deleted');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function viewVersions(docId) {
    alert('View versions for document: ' + docId);
}

function viewAuditLog(docId) {
    alert('View audit log for document: ' + docId);
}

function toggleSelectAllDocs() {
    const selectAll = document.getElementById('select-all-docs');
    document.querySelectorAll('.doc-checkbox').forEach(function(cb) { cb.checked = selectAll.checked; });
    updateBulkDocCount();
}

function updateBulkDocCount() {
    const checked = document.querySelectorAll('.doc-checkbox:checked');
    const panel = document.getElementById('bulk-docs');
    const countSpan = document.getElementById('doc-selected-count');

    if (checked.length > 0) {
        panel.style.display = 'block';
        countSpan.textContent = checked.length + ' selected';
    } else {
        panel.style.display = 'none';
    }
}

function executeBulkDocAction() {
    const action = document.querySelector('#bulkDocForm select').value;
    if (!action) return alert('Select an action');
    if (confirm('Execute on all selected documents?')) {
        document.getElementById('bulkDocForm').submit();
    }
}

function clearDocSelection() {
    document.getElementById('select-all-docs').checked = false;
    document.querySelectorAll('.doc-checkbox').forEach(function(cb) { cb.checked = false; });
    document.getElementById('bulk-docs').style.display = 'none';
}

document.querySelectorAll('.doc-checkbox').forEach(function(cb) {
    cb.addEventListener('change', updateBulkDocCount);
});
</script>

<?php
include '../includes/footer.php';
?>
