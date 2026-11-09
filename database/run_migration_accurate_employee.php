<?php
require_once __DIR__ . '/../config/database.php';

echo "Running accurate employee count database migration...\n";

try {
    // 1. Clean non-numeric employee_count values
    $pdo->exec("UPDATE `leads` SET `employee_count` = NULL WHERE `employee_count` IS NOT NULL AND `employee_count` NOT REGEXP '^[0-9]+$'");
    echo "[OK] Cleaned legacy non-numeric employee counts.\n";

    // 2. Modify employee_count to INT UNSIGNED NULL
    $pdo->exec("ALTER TABLE `leads` MODIFY COLUMN `employee_count` INT UNSIGNED NULL DEFAULT NULL");
    echo "[OK] Modified leads.employee_count to INT UNSIGNED NULL.\n";

    // 3. Add employee_count_source if not exists
    $check = $pdo->query("SHOW COLUMNS FROM `leads` LIKE 'employee_count_source'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE `leads` ADD COLUMN `employee_count_source` VARCHAR(100) NULL AFTER `employee_count`");
        echo "[OK] Added employee_count_source column.\n";
    }

    // 4. Add employee_count_verified_at if not exists
    $check = $pdo->query("SHOW COLUMNS FROM `leads` LIKE 'employee_count_verified_at'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE `leads` ADD COLUMN `employee_count_verified_at` TIMESTAMP NULL AFTER `employee_count_source`");
        echo "[OK] Added employee_count_verified_at column.\n";
    }

    echo "Accurate employee migration completed successfully.\n";
} catch (Throwable $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
}
