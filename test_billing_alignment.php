<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/BillingService.php';

echo "====================================================\n";
echo "PRIMOVATIVE / LEADFLOW BILLING ALIGNMENT TEST SUITE\n";
echo "====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $testName): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $testName . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $testName . "\n";
        $failCount++;
    }
}

$billingService = new BillingService($pdo);

// 1. Subscription Plans Verification
echo "--- 1. Subscription Plans Verification ---\n";
$plans = $billingService->getAllPlans();
$basicPlan = null;
$premiumPlan = null;
foreach ($plans as $p) {
    if ($p['slug'] === 'basic') $basicPlan = $p;
    if ($p['slug'] === 'premium') $premiumPlan = $p;
}

assertTest($basicPlan !== null && (float)$basicPlan['price_monthly'] === 1899.00, "Basic plan configured at PHP 1,899.00/month");
assertTest($premiumPlan !== null && (float)$premiumPlan['price_monthly'] === 2499.00, "Premium plan configured at PHP 2,499.00/month");

// 2. Workspace Subscription & 3-Month Included Period Test
echo "\n--- 2. Commercial Lifecycle & 3-Month Included Period Test ---\n";
$wsId = 1; // Default Primovative Co. workspace
$sub = $billingService->getWorkspaceSubscription($wsId);
assertTest($sub !== null, "Workspace 1 subscription retrieved successfully");
assertTest(isset($sub['is_included_period']) && isset($sub['production_activated_at']), "Billing lifecycle parameters (production_activated_at, is_included_period) calculated");

// 3. Feature Access Enforcement Test
echo "\n--- 3. Feature Access Enforcement Test ---\n";
// Update workspace to basic for test
$pdo->prepare("UPDATE workspace_subscriptions SET plan_id = (SELECT id FROM subscription_plans WHERE slug = 'basic' LIMIT 1) WHERE workspace_id = ?")->execute([$wsId]);
$hasLinkedinBasic = $billingService->hasFeature($wsId, 'linkedin_discovery');
assertTest($hasLinkedinBasic === false, "Basic plan restricts LinkedIn Lead Discovery");

$hasGoogleBasic = $billingService->hasFeature($wsId, 'lead_discovery_google');
assertTest($hasGoogleBasic === true, "Basic plan allows Google Maps lead discovery");

// Update workspace to premium for test
$pdo->prepare("UPDATE workspace_subscriptions SET plan_id = (SELECT id FROM subscription_plans WHERE slug = 'premium' LIMIT 1) WHERE workspace_id = ?")->execute([$wsId]);
$hasLinkedinPremium = $billingService->hasFeature($wsId, 'linkedin_discovery');
assertTest($hasLinkedinPremium === true, "Premium plan enables LinkedIn Lead Discovery");

$hasAdvancedAi = $billingService->hasFeature($wsId, 'advanced_ai');
assertTest($hasAdvancedAi === true, "Premium plan enables Advanced AI Assistance");

// Reset to Basic
$pdo->prepare("UPDATE workspace_subscriptions SET plan_id = (SELECT id FROM subscription_plans WHERE slug = 'basic' LIMIT 1) WHERE workspace_id = ?")->execute([$wsId]);

// 4. Upgrade Request Workflow Test
echo "\n--- 4. Upgrade Request Workflow Test ---\n";
$upgradeRes = $billingService->submitUpgradeRequest($wsId, 1, 2, 1);
assertTest($upgradeRes['success'] === true, "Upgrade request submitted successfully");

$reqCheck = $pdo->prepare("SELECT COUNT(*) FROM subscription_upgrade_requests WHERE workspace_id = ? AND status = 'pending'");
$reqCheck->execute([$wsId]);
assertTest((int)$reqCheck->fetchColumn() > 0, "Upgrade request recorded in database as pending");

// Cleanup test upgrade requests
$pdo->prepare("DELETE FROM subscription_upgrade_requests WHERE workspace_id = ?")->execute([$wsId]);
echo "\n[INFO] Cleaned up test upgrade requests.\n";

echo "\n====================================================\n";
echo "SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "====================================================\n";
