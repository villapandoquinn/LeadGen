<?php

class MailService {
    private static function getSettings() {
        $file = __DIR__ . '/../config/settings.json';
        $settings = ['sender_name' => 'Primovative Team', 'sender_email' => 'support@primovative.com'];
        if (file_exists($file)) {
            $loaded = json_decode(file_get_contents($file), true);
            if (is_array($loaded)) $settings = array_merge($settings, $loaded);
        }
        return $settings;
    }

    private static function logEmail(string $to, string $subject, string $body) {
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) mkdir($logDir, 0755, true);
        $data = "[" . date('Y-m-d H:i:s') . "] TO: {$to} | SUBJECT: {$subject}\n" . strip_tags($body) . "\n--------------------------------------------------\n";
        file_put_contents($logDir . '/mail.log', $data, FILE_APPEND);
    }

    public static function sendEmail(string $toEmail, string $toName, string $subject, string $html): bool {
        $settings = self::getSettings();
        $senderName = $settings['sender_name'] ?? 'Primovative Team';
        $senderEmail = $settings['sender_email'] ?? 'support@primovative.com';

        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: {$senderName} <{$senderEmail}>\r\nReply-To: {$senderEmail}\r\n";

        self::logEmail($toEmail, $subject, $html);
        @mail($toEmail, $subject, $html, $headers);
        return true;
    }

    private static function getBaseUrl(): string {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $protocol = $isHttps ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host;
    }

    public static function sendVerificationEmail(string $toEmail, string $toName, string $token): bool {
        $link = self::getBaseUrl() . '/LeadGen/verify-email.php?token=' . urlencode($token) . '&email=' . urlencode($toEmail);

        $subject = "Verify Your Email Address — LeadFlow";
        $html = '<div style="font-family:Inter,sans-serif;background:#f7f8fc;padding:40px;color:#171923;">'
            . '<div style="max-width:600px;margin:auto;background:#fff;border-radius:12px;padding:40px;box-shadow:0 4px 12px rgba(0,0,0,0.05);">'
            . '<div style="font-size:24px;font-weight:800;color:#6366f1;margin-bottom:24px;">LeadFlow</div>'
            . '<h2>Welcome, ' . htmlspecialchars($toName) . '!</h2>'
            . '<p>Thank you for registering with LeadFlow. Please verify your email address to activate your account.</p>'
            . '<p style="text-align:center;"><a href="' . $link . '" style="display:inline-block;background:#6366f1;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;margin:24px 0;">Verify Email Address</a></p>'
            . '<p>Or copy this link: <span style="color:#6366f1;word-break:break-all;">' . $link . '</span></p>'
            . '<p>This link expires in 24 hours.</p>'
            . '<div style="margin-top:32px;font-size:12px;color:#777d88;border-top:1px solid #edf2f7;padding-top:16px;"><strong>LeadFlow – Powered by Primovative Co.</strong></div>'
            . '</div></div>';

        return self::sendEmail($toEmail, $toName, $subject, $html);
    }
    public static function sendPasswordResetEmail(string $toEmail, string $toName, string $token): bool {
        $link = self::getBaseUrl() . '/LeadGen/reset-password.php?token=' . urlencode($token) . '&email=' . urlencode($toEmail);

        $subject = "Reset Your Password — LeadFlow";
        $html = '<div style="font-family:Inter,sans-serif;background:#f7f8fc;padding:40px;color:#171923;">'
            . '<div style="max-width:600px;margin:auto;background:#fff;border-radius:12px;padding:40px;box-shadow:0 4px 12px rgba(0,0,0,0.05);">'
            . '<div style="font-size:24px;font-weight:800;color:#6366f1;margin-bottom:24px;">LeadFlow</div>'
            . '<h2>Password Reset Request</h2>'
            . '<p>Hello ' . htmlspecialchars($toName) . ', we received a request to reset your password for your LeadFlow account.</p>'
            . '<p style="text-align:center;"><a href="' . $link . '" style="display:inline-block;background:#6366f1;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;margin:24px 0;">Reset Password</a></p>'
            . '<p>Or copy this link: <span style="color:#6366f1;word-break:break-all;">' . $link . '</span></p>'
            . '<p>This password reset link will expire in 30 minutes.</p>'
            . '<div style="margin-top:32px;font-size:12px;color:#777d88;border-top:1px solid #edf2f7;padding-top:16px;"><strong>LeadFlow – Powered by Primovative Co.</strong><br>If you did not request this, please ignore this email.</div>'
            . '</div></div>';

        return self::sendEmail($toEmail, $toName, $subject, $html);
    }
}

