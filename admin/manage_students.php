<?php 
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['userId'])) {
    header("Location: ../login.php");
    exit();
}
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if (!in_array($userType, ['admin', 'instructional_unit'])) {
    header("Location: ../login.php");
    exit();
}

$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? 'All';
$status_filter = $_GET['status'] ?? 'All';

$where = ["1=1"];
if (!empty($search)) {
    $search_escaped = $conn->quote("%$search%");
    $where[] = "(username LIKE $search_escaped OR email LIKE $search_escaped OR first_name LIKE $search_escaped OR last_name LIKE $search_escaped)";
}
if ($type_filter !== 'All') {
    $where[] = "user_type = '$type_filter'";
}
if ($status_filter !== 'All') {
    $where[] = "status = '$status_filter'";
}
$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $conn->query("SELECT * FROM users $whereClause ORDER BY user_id DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = ['All' => 0, 'admin' => 0, 'instructor' => 0, 'trainee' => 0, 'student' => 0, 'support_staff' => 0, 'active' => 0, 'inactive' => 0];
$stmt = $conn->query("SELECT user_type, status, COUNT(*) as cnt FROM users GROUP BY user_type, status");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($counts[$row['user_type']])) {
        $counts[$row['user_type']] = $row['cnt'];
    }
    $counts['All'] += $row['cnt'];
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = $row['cnt'];
    }
}

$stmt = $conn->query("SELECT COUNT(*) as cnt, user_type FROM users WHERE status = 'active' GROUP BY user_type");
$activeByType = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $activeByType[$row['user_type']] = $row['cnt'];
}

$stmt = $conn->query("SELECT COUNT(DISTINCT student_id) as cnt FROM student_program_enrollments WHERE enrollment_status = 'Active'");
$enrolledStudents = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;

$pageTitle = "Manage Students";
$pageSubtitle = "User & Enrollment Management";
$currentPage = "manage_students.php";

include 'sidebar_new.php';
?>

<!-- Statistics -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 30px;">
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Total Users</div>
        <div style="font-size: 28px; font-weight: 700; color: #1e40af;"><?= $counts['All'] ?></div>
    </div>
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Administrators</div>
        <div style="font-size: 28px; font-weight: 700; color: #dc2626;"><?= $counts['admin'] ?></div>
    </div>
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Instructors</div>
        <div style="font-size: 28px; font-weight: 700; color: #2563eb;"><?= $counts['instructor'] ?></div>
    </div>
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Students</div>
        <div style="font-size: 28px; font-weight: 700; color: #10b981;"><?= $counts['trainee'] + $counts['student'] ?></div>
    </div>
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Enrolled</div>
        <div style="font-size: 28px; font-weight: 700; color: #f59e0b;"><?= $enrolledStudents ?></div>
    </div>
</div>

<!-- Filters -->
<div style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
    <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, username..." style="padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; width: 220px;">
        
        <select name="type" style="padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <option value="All">All Types</option>
            <option value="admin" <?= $type_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="instructor" <?= $type_filter === 'instructor' ? 'selected' : '' ?>>Instructor</option>
            <option value="trainee" <?= $type_filter === 'trainee' ? 'selected' : '' ?>>Trainee</option>
            <option value="student" <?= $type_filter === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="support_staff" <?= $type_filter === 'support_staff' ? 'selected' : '' ?>>Support Staff</option>
        </select>
        
        <select name="status" style="padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <option value="All">All Status</option>
            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        
        <button type="submit" class="btn" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer;">Filter</button>
        
        <?php if ($search || $type_filter !== 'All' || $status_filter !== 'All'): ?>
        <a href="manage_students.php" class="btn" style="padding: 10px 20px; background: #64748b; color: white; border-radius: 8px; text-decoration: none;">Clear</a>
        <?php endif; ?>
    </form>
    
    <a href="add_user.php" class="btn" style="padding: 10px 20px; background: #10b981; color: white; border-radius: 8px; text-decoration: none; margin-left: auto;">
        + Add User
    </a>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">All Users (<?= count($users) ?>)</h3>
    </div>
    <div class="card-body" style="padding: 0; overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">ID</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Username</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Name</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Email</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Type</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Status</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Joined</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): 
                    $stmt = $conn->prepare("SELECT spe.nc_level, spe.enrollment_status, spe.enrollment_date FROM student_program_enrollments spe JOIN student s ON spe.student_id = s.StudID WHERE s.user_id = ? ORDER BY spe.enrollment_id DESC LIMIT 1");
                    $stmt->execute([$u['user_id']]);
                    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 14px 16px; font-weight: 600;"><?= $u['user_id'] ?></td>
                    <td style="padding: 14px 16px; font-weight: 600;"><?= htmlspecialchars($u['username']) ?></td>
                    <td style="padding: 14px 16px;">
                        <?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?>
                        <?php if ($enrollment): ?>
                        <div style="font-size: 11px; color: #10b981; font-weight: 600;"><?= $enrollment['nc_level'] ?? 'NC I' ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 14px 16px;"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                    <td style="padding: 14px 16px;">
                        <?php 
                        $typeBadges = [
                            'admin' => 'badge-red',
                            'instructor' => 'badge-blue',
                            'trainee' => 'badge-green',
                            'student' => 'badge-green',
                            'support_staff' => 'badge-orange'
                        ];
                        $badgeClass = $typeBadges[$u['user_type']] ?? 'badge-gray';
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($u['user_type']) ?></span>
                    </td>
                    <td style="padding: 14px 16px;">
                        <span class="badge <?= ($u['status'] ?? 'active') === 'active' ? 'badge-green' : 'badge-gray' ?>">
                            <?= htmlspecialchars($u['status'] ?? 'active') ?>
                        </span>
                    </td>
                    <td style="padding: 14px 16px; color: #64748b; font-size: 13px;">
                        <?= date('M d, Y', strtotime($u['created_at'])) ?>
                    </td>
                    <td style="padding: 14px 16px;">
                        <div style="display: flex; gap: 6px;">
                            <a href="user_details.php?id=<?= $u['user_id'] ?>" class="btn" style="padding: 6px 10px; background: #f1f5f9; color: #374151; border-radius: 6px; text-decoration: none; font-size: 12px;">View</a>
                            <a href="edit_user.php?id=<?= $u['user_id'] ?>" class="btn" style="padding: 6px 10px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none; font-size: 12px;">Edit</a>
                            <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                            <a href="delete_user.php?id=<?= $u['user_id'] ?>" onclick="return confirm('Delete this user?')" class="btn" style="padding: 6px 10px; background: #fee2e2; color: #dc2626; border-radius: 6px; text-decoration: none; font-size: 12px;">Delete</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: #64748b;">
                        No users found matching your filters.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
</div>

<style>
.stats-grid { margin-bottom: 30px; }
@media (max-width: 1000px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr) !important; }
}
@media (max-width: 600px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
}
</style>

</body>
</html>