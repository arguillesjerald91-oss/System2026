<?php 
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

// Check if user is logged in and is a student/trainee
if (!isset($_SESSION['userId']) || !in_array($_SESSION['userRole'], ['student', 'trainee'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['userId'];
$userRole = $_SESSION['userRole'] ?? 'trainee';

// Normalize role
if ($userRole === 'student') {
    $_SESSION['userRole'] = 'trainee';
    $userRole = 'trainee';
}

// Get student information
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));

// Get student's NC level enrollment
$stmt = $conn->prepare("
    SELECT spe.nc_level, spe.enrollment_id, ap.program_title
    FROM student_program_enrollments spe
    LEFT JOIN auto_mechanic_programs ap ON spe.program_id = ap.program_id
    WHERE spe.student_id = (SELECT StudID FROM student WHERE user_id = ?) 
    AND spe.enrollment_status = 'Active'
    ORDER BY spe.enrollment_id DESC LIMIT 1
");
$stmt->execute([$userId]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
$studentNcLevel = $enrollment['nc_level'] ?? 'Not Assigned';

// Get module statistics for student's NC level
$totalModules = 0;
$completedModules = 0;
$inProgressModules = 0;

if ($enrollment) {
    // Get total modules for student's NC level
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM nc_level_subjects nls
        JOIN learning_modules lm ON nls.module_id = lm.module_id
        WHERE nls.nc_level = ? AND lm.is_active = 1
    ");
    $stmt->execute([$studentNcLevel]);
    $totalModules = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get progress statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_enrolled,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress
        FROM student_module_progress smp
        JOIN learning_modules lm ON smp.module_id = lm.module_id
        JOIN nc_level_subjects nls ON lm.module_id = nls.module_id
        WHERE smp.enrollment_id = ? AND nls.nc_level = ?
    ");
    $stmt->execute([$enrollment['enrollment_id'], $studentNcLevel]);
    $progressStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $completedModules = $progressStats['completed'] ?? 0;
    $inProgressModules = $progressStats['in_progress'] ?? 0;
}

// Get recent activities
$stmt = $conn->prepare("
    SELECT lm.module_title, smp.start_date, smp.status, smp.progress_percentage
    FROM student_module_progress smp
    JOIN learning_modules lm ON smp.module_id = lm.module_id
    WHERE smp.enrollment_id = ?
    ORDER BY smp.start_date DESC
    LIMIT 5
");
$stmt->execute([$enrollment['enrollment_id'] ?? 0]);
$recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming assignments/quizzes (placeholder)
$upcomingAssignments = 3; // This would come from database

$pageTitle = "My Dashboard";
$pageSubtitle = "Welcome back, " . htmlspecialchars($fullName);

include '../includes/unified_header.php';
?>

<!-- Student Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $totalModules ?></div>
        <div class="stat-label">Total Modules</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-success"><?= $completedModules ?></div>
        <div class="stat-label">Completed</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-primary"><?= $inProgressModules ?></div>
        <div class="stat-label">In Progress</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-warning"><?= $upcomingAssignments ?></div>
        <div class="stat-label">Upcoming Tasks</div>
    </div>
</div>

<!-- Progress Overview -->
<div class="metrics-grid">
    <div class="metric-card">
        <?php 
        $completionRate = $totalModules > 0 ? round(($completedModules / $totalModules) * 100) : 0;
        ?>
        <div class="metric-value success"><?= $completionRate ?>%</div>
        <div class="metric-label">Completion Rate</div>
        <div style="margin-top: 20px;">
            <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                <div style="background: #10b981; height: 100%; width: <?= $completionRate ?>%; transition: width 0.3s ease;"></div>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #6b7280; margin-top: 4px;">
                <span><?= $completedModules ?> completed</span>
                <span><?= $totalModules ?> total</span>
            </div>
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value primary"><?= $studentNcLevel ?></div>
        <div class="metric-label">Current NC Level</div>
        <small style="color: #6b7280;">
            <?= $enrollment['program_title'] ?? 'Not enrolled in program' ?>
        </small>
        <div style="margin-top: 20px;">
            <a href="learning_modules.php" class="btn btn-sm btn-primary">View Modules</a>
        </div>
    </div>
    
    <div class="metric-card">
        <?php 
        $activeRate = $totalModules > 0 ? round(($inProgressModules / $totalModules) * 100) : 0;
        ?>
        <div class="metric-value warning"><?= $activeRate ?>%</div>
        <div class="metric-label">Active Learning</div>
        <small style="color: #6b7280;">Modules currently in progress</small>
        <div style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; font-size: 14px;">
                <span>In Progress: <?= $inProgressModules ?></span>
                <span>Not Started: <?= $totalModules - $completedModules - $inProgressModules ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity & Quick Links -->
<div class="grid-2" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Activity</h3>
            <a href="my_grades.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentActivities)): ?>
                <p style="text-align: center; color: #6b7280; padding: 20px;">No recent activity. Start learning to see your progress here!</p>
            <?php else: ?>
                <?php foreach ($recentActivities as $activity): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                        <div>
                            <div style="font-weight: 600;"><?= htmlspecialchars($activity['module_title']) ?></div>
                            <div style="font-size: 12px; color: #6b7280;">
                                Started: <?= date('M j, Y', strtotime($activity['start_date'])) ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span class="badge <?= $activity['status'] === 'Completed' ? 'badge-success' : 'badge-primary' ?>">
                                <?= htmlspecialchars($activity['status']) ?>
                            </span>
                            <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                                <?= $activity['progress_percentage'] ?? 0 ?>% complete
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Links -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Quick Links</h3>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <a href="learning_modules.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px;">
                    <i class="fas fa-book"></i>
                    <span>My Modules</span>
                </a>
                <a href="my_grades.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px;">
                    <i class="fas fa-chart-bar"></i>
                    <span>My Grades</span>
                </a>
                <a href="schedule.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px;">
                    <i class="fas fa-calendar"></i>
                    <span>My Schedule</span>
                </a>
                <a href="notices.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px;">
                    <i class="fas fa-bell"></i>
                    <span>Notices</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Current Modules -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Current Modules - <?= htmlspecialchars($studentNcLevel) ?></h3>
        <a href="learning_modules.php" class="btn btn-sm btn-primary">View All</a>
    </div>
    <div class="card-body">
        <?php if ($totalModules === 0): ?>
            <p style="text-align: center; color: #6b7280; padding: 20px;">
                No modules available for your NC level yet. Please contact your administrator.
            </p>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
                <?php
                // Get first few modules for preview
                $stmt = $conn->prepare("
                    SELECT lm.module_title, lm.module_description, lm.duration_mins, nls.sort_order,
                           smp.status, smp.progress_percentage
                    FROM learning_modules lm
                    JOIN nc_level_subjects nls ON lm.module_id = nls.module_id
                    LEFT JOIN student_module_progress smp ON lm.module_id = smp.module_id AND smp.enrollment_id = ?
                    WHERE nls.nc_level = ? AND lm.is_active = 1
                    ORDER BY nls.sort_order ASC
                    LIMIT 6
                ");
                $stmt->execute([$enrollment['enrollment_id'] ?? 0, $studentNcLevel]);
                $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($modules as $module):
                    $status = $module['status'] ?? 'Not Started';
                    $progress = $module['progress_percentage'] ?? 0;
                ?>
                    <div class="module-item" style="background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <div style="width: 40px; height: 40px; background: #2563eb; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                                <i class="fas fa-book"></i>
                            </div>
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 600;">
                                    <?= htmlspecialchars($module['module_title']) ?>
                                </h4>
                                <p style="margin: 0 0 8px 0; font-size: 12px; color: #6b7280;">
                                    <?= htmlspecialchars($module['module_description'] ?? '') ?>
                                </p>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="badge <?= $status === 'Completed' ? 'badge-success' : ($status === 'In Progress' ? 'badge-primary' : 'badge-warning') ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                    <small style="color: #6b7280;"><?= $module['duration_mins'] ?? 0 ?> mins</small>
                                </div>
                                <?php if ($progress > 0): ?>
                                    <div style="margin-top: 8px;">
                                        <div style="background: #e5e7eb; height: 4px; border-radius: 2px; overflow: hidden;">
                                            <div style="background: #2563eb; height: 100%; width: <?= $progress ?>%;"></div>
                                        </div>
                                        <small style="color: #6b7280;"><?= $progress ?>% complete</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
