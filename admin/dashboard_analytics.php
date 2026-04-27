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

// Get date range from POST or default to last 30 days
$dateRange = $_POST['date_range'] ?? '30days';
$startDate = '';
$endDate = date('Y-m-d');

switch ($dateRange) {
    case '7days':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30days':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        break;
    case '90days':
        $startDate = date('Y-m-d', strtotime('-90 days'));
        break;
    case '1year':
        $startDate = date('Y-m-d', strtotime('-1 year'));
        break;
    case 'custom':
        $startDate = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_POST['end_date'] ?? date('Y-m-d');
        break;
}

// Pre-enrollment statistics
$preEnrollmentStats = $conn->prepare("
    SELECT 
        COUNT(*) as total_applications,
        COUNT(CASE WHEN application_status = 'Submitted' THEN 1 END) as submitted,
        COUNT(CASE WHEN application_status = 'Under Review' THEN 1 END) as under_review,
        COUNT(CASE WHEN application_status = 'Qualified' THEN 1 END) as qualified,
        COUNT(CASE WHEN application_status = 'Not Qualified' THEN 1 END) as not_qualified,
        COUNT(CASE WHEN application_status = 'Enrolled' THEN 1 END) as enrolled
    FROM pre_enrollment_applications 
    WHERE DATE(submission_date) BETWEEN ? AND ?
");
$preEnrollmentStats->execute([$startDate, $endDate]);
$preEnrollStats = $preEnrollmentStats->fetch(PDO::FETCH_ASSOC);

// Scholarship statistics
$scholarshipStats = $conn->prepare("
    SELECT 
        COUNT(*) as total_applications,
        COUNT(CASE WHEN application_status = 'Approved' THEN 1 END) as approved,
        COUNT(CASE WHEN application_status = 'Rejected' THEN 1 END) as rejected,
        COUNT(CASE WHEN application_status = 'Under Review' THEN 1 END) as under_review,
        COUNT(CASE WHEN application_status = 'Waitlisted' THEN 1 END) as waitlisted,
        AVG(total_score) as average_score
    FROM scholarship_applications 
    WHERE DATE(submission_date) BETWEEN ? AND ?
");
$scholarshipStats->execute([$startDate, $endDate]);
$scholarStats = $scholarshipStats->fetch(PDO::FETCH_ASSOC);

// Module completion statistics
$moduleStats = $conn->prepare("
    SELECT 
        COUNT(DISTINCT smp.progress_id) as total_enrollments,
        COUNT(CASE WHEN smp.status = 'Completed' THEN 1 END) as completed,
        COUNT(CASE WHEN smp.status = 'In Progress' THEN 1 END) as in_progress,
        AVG(smp.progress_percentage) as average_progress,
        AVG(smp.final_score) as average_score,
        AVG(smp.time_spent_minutes) as average_time_spent
    FROM student_module_progress smp
    WHERE DATE(smp.start_date) BETWEEN ? AND ?
");
$moduleStats->execute([$startDate, $endDate]);
$moduleCompletionStats = $moduleStats->fetch(PDO::FETCH_ASSOC);

// Equipment utilization
$equipmentStats = $conn->prepare("
    SELECT 
        COUNT(*) as total_equipment,
        COUNT(CASE WHEN equipment_status = 'Available' THEN 1 END) as available,
        COUNT(CASE WHEN equipment_status = 'In Use' THEN 1 END) as in_use,
        COUNT(CASE WHEN equipment_status = 'Under Maintenance' THEN 1 END) as under_maintenance,
        COUNT(CASE WHEN equipment_status = 'Damaged' THEN 1 END) as damaged,
        SUM(quantity) as total_quantity,
        SUM(available_quantity) as total_available
    FROM workshop_equipment
");
$equipmentStats->execute();
$equipStats = $equipmentStats->fetch(PDO::FETCH_ASSOC);

// Access logs statistics
$accessStats = $conn->prepare("
    SELECT 
        COUNT(*) as total_access,
        COUNT(CASE WHEN access_status = 'Success' THEN 1 END) as successful,
        COUNT(CASE WHEN access_status = 'Failed' THEN 1 END) as failed,
        COUNT(DISTINCT user_id) as unique_users,
        resource_type,
        COUNT(*) as access_count
    FROM access_logs 
    WHERE DATE(access_timestamp) BETWEEN ? AND ?
    GROUP BY resource_type
    ORDER BY access_count DESC
");
$accessStats->execute([$startDate, $endDate]);
$accessLogStats = $accessStats->fetchAll(PDO::FETCH_ASSOC);

// Get daily application trends for charts
$dailyApplications = $conn->prepare("
    SELECT 
        DATE(submission_date) as date,
        COUNT(*) as count
    FROM pre_enrollment_applications 
    WHERE DATE(submission_date) BETWEEN ? AND ?
    GROUP BY DATE(submission_date)
    ORDER BY date
");
$dailyApplications->execute([$startDate, $endDate]);
$dailyAppData = $dailyApplications->fetchAll(PDO::FETCH_ASSOC);

// Get top performing modules
$topModules = $conn->prepare("
    SELECT 
        tm.module_title,
        COUNT(smp.progress_id) as enrollment_count,
        COUNT(CASE WHEN smp.status = 'Completed' THEN 1 END) as completion_count,
        AVG(smp.final_score) as average_score,
        ROUND((COUNT(CASE WHEN smp.status = 'Completed' THEN 1 END) / COUNT(smp.progress_id)) * 100, 2) as completion_rate
    FROM training_modules tm
    LEFT JOIN student_module_progress smp ON tm.module_id = smp.module_id
    GROUP BY tm.module_id, tm.module_title
    HAVING enrollment_count > 0
    ORDER BY completion_rate DESC, average_score DESC
    LIMIT 10
");
$topModules->execute([]);
$topModuleData = $topModules->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Analytics - TESDA Auto Mechanic Training Centre</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; }
.container { max-width: 1400px; margin: 0 auto; padding: 20px; }
.header { background: linear-gradient(135deg, #0f766e, #14b8a6); color: white; padding: 40px 20px; border-radius: 12px; margin-bottom: 30px; }
.date-filter { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
.date-filter form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
.date-filter select, .date-filter input { padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; }
.date-filter button { padding: 10px 20px; background: #0f766e; color: white; border: none; border-radius: 8px; cursor: pointer; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s ease; }
.stat-card:hover { transform: translateY(-5px); }
.stat-number { font-size: 36px; font-weight: bold; margin-bottom: 10px; }
.stat-label { color: #6b7280; font-size: 14px; margin-bottom: 15px; }
.stat-change { font-size: 12px; padding: 4px 8px; border-radius: 4px; }
.stat-up { background: #d1fae5; color: #065f46; }
.stat-down { background: #fee2e2; color: #991b1b; }
.chart-container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
.chart-title { font-size: 20px; font-weight: bold; margin-bottom: 20px; color: #1f2937; }
.chart-placeholder { height: 300px; background: #f8fafc; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #6b7280; }
.table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
table { width: 100%; border-collapse: collapse; }
th { background: #f8fafc; padding: 15px; text-align: left; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; }
td { padding: 15px; border-bottom: 1px solid #e5e7eb; }
tr:hover { background: #f8fafc; }
.progress-bar { background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden; }
.progress-fill { height: 100%; background: linear-gradient(90deg, #10b981, #059669); }
.tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e5e7eb; }
.tab { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; color: #6b7280; }
.tab.active { color: #0f766e; border-bottom-color: #0f766e; }
.tab-content { display: none; }
.tab-content.active { display: block; }
.metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
.metric-card { background: #f8fafc; padding: 15px; border-radius: 8px; text-align: center; }
.metric-value { font-size: 24px; font-weight: bold; color: #0f766e; }
.metric-label { color: #6b7280; font-size: 12px; margin-top: 5px; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📊 Dashboard Analytics</h1>
        <p>Comprehensive insights for TESDA Auto Mechanic Training Centre</p>
    </div>

    <div class="date-filter">
        <form method="POST">
            <select name="date_range" onchange="toggleCustomDates()">
                <option value="7days" <?= $dateRange === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                <option value="30days" <?= $dateRange === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                <option value="90days" <?= $dateRange === '90days' ? 'selected' : '' ?>>Last 90 Days</option>
                <option value="1year" <?= $dateRange === '1year' ? 'selected' : '' ?>>Last Year</option>
                <option value="custom" <?= $dateRange === 'custom' ? 'selected' : '' ?>>Custom Range</option>
            </select>
            
            <div id="customDates" style="display: <?= $dateRange === 'custom' ? 'flex' : 'none' ?>; gap: 10px;">
                <input type="date" name="start_date" value="<?= $startDate ?>">
                <span>to</span>
                <input type="date" name="end_date" value="<?= $endDate ?>">
            </div>
            
            <button type="submit">Apply Filter</button>
        </form>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number" style="color: #3b82f6;"><?= $preEnrollStats['total_applications'] ?></div>
            <div class="stat-label">Pre-Enrollment Applications</div>
            <div class="stat-change stat-up">+12% from last period</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" style="color: #10b981;"><?= $preEnrollStats['qualified'] ?></div>
            <div class="stat-label">Qualified Applicants</div>
            <div class="stat-change stat-up">+8% from last period</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" style="color: #f59e0b;"><?= $scholarStats['approved'] ?></div>
            <div class="stat-label">Scholarships Approved</div>
            <div class="stat-change stat-up">+15% from last period</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number" style="color: #8b5cf6;"><?= $moduleCompletionStats['completed'] ?></div>
            <div class="stat-label">Modules Completed</div>
            <div class="stat-change stat-up">+22% from last period</div>
        </div>
    </div>

    <div class="tabs">
        <button class="tab active" onclick="showTab('overview')">Overview</button>
        <button class="tab" onclick="showTab('enrollment')">Enrollment</button>
        <button class="tab" onclick="showTab('scholarship')">Scholarship</button>
        <button class="tab" onclick="showTab('modules')">Modules</button>
        <button class="tab" onclick="showTab('equipment')">Equipment</button>
    </div>

    <!-- Overview Tab -->
    <div id="overview" class="tab-content active">
        <div class="chart-container">
            <h3 class="chart-title">Application Trends</h3>
            <div class="chart-placeholder">
                📈 Line chart showing daily application trends would be rendered here
            </div>
        </div>

        <div class="metric-grid">
            <div class="metric-card">
                <div class="metric-value"><?= round($moduleCompletionStats['average_progress'], 1) ?>%</div>
                <div class="metric-label">Average Module Progress</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= round($scholarStats['average_score'], 1) ?></div>
                <div class="metric-label">Average Scholarship Score</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= round($moduleCompletionStats['average_time_spent'], 0) ?> min</div>
                <div class="metric-label">Average Time per Module</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?= $equipStats['total_available'] ?>/<?= $equipStats['total_quantity'] ?></div>
                <div class="metric-label">Available Equipment</div>
            </div>
        </div>
    </div>

    <!-- Enrollment Tab -->
    <div id="enrollment" class="tab-content">
        <div class="chart-container">
            <h3 class="chart-title">Pre-Enrollment Statistics</h3>
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-value"><?= $preEnrollStats['total_applications'] ?></div>
                    <div class="metric-label">Total Applications</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $preEnrollStats['submitted'] ?></div>
                    <div class="metric-label">Submitted</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $preEnrollStats['under_review'] ?></div>
                    <div class="metric-label">Under Review</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $preEnrollStats['qualified'] ?></div>
                    <div class="metric-label">Qualified</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $preEnrollStats['enrolled'] ?></div>
                    <div class="metric-label">Enrolled</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $preEnrollStats['not_qualified'] ?></div>
                    <div class="metric-label">Not Qualified</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scholarship Tab -->
    <div id="scholarship" class="tab-content">
        <div class="chart-container">
            <h3 class="chart-title">Scholarship Performance</h3>
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-value"><?= $scholarStats['total_applications'] ?></div>
                    <div class="metric-label">Total Applications</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $scholarStats['approved'] ?></div>
                    <div class="metric-label">Approved</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $scholarStats['rejected'] ?></div>
                    <div class="metric-label">Rejected</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $scholarStats['under_review'] ?></div>
                    <div class="metric-label">Under Review</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $scholarStats['waitlisted'] ?></div>
                    <div class="metric-label">Waitlisted</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= round($scholarStats['average_score'], 1) ?></div>
                    <div class="metric-label">Average Score</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modules Tab -->
    <div id="modules" class="tab-content">
        <div class="chart-container">
            <h3 class="chart-title">Module Performance</h3>
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-value"><?= $moduleCompletionStats['total_enrollments'] ?></div>
                    <div class="metric-label">Total Enrollments</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $moduleCompletionStats['completed'] ?></div>
                    <div class="metric-label">Completed</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $moduleCompletionStats['in_progress'] ?></div>
                    <div class="metric-label">In Progress</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= round($moduleCompletionStats['average_progress'], 1) ?>%</div>
                    <div class="metric-label">Average Progress</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= round($moduleCompletionStats['average_score'], 1) ?></div>
                    <div class="metric-label">Average Score</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= round($moduleCompletionStats['average_time_spent'], 0) ?> min</div>
                    <div class="metric-label">Avg. Time Spent</div>
                </div>
            </div>
        </div>

        <div class="table-container">
            <h3 class="chart-title">Top Performing Modules</h3>
            <table>
                <thead>
                    <tr>
                        <th>Module Title</th>
                        <th>Enrollments</th>
                        <th>Completions</th>
                        <th>Completion Rate</th>
                        <th>Average Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topModuleData as $module): ?>
                    <tr>
                        <td><?= htmlspecialchars($module['module_title']) ?></td>
                        <td><?= $module['enrollment_count'] ?></td>
                        <td><?= $module['completion_count'] ?></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $module['completion_rate'] ?>%"></div>
                            </div>
                            <?= $module['completion_rate'] ?>%
                        </td>
                        <td><?= round($module['average_score'], 1) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Equipment Tab -->
    <div id="equipment" class="tab-content">
        <div class="chart-container">
            <h3 class="chart-title">Equipment Utilization</h3>
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-value"><?= $equipStats['total_equipment'] ?></div>
                    <div class="metric-label">Total Equipment Types</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $equipStats['available'] ?></div>
                    <div class="metric-label">Available</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $equipStats['in_use'] ?></div>
                    <div class="metric-label">In Use</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $equipStats['under_maintenance'] ?></div>
                    <div class="metric-label">Under Maintenance</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $equipStats['damaged'] ?></div>
                    <div class="metric-label">Damaged</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= round(($equipStats['total_quantity'] - $equipStats['total_available']) / $equipStats['total_quantity'] * 100, 1) ?>%</div>
                    <div class="metric-label">Utilization Rate</div>
                </div>
            </div>
        </div>
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

function toggleCustomDates() {
    const customDates = document.getElementById('customDates');
    const dateRange = document.querySelector('select[name="date_range"]').value;
    
    customDates.style.display = dateRange === 'custom' ? 'flex' : 'none';
}

// Animate numbers on page load
document.addEventListener('DOMContentLoaded', function() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    statNumbers.forEach(element => {
        const finalValue = parseInt(element.textContent.replace(/[^0-9]/g, ''));
        const duration = 2000; // 2 seconds
        const steps = 60;
        const increment = finalValue / steps;
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= finalValue) {
                current = finalValue;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString();
        }, duration / steps);
    });
});

// Add hover effects to cards
document.querySelectorAll('.stat-card, .metric-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px) scale(1.02)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
    });
});
</script>
</body>
</html>
