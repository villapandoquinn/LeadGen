<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$priority = $_POST['priority'] ?? 'medium';
$dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+2 days'));
$leadId = !empty($_POST['lead_id']) ? (int)$_POST['lead_id'] : null;
$campaignId = !empty($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : null;

if (empty($title)) {
    header('Location: index.php?error=missing_title');
    exit;
}

// Validate lead belongs to active workspace if specified
if ($leadId) {
    $lCheck = $pdo->prepare("SELECT id FROM leads WHERE id = ? AND workspace_id = ?");
    $lCheck->execute([$leadId, $currentWorkspaceId]);
    if (!$lCheck->fetch()) {
        $leadId = null;
    }
}

// Validate campaign belongs to active workspace if specified
if ($campaignId) {
    $cCheck = $pdo->prepare("SELECT id FROM campaigns WHERE id = ? AND workspace_id = ?");
    $cCheck->execute([$campaignId, $currentWorkspaceId]);
    if (!$cCheck->fetch()) {
        $campaignId = null;
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO tasks (workspace_id, title, description, priority, status, due_date, lead_id, campaign_id, user_id)
        VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?)
    ");
    $stmt->execute([$currentWorkspaceId, $title, $description, $priority, $dueDate, $leadId, $campaignId, $userId]);
    header('Location: index.php?success=created');
    exit;
} catch (Throwable $e) {
    error_log("Failed to create task: " . $e->getMessage());
    header('Location: index.php?error=db_error');
    exit;
}

