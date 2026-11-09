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

if (!$billingService->checkQuota($currentWorkspaceId, 'leads')) {
    header('Location: index.php?error=leads_limit_exceeded');
    exit;
}

$name = trim($_POST['name'] ?? '');
$company = trim($_POST['company'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$website = trim($_POST['website'] ?? '');
$industry = trim($_POST['industry'] ?? '');
$location = trim($_POST['location'] ?? '');
$campaignId = !empty($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : null;
$assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
$score = max(0, min(100, (int)($_POST['score'] ?? 50)));
$notes = trim($_POST['notes'] ?? '');

if (empty($name) || empty($email)) {
    header('Location: index.php?error=required_fields');
    exit;
}

// Validate campaign belongs to active workspace if specified
if ($campaignId) {
    $cCheck = $pdo->prepare("SELECT id FROM campaigns WHERE id = ? AND workspace_id = ?");
    $cCheck->execute([$campaignId, $currentWorkspaceId]);
    if (!$cCheck->fetch()) {
        header('Location: index.php?error=invalid_campaign');
        exit;
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO leads (workspace_id, name, company, email, phone, website, industry, location, campaign_id, assigned_to, score, notes, status, source)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', 'manual')
    ");
    $stmt->execute([$currentWorkspaceId, $name, $company, $email, $phone, $website, $industry, $location, $campaignId, $assignedTo, $score, $notes]);

    if ($campaignId) {
        $pdo->prepare("UPDATE campaigns SET total_leads = total_leads + 1 WHERE id = ? AND workspace_id = ?")->execute([$campaignId, $currentWorkspaceId]);
    }

    header('Location: index.php?success=created');
    exit;
} catch (Throwable $e) {
    error_log("Failed to insert lead: " . $e->getMessage());
    header('Location: index.php?error=db_error');
    exit;
}

