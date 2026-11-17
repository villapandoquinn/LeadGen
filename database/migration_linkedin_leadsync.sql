-- ================================================================
-- PRIMOVATIVE CO. / PROSPECTHUNT
-- LINKEDIN LEAD SYNC API INTEGRATION MIGRATION
-- Migration Script: migration_linkedin_leadsync.sql
-- Adds LinkedIn Lead Sync fields to the leads table and creates
-- linkedin_sync_logs table for synchronization history tracking.
-- ================================================================

-- 1. Add LinkedIn-specific identifier columns to leads table
--    (use ADD COLUMN IF NOT EXISTS for idempotency)

ALTER TABLE `leads`
    ADD COLUMN IF NOT EXISTS `linkedin_submission_id` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'LinkedIn Lead Form Submission ID (unique per form submission)'
        AFTER `google_place_id`;

ALTER TABLE `leads`
    ADD COLUMN IF NOT EXISTS `linkedin_form_id` VARCHAR(100) NULL DEFAULT NULL
        COMMENT 'LinkedIn Ad Form ID'
        AFTER `linkedin_submission_id`;

ALTER TABLE `leads`
    ADD COLUMN IF NOT EXISTS `linkedin_campaign_id` VARCHAR(100) NULL DEFAULT NULL
        COMMENT 'LinkedIn Sponsored Campaign ID'
        AFTER `linkedin_form_id`;

ALTER TABLE `leads`
    ADD COLUMN IF NOT EXISTS `linkedin_account_id` VARCHAR(100) NULL DEFAULT NULL
        COMMENT 'LinkedIn Sponsored Account ID'
        AFTER `linkedin_campaign_id`;

ALTER TABLE `leads`
    ADD COLUMN IF NOT EXISTS `linkedin_first_name` VARCHAR(100) NULL DEFAULT NULL
        AFTER `linkedin_account_id`;

ALTER TABLE `leads`
    ADD COLUMN IF NOT EXISTS `linkedin_last_name` VARCHAR(100) NULL DEFAULT NULL
        AFTER `linkedin_first_name`;

ALTER TABLE `leads`
    ADD COLUMN IF NOT EXISTS `linkedin_job_title` VARCHAR(150) NULL DEFAULT NULL
        AFTER `linkedin_last_name`;

ALTER TABLE `leads`
    ADD COLUMN IF NOT EXISTS `linkedin_submitted_at` DATETIME NULL DEFAULT NULL
        COMMENT 'Original submission timestamp from LinkedIn'
        AFTER `linkedin_job_title`;

-- 2. Add unique index on linkedin_submission_id for duplicate detection
ALTER TABLE `leads`
    ADD INDEX IF NOT EXISTS `idx_leads_linkedin_submission_id` (`linkedin_submission_id`);

ALTER TABLE `leads`
    ADD INDEX IF NOT EXISTS `idx_leads_linkedin_form_id` (`linkedin_form_id`);

ALTER TABLE `leads`
    ADD INDEX IF NOT EXISTS `idx_leads_linkedin_account_id` (`linkedin_account_id`);

-- 3. Create LinkedIn Sync Logs table for synchronization history
CREATE TABLE IF NOT EXISTS `linkedin_sync_logs` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `workspace_id`      INT UNSIGNED NOT NULL,
    `user_id`           INT UNSIGNED NOT NULL,
    `account_id`        VARCHAR(100) NOT NULL COMMENT 'LinkedIn Ad Account ID',
    `form_id`           VARCHAR(100) NULL DEFAULT NULL COMMENT 'Filtered form ID or NULL for all forms',
    `status`            ENUM('started','completed','partial','failed') NOT NULL DEFAULT 'started',
    `leads_retrieved`   INT UNSIGNED NOT NULL DEFAULT 0,
    `leads_imported`    INT UNSIGNED NOT NULL DEFAULT 0,
    `leads_skipped`     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Duplicate leads skipped',
    `leads_failed`      INT UNSIGNED NOT NULL DEFAULT 0,
    `error_message`     TEXT NULL,
    `sync_from`         DATETIME NULL DEFAULT NULL COMMENT 'Sync start timestamp filter',
    `sync_to`           DATETIME NULL DEFAULT NULL COMMENT 'Sync end timestamp',
    `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at`      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_li_sync_workspace` (`workspace_id`),
    KEY `idx_li_sync_user` (`user_id`),
    KEY `idx_li_sync_account` (`account_id`),
    KEY `idx_li_sync_status` (`status`),
    KEY `idx_li_sync_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- ROLLBACK REFERENCE (do not execute unless reverting):
-- ALTER TABLE `leads` DROP COLUMN IF EXISTS `linkedin_submission_id`;
-- ALTER TABLE `leads` DROP COLUMN IF EXISTS `linkedin_form_id`;
-- ALTER TABLE `leads` DROP COLUMN IF EXISTS `linkedin_campaign_id`;
-- ALTER TABLE `leads` DROP COLUMN IF EXISTS `linkedin_account_id`;
-- ALTER TABLE `leads` DROP COLUMN IF EXISTS `linkedin_first_name`;
-- ALTER TABLE `leads` DROP COLUMN IF EXISTS `linkedin_last_name`;
-- ALTER TABLE `leads` DROP COLUMN IF EXISTS `linkedin_job_title`;
-- ALTER TABLE `leads` DROP COLUMN IF EXISTS `linkedin_submitted_at`;
-- DROP TABLE IF EXISTS `linkedin_sync_logs`;
-- ================================================================
