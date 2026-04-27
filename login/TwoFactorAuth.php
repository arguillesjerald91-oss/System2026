<?php
/**
 * Two-Factor Authentication (2FA) Handler
 * Supports Google Authenticator / TOTP
 */

class TwoFactorAuth {
    private $conn;
    private $issuer = 'TESDA';
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Generate a new 2FA secret
     */
    public function generateSecret($length = 16) {
        return $this->base32Encode(random_bytes($length));
    }
    
    /**
     * Generate Base32 encoded string
     */
    private function base32Encode($data) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $i = 0;
        while (strlen($output) < 16) {
            $output .= $alphabet[ord($data[$i]) & 31];
            $i++;
        }
        return $output;
    }
    
    /**
     * Generate a proper Base32 secret
     */
    public function create_secret($length = 16) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $characters[random_int(0, 31)];
        }
        return $secret;
    }
    
    /**
     * Get Google Authenticator QR Code URL
     */
    public function getQRCodeUrl($issuer, $accountName, $secret) {
        $url = 'otpauth://totp/' . rawurlencode($issuer . ':' . $accountName) 
             . '?secret=' . $secret 
             . '&issuer=' . rawurlencode($issuer) 
             . '&algorithm=SHA1&digits=6&period=30';
        return $url;
    }
    
    /**
     * Get QR code as base64 image (using Google Charts API)
     */
    public function getQRCodeImage($issuer, $accountName, $secret) {
        $url = $this->getQRCodeUrl($issuer, $accountName, $secret);
        return 'https://chart.googleapis.com/chart?cht=qr&chl=' . rawurlencode($url) . '&chs=200x200';
    }
    
    /**
     * Verify TOTP code
     */
    public function verifyCode($secret, $code) {
        $secret = str_replace(' ', '', strtoupper($secret));
        
        // Check current and adjacent time windows
        $time = floor(time() / 30);
        
        for ($i = -1; $i <= 1; $i++) {
            $expected = $this->getCode($secret, $time + $i);
            if ($code === $expected) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get TOTP code for a specific time
     */
    private function getCode($secret, $time) {
        $secret = base64_decode($this->base32Decode($secret));
        $time = pack('N*', 0) . pack('N*', $time);
        $hash = hash_hmac('sha1', $time, $secret, true);
        $offset = ord($hash[19]) & 0x0F;
        $unpacked = unpack('N', substr($hash, $offset, 4));
        return ($unpacked[1] & 0x7FFFFFFF) % 1000000;
    }
    
    /**
     * Base32 decode helper
     */
    private function base32Decode($data) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vv = 0;
        
        foreach (str_split(strtoupper($data)) as $char) {
            if ($char === '=') continue;
            $v = ($v << 5) | strpos($alphabet, $char);
            $vv += 5;
            if ($vv >= 8) {
                $output .= chr(($v >> ($vv - 8)) & 0xFF);
                $vv -= 8;
            }
        }
        return $output;
    }
    
    /**
     * Enable 2FA for a user
     */
    public function enable2FA($userId, $secret) {
        $stmt = $this->conn->prepare("
            UPDATE users SET twofa_secret = ?, twofa_enabled = 1 
            WHERE user_id = ?
        ");
        return $stmt->execute([$secret, $userId]);
    }
    
    /**
     * Disable 2FA for a user
     */
    public function disable2FA($userId) {
        $stmt = $this->conn->prepare("
            UPDATE users SET twofa_secret = NULL, twofa_enabled = 0 
            WHERE user_id = ?
        ");
        return $stmt->execute([$userId]);
    }
    
    /**
     * Check if user has 2FA enabled
     */
    public function is2FAEnabled($userId) {
        $stmt = $this->conn->prepare("SELECT twofa_enabled FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['twofa_enabled'] == 1;
    }
    
    /**
     * Get user's 2FA secret
     */
    public function getSecret($userId) {
        $stmt = $this->conn->prepare("SELECT twofa_secret FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['twofa_secret'] : null;
    }
}

/**
 * Simple 2FA without external libraries
 * Uses a secure random code sent via email as backup
 */
class Simple2FA {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Generate a 6-digit code
     */
    public function generateCode() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Send 2FA code via email
     */
    public function sendCode($email, $code, $userName) {
        $subject = 'Your Verification Code - TESDA';
        $body = $this->getEmailBody($email, $code, $userName);
        
        $headers = "From: TESDA <noreply@tesda.gov.ph>\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($email, $subject, $body, $headers);
    }
    
    /**
     * Store the code in database
     */
    public function storeCode($userId, $code) {
        $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        // Delete any existing codes for this user
        $stmt = $this->conn->prepare("DELETE FROM twofa_codes WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Insert new code
        $stmt = $this->conn->prepare("
            INSERT INTO twofa_codes (user_id, code, expires_at) 
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([$userId, $code, $expires]);
    }
    
    /**
     * Verify the code
     */
    public function verifyCode($userId, $code) {
        $stmt = $this->conn->prepare("
            SELECT code FROM twofa_codes 
            WHERE user_id = ? AND code = ? AND expires_at > NOW() AND used = 0
        ");
        $stmt->execute([$userId, $code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Mark as used
            $this->markUsed($userId);
            return true;
        }
        return false;
    }
    
    /**
     * Mark code as used
     */
    private function markUsed($userId) {
        $stmt = $this->conn->prepare("UPDATE twofa_codes SET used = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
    
    /**
     * Get email body
     */
    private function getEmailBody($email, $code, $userName) {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verification Code</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fc; padding: 30px; border-radius: 10px;">
        <h2 style="color: #2563eb; margin-bottom: 20px;">Two-Factor Authentication</h2>
        <p>Hi ' . htmlspecialchars($userName) . ',</p>
        <p>Your verification code is:</p>
        <div style="background: #ffffff; padding: 20px; text-align: center; font-size: 32px; letter-spacing: 10px; font-weight: bold; margin: 20px 0; border-radius: 8px; border: 2px solid #2563eb;">
            ' . $code . '
        </div>
        <p style="color: #6b7280; font-size: 14px;">This code will expire in 5 minutes.</p>
        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
        <p style="color: #9ca3af; font-size: 12px;">If you did not request this code, please ignore this email or contact support.</p>
    </div>
</body>
</html>';
    }
}