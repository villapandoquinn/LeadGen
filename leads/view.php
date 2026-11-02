<?php
require_once __DIR__ . '/../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("
    SELECT l.*, c.name as campaign_name 
    FROM leads l 
    LEFT JOIN campaigns c ON l.campaign_id = c.id 
    WHERE l.id = ? AND l.workspace_id = ?
");
$stmt->execute([$id, $currentWorkspaceId]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$lead) { header('Location: index.php?error=not_found'); exit; }

$outreachStmt = $pdo->prepare("SELECT * FROM outreach WHERE lead_id = ? AND workspace_id = ? ORDER BY created_at DESC");
$outreachStmt->execute([$id, $currentWorkspaceId]);
$outreachLogs = $outreachStmt->fetchAll(PDO::FETCH_ASSOC);

$tasksStmt = $pdo->prepare("SELECT * FROM tasks WHERE lead_id = ? AND workspace_id = ? ORDER BY due_date ASC");
$tasksStmt->execute([$id, $currentWorkspaceId]);
$tasks = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);

$pageEyebrow = 'Leads';
$pageTitle = $lead['company'] ?: $lead['name'];
$activePage = 'leads';
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> — Primovative Co.</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-area">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <main class="content">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
          <a href="index.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-arrow-left me-1"></i> Back</a>
          <div class="d-flex align-items-center gap-2">
            <h2 class="h4 mb-0 fw-bold"><?= htmlspecialchars($lead['company'] ?: $lead['name']) ?></h2>
            <span class="badge bg-primary"><?= ucfirst($lead['status']) ?></span>
          </div>
          <div class="text-muted small mt-1"><?= htmlspecialchars($lead['name']) ?> &bull; <?= htmlspecialchars($lead['email']) ?></div>
        </div>
        <div class="d-flex gap-2">
          <a href="../outreach/index.php?lead_id=<?= $lead['id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i> Send Outreach</a>
          <a href="../tasks/index.php?lead_id=<?= $lead['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-square me-1"></i> Add Task</a>
        </div>
      </div>
      <div class="row g-4">
        <div class="col-12 col-lg-5">
          <div class="dashboard-card p-4 mb-4">
            <h3 class="h6 fw-bold mb-3">Lead Overview</h3>
            <div class="mb-3">
              <div class="text-muted small">Lead Score</div>
              <div class="d-flex align-items-center gap-2 mt-1">
                <div class="progress flex-grow-1" style="height:8px;"><div class="progress-bar bg-success" style="width:<?= $lead['score'] ?>%;"></div></div>
                <strong class="small"><?= $lead['score'] ?>/100</strong>
              </div>
            </div>
            <div class="mb-3"><div class="text-muted small">Campaign</div><div class="small fw-semibold mt-1"><?= $lead['campaign_name'] ? htmlspecialchars($lead['campaign_name']) : 'None' ?></div></div>
            <div class="mb-3"><div class="text-muted small">Phone</div><div class="small fw-semibold mt-1"><?= htmlspecialchars($lead['phone'] ?: 'N/A') ?></div></div>
            <?php if (!empty($lead['website'])): ?>
              <div class="mb-3"><div class="text-muted small">Website</div><div class="small fw-semibold mt-1"><a href="<?= htmlspecialchars($lead['website']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($lead['website']) ?></a></div></div>
            <?php endif; ?>
            <div class="mb-3"><div class="text-muted small">Location / Address</div><div class="small fw-semibold mt-1"><?= htmlspecialchars($lead['address'] ?: ($lead['location'] ?: 'N/A')) ?></div></div>
            <?php if (!empty($lead['rating'])): ?>
              <div class="mb-3">
                <div class="text-muted small">Google Maps Rating</div>
                <div class="small fw-semibold mt-1">
                  <span class="place-rating-badge"><i class="bi bi-star-fill text-warning"></i> <?= number_format($lead['rating'], 1) ?> (<?= number_format($lead['reviews_count'] ?? 0) ?> reviews)</span>
                </div>
              </div>
            <?php endif; ?>
            <?php if (!empty($lead['google_place_id'])): ?>
              <div class="mb-3">
                <div class="text-muted small">Google Place ID</div>
                <div class="small mt-1"><span class="place-id-pill"><?= htmlspecialchars($lead['google_place_id']) ?></span></div>
              </div>
            <?php endif; ?>
            <?php if (!empty($lead['notes'])): ?>
              <div class="border-top pt-3"><div class="text-muted small">Notes</div><p class="small text-secondary mb-0 mt-1"><?= nl2br(htmlspecialchars($lead['notes'])) ?></p></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-12 col-lg-7">
          <div class="dashboard-card p-4 mb-4">
            <h3 class="h6 fw-bold mb-3">Outreach History (<?= count($outreachLogs) ?>)</h3>
            <?php if (empty($outreachLogs)): ?>
              <p class="text-muted small text-center py-3">No messages recorded. <a href="../outreach/index.php?lead_id=<?= $lead['id'] ?>">Send message</a></p>
            <?php else: ?>
              <?php foreach ($outreachLogs as $o): ?>
                <div class="pb-2 mb-2 border-bottom">
                  <div class="d-flex justify-content-between small fw-semibold">
                    <span><?= htmlspecialchars($o['subject']) ?></span>
                    <span class="badge bg-light text-dark border"><?= ucfirst($o['status']) ?></span>
                  </div>
                  <div class="text-muted small"><?= date('M d, Y', strtotime($o['sent_at'])) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="dashboard-card p-4">
            <h3 class="h6 fw-bold mb-3">Tasks (<?= count($tasks) ?>)</h3>
            <?php if (empty($tasks)): ?>
              <p class="text-muted small text-center py-2">No follow-up tasks.</p>
            <?php else: ?>
              <?php foreach ($tasks as $t): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                  <div class="small fw-semibold"><?= htmlspecialchars($t['title']) ?></div>
                  <span class="badge bg-secondary"><?= ucfirst($t['priority']) ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
