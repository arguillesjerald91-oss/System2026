<?php
session_start();
include '../db.php';
$database = new Database();
$conn = $database->getConnection();

$app_id = $_GET['app_id'] ?? 0;
if (!$app_id) {
    echo '';
    exit;
}

$stmt = $conn->prepare("SELECT n.*, u.username FROM application_notes n LEFT JOIN users u ON n.user_id = u.user_id WHERE n.pre_enroll_id = ? ORDER BY n.created_at DESC");
$stmt->execute([$app_id]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($notes)) {
    echo '';
    exit;
}

foreach ($notes as $note) {
    $date = date('M d, Y h:i A', strtotime($note['created_at']));
    $author = htmlspecialchars($note['username'] ?? 'Unknown');
    $content = htmlspecialchars($note['note']);
    echo <<<HTML
<div style="background:#f8fafc;padding:10px;border-radius:6px;margin-bottom:8px;border:1px solid #e2e8f0;">
    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:4px;">
        <span style="font-size:12px;color:#2563eb;font-weight:600;">{$author}</span>
        <button type="button" onclick="deleteNote({$note['note_id']})" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px;padding:0;">&times;</button>
    </div>
    <div style="font-size:13px;color:#374151;">{$content}</div>
    <div style="font-size:11px;color:#94a3b8;margin-top:4px;">{$date}</div>
</div>
HTML;
}