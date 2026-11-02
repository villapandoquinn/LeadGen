-- ================================================================
-- PRIMOVATIVE CO. / LEADFLOW: MULTI-WORKSPACE & BILLING MIGRATION
-- ================================================================

-- 1. Create Workspaces Table
CREATE TABLE IF NOT EXISTS `workspaces` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `owner_id` INT UNSIGNED NOT NULL,
  `status` ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
  `settings` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_owner` (`owner_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create Workspace Members Table
CREATE TABLE IF NOT EXISTS `workspace_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('owner', 'admin', 'manager', 'member', 'viewer') NOT NULL DEFAULT 'member',
  `status` ENUM('active', 'invited', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_workspace_user` (`workspace_id`, `user_id`),
  KEY `idx_workspace` (`workspace_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create Subscription Plans Table
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `price_monthly` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `price_annual` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_users` INT UNSIGNED NOT NULL DEFAULT 1,
  `max_leads` INT UNSIGNED NOT NULL DEFAULT 100,
  `max_campaigns` INT UNSIGNED NOT NULL DEFAULT 2,
  `max_outreach` INT UNSIGNED NOT NULL DEFAULT 250,
  `features` JSON NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plan_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create Workspace Subscriptions Table
CREATE TABLE IF NOT EXISTS `workspace_subscriptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `billing_cycle` ENUM('monthly', 'annual') NOT NULL DEFAULT 'monthly',
  `status` ENUM('trial', 'active', 'pending_payment', 'past_due', 'cancelled', 'expired', 'suspended') NOT NULL DEFAULT 'active',
  `starts_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `ends_at` TIMESTAMP NULL,
  `trial_ends_at` TIMESTAMP NULL,
  `cancelled_at` TIMESTAMP NULL,
  `cancel_reason` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_workspace` (`workspace_id`),
  KEY `idx_plan` (`plan_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create Payment Transactions Table
CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `subscription_id` INT UNSIGNED NULL,
  `transaction_ref` VARCHAR(100) NOT NULL,
  `invoice_number` VARCHAR(100) NOT NULL,
  `payment_gateway` VARCHAR(50) NOT NULL DEFAULT 'stripe_simulated',
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `status` ENUM('successful', 'pending', 'failed', 'refunded') NOT NULL DEFAULT 'successful',
  `payment_method` VARCHAR(100) NULL DEFAULT 'Credit Card (Visa)',
  `card_last4` VARCHAR(4) NULL DEFAULT '4242',
  `card_brand` VARCHAR(50) NULL DEFAULT 'visa',
  `billing_name` VARCHAR(150) NULL,
  `billing_email` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_transaction_ref` (`transaction_ref`),
  UNIQUE KEY `uk_invoice_number` (`invoice_number`),
  KEY `idx_workspace` (`workspace_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create Discovery Logs Table
CREATE TABLE IF NOT EXISTS `discovery_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT UNSIGNED NOT NULL,
  `campaign_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `search_query` VARCHAR(255) NOT NULL,
  `results_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_workspace` (`workspace_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
