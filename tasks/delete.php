<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND workspace_id = ?");
        $stmt->execute([$id, $currentWorkspaceId]);
    } catch (Throwable $e) {
        error_log("Failed to delete task: " . $e->getMessage());
    }
}

header('Location: index.php?success=deleted');
exit;

