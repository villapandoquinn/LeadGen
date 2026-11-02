<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

if (!$workspaceService->hasPermission($workspaceRole, 'manage_campaigns')) {
    header('Location: index.php?error=unauthorized');
    exit;
}

if (!$billingService->checkQuota($currentWorkspaceId, 'campaigns')) {
    header('Location: index.php?error=campaign_quota_exceeded');
    exit;
}

$name = trim($_POST['name'] ?? '');
$industry = trim($_POST['industry'] ?? '');
$location = trim($_POST['target_location'] ?? $_POST['location'] ?? '');
$keywords = trim($_POST['keywords'] ?? '');
$leadLimit = (int)($_POST['lead_limit'] ?? 100);
$description = trim($_POST['description'] ?? '');

if ($leadLimit <= 0) {
    $leadLimit = 100;
}

if ($name === '') {
    header('Location: index.php?error=name_required');
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO campaigns (workspace_id, name, industry, location, keywords, lead_limit, description, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)
    ");

    $stmt->execute([
        $currentWorkspaceId,
        $name,
        $industry ?: null,
        $location ?: null,
        $keywords ?: null,
        $leadLimit,
        $description ?: null,
        $userId
    ]);

    $newId = $pdo->lastInsertId();
    header("Location: index.php?success=created&id=" . $newId);
    exit;

} catch (Throwable $e) {
    error_log("Failed to create campaign: " . $e->getMessage());
    header('Location: index.php?error=database_error');
    exit;
}

