<?php
session_start();
include 'db.php';
$database = new Database();
$conn = $database->getConnection();

// Fetch users table (select all and normalize fields)
// Only fetch users from this system (admin and student roles)
try {
    $ustmt = $conn->prepare("SELECT * FROM users WHERE Role IN ('admin', 'student') OR Role IS NULL");
    $ustmt->execute();
    $rawUsers = $ustmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rawUsers = [];
}

// Normalize users array to ensure fields used in UI exist
// Also check enrollment status for students
$users = [];
foreach ($rawUsers as $u) {
    $userId = $u['UserID'] ?? $u['id'] ?? null;
    $full = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    
    // Check enrollment status if this is a student
    $isEnrolled = false;
    $enrollmentStatus = '';
    if ($userId && ($u['Role'] ?? $u['role'] ?? '') === 'student') {
        try {
            $enrollCheck = $conn->prepare("
                SELECT enrollment_status FROM student_program_enrollments 
                WHERE student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1) 
                LIMIT 1
            ");
            $enrollCheck->execute([$userId]);
            $enrollmentStatus = $enrollCheck->fetchColumn() ?: '';
            $isEnrolled = $enrollmentStatus === 'Active';
        } catch (Exception $e) {
            $isEnrolled = false;
        }
    }
    
    $users[] = [
        'UserID' => $userId,
        'FullName' => $full,
        'Username' => $u['Username'] ?? $u['user'] ?? '',
        'Email' => $u['Email'] ?? $u['EmailAddr'] ?? '',
        'Role' => $u['Role'] ?? $u['role'] ?? 'user',
        'Status' => $u['Status'] ?? $u['status'] ?? 'inactive',
        'IsEnrolled' => $isEnrolled,
        'EnrollmentStatus' => $enrollmentStatus
    ];
}

// Fetch students for "create user from student" option
// Show all students from student table
try {
    $sstmt = $conn->prepare("SELECT StudID, SchoolID, FirstName, LastName, EmailAddr FROM student ORDER BY FirstName, LastName");
    $sstmt->execute();
    $students = $sstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $students = [];
}

// include header and sidebar
include 'header.php';
include 'sidebar.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Users - Admin Dashboard</title>
  <link rel="stylesheet" href="css/users.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>

<body>

<div class="main-content">

  <!-- ===== HEADER ===== -->
  <div class="header">
    <h2 class="page-title">👤 User Management</h2>
    <button id="openAddUser" class="btn-add">
      <i class="fa-solid fa-user-plus"></i> Add User
    </button>
  </div>

  <!-- ===== FILTERS ===== -->
  <div class="filter-container">
    <input type="text" id="searchInput" placeholder="Search user...">

    <select id="roleFilter">
      <option value="">All Roles</option>
      <option value="admin">Admin</option>
      <option value="student">Student</option>
      <option value="user">User</option>
    </select>

    <select id="statusFilter">
      <option value="">All Status</option>
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
    </select>

    <select id="enrollmentFilter">
      <option value="">All Enrollment</option>
      <option value="enrolled">Enrolled</option>
      <option value="not enrolled">Not Enrolled</option>
    </select>
  </div>

  <!-- ===== EXPORT ===== -->
  <div class="export-buttons">
    <button onclick="exportPDF()" class="export-btn">
      <i class="fa-solid fa-file-pdf"></i> Export
    </button>
  </div>

  <!-- ===== TABLE ===== -->
  <div class="table-container">
    <table id="usersTable">
      <thead>
        <tr>
          <th>User ID</th>
          <th>Name</th>
          <th>Username</th>
          <th>Role</th>
          <th>Status</th>
          <th>Enrollment</th>
          <th>Actions</th>
        </tr>
      </thead>

      <tbody>
      <?php foreach ($users as $user): ?>
        <tr>
          <td><?= htmlspecialchars($user['UserID']) ?></td>
          <td><?= htmlspecialchars($user['FullName']) ?></td>
          <td><?= htmlspecialchars($user['Username']) ?></td>
          <td><?= htmlspecialchars(ucfirst($user['Role'])) ?></td>
          <td>
            <?php if (strtolower($user['Status']) === 'active'): ?>
              <span class="badge active">Active</span>
            <?php else: ?>
              <span class="badge inactive">Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($user['Role'] === 'student'): ?>
              <?php if ($user['IsEnrolled']): ?>
                <span class="badge" style="background: #10b981; color: white;">Enrolled</span>
              <?php else: ?>
                <span class="badge" style="background: #f59e0b; color: white;">Not Enrolled</span>
              <?php endif; ?>
            <?php else: ?>
              <span class="badge" style="background: #6b7280; color: white;">N/A</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="#"
               class="action-btn edit openEditModal"
               data-id="<?= $user['UserID'] ?>"
               data-username="<?= htmlspecialchars($user['Username']) ?>"
               data-role="<?= htmlspecialchars($user['Role']) ?>"
               data-status="<?= htmlspecialchars($user['Status']) ?>">
              <i class="fas fa-edit"></i>
            </a>

            <a href="#"
               class="action-btn delete openDeleteModal"
               data-id="<?= $user['UserID'] ?>"
               data-name="<?= $user['FullName'] ?>">
              <i class="fas fa-trash"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'crud/add_user_modal.php'; ?>
<?php include 'crud/edit_user_modal.php'; ?>
<?php include 'crud/delete_user_modal.php'; ?>

<!-- ===== FILTER SCRIPT ===== -->
<script>
const searchInput = document.getElementById('searchInput');
const roleFilter = document.getElementById('roleFilter');
const statusFilter = document.getElementById('statusFilter');
const enrollmentFilter = document.getElementById('enrollmentFilter');
const rows = document.querySelectorAll('#usersTable tbody tr');

function filterTable() {
  const search = searchInput.value.toLowerCase();
  const role = roleFilter.value.toLowerCase();
  const status = statusFilter.value.toLowerCase();
  const enrollment = enrollmentFilter.value.toLowerCase();

  rows.forEach(row => {
    const id = row.cells[0].textContent.toLowerCase();
    const name = row.cells[1].textContent.toLowerCase();
    const username = row.cells[2].textContent.toLowerCase();
    const userRole = row.cells[3].textContent.toLowerCase();
    const userStatus = row.cells[4].textContent.toLowerCase();
    const userEnrollment = row.cells[5].textContent.toLowerCase();

    const matchSearch =
      id.includes(search) ||
      name.includes(search) ||
      username.includes(search);

    const matchRole = role === "" || userRole === role;
    const matchStatus = status === "" || userStatus.includes(status);
    const matchEnrollment = enrollment === "" || userEnrollment.includes(enrollment);

    row.style.display = (matchSearch && matchRole && matchStatus && matchEnrollment) ? "" : "none";
  });
}

searchInput.addEventListener('keyup', filterTable);
roleFilter.addEventListener('change', filterTable);
statusFilter.addEventListener('change', filterTable);
enrollmentFilter.addEventListener('change', filterTable);
</script>

<script>
// Add/Edit user modal controls
document.getElementById('openAddUser').addEventListener('click', () => document.getElementById('addUserModal').style.display = 'flex');
document.getElementById('closeAddUserModal').addEventListener('click', () => document.getElementById('addUserModal').style.display = 'none');

// create from selection
const createFrom = document.getElementById('createFrom');
const studentSelectWrap = document.getElementById('studentSelectWrap');
const studentSelect = document.getElementById('studentSelect');
createFrom.addEventListener('change', () => {
  if (createFrom.value === 'student') {
    studentSelectWrap.style.display = '';
  } else {
    studentSelectWrap.style.display = 'none';
  }
});

studentSelect.addEventListener('change', function(){
  const opt = this.selectedOptions[0];
  if (!opt) return;
  document.getElementById('add_fullname').value = opt.dataset.first + ' ' + opt.dataset.last;
  document.getElementById('add_email').value = opt.dataset.email;
  document.getElementById('add_username').value = opt.value; // default username = StudID
});
</script>

<!-- ===== EXPORT PDF ===== -->
<script>
function exportPDF() {
  const element = document.getElementById('usersTable');
  html2pdf().set({
    margin: 0.5,
    filename: 'System_Users.pdf',
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2 },
    jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
  }).from(element).save();
}
</script>

<?php if (isset($_SESSION['new_user_password'])): ?>
<script>
  alert('New user created. Temporary password: <?= addslashes($_SESSION['new_user_password']) ?>');
</script>
<?php unset($_SESSION['new_user_password']); endif; ?>

<script>
// Ensure delete modal listeners are attached after DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  console.log('Delete buttons found:', document.querySelectorAll('.openDeleteModal').length);
});
</script>

</body>
</html>
