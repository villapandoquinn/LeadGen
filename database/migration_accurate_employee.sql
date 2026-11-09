-- ================================================================
-- ACCURATE EMPLOYEE COUNT MIGRATION
-- ================================================================

-- 1. Modify employee_count to INT UNSIGNED NULL (storing actual numeric count or NULL)
ALTER TABLE `leads` MODIFY COLUMN `employee_count` INT UNSIGNED NULL DEFAULT NULL;

-- 2. Add employee_count_source and employee_count_verified_at if not exist
-- (Handled safely via run script or ALTER checks)
