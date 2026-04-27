<?php
/**
 * AJAX: View Certificate History
 */

session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$certId = $_GET['certificate_id'] ?? null;
if (!$certId) die('Certificate ID required');

$stmt = $conn->prepare("
    SELECT ch.*, CONCAT(u.first_name, ' ', u.last_name) as performer_name
    FROM certificate_history ch
    LEFT JOIN users u ON ch.performed_by = u.user_id
    WHERE ch.certificate_id = ?
    ORDER BY ch.performed_at DESC
    LIMIT 50
");
$stmt->execute([$certId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="max-height: 400px; overflow-y: auto; padding: 10px;">
    <?php if (empty($history)): ?>
        <p style="text-align: center; color: #6b7280;">No history recorded yet.</p>
    <?php else: ?>
        <?php foreach ($history as $h): ?>
        <div style="padding: 10px; border-left: 3px solid #2563eb; background: #f8fafc; margin-bottom: 8px; border-radius: 0 4px 4px 0;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <strong><?= htmlspecialchars($h['action']) ?></strong>
                <span style="font-size: 0.85rem; color: #6b7280;">
                    <?= date('M j, Y H:i', strtotime($h['performed_at'])) ?>
                </span>
            </div>
            <?php if ($h['field_changed']): ?>
                <div style="font-size: 0.9rem;">
                    <span style="color: #ef4444;"><?= htmlspecialchars($h['old_value']) ?></span>
                    → <span style="color: #10b981;"><?= htmlspecialchars($h['new_value']) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($h['reason']): ?>
                <div style="font-style: italic; margin-top: 4px; font-size: 0.9rem;">
                    Reason: <?= htmlspecialchars($h['reason']) ?>
                </div>
            <?php endif; ?>
            <?php if ($h['performer_name']): ?>
                <div style="font-size: 0.85rem; color: #9ca3af; margin-top: 4px;">
                    By: <?= htmlspecialchars($h['performer_name']) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
