<?php
/**
 * LinkedIn Lead Sync Migration Runner - UPDATED
 * Idempotent: safely run multiple times.
 * PHP-based column/table existence checks for MySQL 5.x compatibility.
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');
$results = [];

function getColumns(PDO $pdo, string $table): array {
    return $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
}
function getIndexNames(PDO $pdo, string $table): array {
    return array_unique(array_column($pdo->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC), 'Key_name'));
}
function exec_safe(PDO $pdo, string $sql): string {
    try { $pdo->exec($sql); return 'OK'; }
    catch (PDOException $e) { return 'ERROR: ' . $e->getMessage(); }
}

// 1. Add LinkedIn columns to leads table
$leadsColumns = getColumns($pdo, 'leads');
$columnsToAdd = [
    'linkedin_submission_id' => "ALTER TABLE `leads` ADD COLUMN `linkedin_submission_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'LinkedIn Lead Form Submission ID' AFTER `google_place_id`",
    'linkedin_form_id'       => "ALTER TABLE `leads` ADD COLUMN `linkedin_form_id` VARCHAR(100) NULL DEFAULT NULL COMMENT 'LinkedIn Ad Form ID' AFTER `linkedin_submission_id`",
    'linkedin_campaign_id'   => "ALTER TABLE `leads` ADD COLUMN `linkedin_campaign_id` VARCHAR(100) NULL DEFAULT NULL COMMENT 'LinkedIn Campaign ID' AFTER `linkedin_form_id`",
    'linkedin_account_id'    => "ALTER TABLE `leads` ADD COLUMN `linkedin_account_id` VARCHAR(100) NULL DEFAULT NULL COMMENT 'LinkedIn Account ID' AFTER `linkedin_campaign_id`",
    'linkedin_first_name'    => "ALTER TABLE `leads` ADD COLUMN `linkedin_first_name` VARCHAR(100) NULL DEFAULT NULL AFTER `linkedin_account_id`",
    'linkedin_last_name'     => "ALTER TABLE `leads` ADD COLUMN `linkedin_last_name` VARCHAR(100) NULL DEFAULT NULL AFTER `linkedin_first_name`",
    'linkedin_job_title'     => "ALTER TABLE `leads` ADD COLUMN `linkedin_job_title` VARCHAR(150) NULL DEFAULT NULL AFTER `linkedin_last_name`",
    'linkedin_submitted_at'  => "ALTER TABLE `leads` ADD COLUMN `linkedin_submitted_at` DATETIME NULL DEFAULT NULL COMMENT 'LinkedIn submission timestamp' AFTER `linkedin_job_title`",
];
foreach ($columnsToAdd as $col => $sql) {
    if (!in_array($col, $leadsColumns)) {
        $results[] = "ADD leads.$col: " . exec_safe($pdo, $sql);
    } else {
        $results[] = "SKIP (exists): leads.$col";
    }
}

// 2. Add indexes
$leadsIndexes = getIndexNames($pdo, 'leads');
$indexesToAdd = [
    'idx_leads_li_submission_id' => "ALTER TABLE `leads` ADD INDEX `idx_leads_li_submission_id` (`linkedin_submission_id`)",
    'idx_leads_li_form_id'       => "ALTER TABLE `leads` ADD INDEX `idx_leads_li_form_id` (`linkedin_form_id`)",
    'idx_leads_li_account_id'    => "ALTER TABLE `leads` ADD INDEX `idx_leads_li_account_id` (`linkedin_account_id`)",
];
foreach ($indexesToAdd as $idxName => $sql) {
    if (!in_array($idxName, $leadsIndexes)) {
        $results[] = "ADD INDEX $idxName: " . exec_safe($pdo, $sql);
    } else {
        $results[] = "SKIP (exists): INDEX $idxName";
    }
}

// 3. Create linkedin_sync_logs table
$tables = $pdo->query("SHOW TABLES LIKE 'linkedin_sync_logs'")->fetchAll();
if (empty($tables)) {
    $ct = "CREATE TABLE `linkedin_sync_logs` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `workspace_id`    INT UNSIGNED NOT NULL,
        `user_id`         INT UNSIGNED NOT NULL,
        `account_id`      VARCHAR(100) NOT NULL,
        `form_id`         VARCHAR(100) NULL DEFAULT NULL,
        `status`          ENUM('started','completed','partial','failed') NOT NULL DEFAULT 'started',
        `leads_retrieved` INT UNSIGNED NOT NULL DEFAULT 0,
        `leads_imported`  INT UNSIGNED NOT NULL DEFAULT 0,
        `leads_skipped`   INT UNSIGNED NOT NULL DEFAULT 0,
        `leads_failed`    INT UNSIGNED NOT NULL DEFAULT 0,
        `error_message`   TEXT NULL,
        `sync_from`       DATETIME NULL DEFAULT NULL,
        `sync_to`         DATETIME NULL DEFAULT NULL,
        `created_at`      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `completed_at`    TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_li_sync_workspace` (`workspace_id`),
        KEY `idx_li_sync_user` (`user_id`),
        KEY `idx_li_sync_account` (`account_id`),
        KEY `idx_li_sync_status` (`status`),
        KEY `idx_li_sync_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $results[] = "CREATE TABLE linkedin_sync_logs: " . exec_safe($pdo, $ct);
} else {
    $results[] = "SKIP (exists): TABLE linkedin_sync_logs";
}

echo "LinkedIn Lead Sync Migration\n============================\n";
foreach ($results as $r) echo $r . "\n";
echo "\nCompleted: " . date('Y-m-d H:i:s') . "\n";
