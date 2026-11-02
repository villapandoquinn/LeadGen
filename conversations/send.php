<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

if (!$workspaceService->hasPermission($workspaceRole, 'manage_outreach')) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$convId = (int)($_POST['conversation_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($convId <= 0 || empty($message)) {
    header('Location: index.php');
    exit;
}

try {
    // Validate conversation belongs to active workspace
    $chk = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND workspace_id = ?");
    $chk->execute([$convId, $currentWorkspaceId]);
    if (!$chk->fetch()) {
        header('Location: index.php?error=unauthorized_conversation');
        exit;
    }

    // 1. Insert message
    $stmt = $pdo->prepare("
        INSERT INTO conversation_messages (conversation_id, sender_type, sender_name, message)
        VALUES (?, 'user', ?, ?)
    ");
    $stmt->execute([$convId, $userName, $message]);

    // 2. Update conversation
    $pdo->prepare("
        UPDATE conversations
        SET last_message = ?, unread_count = 0, last_message_at = NOW(), updated_at = NOW()
        WHERE id = ? AND workspace_id = ?
    ")->execute([$message, $convId, $currentWorkspaceId]);

    header("Location: index.php?id={$convId}");
    exit;
} catch (Throwable $e) {
    error_log("Conversation reply error: " . $e->getMessage());
    header("Location: index.php?id={$convId}&error=failed");
    exit;
}

