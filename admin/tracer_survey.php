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
if (!in_array($userType, ['admin', 'support_staff', 'instructional_unit'])) {
    header("Location: ../login.php");
    exit();
}

// Create table if not exists - with error output
$tableExists = false;
try {
    $result = $conn->query("SHOW TABLES LIKE 'tracer_survey_responses'");
    $tableExists = $result->rowCount() > 0;
    
    if (!$tableExists) {
        $conn->exec("CREATE TABLE `tracer_survey_responses` (
            `survey_id` int(11) NOT NULL AUTO_INCREMENT,
            `student_id` int(11) NOT NULL,
            `survey_date` date DEFAULT NULL,
            `status` enum('Pending','Completed','Expired') DEFAULT 'Pending',
            `employment_status` enum('Employed','Self-Employed','Unemployed','Not Seeking','Continuing Education') DEFAULT 'Not Yet Determined',
            `company_name` varchar(255) DEFAULT NULL,
            `company_address` varchar(255) DEFAULT NULL,
            `job_position` varchar(255) DEFAULT NULL,
            `monthly_salary` decimal(12,2) DEFAULT 0,
            `employment_duration_months` int(11) DEFAULT 0,
            `industry` varchar(100) DEFAULT NULL,
            `course_related` varchar(255) DEFAULT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`survey_id`),
            KEY `idx_student` (`student_id`),
            KEY `idx_status` (`status`),
            KEY `idx_employment` (`employment_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
} catch (PDOException $e) {
    // Silently continue if table creation fails
    $tableExists = false;
}

$survey_status = $_GET['status'] ?? 'All';
$search = $_GET['search'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');

// Initialize arrays
$surveys = [];
$years = [];
$totalSurveys = 0;
$employedCount = 0;
$selfEmployedCount = 0;
$unemployedCount = 0;

// Only query if table exists
if ($tableExists) {
    try {
        $where = ["1=1"];
        if ($survey_status !== 'All') {
            $where[] = "tss.status = '$survey_status'";
        }
        if (!empty($search)) {
            $search_escaped = $conn->quote("%$search%");
            $where[] = "(s.FirstName LIKE $search_escaped OR s.LastName LIKE $search_escaped OR s.Email LIKE $search_escaped)";
        }
        if (!empty($year_filter)) {
            $where[] = "YEAR(tss.survey_date) = '$year_filter'";
        }
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $conn->query("
            SELECT tss.*, s.FirstName, s.LastName, s.Email, s.SchoolID, spe.nc_level
            FROM tracer_survey_responses tss
            LEFT JOIN student s ON tss.student_id = s.StudID
            LEFT JOIN student_program_enrollments spe ON s.StudID = spe.student_id
            $whereClause
            ORDER BY tss.survey_date DESC
        ");
        $surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->query("SELECT DISTINCT YEAR(survey_date) as year FROM tracer_survey_responses ORDER BY year DESC");
        $years = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalSurveys = count($surveys);
        $employedCount = count(array_filter($surveys, fn($s) => ($s['employment_status'] ?? '') === 'Employed'));
        $selfEmployedCount = count(array_filter($surveys, fn($s) => ($s['employment_status'] ?? '') === 'Self-Employed'));
        $unemployedCount = count(array_filter($surveys, fn($s) => ($s['employment_status'] ?? '') === 'Unemployed'));
        $notYetCount = count(array_filter($surveys, fn($s) => ($s['employment_status'] ?? '') === 'Not Seeking'));
    } catch (PDOException $e) {
        // Query failed, continue with empty arrays
    }
}

$avgSalary = 0;
$salaries = array_filter(array_map(fn($s) => $s['monthly_salary'] ?? 0, $surveys), fn($s) => $s > 0);
if (!empty($salaries)) {
    $avgSalary = array_sum($salaries) / count($salaries);
}

$pageTitle = "TRACER Survey";
$pageSubtitle = "Graduate Employment Tracking";
$currentPage = "tracer_survey.php";

include 'sidebar_new.php';
?>

<!-- Statistics -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 30px;">
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Total Responses</div>
        <div style="font-size: 28px; font-weight: 700; color: #1e40af;"><?= $totalSurveys ?></div>
    </div>
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Employed</div>
        <div style="font-size: 28px; font-weight: 700; color: #10b981;"><?= $employedCount ?></div>
    </div>
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Self-Employed</div>
        <div style="font-size: 28px; font-weight: 700; color: #8b5cf6;"><?= $selfEmployedCount ?></div>
    </div>
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Unemployed</div>
        <div style="font-size: 28px; font-weight: 700; color: #dc2626;"><?= $unemployedCount ?></div>
    </div>
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Avg. Salary</div>
        <div style="font-size: 28px; font-weight: 700; color: #f59e0b;">₱<?= number_format($avgSalary, 0) ?></div>
    </div>
</div>

<?php if (!$tableExists): ?>
<div class="card" style="margin-bottom: 20px; border: 2px solid #f59e0b;">
    <div class="card-header" style="background: #fef3c7;">
        <h3 class="card-title">⚠️ Database Setup Required</h3>
    </div>
    <div class="card-body">
        <p style="color: #374151; margin-bottom: 16px;">
            The TRACER Survey table doesn't exist. Please run the setup script first.
        </p>
        <a href="setup_tracer_survey_db.php" class="btn" style="padding: 12px 24px; background: #f59e0b; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">
            Run Database Setup
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
    <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email..." style="padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; width: 200px;">
        
        <select name="status" style="padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <option value="All">All Status</option>
            <option value="Completed" <?= $survey_status === 'Completed' ? 'selected' : '' ?>>Completed</option>
            <option value="Pending" <?= $survey_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="Expired" <?= $survey_status === 'Expired' ? 'selected' : '' ?>>Expired</option>
        </select>
        
        <button type="submit" class="btn" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer;">Filter</button>
        
        <?php if ($search || $survey_status !== 'All'): ?>
        <a href="tracer_survey.php" class="btn" style="padding: 10px 20px; background: #64748b; color: white; border-radius: 8px; text-decoration: none;">Clear</a>
        <?php endif; ?>
    </form>
    
    <?php if ($tableExists): ?>
    <a href="add_tracer_survey.php" class="btn" style="padding: 10px 20px; background: #10b981; color: white; border-radius: 8px; text-decoration: none; margin-left: auto;">
        + New Survey
    </a>
    <?php endif; ?>
</div>

<!-- Survey Responses Table -->
<?php if ($tableExists): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Survey Responses (<?= count($surveys) ?>)</h3>
    </div>
    <div class="card-body" style="padding: 0; overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; min-width: 900px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Student</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Student ID</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">NC Level</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Employment</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Company</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Salary</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Status</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($surveys as $survey): 
                    $empStatus = $survey['employment_status'] ?? 'Not Yet Determined';
                ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 14px 16px; font-weight: 600;">
                        <?= htmlspecialchars(($survey['FirstName'] ?? '') . ' ' . ($survey['LastName'] ?? '')) ?>
                    </td>
                    <td style="padding: 14px 16px;"><?= htmlspecialchars($survey['SchoolID'] ?? '-') ?></td>
                    <td style="padding: 14px 16px;">
                        <span class="badge badge-purple"><?= $survey['nc_level'] ?? '-' ?></span>
                    </td>
                    <td style="padding: 14px 16px;">
                        <?php 
                        $empBadge = [
                            'Employed' => 'badge-green',
                            'Self-Employed' => 'badge-blue',
                            'Unemployed' => 'badge-red',
                            'Not Seeking' => 'badge-orange'
                        ][$empStatus] ?? 'badge-gray';
                        ?>
                        <span class="badge <?= $empBadge ?>"><?= $empStatus ?></span>
                    </td>
                    <td style="padding: 14px 16px;"><?= htmlspecialchars($survey['company_name'] ?? '-') ?></td>
                    <td style="padding: 14px 16px;">
                        <?= $survey['monthly_salary'] > 0 ? '₱' . number_format($survey['monthly_salary']) : '-' ?>
                    </td>
                    <td style="padding: 14px 16px;">
                        <span class="badge <?= ($survey['status'] ?? 'Pending') === 'Completed' ? 'badge-green' : 'badge-orange' ?>">
                            <?= $survey['status'] ?? 'Pending' ?>
                        </span>
                    </td>
                    <td style="padding: 14px 16px; color: #64748b; font-size: 13px;">
                        <?= $survey['survey_date'] ? date('M d, Y', strtotime($survey['survey_date'])) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($surveys)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: #64748b;">
                        No survey responses found. Start a new survey to track graduate employment.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

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