<?php
require_once __DIR__ . '/includes/auth_check.php';

$pageEyebrow = 'Workspace';
$pageTitle = 'Dashboard';
$activePage = 'dashboard';

// Aggregate KPI metrics (workspace-scoped)
$tStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ?");
$tStmt->execute([$currentWorkspaceId]);
$totalLeads = (int)$tStmt->fetchColumn();

$qStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND status = 'qualified'");
$qStmt->execute([$currentWorkspaceId]);
$qualifiedLeads = (int)$qStmt->fetchColumn();

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND status = 'contacted'");
$cntStmt->execute([$currentWorkspaceId]);
$contactedLeads = (int)$cntStmt->fetchColumn();

$rStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND status = 'replied'");
$rStmt->execute([$currentWorkspaceId]);
$repliedLeads = (int)$rStmt->fetchColumn();

$cStmt = $pdo->prepare("SELECT SUM(total_leads), SUM(qualified_leads), SUM(contacted_leads), SUM(replied_leads) FROM campaigns WHERE workspace_id = ?");
$cStmt->execute([$currentWorkspaceId]);
$cRow = $cStmt->fetch(PDO::FETCH_NUM);
if ($cRow) {
    if ((int)$cRow[0] > $totalLeads) $totalLeads = (int)$cRow[0];
    if ((int)$cRow[1] > $qualifiedLeads) $qualifiedLeads = (int)$cRow[1];
    if ((int)$cRow[2] > $contactedLeads) $contactedLeads = (int)$cRow[2];
    if ((int)$cRow[3] > $repliedLeads) $repliedLeads = (int)$cRow[3];
}

$cListStmt = $pdo->prepare("SELECT * FROM campaigns WHERE workspace_id = ? ORDER BY total_leads DESC LIMIT 4");
$cListStmt->execute([$currentWorkspaceId]);
$campaigns = $cListStmt->fetchAll(PDO::FETCH_ASSOC);

$lListStmt = $pdo->prepare("SELECT * FROM leads WHERE workspace_id = ? ORDER BY created_at DESC LIMIT 5");
$lListStmt->execute([$currentWorkspaceId]);
$recentLeads = $lListStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Primovative Co. modern business intelligence dashboard">
  <title>Primovative Co. — Dashboard</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <div class="main-area">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <main id="main-content" class="content">
      <section class="welcome-card" aria-labelledby="welcome-title">
        <div class="welcome-content">
          <span class="welcome-kicker">Overview</span>
          <h2 id="welcome-title">Welcome back, <?= htmlspecialchars($userName) ?> <span aria-hidden="true">👋</span></h2>
          <p>Here's what's happening across your campaigns and sales pipeline.</p>
        </div>
        <div class="d-flex gap-2">
          <a href="lead-discovery/index.php" class="btn btn-light btn-sm"><i class="bi bi-search me-1"></i> Discover Leads</a>
          <a href="campaigns/index.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> New Campaign</a>
        </div>
      </section>

      <!-- KPI METRICS -->
      <section class="row g-3 kpi-grid mb-4" aria-label="Key performance indicators">
        <div class="col-12 col-sm-6 col-xl-3">
          <article class="stat-card">
            <div class="stat-icon icon-violet"><i class="bi bi-people"></i></div>
            <div class="stat-content">
              <span class="stat-label">Total Leads</span>
              <div class="stat-value-row">
                <strong><?= number_format($totalLeads) ?></strong>
                <span class="trend positive"><i class="bi bi-arrow-up"></i> 18.5%</span>
              </div>
              <small>vs last 30 days</small>
            </div>
          </article>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <article class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-content">
              <span class="stat-label">Qualified Leads</span>
              <div class="stat-value-row">
                <strong><?= number_format($qualifiedLeads) ?></strong>
                <span class="trend positive"><i class="bi bi-arrow-up"></i> 22.4%</span>
              </div>
              <small>high purchase intent</small>
            </div>
          </article>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <article class="stat-card">
            <div class="stat-icon icon-amber"><i class="bi bi-envelope"></i></div>
            <div class="stat-content">
              <span class="stat-label">Outreach Sent</span>
              <div class="stat-value-row">
                <strong><?= number_format($contactedLeads) ?></strong>
                <span class="trend positive"><i class="bi bi-arrow-up"></i> 16.2%</span>
              </div>
              <small>emails &amp; messages</small>
            </div>
          </article>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <article class="stat-card">
            <div class="stat-icon icon-blue"><i class="bi bi-reply"></i></div>
            <div class="stat-content">
              <span class="stat-label">Replies Received</span>
              <div class="stat-value-row">
                <strong><?= number_format($repliedLeads) ?></strong>
                <span class="trend positive"><i class="bi bi-arrow-up"></i> 20.1%</span>
              </div>
              <small>active discussions</small>
            </div>
          </article>
        </div>
      </section>

      <!-- CHARTS & CAMPAIGNS -->
      <section class="row g-3 dashboard-section mb-4">
        <!-- PERFORMANCE PREVIEW -->
        <div class="col-12 col-xl-7">
          <article class="dashboard-card chart-card">
            <div class="card-heading">
              <div>
                <span class="section-kicker">Performance</span>
                <h2>Leads Over Time</h2>
              </div>
              <a href="analytics/index.php" class="btn btn-outline-secondary btn-sm">Full Analytics</a>
            </div>

            <div class="chart-wrap" style="padding: 20px 0;">
              <svg viewBox="0 0 900 280" class="chart-svg" preserveAspectRatio="none" style="width:100%;height:220px;">
                <defs>
                  <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#2563eb" stop-opacity="0.25"/>
                    <stop offset="100%" stop-color="#2563eb" stop-opacity="0.0"/>
                  </linearGradient>
                </defs>
                <path fill="url(#chartGradient)" d="M52,247 L80,241 L136,229 L192,220 L248,211 L304,198 L360,199 L416,195 L472,164 L528,126 L584,121 L640,105 L696,108 L752,92 L808,94 L864,76 L875,70 L875,258 L52,258 Z"/>
                <path fill="none" stroke="#2563eb" stroke-width="3" d="M52,247 L80,241 L136,229 L192,220 L248,211 L304,198 L360,199 L416,195 L472,164 L528,126 L584,121 L640,105 L696,108 L752,92 L808,94 L864,76 L875,70"/>
              </svg>
            </div>

            <div class="source-grid">
              <div class="source-chip">
                <span class="source-logo google"><i class="bi bi-google"></i></span>
                <div><span>Google</span><strong>52%</strong></div>
              </div>
              <div class="source-chip">
                <span class="source-logo meta"><i class="bi bi-meta"></i></span>
                <div><span>Meta</span><strong>38%</strong></div>
              </div>
              <div class="source-chip">
                <span class="source-logo other"><i class="bi bi-globe2"></i></span>
                <div><span>Other</span><strong>10%</strong></div>
              </div>
            </div>
          </article>
        </div>

        <!-- ACTIVE CAMPAIGNS -->
        <div class="col-12 col-xl-5">
          <article class="dashboard-card campaigns-card">
            <div class="card-heading">
              <div>
                <span class="section-kicker">Pipeline</span>
                <h2>Active Campaigns</h2>
              </div>
              <a href="campaigns/index.php" class="card-link">View all</a>
            </div>

            <div class="campaign-list">
              <?php foreach ($campaigns as $c): ?>
                <div class="campaign-row">
                  <span class="campaign-avatar campaign-house"><i class="bi bi-megaphone"></i></span>
                  <div class="campaign-info">
                    <strong><a href="campaigns/view.php?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></strong>
                    <small><?= htmlspecialchars($c['industry'] ?: 'General') ?> &bull; <?= htmlspecialchars($c['location'] ?: 'National') ?></small>
                  </div>
                  <span class="status-pill <?= $c['status'] === 'active' ? 'status-active' : 'status-paused' ?>">
                    <?= ucfirst($c['status']) ?>
                  </span>
                  <div class="campaign-count">
                    <strong><?= $c['total_leads'] ?></strong>
                    <small>Leads</small>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </article>
        </div>
      </section>
      <!-- RECENT LEADS -->
      <section class="dashboard-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <span class="section-kicker">Prospects</span>
            <h2 class="h5 fw-bold mb-0">Recent Leads</h2>
          </div>
          <a href="leads/index.php" class="btn btn-sm btn-outline-primary">View All Leads</a>
        </div>

        <div class="table-responsive">
          <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Company</th>
                <th>Source</th>
                <th>Contact Email</th>
                <th>Location</th>
                <th>Score</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentLeads as $l): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($l['company'] ?: $l['name']) ?></strong>
                    <?php if (!empty($l['company']) && !empty($l['name'])): ?>
                      <div class="text-muted small"><?= htmlspecialchars($l['name']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border">
                      <i class="bi bi-<?= $l['source'] === 'google' ? 'google' : ($l['source'] === 'meta' ? 'meta' : 'globe') ?> me-1"></i>
                      <?= ucfirst($l['source']) ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($l['email'] ?? '') ?></td>
                  <td><?= htmlspecialchars($l['location'] ?: 'USA') ?></td>
                  <td><span class="badge bg-light text-dark border"><?= $l['score'] ?>/100</span></td>
                  <td>
                    <span class="badge <?= $l['status'] === 'replied' ? 'bg-primary' : ($l['status'] === 'qualified' ? 'bg-success' : ($l['status'] === 'contacted' ? 'bg-info text-dark' : 'bg-secondary')) ?>">
                      <?= ucfirst($l['status']) ?>
                    </span>
                  </td>
                  <td>
                    <a href="leads/view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

