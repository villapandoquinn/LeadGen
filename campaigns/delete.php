<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

if (!$workspaceService->hasPermission($workspaceRole, 'manage_campaigns')) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = ? AND workspace_id = ?");
        $stmt->execute([$id, $currentWorkspaceId]);

        // Nullify campaign_id on any orphaned leads in this workspace
        $pdo->prepare("UPDATE leads SET campaign_id = NULL WHERE campaign_id = ? AND workspace_id = ?")->execute([$id, $currentWorkspaceId]);
    } catch (Throwable $e) {
        error_log("Failed to delete campaign: " . $e->getMessage());
    }
}

header('Location: index.php?success=deleted');
exit;

