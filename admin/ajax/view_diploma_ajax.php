<?php
/**
 * AJAX: View Diploma Details
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$diplomaId = $_GET['id'] ?? null;
if (!$diplomaId) die('Diploma ID required');

$stmt = $conn->prepare("
    SELECT d.*, s.FirstName, s.LastName, s.SchoolID,
           p.program_code, p.program_title,
           b.batch_code,
           CONCAT(pr.Fname, ' ', pr.Lname) as prepared_by,
           CONCAT(ap.Fname, ' ', ap.Lname) as approved_by,
           CONCAT(cf.Fname, ' ', cf.Lname) as conferred_by
    FROM diplomas d
    JOIN student s ON d.student_id = s.StudID
    JOIN auto_mechanic_programs p ON d.program_id = p.program_id
    LEFT JOIN training_batches b ON d.batch_id = b.batch_id
    LEFT JOIN admins pr ON d.prepared_by = pr.admin_id
    LEFT JOIN admins ap ON d.approved_by = ap.admin_id
    LEFT JOIN admins cf ON d.conferred_by = cf.admin_id
    WHERE d.diploma_id = ?
");
$stmt->execute([$diplomaId]);
$diploma = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$diploma) die('Diploma not found');

// Modules included
$modules = $conn->prepare("
    SELECT dm.*, m.module_code, m.module_title
    FROM diploma_modules dm
    JOIN training_modules m ON dm.module_id = m.module_id
    WHERE dm.diploma_id = ?
    ORDER BY m.module_code
");
$modules->execute([$diplomaId]);
$modules = $modules->fetchAll(PDO::FETCH_ASSOC);

// History
$history = $conn->prepare("SELECT * FROM diploma_history WHERE diploma_id = ? ORDER BY performed_at DESC LIMIT 20");
$history->execute([$diplomaId]);
$history = $history->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="padding: 1rem;">
    <h3 style="color: #1e3a8a; margin-bottom: 1rem;">Diploma Details</h3>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Diploma Number</div>
            <div style="font-weight: bold;"><?= htmlspecialchars($diploma['diploma_number']) ?></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Student</div>
            <div><?= htmlspecialchars($diploma['FirstName'] . ' ' . $diploma['LastName']) ?></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Program</div>
            <div><?= htmlspecialchars($diploma['program_code']) ?></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Status</div>
            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $diploma['status'])) ?>">
                <?= htmlspecialchars($diploma['status']) ?>
            </span>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Graduation Date</div>
            <div><?= htmlspecialchars($diploma['graduation_date'] ?? 'N/A') ?></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Convocation</div>
            <div><?= htmlspecialchars($diploma['convocation_date'] ?? 'N/A') ?></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">GPA</div>
            <div style="font-size: 1.25rem; font-weight: bold; color: #2563eb;">
                <?= number_format($diploma['general_average'], 2) ?>
            </div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Honors</div>
            <div><?= htmlspecialchars($diploma['honors']) ?></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Verification</div>
            <div><code><?= htmlspecialchars($diploma['verification_code'] ?? 'N/A') ?></code></div>
        </div>
        <div>
            <div style="color: #6b7280; font-size: 0.9rem;">Awarded</div>
            <div><?= $diploma['conferred'] ? '<span style="color: #10b981;"><i class="fas fa-check"></i> Yes</span>' : 'No' ?></div>
        </div>
    </div>

    <?php if (!empty($modules)): ?>
    <div style="margin-bottom: 2rem;">
        <h4>Included Modules</h4>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f3f4f6;">
                    <th style="padding: 8px;">Code</th>
                    <th style="padding: 8px;">Title</th>
                    <th style="padding: 8px;">Units</th>
                    <th style="padding: 8px;">Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $m): ?>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 8px;"><?= htmlspecialchars($m['module_code']) ?></td>
                    <td style="padding: 8px;"><?= htmlspecialchars($m['module_title']) ?></td>
                    <td style="padding: 8px;"><?= $m['units'] ?></td>
                    <td style="padding: 8px;"><?= htmlspecialchars($m['grade'] ?? 'N/A') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div>
        <h4>Audit Trail</h4>
        <div style="max-height: 200px; overflow-y: auto; background: #f8fafc; padding: 1rem; border-radius: 0.5rem;">
            <?php if (empty($history)): ?>
                <p class="text-muted">No history recorded.</p>
            <?php else: ?>
                <?php foreach ($history as $h): ?>
                <div style="padding: 8px; border-left: 3px solid #2563eb; margin-bottom: 8px; background: white;">
                    <div style="display: flex; justify-content: space-between;">
                        <strong><?= htmlspecialchars($h['action']) ?></strong>
                        <span><?= date('M j, Y H:i', strtotime($h['performed_at'])) ?></span>
                    </div>
                    <?php if ($h['reason']): ?>
                        <div style="font-style: italic; font-size: 0.9rem; color: #6b7280;">
                            <?= htmlspecialchars($h['reason']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
