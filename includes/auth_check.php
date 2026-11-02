<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calculate relative path to root based on file location
$isInSubdir = (basename(dirname($_SERVER['SCRIPT_FILENAME'])) !== 'LeadGen');
$appRoot = $isInSubdir ? '..' : '.';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . $appRoot . "/index.php");
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$userRole = $_SESSION['user_role'] ?? 'Administrator';
$userPic = $_SESSION['profile_picture'] ?? null;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../services/WorkspaceService.php';
require_once __DIR__ . '/../services/BillingService.php';

$workspaceService = new WorkspaceService($pdo);
$billingService = new BillingService($pdo);

// Initialize active workspace context
$currentWorkspace = $workspaceService->getCurrentWorkspace((int)$userId);
$currentWorkspaceId = (int)$currentWorkspace['id'];
$workspaceRole = $currentWorkspace['member_role'] ?? 'member';
$userRole = ucfirst($workspaceRole);
$userWorkspaces = $workspaceService->getUserWorkspaces((int)$userId);

// Subscription status check for active workspace
$workspaceSubscription = $billingService->getWorkspaceSubscription($currentWorkspaceId);
$isSubscriptionActive = $billingService->isSubscriptionValid($currentWorkspaceId);
$usageStats = $billingService->getUsageStats($currentWorkspaceId);

// Gating check: if script is not billing or profile or settings, and subscription is expired/past due, enforce redirect
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$currentDir = basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
$isBillingOrExempt = in_array($currentDir, ['billing', 'profile', 'workspaces', 'settings'], true) || in_array($currentScript, ['logout.php'], true);

if (!$isSubscriptionActive && !$isBillingOrExempt) {
    header("Location: " . $appRoot . "/billing/index.php?alert=subscription_required");
    exit;
}

// Ensure latest profile info from database if available
if ($userId > 0) {
    try {
        $uSync = $pdo->prepare("SELECT name, email, profile_picture FROM users WHERE id = ? LIMIT 1");
        $uSync->execute([$userId]);
        $row = $uSync->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $userName = $row['name'] ?: $userName;
            $userEmail = $row['email'] ?: $userEmail;
            $userPic = $row['profile_picture'] ?: $userPic;
            $_SESSION['user_name'] = $userName;
            $_SESSION['user_email'] = $userEmail;
            $_SESSION['profile_picture'] = $userPic;
        }
    } catch (Throwable $e) {}
}

// Compute proper URL for avatar relative to current page
$userPicUrl = null;
if (!empty($userPic)) {
    if (preg_match('~^https?://~i', $userPic)) {
        $userPicUrl = $userPic;
    } else {
        $userPicUrl = $appRoot . '/' . ltrim($userPic, '/');
    }
}

// Generate Initials
$initials = '';
$nameParts = preg_split('/\s+/', trim($userName));
if (!empty($nameParts)) {
    $initials .= strtoupper(substr($nameParts[0], 0, 1));
    if (count($nameParts) > 1) {
        $initials .= strtoupper(substr(end($nameParts), 0, 1));
    }
}
if ($initials === '') {
    $initials = 'QV';
}

// Get unread counts or pending task counts for badge (workspace-scoped)
$pendingTasksCount = 0;
try {
    $pStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE status = 'pending' AND workspace_id = ?");
    $pStmt->execute([$currentWorkspaceId]);
    $pendingTasksCount = (int)$pStmt->fetchColumn();
} catch (Throwable $e) {}

$unreadConvCount = 0;
try {
    $cStmt = $pdo->prepare("SELECT SUM(unread_count) FROM conversations WHERE workspace_id = ?");
    $cStmt->execute([$currentWorkspaceId]);
    $unreadConvCount = (int)$cStmt->fetchColumn();
} catch (Throwable $e) {}
