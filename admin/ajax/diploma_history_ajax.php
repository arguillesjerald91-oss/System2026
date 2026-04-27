<?php
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$id = $_GET['diploma_id'] ?? null;
if (!$id) die('Diploma ID required');

$stmt = $conn->prepare("SELECT * FROM diploma_history WHERE diploma_id = ? ORDER BY performed_at DESC LIMIT 50");
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
            <?= htmlspecialchars($h['action']) ?>
            <span><?= date('M j, Y H:i', strtotime($h['performed_at'])) ?></span>
        </div>
        <?php if ($h['field_changed']): ?>
            <div style="margin-top: 0.5rem;">
                <strong><?= htmlspecialchars($h['field_changed']) ?></strong><br>
                <span style="color: #ef4444;">Old: <?= htmlspecialchars($h['old_value'] ?? '') ?></span><br>
                <span style="color: #10b981;">New: <?= htmlspecialchars($h['new_value'] ?? '') ?></span>
            </div>
        <?php endif; ?>
        <?php if ($h['reason']): ?>
            <div style="font-style: italic; font-size: 0.9rem; color: #6b7280; margin-top: 0.25rem;">
                Reason: <?= htmlspecialchars($h['reason']) ?>
            </div>
        <?php endif; ?>
        <div style="font-size: 0.85rem; color: #9ca3af; margin-top: 0.25rem;">
            Performed by: <?= $h['performed_by'] ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
