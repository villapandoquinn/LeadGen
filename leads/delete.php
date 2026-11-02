<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

if (!$workspaceService->hasPermission($workspaceRole, 'manage_leads')) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        // Find campaign if linked
        $cStmt = $pdo->prepare("SELECT campaign_id FROM leads WHERE id = ? AND workspace_id = ?");
        $cStmt->execute([$id, $currentWorkspaceId]);
        $cId = $cStmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ? AND workspace_id = ?");
        $stmt->execute([$id, $currentWorkspaceId]);

        if ($cId) {
            $pdo->prepare("UPDATE campaigns SET total_leads = GREATEST(0, CAST(total_leads AS SIGNED) - 1) WHERE id = ? AND workspace_id = ?")->execute([$cId, $currentWorkspaceId]);
        }
    } catch (Throwable $e) {
        error_log("Failed to delete lead: " . $e->getMessage());
    }
}

header('Location: index.php?success=deleted');
exit;

