<?php
/**
 * Email Verification Helper
 * Handles email verification and login notifications
 */

// Application Configuration (inline to avoid dependency)
class AppConfig {
    const APP_NAME = 'TESDA';
    const APP_URL = 'http://localhost';
    const EMAIL_FROM = 'ladreracherielove10@gmail.com';
    const EMAIL_FROM_NAME = 'TESDA-TESDA';
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USERNAME = 'ladreracherielove10@gmail.com';
    const SMTP_PASSWORD = 'vgaz fjwb chix loly';
    
    public static function getUrl($path = '') {
        return rtrim(self::APP_URL, '/') . '/' . ltrim($path, '/');
    }
}

class EmailVerification {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Generate a secure verification token
     */
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Send verification email
     */
    public function sendVerificationEmail($user, $tokenType = 'verification') {
        $token = $this->generateToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Store the token
        $this->storeToken($user['user_id'], $user['email'], $token, $tokenType, $expiresAt, $ipAddress, $userAgent);
        
        // Build verification URL
        $verifyUrl = AppConfig::getUrl('login/verify_email.php?token=' . $token);
        
        // Email subject and body
        if ($tokenType === 'login_verification') {
            $subject = 'Login Verification Code - ' . AppConfig::APP_NAME;
            $body = $this->getLoginVerificationEmailBody($user, $token, $verifyUrl);
        } else {
            $subject = 'Verify Your Email - ' . AppConfig::APP_NAME;
            $body = $this->getVerificationEmailBody($user, $verifyUrl);
        }
        
        // Send email
        return $this->sendEmail($user['email'], $subject, $body);
    }
    
    /**
     * Verify email token
     */
    public function verifyToken($token, $tokenType = 'verification') {
        $stmt = $this->conn->prepare("
            SELECT ev.*, u.username, u.email, u.first_name, u.last_name
            FROM email_verifications ev
            JOIN users u ON ev.user_id = u.user_id
            WHERE ev.token = ? AND ev.token_type = ? 
            AND ev.used_at IS NULL 
            AND ev.expires_at > NOW()
        ");
        $stmt->execute([$token, $tokenType]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mark token as used
     */
    public function markTokenUsed($token) {
        $stmt = $this->conn->prepare("UPDATE email_verifications SET used_at = NOW() WHERE token = ?");
        return $stmt->execute([$token]);
    }
    
    /**
     * Mark email as verified
     */
    public function markEmailVerified($userId) {
        $stmt = $this->conn->prepare("UPDATE users SET email_verified = 1, email_verified_at = NOW() WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }
    
    /**
     * Store verification token
     */
    private function storeToken($userId, $email, $token, $tokenType, $expiresAt, $ipAddress, $userAgent) {
        $stmt = $this->conn->prepare("
            INSERT INTO email_verifications (user_id, email, token, token_type, expires_at, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$userId, $email, $token, $tokenType, $expiresAt, $ipAddress, $userAgent]);
    }
    
    /**
     * Check if email is verified
     */
    public function isEmailVerified($userId) {
        $stmt = $this->conn->prepare("SELECT email_verified FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['email_verified'] == 1;
    }
    
    /**
     * Send login notification
     */
    public function sendLoginNotification($user, $ipAddress) {
        // Check if user has notifications enabled
        $stmt = $this->conn->prepare("
            SELECT is_enabled FROM login_notifications 
            WHERE user_id = ? AND notification_type = 'login_alert'
        ");
        $stmt->execute([$user['user_id']]);
        $notificationSetting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Default to sending if no preference set
        if ($notificationSetting && $notificationSetting['is_enabled'] == 0) {
            return true; // User disabled notifications
        }
        
        $subject = 'New Login Detected - ' . AppConfig::APP_NAME;
        $body = $this->getLoginNotificationBody($user, $ipAddress);
        
        $result = $this->sendEmail($user['email'], $subject, $body);
        
        // Update last_notified_at
        if ($result) {
            $this->conn->prepare("
                UPDATE login_notifications SET last_notified_at = NOW() 
                WHERE user_id = ? AND notification_type = 'login_alert'
            ")->execute([$user['user_id']]);
        }
        
        return $result;
    }
    
    /**
     * Send email using SMTP
     */
    private function sendEmail($to, $subject, $body) {
        $headers = "From: " . AppConfig::EMAIL_FROM_NAME . " <" . AppConfig::EMAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . AppConfig::EMAIL_FROM . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $headers = "From: " . AppConfig::EMAIL_FROM_NAME . " <" . AppConfig::EMAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . AppConfig::EMAIL_FROM . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Use mail() function as fallback (works on most servers)
        if (AppConfig::SMTP_HOST === '') {
            return mail($to, $subject, $body, $headers);
        }
        
        // Try using SMTP mailer class if available
        if (class_exists('SMTPMailer')) {
            return $this->sendSMTPEmail($to, $subject, $body);
        }
        
        // Fallback to PHP mail()
        return mail($to, $subject, $body, $headers);
    }
    
    /**
     * Send email via SMTP (custom implementation)
     */
    private function sendSMTPEmail($to, $subject, $body) {
        $smtpHost = AppConfig::SMTP_HOST;
        $smtpPort = AppConfig::SMTP_PORT;
        $smtpUsername = AppConfig::SMTP_USERNAME;
        $smtpPassword = AppConfig::SMTP_PASSWORD;
        
        $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 30);
        if (!$socket) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            $headers = "From: " . AppConfig::EMAIL_FROM_NAME . " <" . AppConfig::EMAIL_FROM . ">\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            return mail($to, $subject, $body, $headers);
        }
        
        // Read greeting
        fgets($socket, 512);
        
        // EHLO
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        $this->readSMTPResponse($socket);
        
        // STARTTLS
        fputs($socket, "STARTTLS\r\n");
        $this->readSMTPResponse($socket);
        
        // Upgrade to TLS
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        // EHLO again
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        $this->readSMTPResponse($socket);
        
        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $this->readSMTPResponse($socket);
        
        // Username
        fputs($socket, base64_encode($smtpUsername) . "\r\n");
        $this->readSMTPResponse($socket);
        
        // Password
        fputs($socket, base64_encode($smtpPassword) . "\r\n");
        $this->readSMTPResponse($socket);
        
        // MAIL FROM
        fputs($socket, "MAIL FROM: <" . AppConfig::EMAIL_FROM . ">\r\n");
        $this->readSMTPResponse($socket);
        
        // RCPT TO
        fputs($socket, "RCPT TO: <$to>\r\n");
        $this->readSMTPResponse($socket);
        
        // DATA
        fputs($socket, "DATA\r\n");
        $this->readSMTPResponse($socket);
        
        $message = "From: " . AppConfig::EMAIL_FROM_NAME . " <" . AppConfig::EMAIL_FROM . ">\r\n";
        $message .= "To: $to\r\n";
        $message .= "Subject: $subject\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $body . "\r\n.\r\n";
        
        fputs($socket, $message);
        $this->readSMTPResponse($socket);
        
        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
    }
    
    private function readSMTPResponse($socket) {
        $response = '';
        while ($char = fgets($socket, 512)) {
            $response .= $char;
            if (substr($char, 3, 1) === ' ') break;
        }
        return $response;
    }
    
    /**
     * Get verification email body
     */
    private function getVerificationEmailBody($user, $verifyUrl) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email Verification</title>
        </head>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #f8f9fc; padding: 30px; border-radius: 10px;">
                <h2 style="color: #2563eb; margin-bottom: 20px;">Email Verification</h2>
                <p>Hi ' . htmlspecialchars($user['first_name']) . ',</p>
                <p>Thank you for registering with ' . AppConfig::APP_NAME . '. Please verify your email address by clicking the button below:</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $verifyUrl . '" style="background: #2563eb; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block;">Verify Email Address</a>
                </div>
                <p style="color: #6b7280; font-size: 14px;">Or copy and paste this link in your browser:<br>' . $verifyUrl . '</p>
                <p style="color: #6b7280; font-size: 14px;">This link will expire in 24 hours.</p>
                <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
                <p style="color: #9ca3af; font-size: 12px;">If you did not create this account, please ignore this email.</p>
            </div>
        </body>
        </html>
        ';
    }
    
    /**
     * Get login verification email body
     */
    private function getLoginVerificationEmailBody($user, $token, $verifyUrl) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login Verification</title>
        </head>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #f8f9fc; padding: 30px; border-radius: 10px;">
                <h2 style="color: #2563eb; margin-bottom: 20px;">Login Verification Code</h2>
                <p>Hi ' . htmlspecialchars($user['first_name']) . ',</p>
                <p>We detected a login attempt to your account. Use the verification code below:</p>
                <div style="background: #e5e7eb; padding: 20px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 8px;">
                    ' . substr($token, 0, 6) . '
                </div>
                <p style="color: #6b7280; font-size: 14px;">Or click to verify: ' . $verifyUrl . '</p>
                <p style="color: #6b7280; font-size: 14px;">This code will expire in 15 minutes.</p>
                <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
                <p style="color: #9ca3af; font-size: 12px;">If this was not you, please secure your account by changing your password immediately.</p>
            </div>
        </body>
        </html>
        ';
    }
    
    /**
     * Get login notification email body
     */
    private function getLoginNotificationBody($user, $ipAddress) {
        $loginTime = date('F j, Y \a\g \a\i H:i:s');
        $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login Alert</title>
        </head>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #f8f9fc; padding: 30px; border-radius: 10px;">
                <h2 style="color: #16a34a; margin-bottom: 20px;">New Login Detected</h2>
                <p>Hi ' . htmlspecialchars($user['first_name']) . ',</p>
                <p>A new login was detected on your account:</p>
                <ul style="background: #ffffff; padding: 20px; border-radius: 8px; list-style: none;">
                    <li style="margin-bottom: 10px;"><strong>Time:</strong> ' . $loginTime . '</li>
                    <li style="margin-bottom: 10px;"><strong>IP Address:</strong> ' . htmlspecialchars($ipAddress) . '</li>
                    <li><strong>Browser:</strong> ' . htmlspecialchars($browser) . '</li>
                </ul>
                <p style="color: #6b7280; font-size: 14px; margin-top: 20px;">If this was you, no action is needed.</p>
                <p style="color: #dc2626; font-size: 14px;">If this was not you, please change your password immediately.</p>
            </div>
        </body>
        </html>
        ';
    }
}

/**
 * Notification helper functions
 */
class NotificationHelper {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get notification preferences for a user
     */
    public function getPreferences($userId) {
        $stmt = $this->conn->prepare("
            SELECT notification_type, method, is_enabled 
            FROM login_notifications WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update notification preference
     */
    public function updatePreference($userId, $notificationType, $method, $isEnabled) {
        $stmt = $this->conn->prepare("
            INSERT INTO login_notifications (user_id, notification_type, method, is_enabled)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE method = VALUES(method), is_enabled = VALUES(is_enabled)
        ");
        return $stmt->execute([$userId, $notificationType, $method, $isEnabled ? 1 : 0]);
    }
    
    /**
     * Create default notification preferences for a user
     */
    public function createDefaultPreferences($userId) {
        $preferences = [
            ['login_alert', 'email', 1],
            ['password_change', 'email', 1],
            ['email_change', 'email', 1],
            ['security_alert', 'email', 1]
        ];
        
        foreach ($preferences as $pref) {
            $this->updatePreference($userId, $pref[0], $pref[1], $pref[2]);
        }
    }
}