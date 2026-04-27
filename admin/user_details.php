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

$userId = $_GET['id'] ?? 0;
if (!$userId) {
    header("Location: manage_students.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

$stmt = $conn->prepare("SELECT * FROM student WHERE user_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM student_program_enrollments WHERE student_id = ? ORDER BY enrollment_id DESC");
$stmt->execute([$student['StudID'] ?? 0]);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("
    SELECT smp.*, lm.module_title
    FROM student_module_progress smp
    JOIN learning_modules lm ON smp.module_id = lm.module_id
    WHERE smp.enrollment_id IN (SELECT enrollment_id FROM student_program_enrollments WHERE student_id = ?)
    ORDER BY smp.last_access_date DESC
    LIMIT 10
");
$stmt->execute([$student['StudID'] ?? 0]);
$progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

$typeBadges = [
    'admin' => 'badge-red',
    'instructor' => 'badge-blue',
    'trainee' => 'badge-green',
    'student' => 'badge-green',
    'support_staff' => 'badge-orange',
    'instructional_unit' => 'badge-purple'
];

$pageTitle = "User Details";
$pageSubtitle = htmlspecialchars($user['username']);
$currentPage = "manage_students.php";

include 'sidebar_new.php';
?>

<div style="display: grid; grid-template-columns: 1fr 350px; gap: 20px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">User Information</h3>
            <div style="display: flex; gap: 8px;">
                <a href="edit_user.php?id=<?= $userId ?>" class="btn" style="padding: 8px 16px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none;">Edit</a>
                <a href="manage_students.php" class="btn" style="padding: 8px 16px; background: #f1f5f9; color: #374151; border-radius: 6px; text-decoration: none;">Back</a>
            </div>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Username</div>
                    <div style="font-weight: 600; font-size: 18px;"><?= htmlspecialchars($user['username']) ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">User Type</div>
                    <span class="badge <?= $typeBadges[$user['user_type']] ?? 'badge-gray' ?>"><?= htmlspecialchars($user['user_type']) ?></span>
                </div>
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Email</div>
                    <div style="font-weight: 600;"><?= htmlspecialchars($user['email'] ?? '-') ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Status</div>
                    <span class="badge <?= ($user['status'] ?? 'active') === 'active' ? 'badge-green' : 'badge-gray' ?>"><?= htmlspecialchars($user['status'] ?? 'active') ?></span>
                </div>
                <div style="grid-column: span 2;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Full Name</div>
                    <div style="font-weight: 600;"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Created</div>
                    <div><?= date('M d, Y H:i', strtotime($user['created_at'])) ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Last Login</div>
                    <div><?= $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never' ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <div>
        <?php if ($student): ?>
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h3 class="card-title">Student Record</h3>
            </div>
            <div class="card-body">
                <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Student ID</div>
                <div style="font-weight: 600; margin-bottom: 16px;"><?= htmlspecialchars($student['SchoolID'] ?? '-') ?></div>
                
                <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Status</div>
                <div style="font-weight: 600; margin-bottom: 16px;"><?= htmlspecialchars($student['Status'] ?? '-') ?></div>
                
                <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Enrollment Date</div>
                <div><?= $student['EnrollmentDate'] ? date('M d, Y', strtotime($student['EnrollmentDate'])) : '-' ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Enrollments</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($enrollments)): ?>
                <div style="padding: 20px; text-align: center; color: #64748b;">No enrollments</div>
                <?php else: ?>
                <?php foreach ($enrollments as $enroll): ?>
                <div style="padding: 12px 16px; border-bottom: 1px solid #e2e8f0;">
                    <div style="font-weight: 600; font-size: 14px;"><?= $enroll['nc_level'] ?? 'NC I' ?></div>
                    <div style="font-size: 12px; color: #64748b;">
                        <?= $enroll['enrollment_status'] ?? 'Active' ?> • <?= date('M d, Y', strtotime($enroll['enrollment_date'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($progress)): ?>
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3 class="card-title">Recent Module Progress</h3>
    </div>
    <div class="card-body" style="padding: 0; overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; min-width: 500px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 12px 16px; text-align: left; font-size: 12px; color: #64748b;">Module</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 12px; color: #64748b;">Progress</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 12px; color: #64748b;">Status</th>
                    <th style="padding: 12px 16px; text-align: left; font-size: 12px; color: #64748b;">Last Access</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($progress as $p): ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 12px 16px;"><?= htmlspecialchars($p['module_title']) ?></td>
                    <td style="padding: 12px 16px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 80px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                                <div style="width: <?= $p['progress_percentage'] ?? 0 ?>%; height: 100%; background: #2563eb;"></div>
                            </div>
                            <span style="font-size: 12px;"><?= $p['progress_percentage'] ?? 0 ?>%</span>
                        </div>
                    </td>
                    <td style="padding: 12px 16px;">
                        <span class="badge <?= ($p['status'] ?? '') === 'Completed' ? 'badge-green' : (($p['status'] ?? '') === 'In Progress' ? 'badge-blue' : 'badge-gray') ?>">
                            <?= $p['status'] ?? 'Not Started' ?>
                        </span>
                    </td>
                    <td style="padding: 12px 16px; font-size: 12px; color: #64748b;">
                        <?= $p['last_access_date'] ? date('M d, Y', strtotime($p['last_access_date'])) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</main>
</div>

<style>
@media (max-width: 900px) {
    .card > .grid { grid-template-columns: 1fr !important; }
}
</style>

</body>
</html>