<?php
/**
 * Public Diploma Verification
 */

$code = $_GET['code'] ?? '';
if (!$code) die('Verification code required');

include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("
    SELECT d.*, s.FirstName, s.LastName, p.program_code, p.program_title
    FROM diplomas d
    JOIN student s ON d.student_id = s.StudID
    JOIN auto_mechanic_programs p ON d.program_id = p.program_id
    WHERE d.verification_code = ?
");
$stmt->execute([$code]);
$diploma = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$diploma) die('Invalid verification code.');

$conn->prepare("UPDATE diplomas SET access_count = access_count + 1, last_accessed = NOW() WHERE diploma_id = ?")->execute([$diploma['diploma_id']]);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diploma Verification - TESDA Auto Mechanic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: 'Georgia', serif; background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%); padding: 2rem; min-height: 100vh; }
        .container { max-width: 800px; margin: 0 auto; background: white; border: 8px double #1e3a8a; border-radius: 0; box-shadow: 0 20px 40px rgba(0,0,0,0.15); padding: 3rem; position: relative; }
        .container::before { content: ''; position: absolute; top: 10px; left: 10px; right: 10px; bottom: 10px; border: 1px solid #1e3a8a; pointer-events: none; }
        .header { text-align: center; border-bottom: 3px solid #1e3a8a; padding-bottom: 1.5rem; margin-bottom: 2rem; }
        .seal { width: 120px; height: 120px; border: 3px solid #1e3a8a; border-radius: 50%; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #1e3a8a; font-size: 2rem; text-align: center; transform: rotate(-15deg); }
        h1 { color: #1e3a8a; margin: 0; font-size: 2.5rem; text-transform: uppercase; letter-spacing: 2px; }
        .presented { font-style: italic; font-size: 1.2rem; margin: 1.5rem 0; color: #374151; }
        .recipient { font-size: 1.5rem; font-weight: bold; color: #1e3a8a; margin: 1rem 0; }
        .program { font-size: 1.1rem; color: #4b5563; margin-bottom: 2rem; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin: 2rem 0; }
        .info-box { padding: 1rem; background: #f8fafc; border-radius: 0.5rem; border: 1px solid #e5e7eb; }
        .info-label { font-size: 0.85rem; color: #6b7280; display: block; margin-bottom: 0.25rem; }
        .info-value { font-size: 1.1rem; font-weight: 600; color: #1f2937; }
        .honors-badge { display: inline-block; padding: 0.5rem 1.5rem; background: #fef3c7; color: #92400e; font-weight: bold; border: 1px solid #f59e0b; margin: 1rem 0; }
        .verified-badge { display: flex; align-items: center; justify-content: center; gap: 0.75rem; color: #059669; font-size: 1.1rem; font-weight: bold; margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid #1e3a8a; }
        .footer { text-align: center; margin-top: 2rem; color: #6b7280; font-size: 0.9rem; }
        .stamp { position: absolute; bottom: 80px; right: 80px; border: 3px solid #ef4444; color: #ef4444; padding: 0.5rem 1rem; transform: rotate(15deg); font-weight: bold; opacity: 0.7; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="seal">AUTHENTIC</div>
            <h1>Diploma</h1>
            <p style="color:#6b7280; letter-spacing: 1px;">TESDA AUTO MECHANIC TRAINING CENTRE</p>
            <p style="color:#6b7280; font-size: 0.9rem;">This diploma has been verified as authentic</p>
        </div>

        <div class="presented">
            This diploma is presented to
        </div>
        <div class="recipient">
            <?= htmlspecialchars($diploma['FirstName'] . ' ' . $diploma['LastName']) ?>
        </div>
        <div class="program">
            For completion of the <?= htmlspecialchars($diploma['diploma_type']) ?> program in<br>
            <strong><?= htmlspecialchars($diploma['program_code']) ?></strong>
            <?php if ($diploma['major']): ?>
                <br>Major in <?= htmlspecialchars($diploma['major']) ?>
            <?php endif; ?>
        </div>

        <?php if ($diploma['honors'] !== 'None'): ?>
            <div style="text-align: center;">
                <span class="honors-badge"><?= htmlspecialchars($diploma['honors']) ?></span>
            </div>
        <?php endif; ?>

        <div class="info-grid">
            <div class="info-box">
                <span class="info-label">Diploma Number</span>
                <span class="info-value"><?= htmlspecialchars($diploma['diploma_number']) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Graduation Date</span>
                <span class="info-value"><?= date('F j, Y', strtotime($diploma['graduation_date'] ?? $diploma['conferred_at'])) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">General Average</span>
                <span class="info-value"><?= number_format($diploma['general_average'], 2) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Units Earned</span>
                <span class="info-value"><?= $diploma['units_earned'] ?></span>
            </div>
        </div>

        <div class="verified-badge">
            <i class="fas fa-check-circle"></i>
            Verification Code: <code><?= htmlspecialchars($code) ?></code>
        </div>

        <?php if ($diploma['conferred']): ?>
            <div class="stamp">AWARDED</div>
        <?php endif; ?>

        <div class="footer">
            <p>Verification timestamp: <?= date('F j, Y g:i A') ?></p>
            <p>For official verification, please visit our Registrar's Office</p>
            <p style="font-size: 0.8rem; margin-top: 1rem;">© <?= date('Y') ?> TESDA Auto Mechanic Training Centre</p>
        </div>
    </div>
</body>
</html>
