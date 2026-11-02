<?php
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!validate_csrf()) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failed']);
    exit;
}

if (!$workspaceService->hasPermission($workspaceRole, 'manage_leads')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized action']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$validStatuses = ['new', 'contacted', 'qualified', 'unqualified', 'replied', 'converted'];

if ($id <= 0 || !in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid lead or status.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ? AND workspace_id = ?");
    $stmt->execute([$status, $id, $currentWorkspaceId]);
    echo json_encode(['success' => true, 'status' => $status]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

