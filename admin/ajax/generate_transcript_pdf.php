<?php
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$transcriptId = $_GET['id'] ?? null;
if (!$transcriptId) die('ID required');

$stmt = $conn->prepare("
    SELECT t.*, s.FirstName, s.LastName, s.SchoolID, p.program_code, p.program_title
    FROM transcripts t
    JOIN student s ON t.student_id = s.StudID
    JOIN auto_mechanic_programs p ON t.program_id = p.program_id
    WHERE t.transcript_id = ?
");
$stmt->execute([$transcriptId]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) die('Record not found');

$grades = $conn->prepare("SELECT * FROM transcript_grades WHERE transcript_id = ?");
$grades->execute([$transcriptId]);
$grades->fetchAll(PDO::FETCH_ASSOC);

// PDF generation placeholder
$pdfPath = '../uploads/transcripts/transcript_' . $transcriptId . '.pdf';
if (!file_exists($pdfPath)) {
    // Generate simple HTML that can be printed/saved as PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 20px; margin-bottom: 30px; }
            .logo { width: 100px; height: 100px; background: #1e3a8a; border-radius: 50%; margin: 0 auto 20px; }
            h1 { color: #1e3a8a; margin: 0; }
            .student-info { margin-bottom: 20px; }
            .gpa-box { border: 2px solid #2563eb; padding: 10px; display: inline-block; text-align: center; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background: #f3f4f6; padding: 10px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #e5e7eb; }
            .footer { margin-top: 40px; text-align: center; color: #6b7280; font-size: 0.9rem; }
            .verification { margin-top: 30px; padding: 15px; background: #f8fafc; border-radius: 5px; font-family: monospace; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="logo"></div>
            <h1>TRANSCRIPT OF RECORDS</h1>
            <p>TESDA Auto Mechanic Training Centre</p>
        </div>

        <div class="student-info">
            <strong>Name:</strong> ' . htmlspecialchars($record['FirstName'] . ' ' . $record['LastName']) . '<br>
            <strong>Student ID:</strong> ' . htmlspecialchars($record['SchoolID']) . '<br>
            <strong>Program:</strong> ' . htmlspecialchars($record['program_code'] . ' - ' . $record['program_title']) . '<br>
            <strong>Enrollment:</strong> ' . htmlspecialchars($record['enrollment_id']) . '<br>
            <strong>Issue Date:</strong> ' . htmlspecialchars($record['issue_date']) . '
        </div>

        <div class="gpa-box">
            <div style="font-weight: bold; color: #6b7280;">GPA</div>
            <div style="font-size: 2rem; font-weight: bold; color: #2563eb;">' . number_format($record['gpa'], 2) . '</div>
            <div style="font-size: 0.9rem; color: #6b7280;">of ' . $record['total_units'] . ' units</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Units</th>
                    <th>Grade</th>
                    <th>Grade Point</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($grades as $g) {
        $html .= '<tr>
            <td>' . htmlspecialchars($g['course_title']) . ' (' . htmlspecialchars($g['course_code']) . ')</td>
            <td>' . $g['units'] . '</td>
            <td>' . htmlspecialchars($g['grade']) . '</td>
            <td>' . $g['grade_point'] . '</td>
        </tr>';
    }
    $html .= '
            </tbody>
        </table>

        <div class="verification">
            <strong>Verification Code:</strong> ' . htmlspecialchars($record['verification_code'] ?? 'N/A') . '<br>
            <strong>Verification URL:</strong> ' . htmlspecialchars($record['verification_url'] ?? 'N/A') . '
        </div>

        <div class="footer">
            This transcript has been digitally generated and is valid when verified through our online system.<br>
            Generated on: ' . date('F j, Y') . '
        </div>
    </body>
    </html>';

    file_put_contents($pdfPath, $html);
}

?>
<div style="padding: 20px;">
    <h2 style="color:#1e3a8a;">Transcript Ready</h2>
    <p>PDF generated at: <?= htmlspecialchars($pdfPath) ?></p>
    <p>To integrate actual PDF generation, install TCPDF or Dompdf library and implement proper generation with school logo, signatures, and official formatting.</p>
    <a href="<?= htmlspecialchars($pdfPath) ?>" target="_blank" class="btn btn-primary">Open PDF</a>
    <button onclick="window.close()" class="btn btn-secondary">Close</button>
</div>
