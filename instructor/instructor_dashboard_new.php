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
$userType = ($userType === 'instructor') ? 'trainer' : $userType;

if ($userType !== 'trainer') {
    header("Location: ../login.php");
    exit();
}

// Get trainer details
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$trainer = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($trainer['first_name'] ?? '') . ' ' . ($trainer['last_name'] ?? ''));

// Get statistics for trainer dashboard
$totalStudents = 0;
$activeStudents = 0;
$totalModules = 0;
$graduatedCount = 0;

// Get total students assigned to this trainer
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.user_id) as total
    FROM student s
    JOIN student_program_enrollments spe ON s.StudID = spe.student_id
    WHERE spe.enrollment_status = 'Active'
");
$stmt->execute();
$totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get active students (recent activity)
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.user_id) as active
    FROM student s
    JOIN student_program_enrollments spe ON s.StudID = spe.student_id
    JOIN student_module_progress smp ON spe.enrollment_id = smp.enrollment_id
    WHERE smp.start_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND spe.enrollment_status = 'Active'
");
$stmt->execute();
$activeStudents = $stmt->fetch(PDO::FETCH_ASSOC)['active'] ?? 0;

// Get modules managed by this trainer
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM learning_modules lm
    JOIN module_access_permissions map ON lm.module_id = map.module_id
    WHERE map.user_id = ? AND map.user_type IN ('trainer', 'instructor') AND map.access_status = 'Active'
    AND lm.is_active = 1
");
$stmt->execute([$userId]);
$totalModules = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get graduated students
$stmt = $conn->prepare("
    SELECT COUNT(*) as graduated
    FROM student_program_enrollments spe
    JOIN student s ON spe.student_id = s.StudID
    WHERE spe.enrollment_status = 'Completed'
    AND spe.completion_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
");
$stmt->execute();
$graduatedCount = $stmt->fetch(PDO::FETCH_ASSOC)['graduated'] ?? 0;

// Get recent student activities
$stmt = $conn->prepare("
    SELECT s.FirstName, s.LastName, lm.module_title, smp.start_date, smp.status, smp.progress_percentage
    FROM student_module_progress smp
    JOIN learning_modules lm ON smp.module_id = lm.module_id
    JOIN student_program_enrollments spe ON smp.enrollment_id = spe.enrollment_id
    JOIN student s ON spe.student_id = s.StudID
    WHERE smp.start_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY smp.start_date DESC
    LIMIT 5
");
$stmt->execute();
$recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assessment statistics
$passRate = 0;
$averageScore = 0;
$completionRate = 0;

// Calculate pass rate (placeholder - would come from quiz results)
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_attempts,
        SUM(CASE WHEN score >= passing_score THEN 1 ELSE 0 END) as passed
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.quiz_id
    WHERE qa.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();
$assessmentStats = $stmt->fetch(PDO::FETCH_ASSOC);
if ($assessmentStats['total_attempts'] > 0) {
    $passRate = round(($assessmentStats['passed'] / $assessmentStats['total_attempts']) * 100);
}

// Calculate average score (placeholder)
$stmt = $conn->prepare("
    SELECT AVG(score) as avg_score
    FROM quiz_attempts qa
    WHERE qa.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();
$avgScoreResult = $stmt->fetch(PDO::FETCH_ASSOC);
$averageScore = $avgScoreResult['avg_score'] ? round($avgScoreResult['avg_score']) : 0;

// Calculate completion rate
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_enrollments,
        SUM(CASE WHEN spe.enrollment_status = 'Completed' THEN 1 ELSE 0 END) as completed
    FROM student_program_enrollments spe
    WHERE spe.enrollment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
");
$stmt->execute();
$completionStats = $stmt->fetch(PDO::FETCH_ASSOC);
if ($completionStats['total_enrollments'] > 0) {
    $completionRate = round(($completionStats['completed'] / $completionStats['total_enrollments']) * 100);
}

$pageTitle = "Reports & Analytics";
$pageSubtitle = "Training center performance metrics";
$showPrintButton = true;

include '../includes/unified_header.php';
?>

<!-- Instructor Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $totalStudents ?></div>
        <div class="stat-label">Total Students</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-success"><?= $activeStudents ?></div>
        <div class="stat-label">Active Students</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-primary"><?= $totalModules ?></div>
        <div class="stat-label">Training Modules</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-warning"><?= $graduatedCount ?></div>
        <div class="stat-label">Graduated</div>
    </div>
</div>

<!-- Performance Metrics -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-chart">
            <!-- Donut chart for pass rate -->
            <div style="width: 120px; height: 120px; border-radius: 50%; background: conic-gradient(#ef4444 0deg <?= $passRate * 3.6 ?>deg, #fee2e2 <?= $passRate * 3.6 ?>deg 360deg); display: flex; align-items: center; justify-content: center;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center;">
                    <span style="font-size: 24px; font-weight: bold; color: #ef4444;"><?= $passRate ?>%</span>
                </div>
            </div>
        </div>
        <div class="metric-value danger"><?= $passRate ?>%</div>
        <div class="metric-label">Assessment Pass Rate</div>
        <small style="color: #6b7280;"><?= $assessmentStats['total_attempts'] ?? 0 ?> assessments taken</small>
    </div>
    
    <div class="metric-card">
        <div class="metric-value primary"><?= $averageScore ?>%</div>
        <div class="metric-label">Overall Average Score</div>
        <small style="color: #6b7280;">Across all assessments</small>
        <div style="margin-top: 20px;">
            <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                <div style="background: #2563eb; height: 100%; width: <?= $averageScore ?>%; transition: width 0.3s ease;"></div>
            </div>
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value success"><?= $completionRate ?>%</div>
        <div class="metric-label">Students Completed</div>
        <small style="color: #6b7280;"><?= $completionStats['completed'] ?? 0 ?> of <?= $completionStats['total_enrollments'] ?? 0 ?> students</small>
        <div style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; font-size: 14px;">
                <span>In Progress: <?= ($completionStats['total_enrollments'] ?? 0) - ($completionStats['completed'] ?? 0) ?></span>
                <span>Completed: <?= $completionStats['completed'] ?? 0 ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Recent Student Activity & Performance Overview -->
<div class="grid-2" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Recent Student Activity -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Student Activity</h3>
            <a href="my_students.php" class="btn btn-sm btn-primary">View All Students</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentActivities)): ?>
                <p style="text-align: center; color: #6b7280; padding: 20px;">No recent student activity.</p>
            <?php else: ?>
                <div class="table" style="background: transparent; box-shadow: none; border: none;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Student</th>
                                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Module</th>
                                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Status</th>
                                <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb;">Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $activity): ?>
                                <tr>
                                    <td style="padding: 8px; border-bottom: 1px solid #f3f4f6;">
                                        <div>
                                            <div style="font-weight: 600;"><?= htmlspecialchars($activity['FirstName'] . ' ' . $activity['LastName']) ?></div>
                                            <div style="font-size: 12px; color: #6b7280;">
                                                <?= date('M j, Y', strtotime($activity['start_date'])) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 8px; border-bottom: 1px solid #f3f4f6;">
                                        <div style="font-weight: 500; font-size: 14px;">
                                            <?= htmlspecialchars(substr($activity['module_title'], 0, 20)) ?>
                                            <?php if (strlen($activity['module_title']) > 20): ?>...<?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 8px; border-bottom: 1px solid #f3f4f6;">
                                        <span class="badge <?= $activity['status'] === 'Completed' ? 'badge-success' : 'badge-primary' ?>">
                                            <?= htmlspecialchars($activity['status']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 8px; border-bottom: 1px solid #f3f4f6;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="background: #e5e7eb; height: 4px; width: 40px; border-radius: 2px; overflow: hidden;">
                                                <div style="background: #2563eb; height: 100%; width: <?= $activity['progress_percentage'] ?? 0 ?>%;"></div>
                                            </div>
                                            <span style="font-size: 12px; color: #6b7280;"><?= $activity['progress_percentage'] ?? 0 ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Performance Overview -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Performance Overview</h3>
            <a href="reports.php" class="btn btn-sm btn-primary">Detailed Report</a>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8fafc; border-radius: 8px;">
                    <div>
                        <div style="font-weight: 600; font-size: 14px;">Module Completion</div>
                        <div style="font-size: 12px; color: #6b7280;">This month</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 20px; font-weight: 700; color: #10b981;"><?= $activeStudents ?></div>
                        <div style="font-size: 12px; color: #6b7280;">students</div>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8fafc; border-radius: 8px;">
                    <div>
                        <div style="font-weight: 600; font-size: 14px;">Assessments Given</div>
                        <div style="font-size: 12px; color: #6b7280;">This month</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 20px; font-weight: 700; color: #2563eb;"><?= $assessmentStats['total_attempts'] ?? 0 ?></div>
                        <div style="font-size: 12px; color: #6b7280;">attempts</div>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8fafc; border-radius: 8px;">
                    <div>
                        <div style="font-weight: 600; font-size: 14px;">Avg. Study Time</div>
                        <div style="font-size: 12px; color: #6b7280;">Per student</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 20px; font-weight: 700; color: #f59e0b;">2.5h</div>
                        <div style="font-size: 12px; color: #6b7280;">daily</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions & Module Overview -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Quick Actions</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <a href="my_modules.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px;">
                <i class="fas fa-book"></i>
                <span>Manage Modules</span>
            </a>
            <a href="quizzes.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px;">
                <i class="fas fa-question-circle"></i>
                <span>Create Quiz</span>
            </a>
            <a href="assignments.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px;">
                <i class="fas fa-edit"></i>
                <span>Add Assignment</span>
            </a>
            <a href="my_students.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px;">
                <i class="fas fa-users"></i>
                <span>View Students</span>
            </a>
        </div>
    </div>
</div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
