<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/services/MailService.php';
require_once __DIR__ . '/services/RateLimiter.php';

$rateCheck = RateLimiter::check('forgot_password', 3, 600);
if (!$rateCheck['allowed']) {
    echo json_encode(['success' => false, 'message' => 'Too many password reset requests. Please try again later.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    $upd = $pdo->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ?");
    $upd->execute([$token, $expiresAt, $user['id']]);

    MailService::sendPasswordResetEmail($user['email'], $user['name'], $token);
}

// Always return success to prevent email enumeration
echo json_encode([
    'success' => true,
    'message' => 'If that email address is registered, we have sent password reset instructions to your inbox.'
]);
exit;
