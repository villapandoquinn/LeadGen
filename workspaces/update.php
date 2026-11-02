<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

$wsId = (int)($_POST['workspace_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

// Check user permission
$mCheck = $pdo->prepare("SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ? AND status = 'active'");
$mCheck->execute([$wsId, $userId]);
$role = $mCheck->fetchColumn();

if (!$role || !$workspaceService->hasPermission($role, 'manage_workspace')) {
    header('Location: index.php?error=Unauthorized+action');
    exit;
}

if (empty($name)) {
    header("Location: view.php?id={$wsId}&error=Name+required");
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE workspaces SET name = ?, description = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $description, $status, $wsId]);
    header("Location: view.php?id={$wsId}&success=updated");
    exit;
} catch (Throwable $e) {
    header("Location: view.php?id={$wsId}&error=" . urlencode($e->getMessage()));
    exit;
}
