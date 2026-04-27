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

if ($userType !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));

// Stats
$stmt = $conn->query("SELECT user_type, COUNT(*) as cnt FROM users GROUP BY user_type");
$userStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stats = [];
$total = 0;
foreach ($userStats as $s) {
    $stats[$s['user_type']] = $s['cnt'];
    $total += $s['cnt'];
}

$stmt = $conn->query("SELECT user_id, username, email, user_type, created_at FROM users ORDER BY user_id DESC LIMIT 5");
$recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Admin Dashboard";
$pageSubtitle = "Integrated Pre-Enrollment & Scholarship System";
include 'sidebar_new.php';
?>

<!-- Dashboard Content -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?= $total ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Students/Trainees</div>
        <div class="stat-value"><?= ($stats['student'] ?? 0) + ($stats['trainee'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Instructors</div>
        <div class="stat-value"><?= $stats['instructor'] ?? 0 ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Admin Staff</div>
        <div class="stat-value"><?= $stats['admin'] ?? 0 ?></div>
    </div>
</div>

<div class="grid-2" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Quick Actions</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                <a href="pre_enrollment_management.php" class="action-card" style="display:flex;align-items:center;gap:14px;padding:16px;border-radius:12px;border:1px solid #e2e8f0;text-decoration:none;color:#1e293b;transition:0.2s;">
                    <div style="width:44px;height:44px;border-radius:10px;background:#dbeafe;display:flex;align-items:center;justify-content:center;font-size:20px;color:#2563eb;">📝</div>
                    <div><strong style="font-size:14px;">Pre-Enrollment</strong><p style="font-size:12px;color:#64748b;margin:0;">Module 1</p></div>
                </a>
                <a href="scholarship_qualification.php" class="action-card" style="display:flex;align-items:center;gap:14px;padding:16px;border-radius:12px;border:1px solid #e2e8f0;text-decoration:none;color:#1e293b;transition:0.2s;">
                    <div style="width:44px;height:44px;border-radius:10px;background:#d1fae5;display:flex;align-items:center;justify-content:center;font-size:20px;color:#10b981;">🎓</div>
                    <div><strong style="font-size:14px;">Scholarship</strong><p style="font-size:12px;color:#64748b;margin:0;">Module 2</p></div>
                </a>
                <a href="competency_evaluation.php" class="action-card" style="display:flex;align-items:center;gap:14px;padding:16px;border-radius:12px;border:1px solid #e2e8f0;text-decoration:none;color:#1e293b;transition:0.2s;">
                    <div style="width:44px;height:44px;border-radius:10px;background:#fed7aa;display:flex;align-items:center;justify-content:center;font-size:20px;color:#f59e0b;">📊</div>
                    <div><strong style="font-size:14px;">Competency</strong><p style="font-size:12px;color:#64748b;margin:0;">Module 3</p></div>
                </a>
                <a href="lms_modules.php" class="action-card" style="display:flex;align-items:center;gap:14px;padding:16px;border-radius:12px;border:1px solid #e2e8f0;text-decoration:none;color:#1e293b;transition:0.2s;">
                    <div style="width:44px;height:44px;border-radius:10px;background:#ede9fe;display:flex;align-items:center;justify-content:center;font-size:20px;color:#8b5cf6;">📚</div>
                    <div><strong style="font-size:14px;">LMS Modules</strong><p style="font-size:12px;color:#64748b;margin:0;">Module 4</p></div>
                </a>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header"><h3 class="card-title">Recent Users</h3></div>
        <div class="card-body">
            <?php if (empty($recentUsers)): ?>
            <p style="color:#64748b;text-align:center;padding:20px;">No users</p>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse;">
                <?php foreach ($recentUsers as $u): ?>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:12px 0;font-size:14px;"><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                    <td style="padding:12px 0;"><span class="badge badge-blue"><?= htmlspecialchars($u['user_type']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">System Modules Status</h3></div>
    <div class="card-body">
        <table style="width:100%;border-collapse:collapse;">
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:14px 0;">Module 1: Pre-Enrollment</td>
                <td style="padding:14px 0;"><span class="badge badge-green">Active</span></td>
            </tr>
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:14px 0;">Module 2: Scholarship Qualification</td>
                <td style="padding:14px 0;"><span class="badge badge-green">Active</span></td>
            </tr>
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:14px 0;">Module 3: Competency-Based Evaluation</td>
                <td style="padding:14px 0;"><span class="badge badge-green">Active</span></td>
            </tr>
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:14px 0;">Module 4: LMS (e-Access)</td>
                <td style="padding:14px 0;"><span class="badge badge-green">Active</span></td>
            </tr>
            <tr>
                <td style="padding:14px 0;">Module 5: Reports & Analytics</td>
                <td style="padding:14px 0;"><span class="badge badge-green">Active</span></td>
            </tr>
        </table>
    </div>
</div>

</div><!-- End page-content -->
</main>
</div><!-- End main-wrapper -->
</body>
</html>