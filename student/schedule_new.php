<?php
/**
 * Advanced Schedule Page - Calendar View, List View, and More Features
 * Enhanced with NC level filtering and multiple display options
 */
include __DIR__ . '/../db.php';
$database = new Database();
$conn = $database->getConnection();

session_start();

if (isset($_SESSION['userRole']) && $_SESSION['userRole'] === 'student') {
    $_SESSION['userRole'] = 'trainee';
}

// Set page info for sidebar
$currentPage = 'schedule_new.php';
$pageTitle = 'Schedule';
$pageSubtitle = 'Training Calendar & Schedule';

// Check login
if (!isset($_SESSION['userId']) || !in_array($_SESSION['userRole'], ['trainee', 'student'])) {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION['userId'] ?? $_SESSION['user_id'] ?? null;

// Get student's NC level
$studentNcLevel = 'NC I';
try {
    $ncStmt = $conn->prepare("
        SELECT spe.nc_level 
        FROM student_program_enrollments spe
        WHERE spe.student_id = (SELECT StudID FROM student WHERE user_id = ? LIMIT 1)
        AND spe.enrollment_status = 'Active'
        ORDER BY spe.enrollment_id DESC LIMIT 1
    ");
    $ncStmt->execute([$userId]);
    $studentNcLevel = $ncStmt->fetchColumn() ?: 'NC I';
} catch (Exception $e) {
    $studentNcLevel = 'NC I';
}

// Get schedules filtered by NC level
$schedules = [];
try {
    $stmt = $conn->prepare("
        SELECT s.*, lm.module_title, lm.module_type, lm.nc_level
        FROM schedules s
        LEFT JOIN learning_modules lm ON s.module_id = lm.module_id
        WHERE (lm.nc_level = ? OR s.nc_level = ? OR s.nc_level IS NULL OR s.nc_level = '')
        ORDER BY s.day_of_week, s.start_time
    ");
    $stmt->execute([$studentNcLevel, $studentNcLevel]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $schedules = [];
}

// Group schedules by day
$schedulesByDay = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
foreach ($days as $day) {
    $schedulesByDay[$day] = array_filter($schedules, function($s) use ($day) {
        return ($s['day_of_week'] ?? $s['day'] ?? '') === $day;
    });
}

include 'sidebar_student.php';
?>

<style>
.schedule-view-toggle {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}
.view-btn {
    padding: 10px 20px;
    background: #e2e8f0;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}
.view-btn.active {
    background: #2563eb;
    color: white;
}
.view-btn:hover:not(.active) {
    background: #cbd5e1;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
    margin-top: 20px;
}
.calendar-day {
    background: white;
    border-radius: 12px;
    padding: 15px;
    min-height: 150px;
    border: 1px solid #e2e8f0;
}
.calendar-day-header {
    font-weight: bold;
    color: #1e40af;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 2px solid #2563eb;
}
.schedule-item {
    background: #eff6ff;
    border-left: 3px solid #2563eb;
    padding: 10px;
    margin-bottom: 8px;
    border-radius: 6px;
    font-size: 13px;
}
.schedule-item .time {
    color: #2563eb;
    font-weight: bold;
    font-size: 12px;
}
.schedule-item .module {
    color: #1e293b;
    font-weight: 600;
    margin: 4px 0;
}
.schedule-item .type {
    color: #64748b;
    font-size: 11px;
}
.schedule-item.empty {
    background: #f8fafc;
    border-left: 3px solid #cbd5e1;
    color: #94a3b8;
    font-style: italic;
}

.list-view {
    background: white;
    border-radius: 12px;
    overflow: hidden;
}
.list-header {
    display: grid;
    grid-template-columns: 100px 1fr 150px 100px 80px;
    background: #f8fafc;
    padding: 15px 20px;
    font-weight: bold;
    color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
}
.list-row {
    display: grid;
    grid-template-columns: 100px 1fr 150px 100px 80px;
    padding: 15px 20px;
    border-bottom: 1px solid #f1f5f9;
    align-items: center;
}
.list-row:hover {
    background: #f8fafc;
}

.nc-badge {
    background: #fef3c7;
    color: #92400e;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
</style>

<!-- View Toggle -->
<div class="schedule-view-toggle">
    <button class="view-btn active" onclick="showView('calendar')">
        <i class="fas fa-calendar-alt"></i> Calendar View
    </button>
    <button class="view-btn" onclick="showView('list')">
        <i class="fas fa-list"></i> List View
    </button>
</div>

<!-- Calendar View -->
<div id="calendarView" class="calendar-grid">
    <?php foreach ($days as $day): ?>
    <div class="calendar-day">
        <div class="calendar-day-header"><?= $day ?></div>
        <?php 
        $daySchedules = $schedulesByDay[$day] ?? [];
        if (empty($daySchedules)): 
        ?>
            <div class="schedule-item empty">No class</div>
        <?php else: ?>
            <?php foreach ($daySchedules as $s): ?>
            <div class="schedule-item">
                <div class="time">
                    <i class="fas fa-clock"></i> 
                    <?= date('g:i A', strtotime($s['start_time'] ?? '00:00:00')) ?> - <?= date('g:i A', strtotime($s['end_time'] ?? '00:00:00')) ?>
                </div>
                <div class="module"><?= htmlspecialchars($s['module_title'] ?? $s['subject'] ?? 'Training') ?></div>
                <div class="type"><?= htmlspecialchars($s['module_type'] ?? 'Core') ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- List View -->
<div id="listView" class="list-view" style="display: none;">
    <div class="list-header">
        <div>Day</div>
        <div>Subject/Module</div>
        <div>Time</div>
        <div>Type</div>
        <div>Room</div>
    </div>
    <?php foreach ($schedules as $s): ?>
    <div class="list-row">
        <div><?= $s['day_of_week'] ?? $s['day'] ?? '-' ?></div>
        <div><strong><?= htmlspecialchars($s['module_title'] ?? $s['subject'] ?? 'Training') ?></strong></div>
        <div><?= date('g:i A', strtotime($s['start_time'] ?? '00:00:00')) ?> - <?= date('g:i A', strtotime($s['end_time'] ?? '00:00:00')) ?></div>
        <div><span class="nc-badge"><?= htmlspecialchars($s['module_type'] ?? 'Core') ?></span></div>
        <div><?= htmlspecialchars($s['room'] ?? $s['room_number'] ?? 'TBA') ?></div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($schedules)): ?>
    <div class="list-row">
        <div colspan="5" style="text-align: center; padding: 40px; color: #64748b;">
            <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 10px;"></i>
            <p>No schedules found for your NC level (<?= $studentNcLevel ?>)</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function showView(view) {
    document.getElementById('calendarView').style.display = view === 'calendar' ? 'grid' : 'none';
    document.getElementById('listView').style.display = view === 'list' ? 'block' : 'none';
    
    document.querySelectorAll('.view-btn').forEach(function(btn) { btn.classList.remove('active'); });
    event.target.classList.add('active');
}
</script>

<!-- Page content ends here - sidebar_student.php provides closing -->