<?php 
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['userId'])) {
    header("Location: ../login.php");
    exit();
}
$userId = $_SESSION['user_id'] ?? $_SESSION['userId'] ?? null;
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';

if ($userType !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get admin info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));

// Get statistics
$stmt = $conn->query("SELECT user_type, COUNT(*) as cnt FROM users GROUP BY user_type");
$userStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stats = [];
$totalUsers = 0;
foreach ($userStats as $s) {
    $stats[$s['user_type']] = $s['cnt'];
    $totalUsers += $s['cnt'];
}

// Get student/trainee count
$studentCount = ($stats['student'] ?? 0) + ($stats['trainee'] ?? 0);
$instructorCount = $stats['instructor'] ?? 0;

// Get module statistics
$stmt = $conn->query("SELECT COUNT(*) as total FROM learning_modules WHERE is_active = 1");
$moduleStats = $stmt->fetch(PDO::FETCH_ASSOC);
$totalModules = $moduleStats['total'] ?? 0;

// Get enrollment statistics
$stmt = $conn->query("SELECT COUNT(*) as total FROM student_program_enrollments WHERE enrollment_status = 'Active'");
$enrollmentStats = $stmt->fetch(PDO::FETCH_ASSOC);
$activeEnrollments = $enrollmentStats['total'] ?? 0;

// Get recent users
$stmt = $conn->query("SELECT user_id, username, email, user_type, created_at FROM users ORDER BY user_id DESC LIMIT 5");
$recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get NC level statistics
$stmt = $conn->query("SELECT nc_level, COUNT(*) as count FROM student_program_enrollments WHERE enrollment_status = 'Active' GROUP BY nc_level");
$ncStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Admin Dashboard";
$pageSubtitle = "System Overview & Management";
$showPrintButton = true;

include '../includes/unified_header.php';
?>

<!-- Dashboard Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-label">Total Users</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-success"><?= $studentCount ?></div>
        <div class="stat-label">Active Students</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-primary"><?= $totalModules ?></div>
        <div class="stat-label">Training Modules</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-warning"><?= $instructorCount ?></div>
        <div class="stat-label">Instructors</div>
    </div>
</div>

<!-- Performance Metrics -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-chart">
            <!-- Simple donut chart representation -->
            <div style="width: 120px; height: 120px; border-radius: 50%; background: conic-gradient(#ef4444 0deg 0deg, #fee2e2 0deg 360deg); display: flex; align-items: center; justify-content: center;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center;">
                    <span style="font-size: 24px; font-weight: bold;">0%</span>
                </div>
            </div>
        </div>
        <div class="metric-value danger">0%</div>
        <div class="metric-label">Assessment Pass Rate</div>
        <small style="color: #6b7280;">No assessments completed yet</small>
    </div>
    
    <div class="metric-card">
        <div class="metric-value primary">0%</div>
        <div class="metric-label">Overall Average Score</div>
        <small style="color: #6b7280;">No grades recorded yet</small>
        <div style="margin-top: 20px;">
            <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                <div style="background: #2563eb; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
            </div>
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value success">0%</div>
        <div class="metric-label">Students Completed</div>
        <small style="color: #6b7280;">No program completions yet</small>
        <div style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; font-size: 14px;">
                <span>In Progress: <?= $activeEnrollments ?></span>
                <span>Completed: 0</span>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity & NC Level Overview -->
<div class="grid-2" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Recent Users -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent User Activity</h3>
            <a href="users.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentUsers)): ?>
                <p style="text-align: center; color: #6b7280; padding: 20px;">No recent user activity.</p>
            <?php else: ?>
                <div class="table" style="background: transparent; box-shadow: none; border: none;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">User</th>
                                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Type</th>
                                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid #f3f4f6;">
                                        <div>
                                            <div style="font-weight: 600;"><?= htmlspecialchars($user['username']) ?></div>
                                            <div style="font-size: 12px; color: #6b7280;"><?= htmlspecialchars($user['email']) ?></div>
                                        </div>
                                    </td>
                                    <td style="padding: 8px; border-bottom: 1px solid #f3f4f6;">
                                        <span class="badge badge-primary"><?= htmlspecialchars(ucfirst($user['user_type'])) ?></span>
                                    </td>
                                    <td style="padding: 8px; border-bottom: 1px solid #f3f4f6; font-size: 14px;">
                                        <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- NC Level Distribution -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">NC Level Distribution</h3>
            <a href="manage_nc_subjects.php" class="btn btn-sm btn-primary">Manage</a>
        </div>
        <div class="card-body">
            <?php if (empty($ncStats)): ?>
                <p style="text-align: center; color: #6b7280; padding: 20px;">No NC level enrollments yet.</p>
            <?php else: ?>
                <?php foreach ($ncStats as $nc): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                        <div>
                            <div style="font-weight: 600;"><?= htmlspecialchars($nc['nc_level']) ?></div>
                            <div style="font-size: 12px; color: #6b7280;">Active enrollments</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 24px; font-weight: 700; color: #2563eb;"><?= $nc['count'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Quick Actions</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <a href="manage_students.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px;">
                <i class="fas fa-user-plus"></i>
                <span>Add New Student</span>
            </a>
            <a href="courses.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px;">
                <i class="fas fa-plus-circle"></i>
                <span>Create Course</span>
            </a>
            <a href="manage_nc_subjects.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px;">
                <i class="fas fa-graduation-cap"></i>
                <span>Manage NC Levels</span>
            </a>
            <a href="post_notice.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px;">
                <i class="fas fa-bullhorn"></i>
                <span>Post Notice</span>
            </a>
        </div>
    </div>
</div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
