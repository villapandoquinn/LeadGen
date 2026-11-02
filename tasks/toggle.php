<?php
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (!validate_csrf()) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF security token']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$completed = !empty($_POST['completed']) ? true : false;
$status = $completed ? 'completed' : 'pending';

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND workspace_id = ?");
    $stmt->execute([$status, $id, $currentWorkspaceId]);
    echo json_encode(['success' => true, 'status' => $status]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

