<?php
/**
 * linkedin/api_sync.php -- Sync leads handler (included by api.php for sync_leads action)
 * GET /rest/leadFormSubmissions -- r_ads_leadgen_automation -- LinkedIn-Version: 202509
 */
$aid  = trim($_POST['account_id'] ?? '');
$fid  = trim($_POST['form_id']    ?? '') ?: null;
$cid  = !empty($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : null;
$mode = trim($_POST['sync_mode']  ?? 'incremental');
if (!$aid) { echo json_encode(['success'=>false,'message'=>'LinkedIn Account ID required.']); return; }

// Get last sync timestamp for incremental sync
$lastTs = null;
if ($mode === 'incremental') {
    try {
        $ls = $pdo->prepare("SELECT UNIX_TIMESTAMP(created_at) ts FROM linkedin_sync_logs WHERE workspace_id=? AND account_id=? AND status='completed' ORDER BY created_at DESC LIMIT 1");
        $ls->execute([$currentWorkspaceId, $aid]);
        $row = $ls->fetch();
        if ($row) $lastTs = (int)$row['ts'];
    } catch (Throwable $e) {}
}

// Create sync log
$logId = null;
try {
    $il = $pdo->prepare("INSERT INTO linkedin_sync_logs (workspace_id,user_id,account_id,form_id,status,sync_from) VALUES(?,?,?,?,'started',?)");
    $il->execute([$currentWorkspaceId, $userId, $aid, $fid, $lastTs ? date('Y-m-d H:i:s', $lastTs) : null]);
    $logId = $pdo->lastInsertId();
} catch (Throwable $e) {}

// Fetch all lead form submissions via paginated API
$sr = $service->syncLeads($aid, $fid, $lastTs);

if (!$sr['success']) {
    if ($logId) {
        try { $pdo->prepare("UPDATE linkedin_sync_logs SET status='failed',error_message=?,completed_at=NOW() WHERE id=?")->execute([$sr['message'], $logId]); } catch (Throwable $e) {}
    }
    echo json_encode(['success'=>false,'message'=>$sr['message']]);
    return;
}

$leads = $sr['leads'];
$ret = count($leads); $imp = 0; $skp = 0; $fld = 0;

// Validate campaign belongs to workspace
if ($cid) {
    $cc = $pdo->prepare("SELECT id FROM campaigns WHERE id=? AND workspace_id=?");
    $cc->execute([$cid, $currentWorkspaceId]);
    if (!$cc->fetch()) $cid = null;
}

// Mark already-imported leads
$service->enrichDuplicates($leads, $pdo, $currentWorkspaceId);

foreach ($leads as $lead) {
    // Skip already imported (by linkedin_submission_id)
    if (!empty($lead['is_imported'])) { $skp++; continue; }
    try {
        // Additional email duplicate check
        if (!empty($lead['email'])) {
            $ec = $pdo->prepare("SELECT id FROM leads WHERE workspace_id=? AND email=? LIMIT 1");
            $ec->execute([$currentWorkspaceId, $lead['email']]);
            if ($ec->fetch()) { $skp++; continue; }
        }
        $pdo->prepare("INSERT INTO leads (workspace_id,campaign_id,name,company,email,phone,website,industry,location,source,status,score,notes,linkedin_submission_id,linkedin_form_id,linkedin_campaign_id,linkedin_account_id,linkedin_first_name,linkedin_last_name,linkedin_job_title,linkedin_submitted_at) VALUES(?,?,?,?,?,?,?,?,?,'linkedin','new',?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $currentWorkspaceId, $cid,
                $lead['name']    ?? 'LinkedIn Lead',
                $lead['company'] ?? '',
                !empty($lead['email'])    ? $lead['email']    : null,
                $lead['phone']   ?? '',
                $lead['website'] ?? '',
                $lead['industry'] ?? '',
                $lead['location'] ?? '',
                (int)($lead['score']  ?? 80),
                $lead['notes']   ?? '',
                $lead['linkedin_submission_id'] ?? null,
                $lead['linkedin_form_id']       ?? null,
                $lead['linkedin_campaign_id']   ?? null,
                $lead['linkedin_account_id']    ?? null,
                $lead['linkedin_first_name']    ?? null,
                $lead['linkedin_last_name']     ?? null,
                $lead['linkedin_job_title']     ?? null,
                $lead['linkedin_submitted_at']  ?? null,
            ]);
        $imp++;
        if ($cid) $pdo->prepare("UPDATE campaigns SET total_leads=total_leads+1 WHERE id=? AND workspace_id=?")->execute([$cid, $currentWorkspaceId]);
    } catch (Throwable $e) {
        $fld++;
        error_log('[LinkedIn Sync] Insert error: ' . $e->getMessage());
    }
}

// Update sync log
if ($logId) {
    try {
        $pdo->prepare("UPDATE linkedin_sync_logs SET status='completed',leads_retrieved=?,leads_imported=?,leads_skipped=?,leads_failed=?,sync_to=NOW(),completed_at=NOW() WHERE id=?")
            ->execute([$ret, $imp, $skp, $fld, $logId]);
    } catch (Throwable $e) {}
}

$service->logDiscovery($pdo, $currentWorkspaceId, $cid, $userId, 'Account:' . $aid, $imp);

echo json_encode([
    'success'   => true,
    'message'   => "Sync complete. Retrieved: $ret | Imported: $imp | Skipped (duplicates): $skp | Failed: $fld",
    'retrieved' => $ret,
    'imported'  => $imp,
    'skipped'   => $skp,
    'failed'    => $fld,
]);
