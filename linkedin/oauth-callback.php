<?php
/**
 * LinkedIn OAuth 2.0 Callback Handler
 * linkedin/oauth-callback.php
 *
 * LinkedIn redirects here after user authorization.
 * Exchanges auth code for access token via LinkedInLeadService.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../services/LinkedInLeadService.php';

$service = new LinkedInLeadService();

// Validate state parameter (CSRF protection)
$state       = trim($_GET['state'] ?? '');
$storedState = $_SESSION['linkedin_oauth_state'] ?? '';
unset($_SESSION['linkedin_oauth_state']);

// Handle OAuth errors from LinkedIn
if (!empty($_GET['error'])) {
    $errDesc = htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
    header("Location: {$appRoot}/linkedin/index.php?error=" . urlencode($errDesc));
    exit;
}

// Validate state
if (empty($state) || $state !== $storedState) {
    header("Location: {$appRoot}/linkedin/index.php?error=" . urlencode('OAuth state mismatch. Please try connecting again.'));
    exit;
}

$code = trim($_GET['code'] ?? '');
if (empty($code)) {
    header("Location: {$appRoot}/linkedin/index.php?error=" . urlencode('No authorization code received from LinkedIn.'));
    exit;
}

// Exchange code for access token
$result = $service->handleOAuthCallback($code);

if ($result['success']) {
    // Log the connection event
    try {
        $pdo->prepare("INSERT INTO discovery_logs (workspace_id, campaign_id, user_id, search_query, results_count) VALUES (?, NULL, ?, ?, 0)")
            ->execute([$currentWorkspaceId, $userId, 'LinkedIn OAuth: Account connected']);
    } catch (Throwable $e) {}

    header("Location: {$appRoot}/linkedin/index.php?success=" . urlencode('LinkedIn account connected successfully!'));
} else {
    error_log('[LinkedIn Callback] Failed: ' . $result['message']);
    header("Location: {$appRoot}/linkedin/index.php?error=" . urlencode($result['message']));
}
exit;
