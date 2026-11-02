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

if (!$billingService->checkQuota($currentWorkspaceId, 'outreach')) {
    header('Location: index.php?error=outreach_limit_exceeded');
    exit;
}

$leadId = (int)($_POST['lead_id'] ?? 0);
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$channel = trim($_POST['channel'] ?? 'email');

if ($leadId <= 0 || empty($subject) || empty($message)) {
    header('Location: index.php?error=missing_fields');
    exit;
}

try {
    $lStmt = $pdo->prepare("SELECT id, campaign_id, name, company, email, status FROM leads WHERE id = ? AND workspace_id = ?");
    $lStmt->execute([$leadId, $currentWorkspaceId]);
    $lead = $lStmt->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        header('Location: index.php?error=lead_not_found');
        exit;
    }

    if ($channel === 'email' && empty($lead['email'])) {
        header('Location: index.php?error=no_email');
        exit;
    }

    // Replace merge tags
    $renderedSubject = str_replace(['{{name}}', '{{company}}'], [$lead['name'], $lead['company'] ?: $lead['name']], $subject);
    $renderedMessage = str_replace(['{{name}}', '{{company}}'], [$lead['name'], $lead['company'] ?: $lead['name']], $message);

    // 1. Insert Outreach log
    $stmt = $pdo->prepare("
        INSERT INTO outreach (workspace_id, campaign_id, lead_id, subject, message, channel, status)
        VALUES (?, ?, ?, ?, ?, ?, 'sent')
    ");
    $stmt->execute([$currentWorkspaceId, $lead['campaign_id'], $leadId, $renderedSubject, $renderedMessage, $channel]);

    // 2. Update lead status if new
    if ($lead['status'] === 'new') {
        $pdo->prepare("UPDATE leads SET status = 'contacted' WHERE id = ? AND workspace_id = ?")->execute([$leadId, $currentWorkspaceId]);
    }

    // 3. Increment campaign contacted count
    if (!empty($lead['campaign_id'])) {
        $pdo->prepare("UPDATE campaigns SET contacted_leads = contacted_leads + 1 WHERE id = ? AND workspace_id = ?")->execute([$lead['campaign_id'], $currentWorkspaceId]);
    }

    // 4. Create or update conversation thread
    $cStmt = $pdo->prepare("SELECT id FROM conversations WHERE lead_id = ? AND workspace_id = ? LIMIT 1");
    $cStmt->execute([$leadId, $currentWorkspaceId]);
    $convId = $cStmt->fetchColumn();

    if (!$convId) {
        $insConv = $pdo->prepare("
            INSERT INTO conversations (workspace_id, lead_id, campaign_id, subject, last_message, unread_count, status)
            VALUES (?, ?, ?, ?, ?, 0, 'waiting_reply')
        ");
        $insConv->execute([$currentWorkspaceId, $leadId, $lead['campaign_id'], $renderedSubject, $renderedMessage]);
        $convId = $pdo->lastInsertId();
    } else {
        $updConv = $pdo->prepare("
            UPDATE conversations
            SET last_message = ?, status = 'waiting_reply', updated_at = NOW()
            WHERE id = ? AND workspace_id = ?
        ");
        $updConv->execute([$renderedMessage, $convId, $currentWorkspaceId]);
    }

    // 5. Add to conversation_messages
    $mStmt = $pdo->prepare("
        INSERT INTO conversation_messages (conversation_id, sender_type, sender_name, message)
        VALUES (?, 'user', ?, ?)
    ");
    $mStmt->execute([$convId, $userName, $renderedMessage]);

    header('Location: index.php?success=sent');
    exit;

} catch (Throwable $e) {
    error_log("Outreach error: " . $e->getMessage());
    header('Location: index.php?error=db_error');
    exit;
}

