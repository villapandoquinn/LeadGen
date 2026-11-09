<?php
require_once __DIR__ . '/../config/database.php';
echo "Running Billing Plans & Commercial Alignment Migration...\n";

try {
    // 1. Ensure subscription_plans has Basic (1,899) and Premium (2,499)
    $plans = [
        [
            'name' => 'Basic',
            'slug' => 'basic',
            'description' => 'Essential lead generation & Google Maps prospecting for businesses.',
            'price_monthly' => 1899.00,
            'price_annual' => 18990.00,
            'max_users' => 5,
            'max_leads' => 1000,
            'max_campaigns' => 10,
            'max_outreach' => 2500,
            'features' => json_encode(['lead_discovery_google', 'basic_ai', 'lead_management', 'campaigns', 'workspace', 'users', 'dashboard', 'analytics_standard', 'email_smtp'])
        ],
        [
            'name' => 'Premium',
            'slug' => 'premium',
            'description' => 'Advanced multi-source discovery including LinkedIn, advanced AI, and enhanced analytics.',
            'price_monthly' => 2499.00,
            'price_annual' => 24990.00,
            'max_users' => 15,
            'max_leads' => 5000,
            'max_campaigns' => 50,
            'max_outreach' => 10000,
            'features' => json_encode(['lead_discovery_google', 'lead_discovery_linkedin', 'multi_source', 'basic_ai', 'advanced_ai', 'lead_management', 'campaigns', 'workspace', 'users', 'dashboard', 'analytics_standard', 'analytics_enhanced', 'reporting_enhanced', 'email_smtp'])
        ]
    ];

    foreach ($plans as $p) {
        $chk = $pdo->prepare("SELECT id FROM subscription_plans WHERE slug = ? LIMIT 1");
        $chk->execute([$p['slug']]);
        $existing = $chk->fetch();

        if ($existing) {
            $upd = $pdo->prepare("UPDATE subscription_plans SET name = ?, description = ?, price_monthly = ?, price_annual = ?, max_users = ?, max_leads = ?, max_campaigns = ?, max_outreach = ?, features = ?, is_active = 1 WHERE slug = ?");
            $upd->execute([$p['name'], $p['description'], $p['price_monthly'], $p['price_annual'], $p['max_users'], $p['max_leads'], $p['max_campaigns'], $p['max_outreach'], $p['features'], $p['slug']]);
            echo "[OK] Updated plan: {$p['name']}\n";
        } else {
            $ins = $pdo->prepare("INSERT INTO subscription_plans (name, slug, description, price_monthly, price_annual, max_users, max_leads, max_campaigns, max_outreach, features, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $ins->execute([$p['name'], $p['slug'], $p['description'], $p['price_monthly'], $p['price_annual'], $p['max_users'], $p['max_leads'], $p['max_campaigns'], $p['max_outreach'], $p['features']]);
            echo "[OK] Inserted plan: {$p['name']}\n";
        }
    }

    // 2. Add billing lifecycle columns to workspace_subscriptions if not exist
    $subColumns = [
        "production_activated_at" => "DATETIME NULL DEFAULT NULL",
        "included_ends_at" => "DATETIME NULL DEFAULT NULL",
        "deployment_fee_paid" => "TINYINT(1) NOT NULL DEFAULT 1",
        "deployment_fee_amount" => "DECIMAL(10,2) NOT NULL DEFAULT 120000.00"
    ];

    foreach ($subColumns as $col => $definition) {
        $chkCol = $pdo->query("SHOW COLUMNS FROM workspace_subscriptions LIKE '{$col}'")->fetch();
        if (!$chkCol) {
            $pdo->exec("ALTER TABLE workspace_subscriptions ADD COLUMN {$col} {$definition}");
            echo "[OK] Added column {$col} to workspace_subscriptions\n";
        }
    }

    // 3. Create subscription_upgrade_requests table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `subscription_upgrade_requests` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `workspace_id` INT UNSIGNED NOT NULL,
          `current_plan_id` INT UNSIGNED NOT NULL,
          `requested_plan_id` INT UNSIGNED NOT NULL,
          `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
          `requested_by` INT UNSIGNED NOT NULL,
          `approved_by` INT UNSIGNED NULL,
          `effective_date` DATETIME NULL,
          `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_workspace` (`workspace_id`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "[OK] subscription_upgrade_requests table ready.\n";

    // 4. Update existing subscriptions to have production_activated_at and included_ends_at if null (+3 months)
    $subs = $pdo->query("SELECT id, starts_at, production_activated_at, included_ends_at FROM workspace_subscriptions")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($subs as $s) {
        if (empty($s['production_activated_at'])) {
            $actDate = $s['starts_at'] ?: date('Y-m-d H:i:s');
            $incEnds = date('Y-m-d H:i:s', strtotime($actDate . ' +3 months'));
            $updSub = $pdo->prepare("UPDATE workspace_subscriptions SET production_activated_at = ?, included_ends_at = ? WHERE id = ?");
            $updSub->execute([$actDate, $incEnds, $s['id']]);
        }
    }
    echo "[OK] Initialized billing lifecycle dates for existing subscriptions.\n";

} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
}

echo "Billing commercial alignment migration completed successfully.\n";
