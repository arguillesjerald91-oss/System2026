<?php
/**
 * Public Transcript Verification
 * URL: verify/transcript.php?code=VER-XXXXX
 */

$code = $_GET['code'] ?? '';

if (!$code) {
    die('Verification code required');
}

include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("
    SELECT 
        t.*, s.FirstName, s.LastName, p.program_code, p.program_title
    FROM transcripts t
    JOIN student s ON t.student_id = s.StudID
    JOIN auto_mechanic_programs p ON t.program_id = p.program_id
    WHERE t.verification_code = ?
");

$stmt->execute([$code]);
$transcript = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transcript) {
    die('Invalid verification code or transcript not found.');
}

// Increment access count
$conn->prepare("UPDATE transcripts SET access_count = access_count + 1, last_accessed = NOW() WHERE transcript_id = ?")->execute([$transcript['transcript_id']]);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Transcript Verification - TESDA Auto Mechanic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f8fafc; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 0.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); padding: 2rem; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 1.5rem; margin-bottom: 2rem; }
        .logo { width: 80px; height: 80px; background: #1e3a8a; border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold; }
        h1 { color: #1e3a8a; margin: 0; }
        .status-badge { display: inline-block; padding: 0.5rem 1rem; border-radius: 9999px; font-weight: bold; font-size: 0.9rem; }
        .status-issued { background: #dcfce7; color: #166534; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin: 1.5rem 0; }
        .info-box { padding: 1rem; background: #f8fafc; border-radius: 0.5rem; border-left: 4px solid #2563eb; }
        .info-label { font-size: 0.85rem; color: #6b7280; display: block; margin-bottom: 0.25rem; }
        .info-value { font-size: 1.1rem; font-weight: 600; }
        .gpa-box { text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #1e3a8a, #2563eb); color: white; border-radius: 0.5rem; }
        .gpa-number { font-size: 2.5rem; font-weight: bold; }
        .verified-badge { display: flex; align-items: center; justify-content: center; gap: 0.5rem; color: #10b981; font-size: 1.25rem; font-weight: bold; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #e5e7eb; }
        .footer { text-align: center; margin-top: 2rem; color: #6b7280; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">TA</div>
            <h1>Transcript of Records</h1>
            <p>TESDA Auto Mechanic Training Centre</p>
            <p style="color: #6b7280; font-size: 0.9rem;">This is a digitally verified transcript</p>
        </div>

        <div class="status-badge status-issued">
            ✓ VERIFIED - This transcript is authentic
        </div>

        <div class="info-grid">
            <div class="info-box">
                <span class="info-label">Transcript Number</span>
                <span class="info-value"><?= htmlspecialchars($transcript['transcript_number']) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Student Name</span>
                <span class="info-value"><?= htmlspecialchars($transcript['FirstName'] . ' ' . $transcript['LastName']) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Student ID</span>
                <span class="info-value"><?= htmlspecialchars($transcript['SchoolID']) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Program</span>
                <span class="info-value"><?= htmlspecialchars($transcript['program_code']) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Issue Date</span>
                <span class="info-value"><?= date('F j, Y', strtotime($transcript['issue_date'])) ?></span>
            </div>
            <div class="info-box">
                <span class="info-label">Academic Standing</span>
                <span class="info-value"><?= htmlspecialchars($transcript['academic_standing']) ?></span>
            </div>
        </div>

        <div class="gpa-box">
            <div style="font-size: 0.9rem; opacity: 0.9;">GPA</div>
            <div class="gpa-number"><?= number_format($transcript['gpa'], 2) ?></div>
            <div style="font-size: 0.9rem;">of <?= $transcript['total_units'] ?> Units</div>
        </div>

        <?php if ($transcript['honors']): ?>
            <div style="text-align: center; margin-top: 1rem; padding: 0.75rem; background: #fef3c7; border-radius: 0.5rem;">
                <strong style="color: #92400e;">Honors: <?= htmlspecialchars($transcript['honors']) ?></strong>
            </div>
        <?php endif; ?>

        <div class="verified-badge">
            <i class="fas fa-check-circle"></i>
            <span>Verification Code: <code><?= htmlspecialchars($code) ?></code></span>
        </div>

        <div class="footer">
            <p>This transcript was verified on <?= date('F j, Y g:i A') ?></p>
            <p>For more information, contact the Registrar's Office</p>
            <p style="font-size: 0.8rem; margin-top: 1rem;">© <?= date('Y') ?> TESDA Auto Mechanic Training Centre</p>
        </div>
    </div>
</body>
</html>
