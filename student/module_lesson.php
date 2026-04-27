<?php
session_start();
include __DIR__ . '/../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['userId'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['userId'];
$userRole = $_SESSION['userRole'] ?? 'trainee';
$userType = ($userRole === 'student') ? 'trainee' : $userRole;

$moduleId = intval($_GET['module_id'] ?? 0);

if ($moduleId <= 0) {
    header('Location: learning_modules.php');
    exit;
}

// Verify trainee has enrollment
$enrollmentQuery = $conn->prepare("
    SELECT spe.enrollment_id
    FROM student_program_enrollments spe
    WHERE spe.student_id = ? AND spe.enrollment_status = 'Active'
    LIMIT 1
");
$enrollmentQuery->execute([$userId]);
$enrollment = $enrollmentQuery->fetch(PDO::FETCH_ASSOC);

if (!$enrollment) {
    header('Location: student_dashboard.php?error=no_enrollment');
    exit;
}

$enrollmentId = $enrollment['enrollment_id'];

// Create or get progress for this module
$progressQuery = $conn->prepare("
    SELECT progress_id, status, progress_percentage
    FROM student_module_progress 
    WHERE enrollment_id = ? AND module_id = ?
");
$progressQuery->execute([$enrollmentId, $moduleId]);
$progress = $progressQuery->fetch(PDO::FETCH_ASSOC);

if (!$progress) {
    $insertProgress = $conn->prepare("
        INSERT INTO student_module_progress (enrollment_id, module_id, status, start_date, progress_percentage)
        VALUES (?, ?, 'In Progress', NOW(), 0)
    ");
    $insertProgress->execute([$enrollmentId, $moduleId]);
    $progressId = $conn->lastInsertId();
    $progress = ['progress_id' => $progressId, 'status' => 'In Progress', 'progress_percentage' => 0];
} else {
    $progressId = $progress['progress_id'];
}

// Get module information
$moduleQuery = $conn->prepare("
    SELECT lm.*, 
           (SELECT COUNT(*) FROM module_contents mc WHERE mc.module_id = lm.module_id AND mc.content_type = 'Lesson') as lesson_count
    FROM learning_modules lm
    WHERE lm.module_id = ?
");
$moduleQuery->execute([$moduleId]);
$module = $moduleQuery->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    header('Location: learning_modules.php');
    exit;
}

// Get module contents (lessons, quizzes, assignments as learning items)
$contentsQuery = $conn->prepare("
    SELECT mc.*,
           CASE mc.content_type
               WHEN 'Quiz' THEN (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = mc.content_url AND qa.user_id = ?)
               WHEN 'Assignment' THEN (SELECT COUNT(*) FROM assignment_submissions ass WHERE ass.assignment_id = mc.content_url AND ass.student_id = ?)
               ELSE 0
           END as completion_count
    FROM module_contents mc
    WHERE mc.module_id = ? AND mc.is_published = 1
    ORDER BY mc.sort_order ASC
");
$contentsQuery->execute([$userId, $userId, $moduleId]);
$contents = $contentsQuery->fetchAll(PDO::FETCH_ASSOC);

// Calculate progress based on completed contents
$completedCount = 0;
$totalLessons = count(array_filter($contents, fn($c) => $c['content_type'] == 'Video' || $c['content_type'] == 'PDF' || $c['content_type'] == 'Document' || $c['content_type'] == 'Presentation' || $c['content_type'] == 'Link'));
foreach ($contents as $content) {
    if ($content['completion_count'] > 0 || in_array($content['content_type'], ['Video','PDF','Document','Presentation','Image','Archive','Link','Activity'])) {
        $completedCount++;
    }
}
$progressPercentage = $totalLessons > 0 ? round(($completedCount / $totalLessons) * 100) : 0;

// Update progress if improved
if ($progressPercentage > ($progress['progress_percentage'] ?? 0)) {
    $updateProgress = $conn->prepare("
        UPDATE student_module_progress 
        SET progress_percentage = ?, last_access_date = NOW()
        WHERE progress_id = ?
    ");
    $updateProgress->execute([$progressPercentage, $progressId]);
}

// Handle content completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content_id'])) {
    $contentId = intval($_POST['content_id']);
    $content = null;
    foreach ($contents as $c) {
        if ($c['content_id'] == $contentId) {
            $content = $c;
            break;
        }
    }
    
    if ($content) {
        switch ($content['content_type']) {
            case 'Quiz':
                header('Location: ../instructor/take_quiz.php?quiz_id=' . $content['content_url']);
                exit;
            case 'Assignment':
                header('Location: ../instructor/assignments.php?assignment_id=' . $content['content_url']);
                exit;
            default:
                // Mark as viewed/completed for other content types
                $logStmt = $conn->prepare("
                    INSERT INTO access_logs (user_id, access_type, resource_type, resource_id, access_action, access_timestamp)
                    VALUES (?, 'Content View', 'ModuleContent', ?, 'View', NOW())
                ");
                $logStmt->execute([$userId, $contentId]);
        }
    }
}

$currentPage = 'module_lesson.php';
$pageTitle = htmlspecialchars($module['module_title']);
$pageSubtitle = "Module Content";
include 'sidebar_student.php';
?>