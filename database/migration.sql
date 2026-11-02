-- ================================================================
-- LEAD GENERATION SYSTEM: DATABASE ENHANCEMENT MIGRATION
-- Adds Google Maps / Places attributes to leads table
-- ================================================================

-- 1. Ensure email in leads allows NULL for discovered Google Maps leads pending email enrichment
ALTER TABLE `leads` MODIFY COLUMN `email` VARCHAR(255) NULL DEFAULT NULL;

-- 2. Add google_place_id for duplicate detection across Google Places API queries
ALTER TABLE `leads` ADD COLUMN `google_place_id` VARCHAR(255) NULL AFTER `campaign_id`;
ALTER TABLE `leads` ADD INDEX `idx_leads_place_id` (`google_place_id`);

-- 3. Add Google Maps business rating and reviews count
ALTER TABLE `leads` ADD COLUMN `rating` DECIMAL(3,1) NULL AFTER `score`;
ALTER TABLE `leads` ADD COLUMN `reviews_count` INT UNSIGNED NULL DEFAULT 0 AFTER `rating`;

-- 4. Add full formatted address column
ALTER TABLE `leads` ADD COLUMN `address` VARCHAR(255) NULL AFTER `location`;
