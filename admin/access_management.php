<?php
session_start();
include __DIR__ . '/../db.php';
$database = new Database();
$conn = $database->getConnection();

// Check if user is logged in and is admin
if (!isset($_SESSION['userId']) || $_SESSION['userRole'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get all access levels
$accessLevels = $conn->query("SELECT * FROM access_levels ORDER BY level_hierarchy")->fetchAll(PDO::FETCH_ASSOC);

// Get all users with their access assignments
$usersQuery = $conn->prepare("
    SELECT 
        uaa.assignment_id, uaa.user_id, uaa.user_type, uaa.access_id, uaa.assigned_by, 
        uaa.assigned_by_type, uaa.assigned_date, uaa.expiry_date, uaa.status, uaa.notes,
        al.level_name, al.level_description, al.level_hierarchy,
        CASE 
            WHEN uaa.user_type = 'student' THEN CONCAT(s.FirstName, ' ', s.LastName)
            WHEN uaa.user_type = 'admin' THEN CONCAT(a.Fname, ' ', a.Lname)
            ELSE 'Unknown User'
        END as user_name,
        CASE 
            WHEN uaa.user_type = 'student' THEN s.SchoolID
            WHEN uaa.user_type = 'admin' THEN a.admin_id
            ELSE NULL
        END as user_identifier
    FROM user_access_assignments uaa
    JOIN access_levels al ON uaa.access_id = al.access_id
    LEFT JOIN student s ON uaa.user_id = s.StudID AND uaa.user_type = 'student'
    LEFT JOIN admins a ON uaa.user_id = a.admin_id AND uaa.user_type = 'admin'
    ORDER BY uaa.assigned_date DESC
");
$usersQuery->execute();
$userAccess = $usersQuery->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'assign_access':
                $stmt = $conn->prepare("
                    INSERT INTO user_access_assignments 
                    (user_id, user_type, access_id, assigned_by, assigned_by_type, expiry_date, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['user_id'],
                    $_POST['user_type'],
                    $_POST['access_id'],
                    $_SESSION['userId'],
                    'admin',
                    !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
                    $_POST['notes'] ?? null
                ]);
                break;
                
            case 'revoke_access':
                $stmt = $conn->prepare("
                    UPDATE user_access_assignments 
                    SET status = 'Inactive', expiry_date = NOW()
                    WHERE assignment_id = ?
                ");
                $stmt->execute([$_POST['assignment_id']]);
                break;
                
            case 'extend_access':
                $stmt = $conn->prepare("
                    UPDATE user_access_assignments 
                    SET expiry_date = ?, status = 'Active'
                    WHERE assignment_id = ?
                ");
                $stmt->execute([$_POST['expiry_date'], $_POST['assignment_id']]);
                break;
        }
        
        header('Location: access_management.php');
        exit;
    }
}

// Get students and admins for dropdown
$students = $conn->query("SELECT StudID, CONCAT(FirstName, ' ', LastName) as name, SchoolID FROM student ORDER BY FirstName, LastName")->fetchAll(PDO::FETCH_ASSOC);
$admins = $conn->query("SELECT admin_id, CONCAT(Fname, ' ', Lname) as name FROM admins ORDER BY Fname, Lname")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Access Management - TESDA Auto Mechanic Training Centre</title>
<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f8f9fc;
    color: #2d2d2d;
}
:root {
  --background: #f8f9fc;
  --foreground: #2d2d2d;
  --card: #ffffff;
  --card-foreground: #2d2d2d;
  --primary: #2563eb;
  --muted-foreground: #6b7280;
  --radius: 14px;
  --shadow-soft: 0 4px 15px rgba(0,0,0,0.08);
  --shadow-card: 0 8px 25px rgba(0,0,0,0.12);
}
header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(6px);
    border-bottom: 1px solid #ddd;
    padding: 20px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.logo {
    display: flex;
    align-items: center;
    gap: 12px;
}
.logo-box {
    width: 50px;
    height: 50px;
    border-radius: 50px;
    display: flex;
    justify-content: center;
    align-items: center;
}
header a {
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
}
.btn-primary {
    background: #2563eb;
    color: white;
}
.btn-primary:hover {
    background: #1e4dcc;
}
.container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}
.page-header {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: var(--shadow-card);
    margin-bottom: 30px;
}
.page-header h1 {
    color: #1f2937;
    font-size: 32px;
    margin-bottom: 10px;
}
.page-header p {
    color: #6b7280;
}
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    border-bottom: 2px solid #e5e7eb;
}
.tab {
    padding: 12px 24px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 600;
    color: #6b7280;
    transition: all 0.3s ease;
}
.tab.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
}
.tab:hover {
    color: #2563eb;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}
.form-container {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: var(--shadow-card);
    margin-bottom: 30px;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #374151;
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}
.btn-primary {
    background: #2563eb;
    color: white;
}
.btn-primary:hover {
    background: #1e40af;
    transform: translateY(-2px);
}
.btn-danger {
    background: #dc2626;
    color: white;
}
.btn-danger:hover {
    background: #b91c1c;
}
.btn-success {
    background: #10b981;
    color: white;
}
.btn-success:hover {
    background: #059669;
}
.btn-secondary {
    background: #6b7280;
    color: white;
}
.btn-secondary:hover {
    background: #4b5563;
}
.table-container {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: var(--shadow-card);
}
table {
    width: 100%;
    border-collapse: collapse;
}
th {
    background: #f8fafc;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}
td {
    padding: 15px;
    border-bottom: 1px solid #e5e7eb;
}
tr:hover {
    background: #f8fafc;
}
.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}
.status-active {
    background: #d1fae5;
    color: #065f46;
}
.status-inactive {
    background: #fee2e2;
    color: #991b1b;
}
.status-expired {
    background: #fef3c7;
    color: #92400e;
}
.user-type-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}
.type-student {
    background: #dbeafe;
    color: #1e40af;
}
.type-admin {
    background: #fce7f3;
    color: #a21caf;
}
.type-instructor {
    background: #f3e8ff;
    color: #7c3aed;
}
.actions {
    display: flex;
    gap: 10px;
}
.search-box {
    margin-bottom: 20px;
}
.search-box input {
    width: 100%;
    max-width: 400px;
    padding: 10px 15px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
}
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}
.modal-content {
    background: white;
    margin: 50px auto;
    padding: 30px;
    border-radius: 15px;
    max-width: 500px;
    position: relative;
}
.close {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 28px;
    cursor: pointer;
    color: #6b7280;
}
@media (max-width: 768px) {
    .container {
        margin: 20px auto;
        padding: 0 15px;
    }
    .page-header {
        padding: 25px;
    }
    .form-container {
        padding: 25px;
    }
    header {
        padding: 15px 20px;
        flex-direction: column;
        gap: 15px;
    }
    .tabs {
        flex-wrap: wrap;
    }
    .tab {
        flex: 1;
        min-width: 100px;
        text-align: center;
    }
}
</style>
</head>
<body>
<header>
    <div class="logo">
        <div class="logo-box">
            <img src="../images/image.png" width="35" height="35" alt="Logo">
        </div>
        <strong>TESDA Auto Mechanic Training Centre</strong>
    </div>
    <div>
        <a href="admin_dashboard.php" class="btn-primary">Back to Dashboard</a>
    </div>
</header>

<div class="container">
    <div class="page-header">
        <h1>Access Management</h1>
        <p>Manage user access levels and permissions</p>
    </div>

    <div class="tabs">
        <button class="tab active" onclick="showTab('assign')">Assign Access</button>
        <button class="tab" onclick="showTab('manage')">Manage Access</button>
        <button class="tab" onclick="showTab('levels')">Access Levels</button>
    </div>

    <!-- Assign Access Tab -->
    <div id="assign" class="tab-content active">
        <div class="form-container">
            <h3>Assign User Access</h3>
            <form method="POST">
                <input type="hidden" name="action" value="assign_access">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>User Type *</label>
                        <select name="user_type" id="user_type" required onchange="updateUserDropdown()">
                            <option value="">Select User Type</option>
                            <option value="student">Student</option>
                            <option value="admin">Admin</option>
                            <option value="instructor">Instructor</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Select User *</label>
                        <select name="user_id" id="user_id" required>
                            <option value="">First select user type</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Access Level *</label>
                        <select name="access_id" required>
                            <option value="">Select Access Level</option>
                            <?php foreach ($accessLevels as $level): ?>
                            <option value="<?= $level['access_id'] ?>">
                                <?= htmlspecialchars($level['level_name']) ?> (Level <?= $level['level_hierarchy'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" name="expiry_date" min="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" placeholder="Add notes about this access assignment..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Assign Access</button>
            </form>
        </div>
    </div>

    <!-- Manage Access Tab -->
    <div id="manage" class="tab-content">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search users..." onkeyup="filterTable()">
        </div>
        
        <div class="table-container">
            <table id="accessTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Type</th>
                        <th>Access Level</th>
                        <th>Assigned Date</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userAccess as $access): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($access['user_name']) ?></strong><br>
                            <small style="color: #6b7280;"><?= htmlspecialchars($access['user_identifier']) ?></small>
                        </td>
                        <td>
                            <span class="user-type-badge type-<?= $access['user_type'] ?>">
                                <?= ucfirst($access['user_type']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($access['level_name']) ?></td>
                        <td><?= date('M d, Y', strtotime($access['assigned_date'])) ?></td>
                        <td><?= $access['expiry_date'] ? date('M d, Y', strtotime($access['expiry_date'])) : 'Never' ?></td>
                        <td>
                            <span class="status-badge status-<?= $access['status'] ?>">
                                <?= ucfirst($access['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <?php if ($access['status'] === 'Active'): ?>
                                    <button class="btn btn-danger" onclick="revokeAccess(<?= $access['assignment_id'] ?>)">
                                        Revoke
                                    </button>
                                    <button class="btn btn-secondary" onclick="extendAccess(<?= $access['assignment_id'] ?>)">
                                        Extend
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Access Levels Tab -->
    <div id="levels" class="tab-content">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Level Name</th>
                        <th>Hierarchy</th>
                        <th>Description</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accessLevels as $level): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($level['level_name']) ?></strong></td>
                        <td><?= $level['level_hierarchy'] ?></td>
                        <td><?= htmlspecialchars($level['level_description']) ?></td>
                        <td>
                            <span class="status-badge status-<?= $level['status'] ?>">
                                <?= ucfirst($level['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Extend Access Modal -->
<div id="extendModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Extend Access</h3>
        <form method="POST">
            <input type="hidden" name="action" value="extend_access">
            <input type="hidden" id="extend_assignment_id" name="assignment_id">
            
            <div class="form-group">
                <label>New Expiry Date *</label>
                <input type="date" name="expiry_date" required min="<?= date('Y-m-d') ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">Extend Access</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        </form>
    </div>
</div>

<script>
function showTab(tabName) {
    const tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    const tabButtons = document.querySelectorAll('.tab');
    tabButtons.forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}

function updateUserDropdown() {
    const userType = document.getElementById('user_type').value;
    const userDropdown = document.getElementById('user_id');
    
    userDropdown.innerHTML = '<option value="">Loading...</option>';
    
    if (userType) {
        const users = <?= json_encode(['students' => $students, 'admins' => $admins]) ?>;
        const userList = users[userType + 's'] || [];
        
        userDropdown.innerHTML = '<option value="">Select User</option>';
        userList.forEach(user => {
            const option = document.createElement('option');
            option.value = user[userType === 'student' ? 'StudID' : 'admin_id'];
            option.textContent = `${user.name} (${user[userType === 'student' ? 'SchoolID' : 'admin_id']})`;
            userDropdown.appendChild(option);
        });
    } else {
        userDropdown.innerHTML = '<option value="">First select user type</option>';
    }
}

function filterTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const table = document.getElementById('accessTable');
    const tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        const td = tr[i].getElementsByTagName('td');
        let txtValue = '';
        
        for (let j = 0; j < td.length; j++) {
            txtValue += td[j].textContent || td[j].innerText;
        }
        
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            tr[i].style.display = '';
        } else {
            tr[i].style.display = 'none';
        }
    }
}

function revokeAccess(assignmentId) {
    if (confirm('Are you sure you want to revoke this access?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="revoke_access">
            <input type="hidden" name="assignment_id" value="${assignmentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function extendAccess(assignmentId) {
    document.getElementById('extend_assignment_id').value = assignmentId;
    document.getElementById('extendModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('extendModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('extendModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>
</body>
</html>
