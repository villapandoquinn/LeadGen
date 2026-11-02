<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

if (!$workspaceService->hasPermission($workspaceRole, 'manage_members')) {
    header('Location: index.php?error=unauthorized');
    exit;
}

if (!$billingService->checkQuota($currentWorkspaceId, 'users')) {
    header('Location: index.php?error=user_seat_limit_reached');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = trim($_POST['role'] ?? 'member');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.php?error=valid_email_required');
    exit;
}

$res = $workspaceService->addMember($currentWorkspaceId, $email, $role, $name);
if ($res['success']) {
    header('Location: index.php?success=member_added');
} else {
    header('Location: index.php?error=' . urlencode($res['message']));
}
exit;

