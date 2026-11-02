<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

$wsId = (int)($_POST['workspace_id'] ?? 0);
$targetUserId = (int)($_POST['user_id'] ?? 0);
$role = trim($_POST['role'] ?? '');

$mCheck = $pdo->prepare("SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ? AND status = 'active'");
$mCheck->execute([$wsId, $userId]);
$userRoleInWs = $mCheck->fetchColumn();

if (!$userRoleInWs || !$workspaceService->hasPermission($userRoleInWs, 'manage_members')) {
    header('Location: index.php?error=Unauthorized');
    exit;
}

$res = $workspaceService->updateMemberRole($wsId, $targetUserId, $role);
if ($res['success']) {
    header("Location: view.php?id={$wsId}&success=updated");
} else {
    header("Location: view.php?id={$wsId}&error=" . urlencode($res['message']));
}
exit;
