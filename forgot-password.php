<?php
session_start();
require_once __DIR__ . '/includes/csrf.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — LeadFlow</title>
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
<div class="auth-page">
    <section class="brand-panel">
        <div class="brand-inner">
            <div class="brand-logo">
                <div class="logo-mark">L</div>
                <span>LeadFlow</span>
            </div>
            <div class="brand-content">
                <div class="eyebrow">LEAD GENERATION PLATFORM</div>
                <h1>Secure password recovery. <span>Get back in.</span></h1>
                <p>Enter your registered work email address and we will send you a secure link to reset your password.</p>
            </div>
        </div>
        <div class="brand-footer">
            LeadFlow &mdash; Powered by Primovative Co.
        </div>
    </section>

    <section class="form-panel" style="width: 52%; min-height: 100vh; padding: 60px; display: flex; flex-direction: column; justify-content: center;">
        <div class="form-container" style="max-width: 440px; width: 100%; margin: auto;">
            <div class="mobile-logo" style="display: none; align-items: center; gap: 12px; margin-bottom: 40px;">
                <div class="logo-mark">L</div>
                <span>LeadFlow</span>
            </div>

            <div class="form-header" style="margin-bottom: 30px;">
                <h2>Forgot password?</h2>
                <p>No worries, we will send you reset instructions.</p>
            </div>

            <form id="forgotForm">
                <?= csrf_field() ?>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px;">Work Email</label>
                    <input type="email" id="email" name="email" placeholder="you@company.com" required style="width: 100%; height: 50px; padding: 0 16px; border: 1px solid #d2d6dc; border-radius: 10px; font-size: 15px;">
                </div>

                <button type="submit" id="submitBtn" class="register-btn" style="width: 100%; height: 50px; background: #6366f1; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 650; cursor: pointer;">
                    <span>Send Reset Instructions</span>
                </button>
            </form>

            <div id="auth-message" class="auth-message" style="margin-top: 20px; display: none; padding: 12px; border-radius: 8px; font-size: 13px;"></div>

            <div class="login-link" style="text-align: center; margin-top: 24px; font-size: 13px;">
                Remember your password? <a href="index.php" style="color: #6366f1; font-weight: 650; text-decoration: none;">Sign in</a>
            </div>

            <div style="margin-top: 40px; text-align: center; font-size: 12px; color: #a1a6b0;">
                LeadFlow &mdash; Powered by Primovative Co.
            </div>
        </div>
    </section>
</div>

<script>
document.getElementById('forgotForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const msg = document.getElementById('auth-message');
    const email = document.getElementById('email').value.trim();

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';
    msg.style.display = 'none';

    const formData = new FormData(this);

    fetch('forgot-password-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        msg.style.display = 'block';
        if (data.success) {
            msg.style.background = '#f0fdf4';
            msg.style.border = '1px solid #bbf7d0';
            msg.style.color = '#15803d';
            msg.innerHTML = data.message;
            document.getElementById('forgotForm').reset();
        } else {
            msg.style.background = '#fef2f2';
            msg.style.border = '1px solid #fecaca';
            msg.style.color = '#b91c1c';
            msg.innerHTML = data.message;
        }
    })
    .catch(err => {
        msg.style.display = 'block';
        msg.style.background = '#fef2f2';
        msg.style.border = '1px solid #fecaca';
        msg.style.color = '#b91c1c';
        msg.innerHTML = 'A server error occurred. Please try again.';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<span>Send Reset Instructions</span>';
    });
});
</script>
</body>
</html>
