<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/services/WorkspaceService.php';
require_once __DIR__ . '/services/BillingService.php';
require_once __DIR__ . '/services/GooglePlacesService.php';
require_once __DIR__ . '/services/RateLimiter.php';

echo "====================================================\n";
echo "PRIMOVATIVE / LEADFLOW SYSTEM ENHANCEMENT TEST SUITE\n";
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

// 1. Security & CSRF Testing
echo "--- 1. Security & CSRF Testing ---\n";
$token = csrf_token();
assertTest(!empty($token) && strlen($token) === 64, "CSRF token generated (32 bytes hex)");
assertTest(validate_csrf($token), "CSRF token validation matches session token");
assertTest(!validate_csrf('invalid_token_12345'), "CSRF token rejects tampered tokens");

// 2. Rate Limiting Testing
echo "\n--- 2. Rate Limiting Testing ---\n";
RateLimiter::reset('test_action');
$r1 = RateLimiter::check('test_action', 3, 60);
$r2 = RateLimiter::check('test_action', 3, 60);
$r3 = RateLimiter::check('test_action', 3, 60);
$r4 = RateLimiter::check('test_action', 3, 60);
assertTest($r1['allowed'] && $r2['allowed'] && $r3['allowed'], "First 3 attempts allowed within limit");
assertTest(!$r4['allowed'], "4th attempt blocked by rate limiter");
RateLimiter::reset('test_action');
// 3. Multi-Workspace Architecture & Isolation
echo "\n--- 3. Multi-Workspace Architecture & Isolation ---\n";
$wsService = new WorkspaceService($pdo);
$ws1 = $wsService->getCurrentWorkspace(1);
assertTest(!empty($ws1) && (int)$ws1['id'] === 1, "Default workspace 1 resolved for user 1");

$testWsName = "Test Isolation Corp " . time();
$ws2Id = $wsService->createWorkspace(1, $testWsName, "Isolated test workspace");
assertTest($ws2Id > 1, "Created secondary test workspace (ID: {$ws2Id})");

$campWs1 = $pdo->prepare("INSERT INTO campaigns (workspace_id, name, industry, status, created_by) VALUES (1, 'WS1 Solar', 'Solar', 'active', 1)");
$campWs1->execute();
$c1Id = $pdo->lastInsertId();

$campWs2 = $pdo->prepare("INSERT INTO campaigns (workspace_id, name, industry, status, created_by) VALUES (?, 'WS2 Roofing', 'Roofing', 'active', 1)");
$campWs2->execute([$ws2Id]);
$c2Id = $pdo->lastInsertId();

$stmtCheck1 = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE workspace_id = 1 AND id = ?");
$stmtCheck1->execute([$c2Id]);
assertTest((int)$stmtCheck1->fetchColumn() === 0, "Workspace 1 cannot see Workspace 2 campaigns");

$stmtCheck2 = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE workspace_id = ? AND id = ?");
$stmtCheck2->execute([$ws2Id, $c2Id]);
assertTest((int)$stmtCheck2->fetchColumn() === 1, "Workspace 2 can see its own campaign");

// 4. Role-Based Access Control
echo "\n--- 4. Role-Based Access Control ---\n";
assertTest($wsService->hasPermission('owner', 'delete_workspace'), "Owner has delete_workspace permission");
assertTest($wsService->hasPermission('admin', 'manage_workspace'), "Admin has manage_workspace permission");
assertTest(!$wsService->hasPermission('admin', 'delete_workspace'), "Admin cannot delete workspace");
assertTest($wsService->hasPermission('manager', 'manage_campaigns'), "Manager can manage campaigns");
assertTest(!$wsService->hasPermission('manager', 'billing'), "Manager cannot access billing");
assertTest(!$wsService->hasPermission('viewer', 'create'), "Viewer cannot create records");
assertTest($wsService->hasPermission('viewer', 'view'), "Viewer can view workspace records");

// 5. Billing & Subscription Management
echo "\n--- 5. Billing & Subscription Management ---\n";
$billingService = new BillingService($pdo);
$plans = $billingService->getAllPlans();
assertTest(count($plans) >= 3, "All 3 standard subscription plans configured in DB");

$subWs2 = $billingService->getWorkspaceSubscription($ws2Id);
assertTest(!empty($subWs2) && in_array($subWs2['status'], ['active', 'trial']), "Starter/trial subscription initialized for new workspace");

$quotaLeads = $billingService->checkQuota($ws2Id, 'leads');
assertTest($quotaLeads === true, "Lead quota check passed for new workspace");

$payRes = $billingService->processSubscriptionPayment($ws2Id, 2, 'monthly', ['last4' => '4242', 'brand' => 'visa']);
assertTest($payRes['success'] === true && !empty($payRes['invoice_number']), "Payment processed and invoice generated: " . ($payRes['invoice_number'] ?? ''));

$subAfterPay = $billingService->getWorkspaceSubscription($ws2Id);
assertTest((int)$subAfterPay['plan_id'] === 2 && $subAfterPay['status'] === 'active', "Subscription updated to Growth Pro (Plan ID 2)");

// 6. Lead Discovery & Qualification Engine
echo "\n--- 6. Lead Discovery & Qualification Engine ---\n";
$placesService = new GooglePlacesService();

$mockProspects = [
    [
        'place_id' => 'place_abc123',
        'name' => 'Austin Solar Power Pros',
        'company' => 'Austin Solar Power Pros',
        'category' => 'Solar Energy Equipment Supplier',
        'address' => '100 Congress Ave, Austin, TX',
        'phone' => '+1 512-555-0199',
        'website' => 'https://austinsolarpros.com',
        'rating' => 4.8,
        'reviews_count' => 45,
        'score' => 80
    ],
    [
        'place_id' => 'place_xyz789',
        'name' => 'Discount Wholesale Solar Parts Depot',
        'company' => 'Discount Wholesale Solar Parts Depot',
        'category' => 'Wholesale Store',
        'address' => '200 Industrial Blvd, Austin, TX',
        'phone' => '+1 512-555-0288',
        'website' => 'https://wholesaleparts.com',
        'rating' => 3.2,
        'reviews_count' => 5,
        'score' => 60
    ]
];

$placesService->qualifyProspects(
    $mockProspects,
    ['commercial', 'pros', 'residential'],
    ['wholesale', 'parts', 'depot'],
    'Solar'
);

assertTest($mockProspects[0]['qualification'] === 'Highly Relevant', "Prospect 1 scored Highly Relevant with include keyword and rating bonuses");
assertTest($mockProspects[1]['qualification'] === 'Not Relevant' && !empty($mockProspects[1]['is_excluded']), "Prospect 2 tagged Not Relevant due to negative keyword match");

$mockProspectsDup = [$mockProspects[0]];
$placesService->enrichDuplicates($mockProspectsDup, $pdo, $ws2Id);
assertTest($mockProspectsDup[0]['is_imported'] === false, "Prospect correctly identified as not yet imported in Workspace 2");

// Cleanup
$pdo->prepare("DELETE FROM campaigns WHERE id IN (?, ?)")->execute([$c1Id, $c2Id]);
$pdo->prepare("DELETE FROM payment_transactions WHERE workspace_id = ?")->execute([$ws2Id]);
$pdo->prepare("DELETE FROM workspace_subscriptions WHERE workspace_id = ?")->execute([$ws2Id]);
$pdo->prepare("DELETE FROM workspace_members WHERE workspace_id = ?")->execute([$ws2Id]);
$pdo->prepare("DELETE FROM workspaces WHERE id = ?")->execute([$ws2Id]);
echo "\n[INFO] Cleaned up temporary test artifacts.\n";

echo "\n====================================================\n";
echo "SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "====================================================\n";

