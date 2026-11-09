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

$leadId = (int)($_POST['lead_id'] ?? 0);
$assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

if ($leadId <= 0) {
    header('Location: index.php?error=invalid_lead');
    exit;
}

// Verify lead belongs to active workspace
$lCheck = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND workspace_id = ?");
$lCheck->execute([$leadId, $currentWorkspaceId]);
if (!$lCheck->fetch()) {
    header('Location: index.php?error=not_found');
    exit;
}

// If assignedTo is specified, verify user is member of workspace
if ($assignedTo) {
    $mCheck = $pdo->prepare("SELECT user_id FROM workspace_members WHERE workspace_id = ? AND user_id = ? AND status = 'active'");
    $mCheck->execute([$currentWorkspaceId, $assignedTo]);
    if (!$mCheck->fetch()) {
        header("Location: view.php?id={$leadId}&error=invalid_employee");
        exit;
    }
}

try {
    $stmt = $pdo->prepare("UPDATE leads SET assigned_to = ? WHERE id = ? AND workspace_id = ?");
    $stmt->execute([$assignedTo, $leadId, $currentWorkspaceId]);

    header("Location: view.php?id={$leadId}&success=assigned");
    exit;
} catch (Throwable $e) {
    error_log("Failed to assign lead: " . $e->getMessage());
    header("Location: view.php?id={$leadId}&error=db_error");
    exit;
}
