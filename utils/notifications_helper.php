<?php
/**
 * Notification Helper for Document Management
 * Integrates with existing notifications and email_queue tables
 */

if (!function_exists('sendDocumentNotification')) {
    /**
     * Send notification to user about document status change
     *
     * @param PDO $conn Database connection
     * @param int $userId User to notify
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type document|certificate|diploma|request|system
     * @param int|null $entityId Related entity ID
     * @param string|null $actionUrl Optional action URL
     */
    function sendDocumentNotification($conn, $userId, $title, $message, $type = 'Document', $entityId = null, $actionUrl = null) {
        // Insert into notifications
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, notification_type, related_entity_type, related_entity_id, action_url, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $title, $message, 'Document Status', $type, $entityId, $actionUrl]);

        // Also queue email
        $user = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE user_id = ?");
        $user->execute([$userId]);
        $userData = $user->fetch(PDO::FETCH_ASSOC);

        if ($userData && $userData['email']) {
            $emailBody = "Dear {$userData['first_name']},\n\n";
            $emailBody .= "$message\n\n";
            if ($actionUrl) {
                $emailBody .= "Take action: https://yourdomain.edu.ph/$actionUrl\n\n";
            }
            $emailBody .= "Best regards,\nTESDA Auto Mechanic Training Centre";

            $emailStmt = $conn->prepare("
                INSERT INTO email_queue (recipient_email, recipient_name, recipient_user_id, subject, body, email_type, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'Document Status', 'Pending', NOW())
            ");
            $emailStmt->execute([
                $userData['email'],
                $userData['first_name'] . ' ' . $userData['last_name'],
                $userId,
                $title,
                $emailBody
            ]);
        }
    }
}

if (!function_exists('notifyDocumentRequestUpdate')) {
    /**
     * Notify student about document request status update
     */
    function notifyDocumentRequestUpdate($conn, $requestId, $newStatus, $remarks = '') {
        $stmt = $conn->prepare("
            SELECT dr.*, s.user_id, s.FName, s.LName
            FROM document_requests dr
            JOIN student s ON dr.student_id = s.StudID
            WHERE dr.request_id = ?
        ");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$req) return;

        $title = "Document Request Update - $newStatus";
        $message = "Your document request (#{$req['request_number']}) for {$req['document_type']} has been updated to: $newStatus.";
        if ($remarks) {
            $message .= "\n\nRemarks: $remarks";
        }
        $message .= "\n\nRequest Date: {$req['request_date']}";

        sendDocumentNotification($conn, $req['user_id'], $title, $message, 'request', $requestId, "student/request_document.php");
    }
}

if (!function_exists('notifyTranscriptIssued')) {
    function notifyTranscriptIssued($conn, $transcriptId) {
        $stmt = $conn->prepare("
            SELECT t.*, s.user_id, s.FName, s.LName, p.program_code
            FROM transcripts t
            JOIN student s ON t.student_id = s.StudID
            JOIN auto_mechanic_programs p ON t.program_id = p.program_id
            WHERE t.transcript_id = ?
        ");
        $stmt->execute([$transcriptId]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$t) return;

        $title = "Transcript of Records Issued";
        $message = "Your transcript (#{$t['transcript_number']}) for {$t['program_code']} has been issued and is ready for download.";
        $message .= "\n\nGPA: {$t['gpa']} | Total Units: {$t['total_units']}";
        $message .= "\nIssue Date: {$t['issue_date']}";

        sendDocumentNotification($conn, $t['user_id'], $title, $message, 'transcript', $transcriptId, "student/transcripts.php");
    }
}

if (!function_exists('notifyCertificateIssued')) {
    function notifyCertificateIssued($conn, $certificateId) {
        $stmt = $conn->prepare("
            SELECT c.*, s.user_id, s.FName, s.LName, p.program_code
            FROM certificates c
            JOIN student s ON c.student_id = s.StudID
            JOIN auto_mechanic_programs p ON c.program_id = p.program_id
            WHERE c.certificate_id = ?
        ");
        $stmt->execute([$certificateId]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) return;

        $title = "Certificate Issued - {$c['certificate_type']}";
        $message = "Congratulations! Your certificate for {$c['certificate_type']} has been issued.";
        $message .= "\n\nCertificate #: {$c['certificate_number']}\nIssue Date: {$c['issue_date']}";
        if ($c['nc_level']) $message .= "\nNC Level: {$c['nc_level']}";

        sendDocumentNotification($conn, $c['user_id'], $title, $message, 'certificate', $certificateId, "student/certificates.php");
    }
}

if (!function_exists('notifyDiplomaAwarded')) {
    function notifyDiplomaAwarded($conn, $diplomaId) {
        $stmt = $conn->prepare("
            SELECT d.*, s.user_id, s.FName, s.LName, p.program_code
            FROM diplomas d
            JOIN student s ON d.student_id = s.StudID
            JOIN auto_mechanic_programs p ON d.program_id = p.program_id
            WHERE d.diploma_id = ?
        ");
        $stmt->execute([$diplomaId]);
        $d = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$d) return;

        $title = "Diploma Awarded - Congratulations!";
        $message = "We are pleased to inform you that your diploma for {$d['program_code']} has been awarded.";
        $message .= "\n\nDiploma #: {$d['diploma_number']}\nGPA: {$d['general_average']}\nHonors: {$d['honors']}";
        if ($d['convocation_date']) {
            $message .= "\n\nConvocation: " . date('F j, Y', strtotime($d['convocation_date']));
        }

        sendDocumentNotification($conn, $d['user_id'], $title, $message, 'diploma', $diplomaId, "student/diplomas.php");
    }
}

// Placeholder functions for background email sending
function processEmailQueue($conn) {
    $pending = $conn->query("SELECT * FROM email_queue WHERE status = 'Pending' AND (retry_count < max_retries) LIMIT 50")->fetchAll();

    foreach ($pending as $email) {
        // Actually send email via mail() or external service
        // For now just mark as sent (or increment retry count)
        $update = $conn->prepare("UPDATE email_queue SET status = 'Sent', sent_at = NOW() WHERE email_id = ?");
        $update->execute([$email['email_id']]);
    }
}
?>
