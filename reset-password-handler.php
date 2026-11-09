<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$token = trim($_POST['token'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirmation = $_POST['password_confirmation'] ?? '';

if (empty($token) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least 8 characters.']);
    exit;
}

if ($password !== $passwordConfirmation) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || empty($user['password_reset_token']) || $user['password_reset_token'] !== $token) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired password reset token.']);
    exit;
}

if (!empty($user['password_reset_expires_at']) && strtotime($user['password_reset_expires_at']) < time()) {
    echo json_encode(['success' => false, 'message' => 'Password reset link has expired.']);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Update password and invalidate reset token immediately
$upd = $pdo->prepare("UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = ?");
$upd->execute([$passwordHash, $user['id']]);

echo json_encode([
    'success' => true,
    'message' => 'Password successfully updated! You can now sign in with your new password.'
]);
exit;
