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

if ($userType !== 'trainer' && $userType !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';
$messageType = '';

// Handle assessment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_score' && isset($_POST['assess_id'])) {
            $assessId = intval($_POST['assess_id']);
            $preScore = floatval($_POST['pre_assessment_score'] ?? 0);
            $practicalScore = floatval($_POST['practical_score'] ?? 0);
            $finalScore = floatval($_POST['final_score'] ?? 0);
            $status = $_POST['assessment_status'] ?? 'In Progress';
            
            $stmt = $conn->prepare("UPDATE competency_assessments SET pre_assessment_score = ?, practical_score = ?, final_score = ?, assessment_status = ?, assessed_by = ?, assessment_date = NOW() WHERE assess_id = ?");
            $stmt->execute([$preScore, $practicalScore, $finalScore, $status, $userId, $assessId]);
            $message = 'Assessment updated successfully';
            $messageType = 'success';
        }
        
        if ($_POST['action'] === 'add_notes' && isset($_POST['assess_id'])) {
            $assessId = intval($_POST['assess_id']);
            $notes = trim($_POST['notes'] ?? '');
            
            $stmt = $conn->query("DESCRIBE competency_assessments");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array('instructor_notes', $columns)) {
                $stmt = $conn->prepare("UPDATE competency_assessments SET instructor_notes = ? WHERE assess_id = ?");
                $stmt->execute([$notes, $assessId]);
                $message = 'Notes added successfully';
                $messageType = 'success';
            }
        }
        
        if ($_POST['action'] === 'filter') {
            $_SESSION['assess_filter'] = $_POST['filter_status'] ?? 'all';
        }
    }
}

$filterStatus = $_SESSION['assess_filter'] ?? 'all';

// Get competency units
$stmt = $conn->query("SELECT * FROM competency_units ORDER BY unit_code");
$competencyUnits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assessments with filters
$whereClause = '';
if ($filterStatus !== 'all') {
    if ($filterStatus === 'pending') {
        $whereClause = " WHERE ca.assessment_status IN ('Not Started', 'In Progress')";
    } elseif ($filterStatus === 'completed') {
        $whereClause = " WHERE ca.assessment_status IN ('Passed', 'Failed', 'RPL')";
    } else {
        $whereClause = " WHERE ca.assessment_status = '$filterStatus'";
    }
}

$query = "SELECT ca.*, u.first_name, u.last_name, u.email, cu.unit_code, cu.unit_title as competency_name
    FROM competency_assessments ca 
    JOIN users u ON ca.user_id = u.user_id 
    LEFT JOIN competency_units cu ON ca.unit_id = cu.unit_id
    $whereClause
    ORDER BY ca.assessment_date DESC, ca.assess_id DESC";

$stmt = $conn->query($query);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stmt = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN assessment_status = 'Passed' THEN 1 ELSE 0 END) as passed,
    SUM(CASE WHEN assessment_status = 'Failed' THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN assessment_status IN ('Not Started', 'In Progress') THEN 1 ELSE 0 END) as pending
    FROM competency_assessments");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get trainer info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$trainer = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($trainer['first_name'] ?? '') . ' ' . ($trainer['last_name'] ?? ''));
$currentPage = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assessments - TESDA Auto Mechanic</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { --primary: #2563eb; --primary-dark: #1e40af; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --background: #f1f5f9; --foreground: #1e293b; --card: #ffffff; --muted: #64748b; --border: #e2e8f0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', -apple-system, sans-serif; background: var(--background); min-height: 100vh; }
.sidebar { position: fixed; left: 0; width: 260px; height: 100vh; background: linear-gradient(180deg, var(--primary-dark), #1e3a8a); color: white; display: flex; flex-direction: column; z-index: 100; }
.sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.sidebar-logo { display: flex; align-items: center; gap: 12px; font-size: 18px; font-weight: 700; }
.sidebar-logo span { font-size: 28px; }
.sidebar-subtitle { font-size: 11px; opacity: 0.7; margin-top: 4px; }
.sidebar-nav { flex: 1; padding: 20px 0; overflow-y: auto; }
.nav-section { padding: 0 12px; margin-bottom: 20px; }
.nav-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.6; padding: 0 12px; margin-bottom: 8px; }
.nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px; color: white; text-decoration: none; margin: 2px 8px; font-size: 14px; transition: all 0.2s; }
.nav-item:hover { background: rgba(255,255,255,0.15); }
.nav-item.active { background: rgba(255,255,255,0.2); }
.sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
.user-profile { display: flex; align-items: center; gap: 12px; }
.user-avatar { width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.user-info h4 { font-size: 14px; font-weight: 600; }
.user-info p { font-size: 12px; opacity: 0.7; }
.main-content { margin-left: 260px; }
.top-bar { background: white; padding: 16px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50; }
.page-title { font-size: 24px; font-weight: 600; }
.page-subtitle { font-size: 14px; color: var(--muted); }
.btn { padding: 10px 20px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
.btn-primary { background: var(--primary); color: white; }
.btn-success { background: var(--success); color: white; }
.btn-danger { background: var(--danger); color: white; }
.btn-outline { background: white; border: 1px solid var(--border); color: var(--foreground); }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.container { padding: 30px 40px; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: var(--card); padding: 20px; border-radius: 12px; border: 1px solid var(--border); text-align: center; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 12px; color: var(--muted); margin-top: 4px; }
.card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); margin-bottom: 24px; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.card-title { font-size: 16px; font-weight: 600; }
.card-body { padding: 20px 24px; }
.alert { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
.alert-success { background: #d1fae5; color: #059669; }
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-warning { background: #fed7aa; color: #d97706; }
.badge-danger { background: #fee2e2; color: #dc2626; }
.badge-blue { background: #dbeafe; color: #2563eb; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); }
.table th { font-size: 12px; color: var(--muted); text-transform: uppercase; font-weight: 600; background: #f8fafc; }
.table tr:hover { background: #f8fafc; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; font-family: inherit; }
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: white; border-radius: 16px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.modal-title { font-size: 18px; font-weight: 600; }
.modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--muted); }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }
.filter-bar { display: flex; gap: 12px; margin-bottom: 20px; }
.filter-bar select { padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px; }
.empty-state { text-align: center; padding: 40px; color: var(--muted); }
.score-input { width: 80px; padding: 8px; border: 1px solid var(--border); border-radius: 6px; }
@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo"><span>🔧</span> TESDA</div>
        <p class="sidebar-subtitle">Trainer Portal</p>
    </div>
     <nav class="sidebar-nav">
         <div class="nav-section">
             <p class="nav-section-title">Menu</p>
             <a href="instructor_dashboard.php" class="nav-item <?= $currentPage == 'instructor_dashboard.php' ? 'active' : '' ?>"><span>🏠</span> Dashboard</a>
             <a href="my_modules.php" class="nav-item <?= $currentPage == 'my_modules.php' ? 'active' : '' ?>"><span>📚</span> My Modules</a>
             <a href="learning_materials.php" class="nav-item <?= $currentPage == 'learning_materials.php' ? 'active' : '' ?>"><span>📂</span> Materials</a>
             <a href="quizzes.php" class="nav-item <?= $currentPage == 'quizzes.php' ? 'active' : '' ?>"><span>❓</span> Quizzes</a>
             <a href="assignments.php" class="nav-item <?= $currentPage == 'assignments.php' ? 'active' : '' ?>"><span>📝</span> Assignments</a>
             <a href="my_students.php" class="nav-item <?= $currentPage == 'my_students.php' ? 'active' : '' ?>"><span>👥</span> My Students</a>
             <a href="assessments.php" class="nav-item <?= $currentPage == 'assessments.php' ? 'active' : '' ?>"><span>📋</span> Assessments</a>
             <a href="reports.php" class="nav-item <?= $currentPage == 'reports.php' ? 'active' : '' ?>"><span>📊</span> Reports</a>
         </div>
     </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">👤</div>
            <div class="user-info">
                <h4><?= htmlspecialchars($fullName) ?></h4>
                <p>Trainer</p>
            </div>
        </div>
    </div>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div><h1 class="page-title">Competency Assessments</h1><p class="page-subtitle">Evaluate and manage student assessments</p></div>
        <a href="../logout.php" class="btn btn-outline">Logout</a>
    </div>
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Assessments</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--warning);"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--success);"><?= $stats['passed'] ?></div>
                <div class="stat-label">Passed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--danger);"><?= $stats['failed'] ?></div>
                <div class="stat-label">Failed</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Assessments</h3>
                <form method="POST" class="filter-bar">
                    <input type="hidden" name="action" value="filter">
                    <select name="filter_status" onchange="this.form.submit()">
                        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Passed" <?= $filterStatus === 'Passed' ? 'selected' : '' ?>>Passed Only</option>
                        <option value="Failed" <?= $filterStatus === 'Failed' ? 'selected' : '' ?>>Failed Only</option>
                    </select>
                </form>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($assessments)): ?>
                <div class="empty-state">No assessments found.</div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Competency</th>
                            <th>Pre-Assessment</th>
                            <th>Practical</th>
                            <th>Final</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assessments as $assess): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 500;"><?= htmlspecialchars($assess['first_name'] . ' ' . $assess['last_name']) ?></div>
                                <div style="font-size: 12px; color: var(--muted);"><?= htmlspecialchars($assess['email'] ?? '') ?></div>
                            </td>
                            <td><?= htmlspecialchars($assess['unit_code'] ?? 'N/A') ?></td>
                            <td><?= $assess['pre_assessment_score'] ?? '-' ?></td>
                            <td><?= $assess['practical_score'] ?? '-' ?></td>
                            <td><strong><?= $assess['final_score'] ?? '-' ?></strong></td>
                            <td>
                                <span class="badge badge-<?= $assess['assessment_status'] === 'Passed' ? 'success' : ($assess['assessment_status'] === 'Failed' ? 'danger' : ($assess['assessment_status'] === 'RPL' ? 'blue' : 'warning')) ?>">
                                    <?= htmlspecialchars($assess['assessment_status']) ?>
                                </span>
                            </td>
                            <td><?= $assess['assessment_date'] ? date('M d, Y', strtotime($assess['assessment_date'])) : '-' ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="openScoreModal(<?= $assess['assess_id'] ?>, '<?= htmlspecialchars($assess['first_name'] . ' ' . $assess['last_name']) ?>', '<?= $assess['pre_assessment_score'] ?? 0 ?>', '<?= $assess['practical_score'] ?? 0 ?>', '<?= $assess['final_score'] ?? 0 ?>', '<?= $assess['assessment_status'] ?>')">Grade</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Score Modal -->
<div id="scoreModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Grade Assessment - <span id="studentName"></span></h3>
            <button class="modal-close" onclick="closeModal('scoreModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_score">
                <input type="hidden" name="assess_id" id="modalAssessId">
                <div class="form-group">
                    <label>Pre-Assessment Score</label>
                    <input type="number" id="modalPreScore" name="pre_assessment_score" step="0.01" min="0" max="100" class="score-input">
                </div>
                <div class="form-group">
                    <label>Practical Score</label>
                    <input type="number" id="modalPractical" name="practical_score" step="0.01" min="0" max="100" class="score-input">
                </div>
                <div class="form-group">
                    <label>Final Score</label>
                    <input type="number" id="modalFinal" name="final_score" step="0.01" min="0" max="100" class="score-input">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="assessment_status" id="modalStatus">
                        <option value="Not Started">Not Started</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Passed">Passed</option>
                        <option value="Failed">Failed</option>
                        <option value="RPL">RPL (Recognition of Prior Learning)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('scoreModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Scores</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function openScoreModal(assessId, name, pre, practical, final, status) {
    document.getElementById('modalAssessId').value = assessId;
    document.getElementById('studentName').textContent = name;
    document.getElementById('modalPreScore').value = pre;
    document.getElementById('modalPractical').value = practical;
    document.getElementById('modalFinal').value = final;
    document.getElementById('modalStatus').value = status;
    openModal('scoreModal');
}
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
</script>
</body>
</html>