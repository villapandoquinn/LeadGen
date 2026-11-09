<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

$wsId = (int)($_POST['workspace_id'] ?? 0);
$userIds = $_POST['user_ids'] ?? [];
$role = trim($_POST['role'] ?? 'member');

if ($wsId <= 0) {
    header('Location: index.php?error=Invalid+workspace');
    exit;
}

$mCheck = $pdo->prepare("SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ? AND status = 'active'");
$mCheck->execute([$wsId, $userId]);
$userRoleInWs = $mCheck->fetchColumn();

if (!$userRoleInWs || !$workspaceService->hasPermission($userRoleInWs, 'manage_members')) {
    header("Location: view.php?id={$wsId}&error=Unauthorized");
    exit;
}

if (empty($userIds) || !is_array($userIds)) {
    header("Location: view.php?id={$wsId}&error=No+users+selected");
    exit;
}

$successCount = 0;
$errorMsg = '';

foreach ($userIds as $uId) {
    $uId = (int)$uId;
    if ($uId <= 0) continue;

    // Get user email
    $uStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $uStmt->execute([$uId]);
    $uEmail = $uStmt->fetchColumn();

    if ($uEmail) {
        $res = $workspaceService->addMember($wsId, $uEmail, $role);
        if ($res['success']) {
            $successCount++;
        }
    }
}

if ($successCount > 0) {
    header("Location: view.php?id={$wsId}&success=member_added");
} else {
    header("Location: view.php?id={$wsId}&error=Could+not+assign+selected+users+(already+members?)");
}
exit;
