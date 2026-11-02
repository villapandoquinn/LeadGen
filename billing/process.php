<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

if (!$workspaceService->hasPermission($workspaceRole, 'billing')) {
    header('Location: index.php?error=Only+workspace+administrators+can+manage+billing');
    exit;
}

$planId = (int)($_POST['plan_id'] ?? 0);
$cycle = trim($_POST['billing_cycle'] ?? 'monthly');
$cardNum = trim($_POST['card_number'] ?? '');
$cardLast4 = substr(preg_replace('/\D/', '', $cardNum), -4) ?: '4242';

if ($planId <= 0) {
    header('Location: index.php?error=Please+select+a+valid+subscription+plan');
    exit;
}

$res = $billingService->processSubscriptionPayment($currentWorkspaceId, $planId, $cycle, [
    'last4' => $cardLast4,
    'brand' => 'Visa'
]);

if ($res['success']) {
    header('Location: index.php?success=paid&inv=' . urlencode($res['invoice_number']));
} else {
    header('Location: index.php?error=' . urlencode($res['message']));
}
exit;
