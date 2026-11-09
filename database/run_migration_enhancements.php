<?php
require_once __DIR__ . '/../config/database.php';

echo "Running system enhancements database migration...\n";

// 1. Add employee_count to leads
try {
    $check = $pdo->query("SHOW COLUMNS FROM `leads` LIKE 'employee_count'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE `leads` ADD COLUMN `employee_count` VARCHAR(50) NULL DEFAULT 'Not Available' AFTER `reviews_count`");
        echo "[OK] Added employee_count column to leads table.\n";
    } else {
        echo "[INFO] employee_count column already exists in leads table.\n";
    }
} catch (Throwable $e) {
    echo "[ERROR] employee_count: " . $e->getMessage() . "\n";
}

// 2. Add assigned_to to leads
try {
    $check = $pdo->query("SHOW COLUMNS FROM `leads` LIKE 'assigned_to'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE `leads` ADD COLUMN `assigned_to` INT UNSIGNED NULL AFTER `campaign_id`");
        $pdo->exec("ALTER TABLE `leads` ADD INDEX `idx_leads_assigned_to` (`assigned_to`)");
        echo "[OK] Added assigned_to column and index to leads table.\n";
    } else {
        echo "[INFO] assigned_to column already exists in leads table.\n";
    }
} catch (Throwable $e) {
    echo "[ERROR] assigned_to: " . $e->getMessage() . "\n";
}

// 3. Add created_by to campaigns
try {
    $check = $pdo->query("SHOW COLUMNS FROM `campaigns` LIKE 'created_by'")->fetch();
    if (!$check) {
        $pdo->exec("ALTER TABLE `campaigns` ADD COLUMN `created_by` INT UNSIGNED NULL AFTER `status`");
        $pdo->exec("ALTER TABLE `campaigns` ADD INDEX `idx_campaigns_created_by` (`created_by`)");
        echo "[OK] Added created_by column and index to campaigns table.\n";
    } else {
        echo "[INFO] created_by column already exists in campaigns table.\n";
    }
} catch (Throwable $e) {
    echo "[ERROR] created_by: " . $e->getMessage() . "\n";
}

echo "Migration script completed.\n";

