<?php
/**
 * AJAX: View Transcript Details
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$transcriptId = $_GET['id'] ?? null;

if (!$transcriptId) {
    die('Transcript ID required');
}

// Fetch transcript with all related data
$stmt = $conn->prepare("
    SELECT 
        t.*,
        s.FirstName, s.LastName, s.SchoolID, s.EmailAddr AS email,
        p.program_code, p.program_title,
        tb.batch_code,
        CONCAT(pr.Fname, ' ', pr.Lname) as prepared_by,
        CONCAT(ap.Fname, ' ', ap.Lname) as approved_by,
        CONCAT(isu.Fname, ' ', isu.Lname) as issued_by,
        CONCAT(dl.Fname, ' ', dl.Lname) as delivered_by
    FROM transcripts t
    JOIN student s ON t.student_id = s.StudID
    JOIN auto_mechanic_programs p ON t.program_id = p.program_id
    LEFT JOIN training_batches tb ON t.batch_id = tb.batch_id
    LEFT JOIN admins pr ON t.prepared_by = pr.admin_id
    LEFT JOIN admins ap ON t.approved_by = ap.admin_id
    LEFT JOIN admins isu ON t.issued_by = isu.admin_id
    LEFT JOIN admins dl ON t.delivered_by = dl.admin_id
    WHERE t.transcript_id = ?
");

$stmt->execute([$transcriptId]);
$transcript = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transcript) {
    die('Transcript not found');
}

// Fetch grades
$gradesStmt = $conn->prepare("
    SELECT * FROM transcript_grades 
    WHERE transcript_id = ? 
    ORDER BY taken_date, module_id
");
$gradesStmt->execute([$transcriptId]);
$grades = $gradesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent history
$historyStmt = $conn->prepare("
    SELECT * FROM transcript_history 
    WHERE transcript_id = ? 
    ORDER BY changed_at DESC 
    LIMIT 20
");
$historyStmt->execute([$transcriptId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.record-detail {
    margin-bottom: 2rem;
}
.record-detail h4 {
    color: #1e3a8a;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}
.info-item {
    display: flex;
    flex-direction: column;
}
.info-label {
    font-size: 0.85rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}
.info-value {
    font-size: 1rem;
    font-weight: 500;
}
.grades-table th {
    background: #f3f4f6;
}
.grades-table td, .grades-table th {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.history-list {
    max-height: 300px;
    overflow-y: auto;
}
.history-item {
    padding: 0.75rem;
    border-left: 3px solid #2563eb;
    background: #f8fafc;
    margin-bottom: 0.5rem;
    border-radius: 0 4px 4px 0;
}
.history-meta {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 0.25rem;
}
.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
}
.status-draft { background: #e5e7eb; color: #1f2937; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-approved { background: #d1fae5; color: #065f46; }
.status-issued { background: #dbeafe; color: #1e40af; }
.status-delivered { background: #dcfce7; color: #166534; }
.status-archived { background: #f3f4f6; color: #4b5563; }
</style>

<div class="record-detail">
    <h4>Transcript Information</h4>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Transcript Number</span>
            <span class="info-value"><?= htmlspecialchars($transcript['transcript_number']) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Student</span>
            <span class="info-value"><?= htmlspecialchars($transcript['FirstName'] . ' ' . $transcript['LastName']) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Student ID</span>
            <span class="info-value"><?= htmlspecialchars($transcript['SchoolID']) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Program</span>
            <span class="info-value"><?= htmlspecialchars($transcript['program_code'] . ' - ' . $transcript['program_title']) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Batch</span>
            <span class="info-value"><?= htmlspecialchars($transcript['batch_code'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Issue Date</span>
            <span class="info-value"><?= htmlspecialchars($transcript['issue_date']) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">GPA</span>
            <span class="info-value" style="font-size:1.25rem; font-weight:bold; color:#2563eb;">
                <?= number_format($transcript['gpa'], 2) ?>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">Total Units</span>
            <span class="info-value"><?= $transcript['total_units'] ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Total Hours</span>
            <span class="info-value"><?= $transcript['total_hours'] ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Honors</span>
            <span class="info-value"><?= htmlspecialchars($transcript['honors'] ?? 'None') ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Status</span>
            <span class="info-value">
                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $transcript['status'])) ?>">
                    <?= htmlspecialchars($transcript['status']) ?>
                </span>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">Verification</span>
            <span class="info-value">
                <?php if ($transcript['verification_code']): ?>
                    <code style="font-size:0.85rem;"><?= htmlspecialchars($transcript['verification_code']) ?></code>
                <?php else: ?>
                    Not set
                <?php endif; ?>
            </span>
        </div>
    </div>

    <?php if ($transcript['remarks']): ?>
        <div class="info-item" style="margin-top:1rem;">
            <span class="info-label">Remarks</span>
            <span class="info-value"><?= nl2br(htmlspecialchars($transcript['remarks'])) ?></span>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($grades)): ?>
<div class="record-detail">
    <h4>Grades & Courses (<?= count($grades) ?> entries)</h4>
    <div style="overflow-x:auto;">
        <table class="grades-table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Units</th>
                    <th>Hours</th>
                    <th>Grade</th>
                    <th>Grade Point</th>
                    <th>Type</th>
                    <th>Semester</th>
                    <th>Year</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grades as $g): ?>
                <tr>
                    <td><?= htmlspecialchars($g['course_code']) ?></td>
                    <td><?= htmlspecialchars($g['course_title']) ?></td>
                    <td><?= $g['units'] ?></td>
                    <td><?= $g['contact_hours'] ?></td>
                    <td><strong><?= htmlspecialchars($g['grade']) ?></strong></td>
                    <td><?= $g['grade_point'] ?></td>
                    <td><?= htmlspecialchars($g['grade_type']) ?></td>
                    <td><?= htmlspecialchars($g['semester'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($g['academic_year'] ?? 'N/A') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="record-detail">
    <h4>Audit Trail</h4>
    <div class="history-list">
        <?php foreach ($history as $h): ?>
        <div class="history-item">
            <div style="display:flex; justify-content:space-between;">
                <strong><?= htmlspecialchars($h['change_type']) ?></strong>
                <span><?= date('M j, Y g:i A', strtotime($h['changed_at'])) ?></span>
            </div>
            <?php if ($h['field_changed']): ?>
                <div><?= htmlspecialchars($h['field_changed']) ?>: <?= htmlspecialchars($h['old_value'] ?? '') ?> → <?= htmlspecialchars($h['new_value'] ?? '') ?></div>
            <?php endif; ?>
            <?php if ($h['change_reason']): ?>
                <div style="font-style:italic; margin-top:0.25rem;">Reason: <?= htmlspecialchars($h['change_reason']) ?></div>
            <?php endif; ?>
            <div class="history-meta">By: <?= htmlspecialchars($h['changed_by']) ?> | IP: <?= htmlspecialchars($h['ip_address']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
