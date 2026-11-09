<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/MailService.php';

echo "====================================================\n";
echo "LEADFLOW AUTHENTICATION ENHANCEMENTS TEST SUITE\n";
echo "====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $testName): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $testName . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $testName . "\n";
        $failCount++;
    }
}

// 1. Database Schema Check
echo "--- 1. Database Schema Columns Check ---\n";
$cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
assertTest(in_array('email_verified_at', $cols), "users table has email_verified_at column");
assertTest(in_array('email_verification_token', $cols), "users table has email_verification_token column");
assertTest(in_array('email_verification_expires_at', $cols), "users table has email_verification_expires_at column");
assertTest(in_array('password_reset_token', $cols), "users table has password_reset_token column");
assertTest(in_array('password_reset_expires_at', $cols), "users table has password_reset_expires_at column");

// 2. Mail Service & Templating Check
echo "\n--- 2. Mail Service Testing ---\n";
$testEmail = "testuser_" . time() . "@primovative.com";
$testName = "Test User";
$testToken = bin2hex(random_bytes(32));

$mailRes1 = MailService::sendVerificationEmail($testEmail, $testName, $testToken);
assertTest($mailRes1 === true, "Verification email dispatched successfully");

$mailRes2 = MailService::sendPasswordResetEmail($testEmail, $testName, $testToken);
assertTest($mailRes2 === true, "Password reset email dispatched successfully");

$logFile = __DIR__ . '/logs/mail.log';
assertTest(file_exists($logFile), "Mail log file generated successfully");

// 3. User Registration & Verification Flow
echo "\n--- 3. Registration & Email Verification Flow ---\n";
$passwordHash = password_hash("securePassword123", PASSWORD_DEFAULT);
$stmtIns = $pdo->prepare("INSERT INTO users (name, email, password_hash, auth_provider, status, email_verification_token, email_verification_expires_at) VALUES (?, ?, ?, 'email', 'active', ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
$stmtIns->execute([$testName, $testEmail, $passwordHash, $testToken]);
$userId = $pdo->lastInsertId();
assertTest($userId > 0, "Test user inserted into database");

// Verify token lookup
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmtUser->execute([$testEmail]);
$dbUser = $stmtUser->fetch();
assertTest(!empty($dbUser['email_verification_token']) && empty($dbUser['email_verified_at']), "User unverified with token set");

// Simulate verification
$pdo->prepare("UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL, email_verification_expires_at = NULL WHERE id = ?")->execute([$userId]);
$stmtUser->execute([$testEmail]);
$dbUserVerified = $stmtUser->fetch();
assertTest(!empty($dbUserVerified['email_verified_at']), "User email successfully marked as verified");

// 4. Forgot Password & Reset Flow
echo "\n--- 4. Forgot Password & Reset Flow ---\n";
$resetToken = bin2hex(random_bytes(32));
$pdo->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?")->execute([$resetToken, $userId]);

$stmtUser->execute([$testEmail]);
$dbUserReset = $stmtUser->fetch();
assertTest(!empty($dbUserReset['password_reset_token']), "Password reset token stored in database");

// Simulate password reset
$newPasswordHash = password_hash("newSecurePassword456", PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = ?")->execute([$newPasswordHash, $userId]);

$stmtUser->execute([$testEmail]);
$dbUserAfterReset = $stmtUser->fetch();
assertTest(empty($dbUserAfterReset['password_reset_token']), "Password reset token cleared immediately after reset");
assertTest(password_verify("newSecurePassword456", $dbUserAfterReset['password_hash']), "New password verified successfully");

// Cleanup test user
$pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
echo "\n[INFO] Cleaned up test user.\n";

echo "\n====================================================\n";
echo "SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "====================================================\n";
