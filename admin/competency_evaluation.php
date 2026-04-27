<?php
/**
 * Module 3: Competency-Based Evaluation
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if (!in_array($userType, ['admin', 'instructor', 'instructional_unit'])) {
    header("Location: ../login.php");
    exit();
}

$units = $conn->query("SELECT * FROM competency_units ORDER BY nctype, competency_level")->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT u.user_id, u.username, u.first_name, u.last_name,
        (SELECT COUNT(*) FROM competency_assessments ca WHERE ca.user_id = u.user_id AND ca.assessment_status = 'Passed') as passed,
        (SELECT COUNT(*) FROM competency_assessments ca WHERE ca.user_id = u.user_id) as total
        FROM users u WHERE u.user_type IN ('student', 'trainee') ORDER BY u.last_name";
$trainees = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT assessment_status, COUNT(*) as cnt FROM competency_assessments GROUP BY assessment_status");
$stats = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats[$row['assessment_status']] = $row['cnt'];
}

$pageTitle = "Competency Evaluation";
$pageSubtitle = "Module 3 - CBT Assessment for NC I/NC II";
include 'sidebar_new.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Units</div>
        <div class="stat-value"><?= count($units) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Passed</div>
        <div class="stat-value"><?= $stats['Passed'] ?? 0 ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">In Progress</div>
        <div class="stat-value"><?= $stats['In Progress'] ?? 0 ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Not Started</div>
        <div class="stat-value"><?= $stats['Not Started'] ?? 0 ?></div>
    </div>
</div>

<div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Units of Competency (TESDA NC I/NC II)</h3></div>
        <div class="card-body">
            <?php foreach ($units as $unit): ?>
            <div style="display:flex;align-items:center;padding:14px;border-radius:10px;margin-bottom:10px;background:#f8fafc;">
                <div style="font-weight:700;color:#2563eb;width:80px;"><?= htmlspecialchars($unit['unit_code']) ?></div>
                <div style="flex:1;font-weight:500;"><?= htmlspecialchars($unit['unit_title']) ?></div>
                <span class="badge <?= $unit['nctype'] === 'NC I' ? 'badge-blue' : 'badge-orange' ?>"><?= $unit['nctype'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($units)): ?>
            <p style="text-align:center;color:#64748b;padding:20px;">No competency units found</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header"><h3 class="card-title">Trainee Progress</h3></div>
        <div class="card-body">
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr><th style="text-align:left;font-size:12px;color:#64748b;padding:12px;">Trainee</th><th style="text-align:left;font-size:12px;color:#64748b;padding:12px;">Progress</th><th style="text-align:left;font-size:12px;color:#64748b;padding:12px;">Status</th></tr></thead>
                <tbody>
                    <?php foreach ($trainees as $t): 
                        $pct = $t['total'] > 0 ? round(($t['passed'] / $t['total']) * 100) : 0;
                    ?>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:12px;font-weight:500;"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></td>
                        <td style="padding:12px;">
                            <div style="width:100px;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                                <div style="width:<?= $pct ?>%;height:100%;background:#10b981;border-radius:4px;"></div>
                            </div>
                            <small style="color:#64748b;"><?= $t['passed'] ?>/<?= $t['total'] ?></small>
                        </td>
                        <td style="padding:12px;">
                            <?php if ($pct >= 100): ?>
                            <span class="badge badge-green">NC Ready</span>
                            <?php elseif ($pct > 0): ?>
                            <span class="badge badge-blue">In Progress</span>
                            <?php else: ?>
                            <span class="badge" style="background:#f1f5f9;color:#64748b;">Not Started</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($trainees)): ?>
                    <tr><td colspan="3" style="text-align:center;color:#64748b;padding:20px;">No trainees</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div></div></main></div>
</body>
</html>