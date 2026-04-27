<?php
/**
 * View Module Details - Enhanced Module Viewer
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

$moduleId = $_GET['id'] ?? 0;

if (!$moduleId) {
    header("Location: lms_modules.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM learning_modules WHERE module_id = ?");
$stmt->execute([$moduleId]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    die("Module not found");
}

$ncLevel = $module['nc_level'] ?? 'NC I';

$stmt = $conn->prepare("
    SELECT smp.*, s.FirstName, s.LastName, u.email
    FROM student_module_progress smp
    JOIN student_program_enrollments spe ON smp.enrollment_id = spe.enrollment_id
    JOIN student s ON spe.student_id = s.StudID
    LEFT JOIN users u ON s.user_id = u.user_id
    WHERE smp.module_id = ?
    ORDER BY smp.last_access_date DESC
    LIMIT 20
");
$stmt->execute([$moduleId]);
$progressRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM quizzes WHERE module_id = ? OR nc_level = ? ORDER BY created_at DESC");
$stmt->execute([$moduleId, $ncLevel]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM assignments WHERE module_id = ? OR nc_level = ? ORDER BY due_date ASC");
$stmt->execute([$moduleId, $ncLevel]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM learning_materials WHERE module_id = ? OR nc_level = ? ORDER BY created_at DESC");
$stmt->execute([$moduleId, $ncLevel]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM nc_level_subjects WHERE module_id = ?");
$stmt->execute([$moduleId]);
$ncMapping = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalEnrolled = count($progressRecords);
$completedCount = count(array_filter($progressRecords, fn($p) => ($p['status'] ?? '') === 'Completed'));
$inProgressCount = count(array_filter($progressRecords, fn($p) => ($p['status'] ?? '') === 'In Progress'));
$notStartedCount = $totalEnrolled - $completedCount - $inProgressCount;

$avgProgress = 0;
if (!empty($progressRecords)) {
    $avgProgress = round(array_sum(array_column($progressRecords, 'progress_percentage')) / count($progressRecords));
}

$pageTitle = "Module Details";
$pageSubtitle = htmlspecialchars($module['module_title']);
$currentPage = "lms_modules.php";

include 'sidebar_new.php';
?>

<!-- Module Header -->
<div style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 300px;">
        <div class="card">
            <div class="card-header">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <h3 class="card-title" style="font-size: 20px; margin-bottom: 8px;"><?= htmlspecialchars($module['module_title']) ?></h3>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <span class="badge badge-blue"><?= $module['module_type'] ?? 'Theory' ?></span>
                            <span class="badge badge-purple"><?= $ncLevel ?></span>
                            <span class="badge <?= ($module['is_active'] ?? 1) ? 'badge-green' : 'badge-gray' ?>">
                                <?= ($module['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 32px; font-weight: 700; color: #2563eb;"><?= $module['duration_mins'] ?></div>
                        <div style="font-size: 12px; color: #64748b;">minutes</div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <p style="color: #64748b; line-height: 1.6; margin-bottom: 20px;">
                    <?= htmlspecialchars($module['module_description'] ?? 'No description available') ?>
                </p>
                
                <div style="display: flex; gap: 12px;">
                    <a href="edit_module.php?id=<?= $moduleId ?>" class="btn" style="padding: 10px 20px; background: #2563eb; color: white; border-radius: 8px; text-decoration: none;">
                        Edit Module
                    </a>
                    <a href="module_content.php?id=<?= $moduleId ?>" class="btn" style="padding: 10px 20px; background: #10b981; color: white; border-radius: 8px; text-decoration: none;">
                        Manage Content
                    </a>
                    <a href="lms_modules.php" class="btn" style="padding: 10px 20px; background: #f1f5f9; color: #374151; border-radius: 8px; text-decoration: none;">
                        Back to Modules
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Card -->
    <div style="width: 280px;">
        <div class="card" style="height: 100%;">
            <div class="card-header">
                <h3 class="card-title">Progress Overview</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 20px;">
                    <div style="text-align: center; padding: 16px; background: #f8fafc; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: 700; color: #2563eb;"><?= $totalEnrolled ?></div>
                        <div style="font-size: 11px; color: #64748b;">Enrolled</div>
                    </div>
                    <div style="text-align: center; padding: 16px; background: #f8fafc; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: 700; color: #10b981;"><?= $completedCount ?></div>
                        <div style="font-size: 11px; color: #64748b;">Completed</div>
                    </div>
                    <div style="text-align: center; padding: 16px; background: #f8fafc; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: 700; color: #f59e0b;"><?= $inProgressCount ?></div>
                        <div style="font-size: 11px; color: #64748b;">In Progress</div>
                    </div>
                    <div style="text-align: center; padding: 16px; background: #f8fafc; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: 700; color: #64748b;"><?= $notStartedCount ?></div>
                        <div style="font-size: 11px; color: #64748b;">Not Started</div>
                    </div>
                </div>
                
                <div style="margin-top: 10px;">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                        <span style="color: #64748b;">Average Progress</span>
                        <span style="font-weight: 600; color: #2563eb;"><?= $avgProgress ?>%</span>
                    </div>
                    <div style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                        <div style="width: <?= $avgProgress ?>%; height: 100%; background: linear-gradient(90deg, #2563eb, #10b981); border-radius: 4px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; overflow-x: auto;">
    <a href="#students" class="btn" style="padding: 10px 20px; background: #2563eb; color: white; border-radius: 8px 8px 0 0; text-decoration: none; font-size: 14px;">
        Students (<?= $totalEnrolled ?>)
    </a>
    <a href="#quizzes" class="btn" style="padding: 10px 20px; background: #f1f5f9; color: #374151; border-radius: 8px 8px 0 0; text-decoration: none; font-size: 14px;">
        Quizzes (<?= count($quizzes) ?>)
    </a>
    <a href="#assignments" class="btn" style="padding: 10px 20px; background: #f1f5f9; color: #374151; border-radius: 8px 8px 0 0; text-decoration: none; font-size: 14px;">
        Assignments (<?= count($assignments) ?>)
    </a>
    <a href="#materials" class="btn" style="padding: 10px 20px; background: #f1f5f9; color: #374151; border-radius: 8px 8px 0 0; text-decoration: none; font-size: 14px;">
        Materials (<?= count($materials) ?>)
    </a>
</div>

<!-- Students Section -->
<div id="students" class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Enrolled Students</h3>
        <a href="export_progress.php?module_id=<?= $moduleId ?>" class="btn" style="padding: 8px 16px; background: #10b981; color: white; border-radius: 6px; text-decoration: none; font-size: 13px;">
            Export CSV
        </a>
    </div>
    <div class="card-body" style="padding: 0; overflow-x: auto;">
        <?php if (empty($progressRecords)): ?>
        <div style="text-align: center; padding: 40px; color: #64748b;">
            No students enrolled in this module yet.
        </div>
        <?php else: ?>
        <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Student</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Email</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Progress</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Status</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($progressRecords as $rec): ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 14px 16px; font-weight: 600;">
                        <?= htmlspecialchars(($rec['FirstName'] ?? '') . ' ' . ($rec['LastName'] ?? '')) ?>
                    </td>
                    <td style="padding: 14px 16px; color: #64748b;">
                        <?= htmlspecialchars($rec['email'] ?? '-') ?>
                    </td>
                    <td style="padding: 14px 16px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 100px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
<div style="width: <?= $rec['progress_percentage'] ?? 0 ?>%; height: 100%; background: #2563eb; border-radius: 3px;"></div>
                            </div>
                            <td style="padding: 14px 16px;">
                                <span style="font-size: 13px;"><?= $rec['progress_percentage'] ?? 0 ?>%</span>
                        </div>
                    </td>
                    <td style="padding: 14px 16px;">
                        <?php 
                        $status = $rec['status'] ?? 'Not Started';
                        $statusClass = [
                            'Completed' => 'badge-green',
                            'In Progress' => 'badge-blue',
                            'Not Started' => 'badge-gray'
                        ][$status] ?? 'badge-gray';
                        ?>
                        <span class="badge <?= $statusClass ?>"><?= $status ?></span>
                    </td>
                    <td style="padding: 14px 16px; color: #64748b; font-size: 13px;">
                        <?= date('M d, Y H:i', strtotime($rec['last_access_date'] ?? $rec['start_date'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Quizzes Section -->
<div id="quizzes" class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Associated Quizzes</h3>
        <a href="add_quiz.php?module_id=<?= $moduleId ?>" class="btn" style="padding: 8px 16px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none; font-size: 13px;">
            + Add Quiz
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($quizzes)): ?>
        <div style="text-align: center; padding: 40px; color: #64748b;">
            No quizzes associated with this module.
        </div>
        <?php else: ?>
        <div style="display: grid; gap: 12px; padding: 16px;">
            <?php foreach ($quizzes as $quiz): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 8px;">
                <div>
                    <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($quiz['title']) ?></div>
                    <div style="font-size: 13px; color: #64748b;">
                        <?= $quiz['question_count'] ?? 0 ?> questions • <?= $quiz['time_limit'] ?? 0 ?> mins
                    </div>
                </div>
                <span class="badge badge-blue"><?= $quiz['nc_level'] ?? $ncLevel ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Assignments Section -->
<div id="assignments" class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Associated Assignments</h3>
        <a href="add_assignment.php?module_id=<?= $moduleId ?>" class="btn" style="padding: 8px 16px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none; font-size: 13px;">
            + Add Assignment
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($assignments)): ?>
        <div style="text-align: center; padding: 40px; color: #64748b;">
            No assignments associated with this module.
        </div>
        <?php else: ?>
        <div style="display: grid; gap: 12px; padding: 16px;">
            <?php foreach ($assignments as $assign): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 8px;">
                <div>
                    <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($assign['title']) ?></div>
                    <div style="font-size: 13px; color: #64748b;">
                        Due: <?= date('M d, Y', strtotime($assign['due_date'])) ?> • <?= $assign['max_score'] ?? 100 ?> points
                    </div>
                </div>
                <span class="badge <?= strtotime($assign['due_date']) > time() ? 'badge-green' : 'badge-red' ?>">
                    <?= strtotime($assign['due_date']) > time() ? 'Active' : 'Expired' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Materials Section -->
<div id="materials" class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Learning Materials</h3>
        <a href="add_material.php?module_id=<?= $moduleId ?>" class="btn" style="padding: 8px 16px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none; font-size: 13px;">
            + Add Material
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($materials)): ?>
        <div style="text-align: center; padding: 40px; color: #64748b;">
            No learning materials associated with this module.
        </div>
        <?php else: ?>
        <div style="display: grid; gap: 12px; padding: 16px;">
            <?php foreach ($materials as $mat): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 8px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; background: #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        📄
                    </div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($mat['title']) ?></div>
                        <div style="font-size: 13px; color: #64748b;">
                            <?= $mat['material_type'] ?? 'Document' ?> • <?= number_format($mat['file_size'] ?? 0 / 1024, 1) ?> KB
                        </div>
                    </div>
                </div>
                <a href="<?= htmlspecialchars($mat['file_path']) ?>" download class="btn" style="padding: 8px 16px; background: #f1f5f9; color: #374151; border-radius: 6px; text-decoration: none; font-size: 13px;">
                    Download
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>

</body>
</html>