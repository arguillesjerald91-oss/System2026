<?php
/**
 * Module 2: Scholarship Qualification
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$userType = $_SESSION['user_type'] ?? $_SESSION['userRole'] ?? '';
if (!in_array($userType, ['admin', 'support_staff'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $application_id = $_POST['application_id'] ?? 0;
    $decision = $_POST['decision'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    if ($application_id && in_array($decision, ['Approved', 'Rejected'])) {
        $status = $decision === 'Approved' ? 'Approved' : 'Rejected';
        $stmt = $conn->prepare("UPDATE scholarship_applications SET application_status = ?, admin_remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE application_id = ?");
        $stmt->execute([$status, $remarks, $_SESSION['user_id'] ?? 1, $application_id]);
        
        $stmt = $conn->prepare("SELECT sa.*, p.email_address, p.first_name, p.last_name, sp.program_name FROM scholarship_applications sa JOIN pre_enrollment_applications p ON sa.pre_enroll_id = p.pre_enroll_id JOIN scholarship_programs sp ON sa.program_id = sp.program_id WHERE sa.application_id = ?");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($application && $application['email_address']) {
            $to = $application['email_address'];
            $subject = 'Scholarship Application Status - ' . $application['application_number'];
            $body = "<h2>{$decision}!</h2><p>Dear {$application['first_name']}, your scholarship for {$application['program_name']} has been {$status}.</p>";
            $headers = "From: TESDA <noreply@tesda.gov.ph>\r\nContent-Type: text/html\r\n";
            @mail($to, $subject, $body, $headers);
        }
        echo "<script>location.reload();</script>";
    }
}

$filter = $_GET['status'] ?? 'All';
$where = $filter !== 'All' ? "WHERE sa.application_status = '$filter'" : "WHERE sa.application_status != 'Draft'";

$sql = "SELECT sa.*, p.first_name, p.last_name, p.email_address, p.contact_number, sp.program_name 
        FROM scholarship_applications sa
        JOIN pre_enrollment_applications p ON sa.pre_enroll_id = p.pre_enroll_id
        JOIN scholarship_programs sp ON sa.program_id = sp.program_id
        $where ORDER BY sa.submission_date DESC";
$applications = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT application_status, COUNT(*) as cnt FROM scholarship_applications GROUP BY application_status");
$counts = ['All'=>0,'Submitted'=>0,'Under Review'=>0,'Approved'=>0,'Rejected'=>0];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['application_status']] = $row['cnt'];
    $counts['All'] += $row['cnt'];
}

$pageTitle = "Scholarship Qualification";
$pageSubtitle = "Module 2 - Scholarship Assessment";
include 'sidebar_new.php';
?>

<div class="stats-grid" style="display:flex;gap:15px;margin-bottom:30px;flex-wrap:wrap;">
    <?php foreach (['All', 'Submitted', 'Under Review', 'Approved', 'Rejected'] as $s): ?>
    <a href="?status=<?= $s ?>" class="stat-card" style="padding:14px 24px;background:white;border-radius:12px;border:2px solid #e2e8f0;text-decoration:none;<?= $filter === $s ? 'border-color:#2563eb;background:#2563eb;color:white;' : '' ?>">
        <div style="font-size:24px;font-weight:700;"><?= $counts[$s] ?></div>
        <div style="font-size:12px;opacity:0.8;"><?= $s ?></div>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Scholarship Applicants (<?= count($applications) ?>)</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:800px;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">App No.</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Applicant</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Program</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Income</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Score</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Status</th>
                    <th style="padding:14px 16px;text-align:left;font-size:12px;color:#64748b;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:14px 16px;font-weight:600;"><?= htmlspecialchars($app['application_number']) ?></td>
                    <td style="padding:14px 16px;"><strong><?= htmlspecialchars($app['first_name'].' '.$app['last_name']) ?></strong><br><small style="color:#64748b"><?= htmlspecialchars($app['email_address']) ?></small></td>
                    <td style="padding:14px 16px;"><?= htmlspecialchars($app['program_name']) ?></td>
                    <td style="padding:14px 16px;">₱<?= number_format($app['household_income'] ?? 0) ?></td>
                    <td style="padding:14px 16px;font-weight:700;"><?= $app['total_score'] ?? 0 ?></td>
                    <td style="padding:14px 16px;">
                        <?php $badgeClass = ['Submitted'=>'badge-blue','Under Review'=>'badge-orange','Approved'=>'badge-green','Rejected'=>'badge-red'][$app['application_status']] ?? 'badge-blue'; ?>
                        <span class="badge <?= $badgeClass ?>"><?= $app['application_status'] ?></span>
                    </td>
                    <td style="padding:14px 16px;">
                        <?php if (in_array($app['application_status'], ['Submitted','Under Review'])): ?>
                        <button onclick="openDecision(<?= $app['application_id'] ?>, 'Approved')" class="btn" style="padding:6px 12px;background:#10b981;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">✓</button>
                        <button onclick="openDecision(<?= $app['application_id'] ?>, 'Rejected')" class="btn" style="padding:6px 12px;background:#dc2626;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">✗</button>
                        <?php else: ?>
                        <span style="color:#64748b;font-size:12px;">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($applications)): ?>
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#64748b;">No applications</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="decisionModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center;">
    <div style="background:white;padding:30px;border-radius:14px;max-width:500px;width:90%;">
        <h3 id="modalTitle" style="margin-bottom:20px;">Decision</h3>
        <form method="POST">
            <input type="hidden" name="action" value="decide">
            <input type="hidden" name="application_id" id="modalAppId">
            <input type="hidden" name="decision" id="modalDecision">
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:600;">Remarks (Optional)</label>
                <textarea name="remarks" style="width:100%;padding:12px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit;min-height:100px;"></textarea>
            </div>
            <button type="submit" id="submitBtn" class="btn btn-primary" style="width:100%;">Submit</button>
            <button type="button" onclick="closeModal()" style="width:100%;margin-top:10px;padding:12px;background:#f1f5f9;border:none;border-radius:8px;cursor:pointer;">Cancel</button>
        </form>
    </div>
</div>

<script>
function openDecision(id, decision) {
    document.getElementById('modalAppId').value = id;
    document.getElementById('modalDecision').value = decision;
    document.getElementById('modalTitle').textContent = decision === 'Approved' ? 'Approve Application' : 'Reject Application';
    document.getElementById('submitBtn').style.background = decision === 'Approved' ? '#10b981' : '#dc2626';
    document.getElementById('decisionModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('decisionModal').style.display = 'none';
}
</script>

</div></div></main></div>
</body>
</html>