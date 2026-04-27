<?php
/**
 * Module 1: Pre-Enrollment Management
 * Updated with NC Level selection and Enroll functionality
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if (!in_array($userType, ['admin', 'support_staff', 'instructional_unit'])) {
    header("Location: ../login.php");
    exit();
}

$filter = $_GET['status'] ?? 'All';
$nc_filter = $_GET['nc_level'] ?? 'All';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = [];
if ($filter !== 'All') $where[] = "application_status = '$filter'";
if ($nc_filter !== 'All') $where[] = "nc_level = '$nc_filter'";
if (!empty($search)) {
    $search_escaped = $conn->quote("%$search%");
    $where[] = "(first_name LIKE $search_escaped OR last_name LIKE $search_escaped OR email_address LIKE $search_escaped OR application_number LIKE $search_escaped OR contact_number LIKE $search_escaped)";
}
if (!empty($date_from)) {
    $where[] = "submission_date >= '$date_from'";
}
if (!empty($date_to)) {
    $where[] = "submission_date <= '$date_to 23:59:59'";
}
$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$applications = $conn->query("SELECT * FROM pre_enrollment_applications $whereClause ORDER BY submission_date DESC")->fetchAll(PDO::FETCH_ASSOC);

$counts = ['All' => 0, 'Pending' => 0, 'Under Review' => 0, 'Qualified' => 0, 'Not Qualified' => 0, 'Enrolled' => 0];
$stmt = $conn->query("SELECT application_status, COUNT(*) as cnt FROM pre_enrollment_applications GROUP BY application_status");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status = $row['application_status'];
    if (isset($counts[$status])) {
        $counts[$status] = $row['cnt'];
    }
    $counts['All'] += $row['cnt'];
}

$nc_levels = ['NC I', 'NC II', 'NC III', 'NC IV'];

$pageTitle = "Pre-Enrollment Management";
$pageSubtitle = "Module 1 - Digital Intake Process";

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS application_notes (
        note_id INT AUTO_INCREMENT PRIMARY KEY,
        pre_enroll_id INT NOT NULL,
        user_id INT NOT NULL,
        note TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_pre_enroll (pre_enroll_id)
    )");
} catch (PDOException $e) {}

include 'sidebar_new.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 1;
    $nc_level = $_POST['nc_level'] ?? 'NC I';
    
    if (isset($_POST['batch_actions']) && !empty($_POST['selected_apps'])) {
        $selected = $_POST['selected_apps'];
        $status_map = [
            'batch_review' => 'Under Review',
            'batch_qualified' => 'Qualified',
            'batch_not_qualified' => 'Not Qualified',
            'batch_enroll' => 'Enrolled'
        ];
        if (isset($status_map[$action])) {
            $new_status = $status_map[$action];
            $placeholders = implode(',', array_fill(0, count($selected), '?'));
            $stmt = $conn->prepare("UPDATE pre_enrollment_applications SET application_status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE pre_enroll_id IN ($placeholders)");
            $params = array_merge([$new_status, $user_id], $selected);
            $stmt->execute($params);
        }
        echo "<script>location.reload();</script>";
        exit();
    }
    
    if (isset($_POST['add_note']) && !empty($_POST['app_id'])) {
        $app_id = $_POST['app_id'];
        $note = trim($_POST['note'] ?? '');
        if (!empty($note)) {
            $stmt = $conn->prepare("INSERT INTO application_notes (pre_enroll_id, user_id, note, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$app_id, $user_id, $note]);
        }
    }
    
    if (isset($_POST['delete_note']) && !empty($_POST['note_id'])) {
        $note_id = $_POST['note_id'];
        $stmt = $conn->prepare("DELETE FROM application_notes WHERE note_id = ?");
        $stmt->execute([$note_id]);
    }
    
    if (isset($_POST['add_note']) || isset($_POST['delete_note'])) {
        echo "<script>location.reload();</script>";
        exit();
    }
    
    if (isset($_POST['export_csv'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="pre_enrollment_export_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['App No.', 'Name', 'Email', 'Contact', 'NC Level', 'Education', 'Employment', 'Date', 'Status']);
        foreach ($applications as $app) {
            fputcsv($output, [
                $app['application_number'],
                $app['first_name'] . ' ' . $app['last_name'],
                $app['email_address'],
                $app['contact_number'],
                $app['nc_level'] ?? '',
                $app['highest_educational_attainment'] ?? '',
                $app['employment_status'] ?? '',
                $app['submission_date'],
                $app['application_status']
            ]);
        }
        fclose($output);
        exit();
    }
    
    $app_id = $_POST['app_id'] ?? 0;
    
    if ($action === 'Enroll') {
        $stmt = $conn->prepare("SELECT * FROM pre_enrollment_applications WHERE pre_enroll_id = ?");
        $stmt->execute([$app_id]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($app) {
            // Check if email already exists in users table
            $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $checkStmt->execute([$app['email_address']]);
            $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingUser) {
                // User already exists, use existing user_id
                $userId = $existingUser['user_id'];
            } else {
                // Create new user account
                $fullName = strtolower($app['first_name'] . $app['last_name']);
                $username = preg_replace('/[^a-z]/', '', $fullName) . rand(100, 999);
                $password = password_hash('Tesda2026!', PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, user_type, first_name, last_name, status, created_at) VALUES (?, ?, ?, 'trainee', ?, ?, 'active', NOW())");
                $stmt->execute([$username, $password, $app['email_address'], $app['first_name'], $app['last_name']]);
                $userId = $conn->lastInsertId();
            }
            
            // Check if student record already exists
            $checkStudentStmt = $conn->prepare("SELECT StudID FROM student WHERE user_id = ?");
            $checkStudentStmt->execute([$userId]);
            $existingStudent = $checkStudentStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingStudent) {
                // Student already exists, use existing StudID
                $studId = $existingStudent['StudID'];
            } else {
                // Get actual column names from student table
                $colsStmt = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student' AND COLUMN_NAME NOT IN ('StudID') ORDER BY ORDINAL_POSITION");
                $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Determine column names
                $firstNameCol = in_array('FirstName', $columns) ? 'FirstName' : (in_array('FName', $columns) ? 'FName' : 'FirstName');
                $lastNameCol = in_array('LastName', $columns) ? 'LastName' : (in_array('LName', $columns) ? 'LName' : 'LastName');
                $emailCol = in_array('Email', $columns) ? 'Email' : (in_array('EmailAddr', $columns) ? 'EmailAddr' : 'Email');
                
                // Map values to columns
                $schoolId = 'TESDA-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                $insertCols = ['SchoolID', $firstNameCol, $lastNameCol, $emailCol, 'Status', 'EnrollmentDate', 'user_id'];
                $insertVals = [$schoolId, $app['first_name'], $app['last_name'], $app['email_address'], 'Enrolled', date('Y-m-d H:i:s'), $userId];
                
                $sql = "INSERT INTO student (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', array_fill(0, count($insertVals), '?')) . ")";
                $stmt = $conn->prepare($sql);
                $stmt->execute($insertVals);
                $studId = $conn->lastInsertId();
            }
            
            // Check if program enrollment already exists
            $checkEnrollStmt = $conn->prepare("SELECT enrollment_id FROM student_program_enrollments WHERE student_id = ? AND pre_enroll_id = ?");
            $checkEnrollStmt->execute([$studId, $app_id]);
            $existingEnrollment = $checkEnrollStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existingEnrollment) {
                $nc_level = $_POST['nc_level'] ?? 'NC I';
                $stmt = $conn->prepare("INSERT INTO student_program_enrollments (student_id, pre_enroll_id, enrollment_date, enrollment_status, nc_level, created_at) VALUES (?, ?, NOW(), 'Active', ?, NOW())");
                $stmt->execute([$studId, $app_id, $nc_level]);
            }
            
            $stmt = $conn->prepare("UPDATE pre_enrollment_applications SET application_status = 'Enrolled', nc_level = ?, reviewed_by = ?, reviewed_at = NOW() WHERE pre_enroll_id = ?");
            $stmt->execute([$nc_level, $user_id, $app_id]);
        }
    } else {
        $stmt = $conn->prepare("UPDATE pre_enrollment_applications SET application_status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE pre_enroll_id = ?");
        $stmt->execute([$action, $user_id, $app_id]);
    }
    
    echo "<script>location.reload();</script>";
    exit();
}
?>

<div class="stats-grid" style="display:flex;gap:15px;margin-bottom:30px;flex-wrap:wrap;">
    <?php foreach (['All', 'Pending', 'Under Review', 'Qualified', 'Not Qualified', 'Enrolled'] as $s): ?>
    <a href="?status=<?= $s ?><?= $nc_filter !== 'All' ? '&nc_level=' . $nc_filter : '' ?>" class="stat-card" style="padding:14px 24px;background:white;border-radius:12px;border:2px solid #e2e8f0;text-decoration:none;<?= $filter === $s ? 'border-color:#2563eb;background:#2563eb;color:white;' : '' ?>">
        <div style="font-size:24px;font-weight:700;"><?= $counts[$s] ?? 0 ?></div>
        <div style="font-size:12px;opacity:0.8;"><?= $s ?></div>
    </a>
    <?php endforeach; ?>
</div>

<div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
    <label style="font-weight:600;">Filter by NC Level:</label>
    <select onchange="location = '?status=<?= $filter ?>&nc_level=' + this.value<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_from) ? '&date_from=' . $date_from : '' ?><?= !empty($date_to) ? '&date_to=' . $date_to : '' ?>'" style="padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;">
        <option value="All" <?= $nc_filter === 'All' ? 'selected' : '' ?>>All Levels</option>
        <?php foreach ($nc_levels as $level): ?>
        <option value="<?= $level ?>" <?= $nc_filter === $level ? 'selected' : '' ?>><?= $level ?></option>
        <?php endforeach; ?>
    </select>
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="status" value="<?= $filter ?>">
        <input type="hidden" name="nc_level" value="<?= $nc_filter ?>">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, app no..." style="padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;width:200px;">
        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" style="padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;">
        <span>to</span>
        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" style="padding:8px 12px;border-radius:6px;border:1px solid #e2e8f0;">
        <button type="submit" class="btn" style="padding:8px 16px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;">Search</button>
        <a href="?status=All" class="btn" style="padding:8px 16px;background:#64748b;color:white;border:none;border-radius:6px;text-decoration:none;">Clear</a>
    </form>
    <form method="POST" style="display:inline;">
        <button type="submit" name="export_csv" class="btn" style="padding:8px 16px;background:#10b981;color:white;border:none;border-radius:6px;cursor:pointer;">Export CSV</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Applications (<?= count($applications) ?>)</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <form method="POST" id="batchForm">
            <input type="hidden" name="batch_actions" value="1">
            <div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="checkbox" id="selectAll" onchange="document.querySelectorAll('.app-check').forEach(c=>c.checked=this.checked)">
                <label for="selectAll" style="font-weight:600;cursor:pointer;">Select All</label>
                <span style="margin-left:auto;display:flex;gap:8px;">
                    <button type="button" onclick="submitBatch('batch_review')" class="btn" style="padding:6px 12px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Mark Review</button>
                    <button type="button" onclick="submitBatch('batch_qualified')" class="btn" style="padding:6px 12px;background:#10b981;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Mark Qualified</button>
                    <button type="button" onclick="submitBatch('batch_not_qualified')" class="btn" style="padding:6px 12px;background:#dc2626;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Mark Not Qualified</button>
                </span>
            </div>
        <table style="width:100%;border-collapse:collapse;min-width:900px;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;width:40px;"></th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">App No.</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Name</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Contact</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">NC Level</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Education</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Date</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Status</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:14px 16px;"><input type="checkbox" name="selected_apps[]" value="<?= $app['pre_enroll_id'] ?>" class="app-check"></td>
                    <td style="padding:14px 16px;font-weight:600;"><?= htmlspecialchars($app['application_number']) ?></td>
                    <td style="padding:14px 16px;"><strong><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></strong></td>
                    <td style="padding:14px 16px;"><?= htmlspecialchars($app['contact_number']) ?></td>
                    <?php 
                $notes_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM application_notes WHERE pre_enroll_id = ?");
                $notes_stmt->execute([$app['pre_enroll_id']]);
                $note_count = $notes_stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
                ?>
                    <td style="padding:14px 16px;">
                        <?php if ($app['application_status'] === 'Enrolled'): ?>
                        <span class="badge badge-green"><?= htmlspecialchars($app['nc_level'] ?? 'NC I') ?></span>
                        <?php else: ?>
                        <span style="color:#94a3b8;font-size:12px;">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:14px 16px;"><?= htmlspecialchars($app['highest_educational_attainment'] ?? $app['highest_education'] ?? '-') ?></td>
                    <td style="padding:14px 16px;"><?= date('M d, Y', strtotime($app['submission_date'])) ?></td>
                    <td style="padding:14px 16px;">
                        <?php $badgeClass = [
                            'Pending'=>'badge-orange',
                            'Under Review'=>'badge-blue',
                            'Qualified'=>'badge-green',
                            'Not Qualified'=>'badge-red',
                            'Enrolled'=>'badge-green',
                            'Waitlisted'=>'badge-orange',
                            'Draft'=>'badge-gray'
                        ][$app['application_status']] ?? 'badge-blue'; ?>
                        <span class="badge <?= $badgeClass ?>"><?= $app['application_status'] ?></span>
                        <?php if ($note_count > 0): ?>
                        <span class="badge badge-blue" style="margin-left:4px;" title="<?= $note_count ?> note(s)">📝 <?= $note_count ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:14px 16px;">
                        <?php if ($app['application_status'] === 'Enrolled'): ?>
                        <span style="color:#10b981;font-size:12px;">✓ Enrolled</span>
                        <?php elseif (in_array($app['application_status'], ['Qualified'])): ?>
                        <form method="POST" style="display:inline-flex;gap:5px;align-items:center;">
                            <input type="hidden" name="app_id" value="<?= $app['pre_enroll_id'] ?>">
                            <select name="nc_level" style="padding:4px 8px;font-size:12px;border-radius:4px;border:1px solid #e2e8f0;">
                                <?php foreach ($nc_levels as $level): ?>
                                <option value="<?= $level ?>"><?= $level ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="action" value="Enroll" class="btn" style="padding:6px 12px;background:#10b981;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Enroll</button>
                        </form>
                        <?php elseif (in_array($app['application_status'], ['Pending', 'Draft'])): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="app_id" value="<?= $app['pre_enroll_id'] ?>">
                            <button type="submit" name="action" value="Under Review" class="btn" style="padding:6px 12px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Review</button>
                            <button type="submit" name="action" value="Qualified" class="btn" style="padding:6px 12px;background:#10b981;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">✓</button>
                            <button type="submit" name="action" value="Not Qualified" class="btn" style="padding:6px 12px;background:#dc2626;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">✗</button>
                        </form>
                        <?php else: ?>
                        <span style="color:#64748b;font-size:12px;">Processed</span>
                        <?php endif; ?>
                        <button type="button" onclick="showNoteDialog(<?= $app['pre_enroll_id'] ?>)" class="btn" style="padding:4px 8px;background:#f59e0b;color:white;border:none;border-radius:4px;cursor:pointer;font-size:11px;margin-left:4px;">Note</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($applications)): ?>
                <tr><td colspan="9" style="text-align:center;padding:40px;color:#64748b;">No applications found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </form>
    </div>
</div>

<div id="noteDialog" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:white;padding:24px;border-radius:12px;width:500px;max-width:90%;max-height:80vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;">Application Notes</h3>
            <button type="button" onclick="closeNoteDialog()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#64748b;">&times;</button>
        </div>
        <div id="existingNotes" style="margin-bottom:16px;max-height:200px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;padding:8px;">
            <div style="text-align:center;color:#64748b;padding:20px;" id="noNotesMsg">No notes yet</div>
        </div>
        <form method="POST" id="noteForm">
            <input type="hidden" name="add_note" value="1">
            <input type="hidden" name="app_id" id="note_app_id" value="">
            <textarea name="note" id="noteText" rows="3" style="width:100%;padding:12px;border-radius:6px;border:1px solid #e2e8f0;resize:vertical;font-family:inherit;" placeholder="Add a new note..."></textarea>
            <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="closeNoteDialog()" class="btn" style="padding:8px 16px;background:#64748b;color:white;border:none;border-radius:6px;cursor:pointer;">Close</button>
                <button type="submit" class="btn" style="padding:8px 16px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;">Add Note</button>
            </div>
        </form>
    </div>
</div>

<div id="successToast" style="display:none;position:fixed;bottom:30px;right:30px;background:#10b981;color:white;padding:16px 24px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:2000;font-weight:500;align-items:center;gap:10px;">
    <span style="font-size:20px;">✓</span>
    <span>Note added successfully!</span>
</div>

<script>
let currentAppId = null;
function showNoteDialog(appId) {
    currentAppId = appId;
    document.getElementById('note_app_id').value = appId;
    document.getElementById('noteDialog').style.display = 'flex';
    loadNotes(appId);
}
function loadNotes(appId) {
    const container = document.getElementById('existingNotes');
    container.innerHTML = '<div style="text-align:center;color:#64748b;padding:20px;">Loading...</div>';
    fetch('get_notes.php?app_id=' + appId)
        .then(res => res.text())
        .then(html => {
            container.innerHTML = html || '<div style="text-align:center;color:#64748b;padding:20px;">No notes yet</div>';
        })
        .catch(() => {
            container.innerHTML = '<div style="text-align:center;color:#dc2626;padding:20px;">Error loading notes</div>';
        });
}
function deleteNote(noteId) {
    if (!confirm('Delete this note?')) return;
    const formData = new FormData();
    formData.append('delete_note', '1');
    formData.append('note_id', noteId);
    fetch('', {method: 'POST', body: formData})
        .then(() => loadNotes(currentAppId));
}
function closeNoteDialog() {
    document.getElementById('noteDialog').style.display = 'none';
}
document.getElementById('noteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const note = document.getElementById('noteText').value.trim();
    if (!note) {
        alert('Please enter a note');
        return;
    }
    const formData = new FormData(this);
    fetch('', {method: 'POST', body: formData}).then(() => {
        document.getElementById('noteText').value = '';
        loadNotes(currentAppId);
        showSuccessToast();
    });
});
function showSuccessToast() {
    const toast = document.getElementById('successToast');
    toast.style.display = 'flex';
    setTimeout(() => { toast.style.display = 'none'; }, 3000);
}
function submitBatch(action) {
    const checked = document.querySelectorAll('.app-check:checked');
    if (checked.length === 0) {
        alert('Please select at least one application');
        return;
    }
    if (confirm('Process ' + checked.length + ' application(s)?')) {
        const form = document.getElementById('batchForm');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = action;
        form.appendChild(input);
        form.submit();
    }
}
</script>

<div class="stats-grid" style="display:flex;gap:15px;margin-bottom:30px;flex-wrap:wrap;">
    <?php foreach (['All', 'Pending', 'Under Review', 'Qualified', 'Not Qualified', 'Enrolled'] as $s): ?>
    <a href="?status=<?= $s ?>" class="stat-card" style="padding:14px 24px;background:white;border-radius:12px;border:2px solid #e2e8f0;text-decoration:none;<?= $filter === $s ? 'border-color:#2563eb;background:#2563eb;color:white;' : '' ?>">
        <div style="font-size:24px;font-weight:700;"><?= $counts[$s] ?? 0 ?></div>
        <div style="font-size:12px;opacity:0.8;"><?= $s ?></div>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Applications (<?= count($applications) ?>)</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:800px;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;width:40px;"></th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">App No.</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Name</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Contact</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Education</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Employment</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Date</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Status</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:14px 16px;font-weight:600;"><?= htmlspecialchars($app['application_number']) ?></td>
                    <td style="padding:14px 16px;"><strong><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></strong></td>
                    <td style="padding:14px 16px;"><?= htmlspecialchars($app['contact_number']) ?></td>
                    <td style="padding:14px 16px;"><?= htmlspecialchars($app['highest_educational_attainment'] ?? $app['highest_education'] ?? '-') ?></td>
                    <td style="padding:14px 16px;"><?= htmlspecialchars($app['employment_status']) ?></td>
                    <td style="padding:14px 16px;"><?= date('M d, Y', strtotime($app['submission_date'])) ?></td>
                    <td style="padding:14px 16px;">
                        <?php $badgeClass = [
    'Pending'=>'badge-orange',
    'Under Review'=>'badge-blue',
    'Qualified'=>'badge-green',
    'Not Qualified'=>'badge-red',
    'Enrolled'=>'badge-green',
    'Waitlisted'=>'badge-orange',
    'Draft'=>'badge-gray'
][$app['application_status']] ?? 'badge-blue'; ?>
                        <span class="badge <?= $badgeClass ?>"><?= $app['application_status'] ?></span>
                    </td>
                    <td style="padding:14px 16px;">
                        <?php if (in_array($app['application_status'], ['Pending', 'Draft'])): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="app_id" value="<?= $app['pre_enroll_id'] ?>">
                            <button type="submit" name="action" value="Under Review" class="btn" style="padding:6px 12px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Review</button>
                            <button type="submit" name="action" value="Qualified" class="btn" style="padding:6px 12px;background:#10b981;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">✓</button>
                            <button type="submit" name="action" value="Not Qualified" class="btn" style="padding:6px 12px;background:#dc2626;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">✗</button>
                        </form>
                        <?php else: ?>
                        <span style="color:#64748b;font-size:12px;">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($applications)): ?>
                <tr><td colspan="8" style="text-align:center;padding:40px;color:#64748b;">No applications found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div></main></div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['app_id'])) {
    $app_id = $_POST['app_id'];
    $action = $_POST['action'];
    $user_id = $_SESSION['user_id'] ?? 1;
    
    $stmt = $conn->prepare("UPDATE pre_enrollment_applications SET application_status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE pre_enroll_id = ?");
    $stmt->execute([$action, $user_id, $app_id]);
    
    echo "<script>location.reload();</script>";
}
?>
</body>
</html>