<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

if (!$workspaceService->hasPermission($workspaceRole, 'billing')) {
    header('Location: index.php?error=Only+workspace+administrators+can+cancel+subscriptions');
    exit;
}

$reason = trim($_POST['cancel_reason'] ?? '');
$res = $billingService->cancelSubscription($currentWorkspaceId, $reason);

if ($res['success']) {
    header('Location: index.php?success=cancelled');
} else {
    header('Location: index.php?error=' . urlencode($res['message']));
}
exit;
