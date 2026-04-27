<?php
/**
 * Admin Reports for Document Management
 * Analytics & Insights for TOR, Certificates, Diplomas
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if (!in_array($userType, ['admin', 'support_staff', 'instructional_unit'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'] ?? 1;

$pageTitle = "Document Reports & Analytics";
$pageSubtitle = "Insights on Transcripts, Certificates & Diplomas";
include 'sidebar_new.php';
include '../includes/unified_header.php';

// Date range filters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month

// 1. Issuance Statistics
$issuanceStats = $conn->query("
    SELECT 
        DATE(issue_date) as date,
        COUNT(*) as count,
        SUM(CASE WHEN status = 'Issued' THEN 1 ELSE 0 END) as issued,
        SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as drafts
    FROM transcripts
    WHERE issue_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY DATE(issue_date)
    ORDER BY date DESC
    LIMIT 30
")->fetchAll();

// 2. Certificates by Type
$certByType = $conn->query("
    SELECT certificate_type, COUNT(*) as total
    FROM certificates
    WHERE issue_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY certificate_type
    ORDER BY total DESC
")->fetchAll();

// 3. Diplomas by Honors
$diplomasByHonors = $conn->query("
    SELECT honors, COUNT(*) as total
    FROM diplomas
    WHERE graduation_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY honors
    ORDER BY total DESC
")->fetchAll();

// 4. Top Programs by Diploma Count
$topPrograms = $conn->query("
    SELECT p.program_code, p.program_title, COUNT(d.diploma_id) as count
    FROM diplomas d
    JOIN auto_mechanic_programs p ON d.program_id = p.program_id
    WHERE d.graduation_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY p.program_id
    ORDER BY count DESC
    LIMIT 10
")->fetchAll();

// 5. Students with multiple certificates
$multiCertStudents = $conn->query("
    SELECT s.FirstName, s.LastName, s.SchoolID, COUNT(c.certificate_id) as cert_count
    FROM student s
    JOIN certificates c ON s.StudID = c.student_id
    WHERE c.issue_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY s.StudID
    HAVING cert_count > 1
    ORDER BY cert_count DESC
    LIMIT 10
")->fetchAll();

// 6. Document Requests Status
$requestStats = $conn->query("
    SELECT status, COUNT(*) as total
    FROM document_requests
    WHERE request_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY status
    ORDER BY total DESC
")->fetchAll();

// 7. Aging Report - Unissued transcripts
$agingTranscripts = $conn->query("
    SELECT COUNT(*) as count
    FROM transcripts
    WHERE status IN ('Draft', 'Pending Approval')
    AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch(PDO::FETCH_ASSOC);

// 8. Expiring Certificates (next 90 days)
$expiringCerts = $conn->query("
    SELECT COUNT(*) as count
    FROM certificates
    WHERE valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    AND status = 'Active'
")->fetch(PDO::FETCH_ASSOC);

// 9. Monthly Comparison
$monthly = $conn->query("
    SELECT 
        YEAR(issue_date) as yr, 
        MONTH(issue_date) as mn, 
        COUNT(*) as total 
    FROM transcripts 
    WHERE issue_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY YEAR(issue_date), MONTH(issue_date)
    ORDER BY yr DESC, mn DESC
")->fetchAll();
?>

<div class="content-header">
    <h2><i class="fas fa-chart-bar"></i> <?= htmlspecialchars($pageTitle) ?></h2>
    <p class="text-muted"><?= htmlspecialchars($pageSubtitle) ?></p>
</div>

<!-- Date Filter -->
<div class="card">
    <div class="card-body">
        <form method="GET" style="display:flex; gap:1rem; align-items:end;">
            <div class="form-group" style="margin:0;">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Apply Filter</button>
            <a href="?" class="btn btn-secondary">Reset</a>
        </form>
    </div>
</div>

<!-- KPI Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= count($issuanceStats) ?></div>
        <div class="stat-label">Days with Issuance</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= array_sum(array_column($issuanceStats, 'issued')) ?></div>
        <div class="stat-label">Transcripts Issued</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $agingTranscripts['count'] ?></div>
        <div class="stat-label">Stale Drafts (>7d)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $expiringCerts['count'] ?></div>
        <div class="stat-label">Expiring Certs (90d)</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-top: 2rem;">

    <!-- Issuance Trend -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daily Issuance Trend</h3>
        </div>
        <div class="card-body">
            <?php if (empty($issuanceStats)): ?>
                <p class="text-muted">No data in selected period.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Date</th><th>Issued</th><th>Drafts</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($issuanceStats as $row): ?>
                        <tr>
                            <td><?= date('M j', strtotime($row['date'])) ?></td>
                            <td class="text-success"><?= $row['issued'] ?></td>
                            <td class="text-warning"><?= $row['drafts'] ?></td>
                            <td><?= $row['count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Certificates by Type -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Certificates by Type</h3>
        </div>
        <div class="card-body">
            <?php if (empty($certByType)): ?>
                <p class="text-muted">No certificates issued.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Type</th><th>Count</th></tr></thead>
                    <tbody>
                        <?php foreach ($certByType as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['certificate_type']) ?></td>
                            <td><?= $row['total'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Diplomas by Honors -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Diplomas by Honors</h3>
        </div>
        <div class="card-body">
            <?php if (empty($diplomasByHonors)): ?>
                <p class="text-muted">No diplomas issued.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Honors</th><th>Count</th></tr></thead>
                    <tbody>
                        <?php foreach ($diplomasByHonors as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['honors']) ?></td>
                            <td><?= $row['total'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Programs -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Top Programs by Diplomas</h3>
        </div>
        <div class="card-body">
            <?php if (empty($topPrograms)): ?>
                <p class="text-muted">No data.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Program</th><th>Diplomas</th></tr></thead>
                    <tbody>
                        <?php foreach ($topPrograms as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['program_code'] . ' - ' . $row['program_title']) ?></td>
                            <td><?= $row['count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Document Request Status -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Document Request Status</h3>
        </div>
        <div class="card-body">
            <?php if (empty($requestStats)): ?>
                <p class="text-muted">No requests in period.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Status</th><th>Count</th></tr></thead>
                    <tbody>
                        <?php foreach ($requestStats as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['status']) ?></td>
                            <td><?= $row['total'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Students with Multiple Certs -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Top Certificate Earners</h3>
        </div>
        <div class="card-body">
            <?php if (empty($multiCertStudents)): ?>
                <p class="text-muted">No multi-certificate students in period.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Student</th><th># of Certs</th></tr></thead>
                    <tbody>
                        <?php foreach ($multiCertStudents as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']) ?> (<?= $row['SchoolID'] ?>)</td>
                            <td><?= $row['cert_count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Export Actions -->
<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">Export Reports</h3>
    </div>
    <div class="card-body">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="export_report.php?type=transcripts&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn btn-secondary" target="_blank">
                <i class="fas fa-file-excel"></i> Export Transcripts
            </a>
            <a href="export_report.php?type=certificates&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn btn-secondary" target="_blank">
                <i class="fas fa-file-excel"></i> Export Certificates
            </a>
            <a href="export_report.php?type=diplomas&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn btn-secondary" target="_blank">
                <i class="fas fa-file-excel"></i> Export Diplomas
            </a>
            <a href="export_report.php?type=requests&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn btn-secondary" target="_blank">
                <i class="fas fa-file-excel"></i> Export Document Requests
            </a>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
