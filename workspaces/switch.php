<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $appRoot . '/dashboard.php');
    exit;
}

require_csrf();

$targetWorkspaceId = (int)($_POST['workspace_id'] ?? 0);
$redirectTo = trim($_POST['redirect_to'] ?? '');

if ($targetWorkspaceId > 0) {
    $switched = $workspaceService->switchWorkspace($userId, $targetWorkspaceId);
    if ($switched) {
        if (!empty($redirectTo) && str_starts_with($redirectTo, '/') && !str_contains($redirectTo, '//')) {
            header('Location: ' . $redirectTo);
            exit;
        }
        header('Location: ' . $appRoot . '/dashboard.php?switched=1');
        exit;
    }
}

header('Location: ' . $appRoot . '/dashboard.php?error=unauthorized_workspace');
exit;
