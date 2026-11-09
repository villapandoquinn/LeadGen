<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/MailService.php';
require_once __DIR__ . '/services/RateLimiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

$rateCheck = RateLimiter::check('resend_verification_' . $email, 3, 300);
if (!$rateCheck['allowed']) {
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait a few minutes before requesting another verification email.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Generic response to avoid email enumeration
    echo json_encode(['success' => true, 'message' => 'If that email address is registered and unverified, a new verification link has been sent.']);
    exit;
}

if (!empty($user['email_verified_at'])) {
    echo json_encode(['success' => false, 'message' => 'This email address is already verified. You can sign in.']);
    exit;
}

// Generate new token
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

$upd = $pdo->prepare("UPDATE users SET email_verification_token = ?, email_verification_expires_at = ? WHERE id = ?");
$upd->execute([$token, $expiresAt, $user['id']]);

MailService::sendVerificationEmail($user['email'], $user['name'], $token);

echo json_encode(['success' => true, 'message' => 'Verification email resent successfully. Please check your inbox.']);
exit;
