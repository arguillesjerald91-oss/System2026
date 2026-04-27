<?php
/**
 * Module 5: Reports & Analytics
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if ($userType !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$userStats = [];
$stmt = $conn->query("SELECT user_type, COUNT(*) as cnt FROM users GROUP BY user_type");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $userStats[$row['user_type']] = $row['cnt'];
}
$totalUsers = array_sum($userStats);

$preStats = [];
$stmt = $conn->query("SELECT application_status, COUNT(*) as cnt FROM pre_enrollment_applications GROUP BY application_status");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $preStats[$row['application_status']] = $row['cnt'];
}
$totalPre = array_sum($preStats);

$schStats = [];
$stmt = $conn->query("SELECT application_status, COUNT(*) as cnt FROM scholarship_applications GROUP BY application_status");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $schStats[$row['application_status']] = $row['cnt'];
}

$compStats = [];
$stmt = $conn->query("SELECT assessment_status, COUNT(*) as cnt FROM competency_assessments GROUP BY assessment_status");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $compStats[$row['assessment_status']] = $row['cnt'];
}

$batches = $conn->query("SELECT * FROM training_batches ORDER BY batch_name")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Reports & Analytics";
$pageSubtitle = "Module 5 - Admin & Reporting Dashboard";
include 'sidebar_new.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?= $totalUsers ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pre-Enrollments</div>
        <div class="stat-value"><?= $totalPre ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Scholarships</div>
        <div class="stat-value"><?= array_sum($schStats) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Assessments</div>
        <div class="stat-value"><?= array_sum($compStats) ?></div>
    </div>
</div>

<div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:30px;">
    <div class="card">
        <div class="card-header"><h3 class="card-title">User Distribution</h3></div>
        <div class="card-body">
            <?php 
            $max = max(array_values($userStats)) ?: 1;
            foreach ($userStats as $type => $cnt): 
                $pct = round(($cnt / $max) * 100);
            ?>
            <div style="display:flex;align-items:center;margin-bottom:15px;">
                <div style="width:100px;font-size:13px;"><?= htmlspecialchars(ucfirst($type)) ?></div>
                <div style="width:50px;text-align:right;font-weight:600;margin-right:10px;"><?= $cnt ?></div>
                <div style="flex:1;height:20px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                    <div style="width:<?= $pct ?>%;height:100%;background:#2563eb;border-radius:4px;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header"><h3 class="card-title">Training Batches</h3></div>
        <div class="card-body">
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr><th style="text-align:left;font-size:12px;color:#64748b;padding:8px 0;">Batch</th><th style="text-align:left;font-size:12px;color:#64748b;padding:8px 0;">Schedule</th><th style="text-align:left;font-size:12px;color:#64748b;padding:8px 0;">Slots</th><th style="text-align:left;font-size:12px;color:#64748b;padding:8px 0;">Status</th></tr></thead>
                <tbody>
                    <?php foreach ($batches as $batch): ?>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:12px 0;"><?= htmlspecialchars($batch['batch_name']) ?></td>
                        <td style="padding:12px 0;"><?= htmlspecialchars($batch['schedule_type']) ?></td>
                        <td style="padding:12px 0;"><?= $batch['enrolled_count'] ?>/<?= $batch['max_slots'] ?></td>
                        <td style="padding:12px 0;"><span class="badge <?= $batch['status'] === 'Open' ? 'badge-green' : 'badge-blue' ?>"><?= $batch['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($batches)): ?>
                    <tr><td colspan="4" style="text-align:center;color:#64748b;padding:20px;">No batches</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:30px;">
    <div class="card">
        <div class="card-header"><h3 class="card-title">TRACER Survey - Employment Tracking</h3></div>
        <div class="card-body">
            <p style="color:#64748b;margin-bottom:20px;">Track graduate employment status for TESDA regional reporting requirements.</p>
            <a href="tracer_survey.php" class="btn btn-primary" style="display:inline-block;padding:12px 24px;background:#2563eb;color:white;border-radius:10px;text-decoration:none;font-weight:600;">
                📋 Start TRACER Survey
            </a>
            <div style="margin-top:20px;display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
                <?php 
                $empStats = [];
                $totalSurvey = 0;
                $employed = 0;
                try {
                    $stmt = $conn->query("SELECT employment_status, COUNT(*) as cnt FROM tracer_survey_responses GROUP BY employment_status");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $empStats[$row['employment_status']] = $row['cnt'];
                    }
                    $totalSurvey = array_sum($empStats);
                    $employed = $empStats['Employed'] ?? 0;
                } catch (PDOException $e) {
                    $totalSurvey = 0;
                    $employed = 0;
                }
                ?>
                <div style="padding:16px;background:#f8fafc;border-radius:8px;text-align:center;">
                    <div style="font-size:24px;font-weight:700;color:#10b981;"><?= $totalSurvey ?></div>
                    <div style="font-size:12px;color:#64748b;">Total Responses</div>
                </div>
                <div style="padding:16px;background:#f8fafc;border-radius:8px;text-align:center;">
                    <div style="font-size:24px;font-weight:700;color:#2563eb;"><?= $employed ?></div>
                    <div style="font-size:12px;color:#64748b;">Employed</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header"><h3 class="card-title">Quick Reports</h3></div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:12px;">
                <a href="reports_enrollment.php" style="display:flex;align-items:center;gap:12px;padding:16px;background:#f8fafc;border-radius:10px;text-decoration:none;color:#374151;">
                    <span style="font-size:24px;">📊</span>
                    <div>
                        <div style="font-weight:600;">Enrollment Report</div>
                        <div style="font-size:12px;color:#64748b;">NC Level distribution</div>
                    </div>
                </a>
                <a href="reports_assessment.php" style="display:flex;align-items:center;gap:12px;padding:16px;background:#f8fafc;border-radius:10px;text-decoration:none;color:#374151;">
                    <span style="font-size:24px;">📈</span>
                    <div>
                        <div style="font-weight:600;">Assessment Results</div>
                        <div style="font-size:12px;color:#64748b;">Competency pass rates</div>
                    </div>
                </a>
                <a href="reports_financial.php" style="display:flex;align-items:center;gap:12px;padding:16px;background:#f8fafc;border-radius:10px;text-decoration:none;color:#374151;">
                    <span style="font-size:24px;">💰</span>
                    <div>
                        <div style="font-weight:600;">Financial Summary</div>
                        <div style="font-size:12px;color:#64748b;">Revenue & expenses</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

</div></div></main></div>

</body>
</html>