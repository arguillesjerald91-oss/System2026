<?php
/**
 * Staff Portal - Document Processing Queue
 * Limited access interface for support staff and department-specific staff
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if (!in_array($userType, ['support_staff', 'instructional_unit'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'] ?? $_SESSION['userId'] ?? 1;

// Get staff department
$staffDeptStmt = $conn->prepare("SELECT * FROM staff_department_assignments WHERE user_id = ? AND is_active = 1");
$staffDeptStmt->execute([$userId]);
$staffDept = $staffDeptStmt->fetch(PDO::FETCH_ASSOC);

// Filters
$statusFilter = $_GET['status'] ?? 'All';
$departmentFilter = $_GET['dept'] ?? ($staffDept['department'] ?? 'All');
$priorityFilter = $_GET['priority'] ?? 'All';
$search = trim($_GET['search'] ?? '');

// Build query
$where = [];
$params = [];

if ($statusFilter !== 'All') {
    $where[] = "dr.status = ?";
    $params[] = $statusFilter;
}

if ($priorityFilter !== 'All') {
    $where[] = "dr.priority = ?";
    $params[] = $priorityFilter;
}

if (!empty($search)) {
    $where[] = "(s.FirstName LIKE ? OR s.LastName LIKE ? OR s.SchoolID LIKE ? OR dr.request_number LIKE ?)";
    $sp = "%$search%";
    $params = array_merge($params, array_fill(0, 4, $sp));
}

// Staff can only see requests assigned to their department, unless admin
if ($userType !== 'admin' && $staffDept && $departmentFilter === 'All') {
    $where[] = "dr.department = ?";
    $params[] = $staffDept['department'];
} elseif ($departmentFilter !== 'All' && $userType !== 'admin') {
    $where[] = "dr.department = ?";
    $params[] = $departmentFilter;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
SELECT dr.*, s.FirstName, s.LastName, s.SchoolID,
       CONCAT(u1.first_name, ' ', u1.last_name) as created_by_name,
       CONCAT(u2.first_name, ' ', u2.last_name) as assigned_to_name
FROM document_requests dr
JOIN student s ON dr.student_id = s.StudID
LEFT JOIN users u1 ON dr.created_by = u1.user_id
LEFT JOIN users u2 ON dr.assigned_to = u2.user_id
$whereClause
ORDER BY 
    CASE dr.priority 
        WHEN 'Urgent' THEN 1 
        WHEN 'High' THEN 2 
        WHEN 'Normal' THEN 3 
        ELSE 4 
    END,
    dr.request_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts
$counts = [];
$countStmt = $conn->query("SELECT status, COUNT(*) as cnt FROM document_requests GROUP BY status");
while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['status']] = $row['cnt'];
}

// Departments for filter (restricted for staff)
if ($userType === 'admin') {
    $depts = [
        'Registrar','Certification','Academic','Admission',
        'Finance','Records','IT','HR','Other'
    ];
} else {
    $depts = [$staffDept['department'] ?? 'Registrar'];
}

$statusOpts = ['Pending','Processing','Ready for Pickup','Delivered','Cancelled','Rejected'];
$priorities = ['Low','Normal','High','Urgent'];

$pageTitle = "Document Processing Queue";
$pageSubtitle = "Staff Portal - " . ($staffDept['department'] ?? 'Department') . " Tasks";
include 'sidebar_new.php';
include '../includes/unified_header.php';

// POST: Update request status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $requestId = $_POST['request_id'] ?? null;

    if ($action === 'update_status' && $requestId) {
        $newStatus = $_POST['status'];
        $remarks = $_POST['remarks'] ?? '';

        $update = $conn->prepare("UPDATE document_requests SET status = ?, remarks = ?, updated_at = NOW() WHERE request_id = ?");
        $update->execute([$newStatus, $remarks, $requestId]);

        // Add note
        if (!empty($_POST['note'])) {
            $note = $conn->prepare("INSERT INTO document_request_notes (request_id, note_text, note_type, added_by, is_internal) VALUES (?, ?, 'Status Update', ?, 0)");
            $note->execute([$requestId, $_POST['note'], $userId]);
        }

        $_SESSION['success'] = "Request status updated.";
        header("Location: staff_document_requests.php");
        exit();
    }

    if ($action === 'assign_to_staff') {
        $assignTo = $_POST['assign_to'];
        $stmt = $conn->prepare("UPDATE document_requests SET assigned_to = ? WHERE request_id = ?");
        $stmt->execute([$assignTo, $requestId]);
        $_SESSION['success'] = "Request reassigned.";
        header("Location: staff_document_requests.php");
        exit();
    }
}
?>

<div class="content-header">
    <h2><i class="fas fa-tasks"></i> <?= htmlspecialchars($pageTitle) ?></h2>
    <p class="text-muted"><?= htmlspecialchars($pageSubtitle) ?></p>
</div>

<!-- Queue Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= array_sum($counts) ?></div>
        <div class="stat-label">Total Requests</div>
    </div>
    <div class="stat-card text-warning">
        <div class="stat-value"><?= $counts['Pending'] ?? 0 ?></div>
        <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card text-primary">
        <div class="stat-value"><?= $counts['Processing'] ?? 0 ?></div>
        <div class="stat-label">In Process</div>
    </div>
    <div class="stat-card text-success">
        <div class="stat-value"><?= $counts['Ready for Pickup'] ?? 0 ?></div>
        <div class="stat-label">Ready for Pickup</div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Filter Requests</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filters-form">
            <div class="filters-row">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All Statuses</option>
                        <?php foreach ($statusOpts as $s): ?>
                            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($userType === 'admin'): ?>
                <div class="filter-group">
                    <label>Department</label>
                    <select name="dept">
                        <option value="All">All Departments</option>
                        <?php foreach ($depts as $d): ?>
                            <option value="<?= $d ?>" <?= $departmentFilter === $d ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="filter-group">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="All">All Priorities</option>
                        <?php foreach ($priorities as $p): ?>
                            <option value="<?= $p ?>" <?= $priorityFilter === $p ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Student name, ID, request #" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-group filter-submit">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="?" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Requests Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Processing Queue</h3>
        <span class="badge"><?= count($requests) ?> requests</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Request #</th>
                        <th>Student</th>
                        <th>Type</th>
                        <th>Copies</th>
                        <th>Priority</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['request_number']) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?><br>
                            <small><?= htmlspecialchars($r['SchoolID']) ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($r['document_type']) ?></strong>
                        </td>
                        <td><?= $r['copies_requested'] ?></td>
                        <td>
                            <span class="badge badge-<?= $r['priority'] === 'Urgent' ? 'danger' : ($r['priority'] === 'High' ? 'warning' : ($r['priority'] === 'Normal' ? 'primary' : 'secondary')) ?>">
                                <?= htmlspecialchars($r['priority']) ?>
                            </span>
                        </td>
                        <td><?= date('M j', strtotime($r['request_date'])) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $r['status'])) ?>">
                                <?= htmlspecialchars($r['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($r['assigned_to_name'] ?? 'Unassigned') ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-info" onclick="viewRequest(<?= $r['request_id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($r['status'] !== 'Delivered' && $r['status'] !== 'Cancelled' && $r['status'] !== 'Rejected'): ?>
                                    <button class="btn btn-sm btn-primary" onclick="updateStatus(<?= $r['request_id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem;">
                                No document requests found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal" id="statusModal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Update Request Status</h3>
            <button class="btn-close" onclick="closeStatusModal()">&times;</button>
        </div>
        <form id="statusForm" method="POST">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="request_id" id="statusRequestId">

            <div class="modal-body">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="statusSelect" required>
                        <?php foreach ($statusOpts as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Note / Update</label>
                    <textarea name="note" rows="3" placeholder="Add a note about this status change..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewRequest(id) {
    window.open('view_request.php?id=' + id, '_blank', 'width=800,height=600');
}

function updateStatus(id) {
    document.getElementById('statusRequestId').value = id;
    document.getElementById('statusModal').style.display = 'flex';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>
