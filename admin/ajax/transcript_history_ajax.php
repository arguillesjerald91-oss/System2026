<?php
session_start();
header('Content-Type: text/html');

include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$id = $_GET['transcript_id'] ?? null;
if (!$id) die('Transcript ID required');

$stmt = $conn->prepare("SELECT * FROM transcript_history WHERE transcript_id = ? ORDER BY changed_at DESC LIMIT 50");
$stmt->execute([$id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($history)) {
    echo '<p style="text-align:center; color:#6b7280; padding:2rem;">No history recorded yet.</p>';
    exit();
}
?>

<div style="max-height: 400px; overflow-y: auto;">
    <?php foreach ($history as $h): ?>
    <div style="padding: 0.75rem; border-left: 3px solid #2563eb; background: #f8fafc; margin-bottom: 0.5rem; border-radius: 0 4px 4px 0;">
        <div style="display: flex; justify-content: space-between; font-weight: 600;">
            <?= htmlspecialchars($h['change_type']) ?>
            <span><?= date('M j, Y H:i', strtotime($h['changed_at'])) ?></span>
        </div>
        <?php if ($h['field_changed']): ?>
            <div style="margin-top: 0.5rem;">
                <strong><?= htmlspecialchars($h['field_changed']) ?></strong><br>
                <span style="color: #ef4444;">Old: <?= htmlspecialchars($h['old_value'] ?? '') ?></span><br>
                <span style="color: #10b981;">New: <?= htmlspecialchars($h['new_value'] ?? '') ?></span>
            </div>
        <?php endif; ?>
        <?php if ($h['change_reason']): ?>
            <div style="font-style: italic; font-size: 0.9rem; color: #6b7280; margin-top: 0.25rem;">
                Reason: <?= htmlspecialchars($h['change_reason']) ?>
            </div>
        <?php endif; ?>
        <div style="font-size: 0.85rem; color: #9ca3af; margin-top: 0.25rem;">
            By user ID: <?= $h['changed_by'] ?> | IP: <?= htmlspecialchars($h['ip_address']) ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
