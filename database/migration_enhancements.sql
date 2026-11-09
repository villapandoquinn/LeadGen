-- ================================================================
-- PRIMOVATIVE CO. / LEAD GENERATION SYSTEM
-- SYSTEM ENHANCEMENTS MIGRATION SCRIPT
-- ================================================================

-- 1. Leads Table Enhancements: Employee Count & Assigned Employee
ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `employee_count` VARCHAR(50) NULL DEFAULT 'Not Available' AFTER `reviews_count`;
ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `assigned_to` INT UNSIGNED NULL AFTER `campaign_id`;
ALTER TABLE `leads` ADD INDEX IF NOT EXISTS `idx_leads_assigned_to` (`assigned_to`);
ALTER TABLE `leads` ADD INDEX IF NOT EXISTS `idx_leads_employee_count` (`employee_count`);

-- 2. Campaigns Table Enhancements: Campaign Creator (created_by)
ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `created_by` INT UNSIGNED NULL AFTER `status`;
ALTER TABLE `campaigns` ADD INDEX IF NOT EXISTS `idx_campaigns_created_by` (`created_by`);
