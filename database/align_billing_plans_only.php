<?php
require_once __DIR__ . '/../config/database.php';

echo "Aligning subscription plans to strictly Basic (PHP 1,899) and Premium (PHP 2,499)...\n";

try {
    // 1. Deactivate or delete old starter/growth/scale plans
    $pdo->exec("UPDATE subscription_plans SET is_active = 0 WHERE slug NOT IN ('basic', 'premium')");
    echo "[OK] Deactivated legacy plans.\n";

    // 2. Ensure Basic and Premium are active with correct pricing
    $stmtBasic = $pdo->prepare("UPDATE subscription_plans SET name = 'Basic Tier', description = 'Essential prospecting tools and Google Maps lead discovery.', price_monthly = 1899.00, price_annual = 18990.00, is_active = 1 WHERE slug = 'basic'");
    $stmtBasic->execute();

    $stmtPrem = $pdo->prepare("UPDATE subscription_plans SET name = 'Premium Tier', description = 'Advanced multi-source discovery including LinkedIn, advanced AI, and enhanced analytics.', price_monthly = 2499.00, price_annual = 24990.00, is_active = 1 WHERE slug = 'premium'");
    $stmtPrem->execute();

    echo "[OK] Basic and Premium plans updated with correct pricing.\n";

} catch (Exception $e) {
    echo "[ERROR] Plan alignment failed: " . $e->getMessage() . "\n";
}

echo "Billing plans alignment completed.\n";
