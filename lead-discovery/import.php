<?php
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!validate_csrf()) {
    echo json_encode(['success' => false, 'message' => 'CSRF verification failed. Please refresh the page.']);
    exit;
}

if (!$workspaceService->hasPermission($workspaceRole, 'manage_leads')) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to import leads in this workspace.']);
    exit;
}

if (!$billingService->checkQuota($currentWorkspaceId, 'leads')) {
    echo json_encode(['success' => false, 'message' => 'Monthly lead limit reached for this workspace plan. Upgrade in Billing to import more leads.']);
    exit;
}

$company = trim($_POST['company'] ?? '');
$name = trim($_POST['name'] ?? $company);
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$website = trim($_POST['website'] ?? '');
$industry = trim($_POST['industry'] ?? '');
$location = trim($_POST['location'] ?? '');
$address = trim($_POST['address'] ?? $location);
$placeId = trim($_POST['place_id'] ?? '');
$rating = isset($_POST['rating']) && $_POST['rating'] !== '' ? (float)$_POST['rating'] : null;
$reviewsCount = (int)($_POST['reviews_count'] ?? 0);
$employeeCount = isset($_POST['employee_count']) && $_POST['employee_count'] !== '' && is_numeric($_POST['employee_count']) ? (int)$_POST['employee_count'] : null;
$employeeSource = $employeeCount !== null ? trim($_POST['employee_count_source'] ?? 'Google Maps / Enrichment') : null;
$source = trim($_POST['source'] ?? 'google');
$score = (int)($_POST['score'] ?? 85);
$campaignId = !empty($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : null;

if (empty($company)) {
    echo json_encode(['success' => false, 'message' => 'Business / company name is required.']);
    exit;
}

// Validate campaign belongs to active workspace
if ($campaignId) {
    $cChk = $pdo->prepare("SELECT id FROM campaigns WHERE id = ? AND workspace_id = ?");
    $cChk->execute([$campaignId, $currentWorkspaceId]);
    if (!$cChk->fetch()) {
        $campaignId = null;
    }
}

try {
    // 1. Check duplicate by place_id within current workspace
    if (!empty($placeId)) {
        $cStmt = $pdo->prepare("SELECT id FROM leads WHERE workspace_id = ? AND google_place_id = ? LIMIT 1");
        $cStmt->execute([$currentWorkspaceId, $placeId]);
        if ($cStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This business has already been imported into this workspace.']);
            exit;
        }
    }

    // 2. Check duplicate by phone within current workspace
    if (!empty($phone)) {
        $pStmt = $pdo->prepare("SELECT id FROM leads WHERE workspace_id = ? AND phone = ? LIMIT 1");
        $pStmt->execute([$currentWorkspaceId, $phone]);
        if ($pStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A lead with this phone number already exists in your workspace.']);
            exit;
        }
    }

    // 3. Check duplicate by email within current workspace if provided
    if (!empty($email)) {
        $eStmt = $pdo->prepare("SELECT id FROM leads WHERE workspace_id = ? AND email = ? LIMIT 1");
        $eStmt->execute([$currentWorkspaceId, $email]);
        if ($eStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A lead with this email address already exists in your workspace.']);
            exit;
        }
    }

    // Insert lead scoped to current workspace
    $stmt = $pdo->prepare("
        INSERT INTO leads (workspace_id, google_place_id, campaign_id, name, company, email, phone, website, industry, location, address, source, status, score, rating, reviews_count, employee_count, employee_count_source, employee_count_verified_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, ?, ?, ?, " . ($employeeCount !== null ? "NOW()" : "NULL") . ")
    ");
    $stmt->execute([
        $currentWorkspaceId,
        !empty($placeId) ? $placeId : null,
        $campaignId,
        $name,
        $company,
        !empty($email) ? $email : null,
        $phone,
        $website,
        $industry,
        $location,
        $address,
        $source,
        $score,
        $rating,
        $reviewsCount,
        $employeeCount,
        $employeeSource
    ]);
    $leadId = $pdo->lastInsertId();

    if ($campaignId) {
        $pdo->prepare("UPDATE campaigns SET total_leads = total_leads + 1 WHERE id = ? AND workspace_id = ?")->execute([$campaignId, $currentWorkspaceId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Lead successfully imported into workspace!',
        'lead_id' => $leadId
    ]);

} catch (Throwable $e) {
    error_log("Import lead error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}


