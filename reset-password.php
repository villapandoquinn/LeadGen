<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';

$token = trim($_GET['token'] ?? '');
$email = trim($_GET['email'] ?? '');
$error = '';
$validToken = false;

if (empty($token) || empty($email)) {
    $error = "Invalid password reset link.";
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || empty($user['password_reset_token']) || $user['password_reset_token'] !== $token) {
        $error = "Invalid or expired password reset link.";
    } elseif (!empty($user['password_reset_expires_at']) && strtotime($user['password_reset_expires_at']) < time()) {
        $error = "Password reset link has expired. Please request a new one.";
    } else {
        $validToken = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — ProspectHunt</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
<div class="auth-page">
<?php if (!$validToken): ?>
                <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 14px; border-radius: 8px; font-size: 14px; margin-bottom: 24px;">
                    <?= htmlspecialchars($error) ?>
                </div>
                <div style="text-align: center;">
                    <a href="forgot-password.php" class="register-btn" style="display: inline-block; text-decoration: none; padding: 12px 24px; background: #6366f1; color: #fff; border-radius: 8px; font-weight: 600;">Request New Reset Link</a>
                </div>
            <?php else: ?>
                <form id="resetForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px;">New Password</label>
                        <input type="password" id="password" name="password" placeholder="At least 8 characters" minlength="8" required style="width: 100%; height: 50px; padding: 0 16px; border: 1px solid #d2d6dc; border-radius: 10px; font-size: 15px;">
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px;">Confirm New Password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm new password" minlength="8" required style="width: 100%; height: 50px; padding: 0 16px; border: 1px solid #d2d6dc; border-radius: 10px; font-size: 15px;">
                    </div>
                    <button type="submit" id="submitBtn" class="register-btn" style="width: 100%; height: 50px; background: #6366f1; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 650; cursor: pointer;">
                        <span>Update Password</span>
                    </button>
                </form>
                <div id="auth-message" class="auth-message" style="margin-top: 20px; display: none; padding: 12px; border-radius: 8px; font-size: 13px;"></div>
            <?php endif; ?>
            <div class="login-link" style="text-align: center; margin-top: 24px; font-size: 13px;"><a href="index.php" style="color: #6366f1; font-weight: 650; text-decoration: none;">Return to Sign In</a></div>
            <div style="margin-top: 40px; text-align: center; font-size: 12px; color: #a1a6b0;">LeadFlow &mdash; Powered by Primovative Co.</div>
        </div>
    </section>
</div>
<script>
const form = document.getElementById('resetForm');
if (form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        const msg = document.getElementById('auth-message');
        const p1 = document.getElementById('password').value;
        const p2 = document.getElementById('password_confirmation').value;
        if (p1 !== p2) {
            msg.style.display = 'block'; msg.style.background = '#fef2f2'; msg.style.border = '1px solid #fecaca'; msg.style.color = '#b91c1c';
            msg.innerHTML = 'Passwords do not match.'; return;
        }
        btn.disabled = true; btn.innerHTML = 'Updating...'; msg.style.display = 'none';
        const formData = new FormData(this);
        fetch('reset-password-handler.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            msg.style.display = 'block';
            if (data.success) {
                msg.style.background = '#f0fdf4'; msg.style.border = '1px solid #bbf7d0'; msg.style.color = '#15803d';
                msg.innerHTML = data.message + ' Redirecting...';
                setTimeout(() => { window.location.href = 'index.php?reset=1'; }, 2000);
            } else {
                msg.style.background = '#fef2f2'; msg.style.border = '1px solid #fecaca'; msg.style.color = '#b91c1c';
                msg.innerHTML = data.message; btn.disabled = false; btn.innerHTML = 'Update Password';
            }
        });
    });
}
</script>
</body>
</html>

    <section class="brand-panel">
        <div class="brand-inner">
            <div class="brand-logo"><div class="logo-mark">P</div><span>ProspectHunt</span></div>
            <div class="brand-content">
                <div class="eyebrow">LEAD GENERATION PLATFORM</div>
                <h1>Choose a new password. <span>Secure your account.</span></h1>
                <p>Create a strong password with at least 8 characters to secure your ProspectHunt account.</p>
            </div>
        </div>
        <div class="brand-footer">ProspectHunt &mdash; Powered by Primovative Co.</div>
    </section>
    <section class="form-panel" style="width: 52%; min-height: 100vh; padding: 60px; display: flex; flex-direction: column; justify-content: center;">
        <div class="form-container" style="max-width: 440px; width: 100%; margin: auto;">
            <div class="form-header" style="margin-bottom: 30px;"><h2>Reset password</h2><p>Enter your new password below.</p></div>
