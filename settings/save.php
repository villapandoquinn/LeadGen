<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf();

if (!$workspaceService->hasPermission($workspaceRole, 'manage_workspace')) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$workspaceName = trim($_POST['workspace_name'] ?? 'Primovative Co.');
$supportEmail = trim($_POST['support_email'] ?? 'support@primovative.com');
$timezone = trim($_POST['timezone'] ?? 'America/New_York');
$dailyLimit = (int)($_POST['daily_limit'] ?? 500);
$enableTracking = !empty($_POST['enable_tracking']);
$senderName = trim($_POST['sender_name'] ?? 'Primovative Team');
$senderEmail = trim($_POST['sender_email'] ?? $supportEmail);

$smtpHost = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
$smtpPort = (int)($_POST['smtp_port'] ?? 587);
$smtpEncryption = trim($_POST['smtp_encryption'] ?? 'TLS');
$smtpUsername = trim($_POST['smtp_username'] ?? '');
$smtpPassword = trim($_POST['smtp_password'] ?? '');

$googleApiKey = trim($_POST['google_maps_api_key'] ?? '');
$linkedInClientId = trim($_POST['linkedin_client_id'] ?? '');
$linkedInClientSecret = trim($_POST['linkedin_client_secret'] ?? '');
$linkedInRedirectUri = trim($_POST['linkedin_redirect_uri'] ?? '');
$linkedInAccessToken = trim($_POST['linkedin_access_token'] ?? '');

$settingsFile = __DIR__ . '/../config/settings.json';
$current = [];
if (file_exists($settingsFile)) {
    $current = json_decode(file_get_contents($settingsFile), true) ?: [];
}

$payload = [
    'workspace_name' => $workspaceName,
    'support_email' => $supportEmail,
    'timezone' => $timezone,
    'daily_limit' => $dailyLimit,
    'enable_tracking' => $enableTracking,
    'sender_name' => $senderName,
    'sender_email' => $senderEmail,
    'smtp_host' => $smtpHost ?: 'smtp.gmail.com',
    'smtp_port' => $smtpPort ?: 587,
    'smtp_encryption' => $smtpEncryption ?: 'TLS',
    'smtp_username' => $smtpUsername ?: ($current['smtp_username'] ?? ''),
    'smtp_password' => $smtpPassword ?: ($current['smtp_password'] ?? ''),
    'google_maps_api_key' => $googleApiKey ?: ($current['google_maps_api_key'] ?? ''),
    'linkedin_client_id' => $linkedInClientId ?: ($current['linkedin_client_id'] ?? ''),
    'linkedin_client_secret' => $linkedInClientSecret ?: ($current['linkedin_client_secret'] ?? ''),
    'linkedin_redirect_uri' => $linkedInRedirectUri ?: ($current['linkedin_redirect_uri'] ?? ''),
    'linkedin_access_token' => $linkedInAccessToken ?: ($current['linkedin_access_token'] ?? ''),
    'updated_at' => date('Y-m-d H:i:s')
];

// 1. Update current workspace in database
try {
    $uWs = $pdo->prepare("UPDATE workspaces SET name = ?, settings = ? WHERE id = ?");
    $uWs->execute([$workspaceName, json_encode($payload), $currentWorkspaceId]);
} catch (Throwable $e) {
    error_log("Failed to update workspace settings: " . $e->getMessage());
}

// 2. Update config/settings.json
file_put_contents($settingsFile, json_encode(array_merge($current, $payload), JSON_PRETTY_PRINT));

header('Location: index.php?success=saved');
exit;


