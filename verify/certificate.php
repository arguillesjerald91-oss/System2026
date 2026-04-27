<?php
/**
 * Public Certificate Verification
 */

$code = $_GET['code'] ?? '';
if (!$code) die('Verification code required');

include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("
    SELECT c.*, s.FirstName, s.LastName, p.program_code, p.program_title
    FROM certificates c
    JOIN student s ON c.student_id = s.StudID
    JOIN auto_mechanic_programs p ON c.program_id = p.program_id
    WHERE c.verification_code = ?
");
$stmt->execute([$code]);
$cert = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cert) {
    die('Invalid verification code.');
}

$conn->prepare("UPDATE certificates SET access_count = access_count + 1, last_accessed = NOW() WHERE certificate_id = ?")->execute([$cert['certificate_id']]);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Certificate Verification - TESDA Auto Mechanic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f0fdf4; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); padding: 2rem; border: 3px solid #10b981; }
        .header { text-align: center; border-bottom: 2px solid #10b981; padding-bottom: 1.5rem; margin-bottom: 2rem; }
        .logo { width: 100px; height: 100px; background: #10b981; border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; color: white; font-size: 2.5rem; font-weight: bold; }
        h1 { color: #065f46; margin: 0; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin: 1.5rem 0; }
        .info-box { padding: 1rem; background: #f0fdf4; border-radius: 0.5rem; border-left: 4px solid #10b981; }
        .info-label { font-size: 0.85rem; color: #6b7280; display: block; margin-bottom: 0.25rem; }
        .info-value { font-size: 1.1rem; font-weight: 600; color: #065f46; }
        .status-badge { display: inline-block; padding: 1rem 2rem; border-radius: 9999px; font-weight: bold; font-size: 1.1rem; background: #10b981; color: white; }
        .verified-badge { display: flex; align-items: center; justify-content: center; gap: 0.75rem; color: #065f46; font-size: 1.25rem; font-weight: bold; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #e5e7eb; }
        .footer { text-align: center; margin-top: 2rem; color: #6b7280; font-size: 0.9rem; }
        .details { background: #f8fafc; padding: 1.5rem; border-radius: 0.5rem; margin: 1rem 0; }
        .details h3 { margin-top: 0; color: #374151; }
        .nc-badge { background: #dbeafe; color: #1e40af; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo"><i class="fas fa-certificate"></i></div>
            <h1>Certificate Verification</h1>
            <p>TESDA Auto Mechanic Training Centre</p>
            <p style="color: #6b7280; font-size: 0.9rem;">This certificate has been verified as authentic</p>
        </div>

        <div style="text-align: center;">
            <div class="status-badge">✓ AUTHENTIC</div>
        </div>

        <?php if ($cert['nc_level']): ?>
            <div style="text-align: center; margin-top: 1rem;">
                <span class="nc-badge"><?= htmlspecialchars($cert['nc_level']) ?> Competency</span>
            </div>
        <?php endif; ?>

        <div class="info-grid">
            <div class="info-box">
                <span class="info-label">Certificate Number</span>
                <span class="info-value"><?= htmlspecialchars($cert['certificate_number']) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Recipient</span>
                <span class="info-value"><?= htmlspecialchars($cert['FirstName'] . ' ' . $cert['LastName']) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Certificate Type</span>
                <span class="info-value"><?= htmlspecialchars($cert['certificate_type']) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Program</span>
                <span class="info-value"><?= htmlspecialchars($cert['program_code']) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Issue Date</span>
                <span class="info-value"><?= date('F j, Y', strtotime($cert['issue_date'])) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Valid Until</span>
                <span class="info-value"><?= $cert['valid_until'] ? date('F j, Y', strtotime($cert['valid_until'])) : 'Lifetime' ?></span>
            </div>
        </div>

        <?php if ($cert['description']): ?>
            <div class="details">
                <h3>Description</h3>
                <p><?= nl2br(htmlspecialchars($cert['description'])) ?></p>
            </div>
        <?php endif; ?>

        <div class="verified-badge">
            <i class="fas fa-check-circle"></i>
            <span>Verification Code: <code><?= htmlspecialchars($code) ?></code></span>
        </div>

        <div class="footer">
            <p>Verified on <?= date('F j, Y g:i A') ?></p>
            <p>This digital verification is the official confirmation of authenticity</p>
            <p style="font-size: 0.8rem; margin-top: 1rem;">© <?= date('Y') ?> TESDA Auto Mechanic Training Centre</p>
        </div>
    </div>
</body>
</html>
