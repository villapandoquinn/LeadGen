<?php
/**
 * LinkedIn OAuth Disconnect Handler
 * linkedin/disconnect.php
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../services/LinkedInLeadService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$appRoot}/linkedin/index.php");
    exit;
}

require_csrf();

if (!$workspaceService->hasPermission($workspaceRole, 'manage_workspace')) {
    header("Location: {$appRoot}/linkedin/index.php?error=" . urlencode('You do not have permission to manage LinkedIn integration.'));
    exit;
}

$service = new LinkedInLeadService();
$service->revokeTokens();

header("Location: {$appRoot}/linkedin/index.php?success=" . urlencode('LinkedIn account disconnected successfully.'));
exit;
