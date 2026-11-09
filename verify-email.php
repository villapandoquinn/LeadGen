<?php
session_start();
require_once __DIR__ . '/config/database.php';

$token = trim($_GET['token'] ?? '');
$email = trim($_GET['email'] ?? '');

$error = '';
$success = '';

if (empty($token) || empty($email)) {
    $error = "Invalid verification link.";
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "User not found.";
    } elseif (!empty($user['email_verified_at'])) {
        $success = "Your email address is already verified. You can now sign in.";
    } elseif (empty($user['email_verification_token']) || $user['email_verification_token'] !== $token) {
        $error = "Invalid or expired verification token.";
    } elseif (!empty($user['email_verification_expires_at']) && strtotime($user['email_verification_expires_at']) < time()) {
        $error = "Verification link has expired. Please request a new verification link.";
    } else {
        // Mark verified
        $upd = $pdo->prepare("UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL, email_verification_expires_at = NULL, status = 'active' WHERE id = ?");
        $upd->execute([$user['id']]);
        $success = "Email address successfully verified! You can now sign in to your ProspectHunt account.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification — ProspectHunt</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
<div class="auth-page" style="justify-content: center; align-items: center;">
    <div class="form-panel" style="width: 100%; max-width: 500px; padding: 40px; background: #fff; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center;">
        <div class="logo-mark" style="margin: 0 auto 20px auto;">P</div>
        <h2 style="font-size: 24px; font-weight: 750; margin-bottom: 12px;">Email Verification</h2>
        
        <?php if (!empty($error)): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 14px; border-radius: 8px; font-size: 14px; margin-bottom: 24px;">
                <?= htmlspecialchars($error) ?>
            </div>
            <p><a href="index.php" class="register-btn" style="display: inline-block; text-decoration: none; padding: 12px 24px; background: #6366f1; color: #fff; border-radius: 8px; font-weight: 600;">Return to Sign In</a></p>
        <?php else: ?>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 14px; border-radius: 8px; font-size: 14px; margin-bottom: 24px;">
                <?= htmlspecialchars($success) ?>
            </div>
            <p><a href="index.php" class="register-btn" style="display: inline-block; text-decoration: none; padding: 12px 24px; background: #6366f1; color: #fff; border-radius: 8px; font-weight: 600;">Sign In Now</a></p>
        <?php endif; ?>

        <div style="margin-top: 32px; font-size: 11px; color: #a1a6b0;">
            ProspectHunt &mdash; Powered by Primovative Co.
        </div>
    </div>
</div>
</body>
</html>
