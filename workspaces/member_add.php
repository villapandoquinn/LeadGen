<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

$wsId = (int)($_POST['workspace_id'] ?? 0);
$email = trim($_POST['email'] ?? '');
$name = trim($_POST['name'] ?? '');
$role = trim($_POST['role'] ?? 'member');

$mCheck = $pdo->prepare("SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ? AND status = 'active'");
$mCheck->execute([$wsId, $userId]);
$userRoleInWs = $mCheck->fetchColumn();

if (!$userRoleInWs || !$workspaceService->hasPermission($userRoleInWs, 'manage_members')) {
    header('Location: index.php?error=Unauthorized');
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: view.php?id={$wsId}&error=Valid+email+required");
    exit;
}

$res = $workspaceService->addMember($wsId, $email, $role, $name);
if ($res['success']) {
    header("Location: view.php?id={$wsId}&success=member_added");
} else {
    header("Location: view.php?id={$wsId}&error=" . urlencode($res['message']));
}
exit;
