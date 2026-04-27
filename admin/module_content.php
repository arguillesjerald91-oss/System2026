<?php
/**
 * Module Content Management
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

$stmt = $conn->prepare("SELECT * FROM module_contents WHERE module_id = ? ORDER BY content_order ASC");
$stmt->execute([$moduleId]);
$contents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM quizzes WHERE module_id = ? ORDER BY created_at DESC");
$stmt->execute([$moduleId]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM assignments WHERE module_id = ? ORDER BY due_date ASC");
$stmt->execute([$moduleId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM learning_materials WHERE module_id = ? ORDER BY created_at DESC");
$stmt->execute([$moduleId]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_content'])) {
    $content_title = trim($_POST['content_title'] ?? '');
    $content_type = $_POST['content_type'] ?? 'text';
    $content_order = (int)($_POST['content_order'] ?? 1);
    $content_text = trim($_POST['content_text'] ?? '');
    
    if (!empty($content_title)) {
        $stmt = $conn->prepare("
            INSERT INTO module_contents (module_id, content_title, content_type, content_text, content_order, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$moduleId, $content_title, $content_type, $content_text, $content_order]);
        header("Location: module_content.php?id=" . $moduleId);
        exit();
    }
}

$pageTitle = "Module Content";
$pageSubtitle = "Manage " . htmlspecialchars($module['module_title']);
$currentPage = "lms_modules.php";

include 'sidebar_new.php';
?>

<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 700; color: #1e40af; margin-bottom: 4px;"><?= htmlspecialchars($module['module_title']) ?></h2>
        <div style="display: flex; gap: 8px;">
            <span class="badge badge-blue"><?= $module['module_type'] ?? 'Theory' ?></span>
            <span class="badge badge-purple"><?= $module['nc_level'] ?? 'NC I' ?></span>
            <span class="badge badge-green"><?= $module['duration_mins'] ?> mins</span>
        </div>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="view_module.php?id=<?= $moduleId ?>" class="btn" style="padding: 10px 20px; background: #f1f5f9; color: #374151; border-radius: 8px; text-decoration: none;">
            Back to Details
        </a>
        <a href="lms_modules.php" class="btn" style="padding: 10px 20px; background: #f1f5f9; color: #374151; border-radius: 8px; text-decoration: none;">
            All Modules
        </a>
    </div>
</div>

<!-- Tabs Navigation -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; overflow-x: auto;">
    <a href="#contents" class="btn" style="padding: 10px 20px; background: #2563eb; color: white; border-radius: 8px 8px 0 0; text-decoration: none;">
        Contents (<?= count($contents) ?>)
    </a>
    <a href="#quizzes" class="btn" style="padding: 10px 20px; background: #f1f5f9; color: #374151; border-radius: 8px; text-decoration: none;">
        Quizzes (<?= count($quizzes) ?>)
    </a>
    <a href="#assignments" class="btn" style="padding: 10px 20px; background: #f1f5f9; color: #374151; border-radius: 8px; text-decoration: none;">
        Assignments (<?= count($assignments) ?>)
    </a>
    <a href="#materials" class="btn" style="padding: 10px 20px; background: #f1f5f9; color: #374151; border-radius: 8px; text-decoration: none;">
        Materials (<?= count($materials) ?>)
    </a>
</div>

<!-- Module Contents -->
<div id="contents" class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Module Contents</h3>
    </div>
    <div class="card-body">
        <form method="POST" style="display: grid; gap: 16px; padding: 16px; background: #f8fafc; border-radius: 12px; margin-bottom: 20px;">
            <input type="hidden" name="add_content" value="1">
            <input type="hidden" name="content_order" value="<?= count($contents) + 1 ?>">
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Content Title</label>
                    <input type="text" name="content_title" placeholder="e.g., Introduction to Engine Parts"
                        style="width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 8px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Content Type</label>
                    <select name="content_type" style="width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <option value="text">Text</option>
                        <option value="video">Video</option>
                        <option value="image">Image</option>
                        <option value="link">External Link</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Content</label>
                <textarea name="content_text" rows="4" placeholder="Enter lesson content here..."
                    style="width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 8px; resize: vertical;"></textarea>
            </div>
            
            <button type="submit" class="btn" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer;">
                + Add Content
            </button>
        </form>
        
        <?php if (empty($contents)): ?>
        <div style="text-align: center; padding: 40px; color: #64748b;">
            No content added yet. Use the form above to add content.
        </div>
        <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($contents as $idx => $content): ?>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 16px; background: #f8fafc; border-radius: 8px;">
                <div style="display: flex; gap: 16px; align-items: flex-start; flex: 1;">
                    <div style="width: 32px; height: 32px; background: #2563eb; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                        <?= $idx + 1 ?>
                    </div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($content['content_title']) ?></div>
                        <span class="badge badge-gray"><?= $content['content_type'] ?></span>
                        <p style="font-size: 13px; color: #64748b; margin-top: 8px;">
                            <?= htmlspecialchars(substr($content['content_text'] ?? '', 0, 150)) ?>...
                        </p>
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="edit_content.php?id=<?= $content['content_id'] ?>&module_id=<?= $moduleId ?>" 
                       class="btn" style="padding: 8px 14px; background: #f1f5f9; color: #374151; border-radius: 6px; text-decoration: none;">
                        Edit
                    </a>
                    <a href="delete_content.php?id=<?= $content['content_id'] ?>&module_id=<?= $moduleId ?>"
                       onclick="return confirm('Delete this content?')"
                       class="btn" style="padding: 8px 14px; background: #fee2e2; color: #dc2626; border-radius: 6px; text-decoration: none;">
                        Delete
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quizzes -->
<div id="quizzes" class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Quizzes</h3>
        <a href="add_quiz.php?module_id=<?= $moduleId ?>" class="btn" style="padding: 8px 16px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none;">
            + Add Quiz
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($quizzes)): ?>
        <div style="text-align: center; padding: 40px; color: #64748b;">
            No quizzes created for this module.
        </div>
        <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Quiz Title</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Questions</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Time Limit</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quizzes as $quiz): ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 14px 16px; font-weight: 600;"><?= htmlspecialchars($quiz['title']) ?></td>
                    <td style="padding: 14px 16px;"><?= $quiz['question_count'] ?? 0 ?></td>
                    <td style="padding: 14px 16px;"><?= $quiz['time_limit'] ?? 30 ?> mins</td>
                    <td style="padding: 14px 16px;">
                        <a href="edit_quiz.php?id=<?= $quiz['quiz_id'] ?>" class="btn" style="padding: 6px 12px; background: #f1f5f9; color: #374151; border-radius: 6px; text-decoration: none; font-size: 12px;">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Assignments -->
<div id="assignments" class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Assignments</h3>
        <a href="add_assignment.php?module_id=<?= $moduleId ?>" class="btn" style="padding: 8px 16px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none;">
            + Add Assignment
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($assignments)): ?>
        <div style="text-align: center; padding: 40px; color: #64748b;">
            No assignments created for this module.
        </div>
        <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Title</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Due Date</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Max Score</th>
                    <th style="padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $assign): ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 14px 16px; font-weight: 600;"><?= htmlspecialchars($assign['title']) ?></td>
                    <td style="padding: 14px 16px;"><?= date('M d, Y', strtotime($assign['due_date'])) ?></td>
                    <td style="padding: 14px 16px;"><?= $assign['max_score'] ?? 100 ?></td>
                    <td style="padding: 14px 16px;">
                        <a href="edit_assignment.php?id=<?= $assign['assignment_id'] ?>" class="btn" style="padding: 6px 12px; background: #f1f5f9; color: #374151; border-radius: 6px; text-decoration: none; font-size: 12px;">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Materials -->
<div id="materials" class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 class="card-title">Learning Materials</h3>
        <a href="add_material.php?module_id=<?= $moduleId ?>" class="btn" style="padding: 8px 16px; background: #2563eb; color: white; border-radius: 6px; text-decoration: none;">
            + Upload Material
        </a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($materials)): ?>
        <div style="text-align: center; padding: 40px; color: #64748b;">
            No materials uploaded for this module.
        </div>
        <?php else: ?>
        <div style="display: grid; gap: 12px; padding: 16px;">
            <?php foreach ($materials as $mat): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 8px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; background: #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px;">📄</div>
                    <div>
                        <div style="font-weight: 600;"><?= htmlspecialchars($mat['title']) ?></div>
                        <div style="font-size: 12px; color: #64748b;"><?= $mat['material_type'] ?> • <?= number_format(($mat['file_size'] ?? 0) / 1024, 1) ?> KB</div>
                    </div>
                </div>
                <a href="<?= htmlspecialchars($mat['file_path']) ?>" download class="btn" style="padding: 8px 14px; background: #f1f5f9; color: #374151; border-radius: 6px; text-decoration: none;">Download</a>
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