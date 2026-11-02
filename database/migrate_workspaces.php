<?php
require_once __DIR__ . '/../config/database.php';
echo "Running Workspaces & Billing migration...\n";

// 1. DDL
try {
    $sqlFile = __DIR__ . '/migration_workspaces_billing.sql';
    if (file_exists($sqlFile)) {
        $pdo->exec(file_get_contents($sqlFile));
        echo "[OK] Tables ready.\n";
    }
} catch (Exception $e) { echo "[ERROR] DDL: " . $e->getMessage() . "\n"; }

// 2. Add workspace_id column
$tables = ['campaigns', 'leads', 'conversations', 'outreach', 'tasks'];
foreach ($tables as $tbl) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE 'workspace_id'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `workspace_id` INT UNSIGNED NULL AFTER `id`, ADD INDEX `idx_workspace_id` (`workspace_id`)");
            echo "[OK] Added workspace_id to `{$tbl}`\n";
        }
    } catch (Exception $e) { echo "[ERROR] Alter `{$tbl}`: " . $e->getMessage() . "\n"; }
}

// 3. Seed Subscription Plans
try {
    $planCount = (int)$pdo->query("SELECT COUNT(*) FROM subscription_plans")->fetchColumn();
    if ($planCount === 0) {
        $f1 = json_encode(['lead_discovery', 'manual_leads', 'basic_outreach', 'tasks']);
        $f2 = json_encode(['lead_discovery', 'manual_leads', 'bulk_import', 'campaigns', 'outreach', 'conversations', 'tasks', 'csv_export']);
        $f3 = json_encode(['lead_discovery', 'manual_leads', 'bulk_import', 'unlimited_campaigns', 'multi_channel_outreach', 'conversations', 'tasks', 'csv_export', 'api_access']);

        $stmt = $pdo->prepare("
            INSERT INTO subscription_plans (name, slug, description, price_monthly, price_annual, max_users, max_leads, max_campaigns, max_outreach, features, is_active)
            VALUES 
            ('Starter', 'starter', 'Essential prospecting tools for individuals.', 0.00, 0.00, 2, 250, 3, 500, ?, 1),
            ('Growth Pro', 'growth-pro', 'Accelerate pipeline growth with automated lead extraction.', 49.00, 490.00, 5, 1500, 15, 3000, ?, 1),
            ('LeadFlow Pro Scale', 'pro-scale', 'Full automated extraction, unlimited campaigns, outreach and team.', 199.00, 1990.00, 15, 5000, 50, 15000, ?, 1)
        ");
        $stmt->execute([$f1, $f2, $f3]);
        echo "[OK] Seeded subscription plans.\n";
    }
} catch (Exception $e) { echo "[ERROR] Seed plans: " . $e->getMessage() . "\n"; }

// 4. Default Workspace & Data Migration
try {
    $ws = $pdo->query("SELECT id FROM workspaces WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $defaultWsId = $ws ? (int)$ws['id'] : null;

    if (!$defaultWsId) {
        $firstUser = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $ownerId = $firstUser ? (int)$firstUser['id'] : 1;
        $settingsPath = __DIR__ . '/../config/settings.json';
        $settingsJson = file_exists($settingsPath) ? file_get_contents($settingsPath) : null;

        $stmt = $pdo->prepare("INSERT INTO workspaces (id, name, slug, description, owner_id, status, settings) VALUES (1, 'Primovative Co.', 'primovative-co', 'Main lead generation workspace.', ?, 'active', ?)");
        $stmt->execute([$ownerId, $settingsJson]);
        $defaultWsId = 1;
        echo "[OK] Created default workspace 1.\n";
    }

    $users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        $uid = (int)$u['id'];
        $mCheck = $pdo->prepare("SELECT id FROM workspace_members WHERE workspace_id = ? AND user_id = ?");
        $mCheck->execute([$defaultWsId, $uid]);
        if (!$mCheck->fetch()) {
            $role = ($uid === 1) ? 'owner' : 'admin';
            $ins = $pdo->prepare("INSERT INTO workspace_members (workspace_id, user_id, role, status) VALUES (?, ?, ?, 'active')");
            $ins->execute([$defaultWsId, $uid, $role]);
            echo "[OK] Assigned user {$uid} to workspace {$defaultWsId} as {$role}\n";
        }
    }

    foreach ($tables as $tbl) {
        $upd = $pdo->prepare("UPDATE `{$tbl}` SET workspace_id = ? WHERE workspace_id IS NULL");
        $upd->execute([$defaultWsId]);
        $cnt = $upd->rowCount();
        if ($cnt > 0) echo "[OK] Migrated {$cnt} rows in `{$tbl}`\n";
    }

    $sub = $pdo->prepare("SELECT id FROM workspace_subscriptions WHERE workspace_id = ? LIMIT 1");
    $sub->execute([$defaultWsId]);
    if (!$sub->fetch()) {
        $pRow = $pdo->query("SELECT id FROM subscription_plans WHERE slug = 'pro-scale' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $pId = $pRow ? (int)$pRow['id'] : 3;

        $insSub = $pdo->prepare("INSERT INTO workspace_subscriptions (workspace_id, plan_id, billing_cycle, status, starts_at, ends_at) VALUES (?, ?, 'monthly', 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))");
        $insSub->execute([$defaultWsId, $pId]);
        $subId = $pdo->lastInsertId();

        $inv = $pdo->prepare("INSERT INTO payment_transactions (workspace_id, subscription_id, transaction_ref, invoice_number, payment_gateway, amount, currency, status, payment_method, card_last4, card_brand, billing_name, billing_email, notes, created_at) VALUES (?, ?, ?, ?, 'stripe_simulated', 199.00, 'USD', 'successful', 'Credit Card (Visa)', '4242', 'visa', 'Primovative Co.', 'support@primovative.com', ?, ?)");
        $inv->execute([$defaultWsId, $subId, 'TXN-2026-009', 'INV-2026-009', 'LeadFlow Pro Scale Monthly', '2026-09-01 10:00:00']);
        $inv->execute([$defaultWsId, $subId, 'TXN-2026-008', 'INV-2026-008', 'LeadFlow Pro Scale Monthly', '2026-08-01 10:00:00']);
        $inv->execute([$defaultWsId, $subId, 'TXN-2026-007', 'INV-2026-007', 'LeadFlow Pro Scale Monthly', '2026-07-01 10:00:00']);
        echo "[OK] Seeded subscription & invoices\n";
    }
} catch (Exception $e) { echo "[ERROR] Migration: " . $e->getMessage() . "\n"; }
