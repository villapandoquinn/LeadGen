<?php
require_once __DIR__ . '/../config/database.php';

echo "Running Auth Tokens Migration...\n";

$columns = [
    "email_verified_at" => "DATETIME NULL DEFAULT NULL AFTER status",
    "email_verification_token" => "VARCHAR(255) NULL DEFAULT NULL AFTER email_verified_at",
    "email_verification_expires_at" => "DATETIME NULL DEFAULT NULL AFTER email_verification_token",
    "password_reset_token" => "VARCHAR(255) NULL DEFAULT NULL AFTER email_verification_expires_at",
    "password_reset_expires_at" => "DATETIME NULL DEFAULT NULL AFTER password_reset_token"
];

foreach ($columns as $col => $definition) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM users LIKE '{$col}'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$definition}");
            echo "[OK] Added column {$col} to users table\n";
        } else {
            echo "[INFO] Column {$col} already exists in users table\n";
        }
    } catch (Exception $e) {
        echo "[ERROR] Column {$col}: " . $e->getMessage() . "\n";
    }
}

// Add indexes if not exist
try {
    $pdo->exec("ALTER TABLE users ADD INDEX idx_email_verification_token (email_verification_token)");
    echo "[OK] Added index idx_email_verification_token\n";
} catch (Exception $e) {
    echo "[INFO] Index idx_email_verification_token already exists or skipped\n";
}

try {
    $pdo->exec("ALTER TABLE users ADD INDEX idx_password_reset_token (password_reset_token)");
    echo "[OK] Added index idx_password_reset_token\n";
} catch (Exception $e) {
    echo "[INFO] Index idx_password_reset_token already exists or skipped\n";
}

echo "Auth tokens migration completed.\n";
