<?php
/**
 * LMS Modules - Enhanced Learning Management System
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

$search = $_GET['search'] ?? '';
$nc_filter = $_GET['nc_level'] ?? 'All';
$type_filter = $_GET['module_type'] ?? 'All';
$status_filter = $_GET['status'] ?? 'All';

$where = ["lm.is_active = 1"];
if (!empty($search)) {
    $search_escaped = $conn->quote("%$search%");
    $where[] = "(lm.module_title LIKE $search_escaped OR lm.module_description LIKE $search_escaped)";
}
if ($nc_filter !== 'All') {
    $where[] = "nls.nc_level = '$nc_filter'";
}
if ($type_filter !== 'All') {
    $where[] = "lm.module_type = '$type_filter'";
}
if ($status_filter !== 'All') {
    $where[] = "lm.status = '$status_filter'";
}
$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$modules = $conn->query("
    SELECT lm.*, nls.nc_level, nls.sort_order
    FROM learning_modules lm
    LEFT JOIN nc_level_subjects nls ON lm.module_id = nls.module_id
    $whereClause
    ORDER BY lm.sort_order ASC
")->fetchAll(PDO::FETCH_ASSOC);

$nc_levels = ['NC I', 'NC II', 'NC III', 'NC IV'];
$module_types = ['Theory', 'Practical', 'Assessment', 'Demo'];

$totalModules = count($modules);
$totalDuration = array_sum(array_column($modules, 'duration_mins'));

$stmt = $conn->query("
    SELECT lm.module_id, lm.module_title, 
           COUNT(DISTINCT smp.enrollment_id) as enrolled_count,
           SUM(CASE WHEN smp.status = 'Completed' THEN 1 ELSE 0 END) as completed_count
    FROM learning_modules lm
    LEFT JOIN student_module_progress smp ON lm.module_id = smp.module_id
    WHERE lm.is_active = 1
    GROUP BY lm.module_id, lm.module_title
");
$moduleStats = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $moduleStats[$row['module_id']] = $row;
}

$activeStudents = $conn->query("SELECT COUNT(DISTINCT student_id) as cnt FROM student_program_enrollments WHERE enrollment_status = 'Active'")->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;

$pageTitle = "Learning Modules";
$pageSubtitle = "LMS - Manage & Track Training Content";
$currentPage = "lms_modules.php";

include 'sidebar_new.php';
?>

<!-- Statistics -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px;">
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Total Modules</div>
        <div style="font-size: 28px; font-weight: 700; color: #1e40af;"><?= $totalModules ?></div>
    </div>
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Total Duration</div>
        <div style="font-size: 28px; font-weight: 700; color: #10b981;"><?= round($totalDuration / 60) ?>h</div>
    </div>
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Active Students</div>
        <div style="font-size: 28px; font-weight: 700; color: #f59e0b;"><?= $activeStudents ?></div>
    </div>
    <div class="stat-card" style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">NC Levels</div>
        <div style="font-size: 28px; font-weight: 700; color: #8b5cf6;"><?= count($nc_levels) ?></div>
    </div>
</div>

<!-- Filters -->
<div style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
    <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search modules..." style="padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; width: 200px;">
        
        <select name="nc_level" style="padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <option value="All">All NC Levels</option>
            <?php foreach ($nc_levels as $level): ?>
            <option value="<?= $level ?>" <?= $nc_filter === $level ? 'selected' : '' ?>><?= $level ?></option>
            <?php endforeach; ?>
        </select>
        
        <select name="module_type" style="padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <option value="All">All Types</option>
            <?php foreach ($module_types as $type): ?>
            <option value="<?= $type ?>" <?= $type_filter === $type ? 'selected' : '' ?>><?= $type ?></option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit" class="btn" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer;">Filter</button>
        
        <?php if ($search || $nc_filter !== 'All' || $type_filter !== 'All'): ?>
        <a href="lms_modules.php" class="btn" style="padding: 10px 20px; background: #64748b; color: white; border-radius: 8px; text-decoration: none;">Clear</a>
        <?php endif; ?>
    </form>
    
    <a href="add_module.php" class="btn" style="padding: 10px 20px; background: #10b981; color: white; border-radius: 8px; text-decoration: none; margin-left: auto;">
        + Add Module
    </a>
</div>

<!-- Modules Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
    <?php foreach ($modules as $mod): 
        $stats = $moduleStats[$mod['module_id']] ?? ['enrolled_count' => 0, 'completed_count' => 0];
        $ncLevel = $mod['nc_level'] ?? 'General';
    ?>
    <div class="card" style="overflow: hidden;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: flex-start; padding: 16px 20px;">
            <div style="flex: 1;">
                <h3 class="card-title" style="font-size: 16px; margin-bottom: 4px;"><?= htmlspecialchars($mod['module_title']) ?></h3>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <span class="badge badge-blue"><?= $mod['module_type'] ?? 'Theory' ?></span>
                    <span class="badge badge-purple"><?= $ncLevel ?></span>
                    <span class="badge <?= ($mod['status'] ?? 'active') === 'active' ? 'badge-green' : 'badge-gray' ?>">
                        <?= ($mod['status'] ?? 'active') === 'active' ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 24px; font-weight: 700; color: #2563eb;"><?= $mod['duration_mins'] ?></div>
                <div style="font-size: 11px; color: #64748b;">minutes</div>
            </div>
        </div>
        
        <div class="card-body" style="padding: 16px 20px;">
            <p style="color: #64748b; font-size: 13px; margin-bottom: 16px; line-height: 1.5;">
                <?= htmlspecialchars($mod['module_description'] ?? 'No description available') ?>
            </p>
            
            <!-- Progress Stats -->
            <div style="display: flex; gap: 20px; padding: 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 16px;">
                <div style="flex: 1; text-align: center;">
                    <div style="font-size: 18px; font-weight: 700; color: #2563eb;"><?= $stats['enrolled_count'] ?></div>
                    <div style="font-size: 11px; color: #64748b;">Enrolled</div>
                </div>
                <div style="width: 1px; background: #e2e8f0;"></div>
                <div style="flex: 1; text-align: center;">
                    <div style="font-size: 18px; font-weight: 700; color: #10b981;"><?= $stats['completed_count'] ?></div>
                    <div style="font-size: 11px; color: #64748b;">Completed</div>
                </div>
                <div style="width: 1px; background: #e2e8f0;"></div>
                <div style="flex: 1; text-align: center;">
                    <?php $progress = $stats['enrolled_count'] > 0 ? round(($stats['completed_count'] / $stats['enrolled_count']) * 100) : 0; ?>
                    <div style="font-size: 18px; font-weight: 700; color: #f59e0b;"><?= $progress ?>%</div>
                    <div style="font-size: 11px; color: #64748b;">Progress</div>
                </div>
            </div>
            
            <!-- Actions -->
            <div style="display: flex; gap: 8px;">
                <a href="view_module.php?id=<?= $mod['module_id'] ?>" class="btn" style="flex: 1; padding: 10px; background: #f1f5f9; color: #374151; border-radius: 8px; text-decoration: none; text-align: center; font-size: 13px;">
                    View Details
                </a>
                <a href="edit_module.php?id=<?= $mod['module_id'] ?>" class="btn" style="flex: 1; padding: 10px; background: #2563eb; color: white; border-radius: 8px; text-decoration: none; text-align: center; font-size: 13px;">
                    Edit
                </a>
                <a href="module_content.php?id=<?= $mod['module_id'] ?>" class="btn" style="flex: 1; padding: 10px; background: #10b981; color: white; border-radius: 8px; text-decoration: none; text-align: center; font-size: 13px;">
                    Content
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($modules)): ?>
    <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div style="font-size: 48px; margin-bottom: 16px;">📚</div>
        <h3 style="color: #374151; margin-bottom: 8px;">No Modules Found</h3>
        <p style="color: #64748b; margin-bottom: 20px;">No modules match your search criteria.</p>
        <a href="add_module.php" class="btn" style="padding: 12px 24px; background: #2563eb; color: white; border-radius: 8px; text-decoration: none;">
            Create First Module
        </a>
    </div>
    <?php endif; ?>
</div>

</main>
</div>

<style>
.stats-grid { margin-bottom: 30px; }
@media (max-width: 900px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
}
@media (max-width: 500px) {
    .stats-grid { grid-template-columns: 1fr !important; }
}
</style>

</body>
</html>