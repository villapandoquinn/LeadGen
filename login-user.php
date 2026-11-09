<?php

session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/services/RateLimiter.php";

function showAuthError(string $message, bool $isUnverified = false, string $email = "") {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authentication Notice — ProspectHunt</title>
        <link rel="stylesheet" href="css/auth.css">
    </head>
    <body>
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f7f8fc; padding: 20px; font-family: Inter, sans-serif;">
        <div style="width: 100%; max-width: 460px; background: #ffffff; padding: 48px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); text-align: center;">
            <div style="width: 52px; height: 52px; background: #6366f1; color: #fff; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 800; margin: 0 auto 24px auto; box-shadow: 0 6px 16px rgba(99, 102, 241, 0.3);">P</div>
            <h2 style="font-size: 24px; font-weight: 750; color: #171923; margin-bottom: 8px; letter-spacing: -0.5px;">Authentication Notice</h2>
            <p style="font-size: 14px; color: #777d88; margin-bottom: 24px;">Please review the notice below to continue.</p>
            
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 16px; border-radius: 10px; font-size: 14px; margin-bottom: 28px; text-align: left; line-height: 1.5; font-weight: 500;">
                <?= htmlspecialchars($message) ?>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <a href="index.php" style="display: block; text-decoration: none; padding: 14px 24px; background: #6366f1; color: #fff; border-radius: 10px; font-weight: 650; font-size: 15px; text-align: center; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25); transition: background 0.2s;">Return to Sign In</a>
                
                <?php if ($isUnverified && !empty($email)): ?>
                    <button type="button" id="resendBtn" onclick="resendVerification('<?= htmlspecialchars($email, ENT_QUOTES) ?>')" style="padding: 14px 24px; background: #ffffff; color: #6366f1; border: 2px solid #6366f1; border-radius: 10px; font-weight: 650; font-size: 15px; cursor: pointer; transition: background 0.2s;">Resend Verification Email</button>
                    <div id="resendMsg" style="font-size: 13px; margin-top: 4px; display: none; font-weight: 500;"></div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 36px; padding-top: 20px; border-top: 1px solid #edf2f7; font-size: 12px; color: #a1a6b0; font-weight: 500;">
                ProspectHunt &mdash; Powered by Primovative Co.
            </div>
        </div>
    </div>
    <?php if ($isUnverified && !empty($email)): ?>
    <script>
    function resendVerification(email) {
        const btn = document.getElementById("resendBtn");
        const msg = document.getElementById("resendMsg");
        btn.disabled = true;
        btn.innerHTML = "Sending...";
        fetch("resend-verification.php", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "email=" + encodeURIComponent(email)
        })
        .then(r => r.json())
        .then(data => {
            msg.style.display = "block";
            msg.style.color = data.success ? "#15803d" : "#b91c1c";
            msg.innerHTML = data.message;
            btn.innerHTML = "Resend Verification Email";
            btn.disabled = false;
        })
        .catch(() => {
            msg.style.display = "block";
            msg.style.color = "#b91c1c";
            msg.innerHTML = "Failed to send request.";
            btn.innerHTML = "Resend Verification Email";
            btn.disabled = false;
        });
    }
    </script>
    <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

$rateCheck = RateLimiter::check("login", 5, 300);
if (!$rateCheck["allowed"]) {
    showAuthError($rateCheck["message"]);
}

require_csrf();

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if (!$email || !$password) {
    showAuthError("Email and password are required.");
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !$user["password_hash"] || !password_verify($password, $user["password_hash"])) {
    showAuthError("Invalid email or password.");
}

if ($user["status"] !== "active") {
    showAuthError("Your account is inactive.");
}

if ($user["auth_provider"] === "email" && empty($user["email_verified_at"])) {
    showAuthError("Your email address is not yet verified. Please check your inbox for the verification link or request a new one.", true, $user["email"]);
}

RateLimiter::reset("login");
session_regenerate_id(true);

$_SESSION["user_id"] = $user["id"];
$_SESSION["user_name"] = $user["name"];
$_SESSION["user_email"] = $user["email"];
$_SESSION["profile_picture"] = $user["profile_picture"];
$_SESSION["auth_provider"] = $user["auth_provider"];

header("Location: dashboard.php");
exit;
