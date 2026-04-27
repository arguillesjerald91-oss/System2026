<?php
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$certId = $_GET['id'] ?? null;
if (!$certId) die('Certificate ID required');

$stmt = $conn->prepare("
    SELECT c.*, s.FirstName, s.LastName, s.SchoolID,
           p.program_code, p.program_title,
           CONCAT(pr.Fname, ' ', pr.Lname) as prepared_by,
           CONCAT(ap.Fname, ' ', ap.Lname) as approved_by,
           CONCAT(isu.Fname, ' ', isu.Lname) as issued_by
    FROM certificates c
    JOIN student s ON c.student_id = s.StudID
    JOIN auto_mechanic_programs p ON c.program_id = p.program_id
    LEFT JOIN admins pr ON c.prepared_by = pr.admin_id
    LEFT JOIN admins ap ON c.approved_by = ap.admin_id
    LEFT JOIN admins isu ON c.issued_by = isu.admin_id
    WHERE c.certificate_id = ?
");
$stmt->execute([$certId]);
$cert = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cert) die('Certificate not found');

$comps = $conn->prepare("SELECT * FROM certificate_competencies WHERE certificate_id = ?");
$comps->execute([$certId]);

$history = $conn->prepare("SELECT * FROM certificate_history WHERE certificate_id = ? ORDER BY performed_at DESC LIMIT 20");
$history->execute([$certId]);
$history->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="padding: 1rem;">
    <h3 style="color: #1e3a8a; margin-bottom: 1rem;"><?= htmlspecialchars($cert['certificate_type']) ?></h3>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Certificate Number</div>
            <div style="font-weight: bold;"><?= htmlspecialchars($cert['certificate_number']) ?></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Student</div>
            <div><?= htmlspecialchars($cert['FirstName'] . ' ' . $cert['LastName']) ?></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Student ID</div>
            <div><?= htmlspecialchars($cert['SchoolID']) ?></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Status</div>
            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $cert['status'])) ?>">
                <?= htmlspecialchars($cert['status']) ?>
            </span>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Issue Date</div>
            <div><?= htmlspecialchars($cert['issue_date']) ?></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Valid Until</div>
            <div><?= htmlspecialchars($cert['valid_until'] ?? 'Lifetime') ?></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Verification Code</div>
            <div><code><?= htmlspecialchars($cert['verification_code'] ?? 'Not issued') ?></code></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Template</div>
            <div><?= htmlspecialchars($cert['template_id'] ?? 'Default') ?></div>
        </div>
    </div>

    <div style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
        <h4 style="margin-top:0;">Notes</h4>
        <p style="margin:0;"><?= nl2br(htmlspecialchars($cert['description'] ?? 'No description provided')) ?></p>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <h4>Included Competencies</h4>
        <table class="table" style="font-size: 0.9rem;">
            <thead><tr><th>Code</th><th>Title</th><th>Score</th><th>Completed</th></tr></thead>
            <tbody>
                <tr><td colspan="4" style="text-align:center; color:#6b7280;">No competencies listed</td></tr>
            </tbody>
        </table>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <h4>Audit Trail</h4>
        <div style="max-height: 200px; overflow-y: auto; background: #f8fafc; padding: 1rem; border-radius: 0.5rem;">
            <p style="text-align: center; color: #6b7280;">History logging in progress</p>
        </div>
    </div>

    <div style="text-align: right;">
        <button onclick="window.close()" class="btn btn-secondary">Close</button>
    </div>
</div>
