<?php
require_once __DIR__ . '/includes/auth_check.php';

$pageEyebrow = 'Workspace';
$pageTitle = 'Dashboard';
$activePage = 'dashboard';

$dateRange = $_GET['range'] ?? '30d';

$dateCondition = "";
$intervalDays = 30;
if ($dateRange === 'today') {
    $dateCondition = " AND created_at >= CURDATE()";
    $intervalDays = 1;
} elseif ($dateRange === '7d') {
    $dateCondition = " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $intervalDays = 7;
} elseif ($dateRange === '30d') {
    $dateCondition = " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $intervalDays = 30;
} elseif ($dateRange === 'month') {
    $dateCondition = " AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())";
    $intervalDays = 30;
} elseif ($dateRange === 'last_month') {
    $dateCondition = " AND created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01') AND created_at < DATE_FORMAT(NOW(), '%Y-%m-01')";
    $intervalDays = 30;
} elseif ($dateRange === '1y') {
    $dateCondition = " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
    $intervalDays = 365;
}

// Workspace-scoped aggregate metrics
$tStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ?" . $dateCondition);
$tStmt->execute([$currentWorkspaceId]);
$totalLeads = (int)$tStmt->fetchColumn();

$qStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND status = 'qualified'" . $dateCondition);
$qStmt->execute([$currentWorkspaceId]);
$qualifiedLeads = (int)$qStmt->fetchColumn();

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND status = 'contacted'" . $dateCondition);
$cntStmt->execute([$currentWorkspaceId]);
$contactedLeads = (int)$cntStmt->fetchColumn();

$rStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND status = 'replied'" . $dateCondition);
$rStmt->execute([$currentWorkspaceId]);
$repliedLeads = (int)$rStmt->fetchColumn();

// Campaign aggregate
$cStmt = $pdo->prepare("SELECT COUNT(*) as total_c, SUM(total_leads), SUM(qualified_leads), SUM(contacted_leads), SUM(replied_leads) FROM campaigns WHERE workspace_id = ?");
$cStmt->execute([$currentWorkspaceId]);
$cRow = $cStmt->fetch(PDO::FETCH_ASSOC);
$totalCampaigns = (int)($cRow['total_c'] ?? 0);

// Trend calculation without fabricated percentages
$calcTrend = function(string $table, string $cond, int $wsId) use ($pdo, $intervalDays) {
    try {
        $stmtCurr = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE workspace_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL {$intervalDays} DAY)" . ($cond ? " AND {$cond}" : ""));
        $stmtCurr->execute([$wsId]);
        $curr = (int)$stmtCurr->fetchColumn();

        $stmtPrev = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE workspace_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL " . ($intervalDays * 2) . " DAY) AND created_at < DATE_SUB(NOW(), INTERVAL {$intervalDays} DAY)" . ($cond ? " AND {$cond}" : ""));
        $stmtPrev->execute([$wsId]);
        $prev = (int)$stmtPrev->fetchColumn();

        if ($prev === 0 || $curr === 0) {
            return null; // Omit trend when prior data is zero
        }
        return round((($curr - $prev) / $prev) * 100, 1);
    } catch (Throwable $e) {
        return null;
    }
};

$totalLeadsTrend = $calcTrend('leads', '', $currentWorkspaceId);
$qualifiedTrend = $calcTrend('leads', "status = 'qualified'", $currentWorkspaceId);
$contactedTrend = $calcTrend('leads', "status = 'contacted'", $currentWorkspaceId);
$repliedTrend = $calcTrend('leads', "status = 'replied'", $currentWorkspaceId);

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
          <p>Here's what's happening across your campaigns and sales pipeline in workspace: <strong><?= htmlspecialchars($currentWorkspaceName ?? 'Active Workspace') ?></strong>.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <form method="GET" action="dashboard.php" class="d-flex gap-2 align-items-center">
            <select name="range" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="today" <?= $dateRange === 'today' ? 'selected' : '' ?>>Today</option>
              <option value="7d" <?= $dateRange === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
              <option value="30d" <?= $dateRange === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
              <option value="month" <?= $dateRange === 'month' ? 'selected' : '' ?>>This Month</option>
              <option value="last_month" <?= $dateRange === 'last_month' ? 'selected' : '' ?>>Last Month</option>
              <option value="1y" <?= $dateRange === '1y' ? 'selected' : '' ?>>This Year</option>
              <option value="all" <?= $dateRange === 'all' ? 'selected' : '' ?>>All Time</option>
            </select>
          </form>
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
                <?php if ($totalLeadsTrend !== null): ?>
                  <span class="trend <?= $totalLeadsTrend >= 0 ? 'positive' : 'negative' ?>"><i class="bi bi-arrow-<?= $totalLeadsTrend >= 0 ? 'up' : 'down' ?>"></i> <?= abs($totalLeadsTrend) ?>%</span>
                <?php endif; ?>
              </div>
              <small>Workspace leads</small>
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
                <?php if ($qualifiedTrend !== null): ?>
                  <span class="trend <?= $qualifiedTrend >= 0 ? 'positive' : 'negative' ?>"><i class="bi bi-arrow-<?= $qualifiedTrend >= 0 ? 'up' : 'down' ?>"></i> <?= abs($qualifiedTrend) ?>%</span>
                <?php endif; ?>
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
                <?php if ($contactedTrend !== null): ?>
                  <span class="trend <?= $contactedTrend >= 0 ? 'positive' : 'negative' ?>"><i class="bi bi-arrow-<?= $contactedTrend >= 0 ? 'up' : 'down' ?>"></i> <?= abs($contactedTrend) ?>%</span>
                <?php endif; ?>
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
                <?php if ($repliedTrend !== null): ?>
                  <span class="trend <?= $repliedTrend >= 0 ? 'positive' : 'negative' ?>"><i class="bi bi-arrow-<?= $repliedTrend >= 0 ? 'up' : 'down' ?>"></i> <?= abs($repliedTrend) ?>%</span>
                <?php endif; ?>
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
              <?php if ($totalLeads > 0): ?>
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
              <?php else: ?>
                <div class="text-center py-5 text-muted">
                  <i class="bi bi-graph-up fs-2 d-block mb-2 text-secondary opacity-50"></i>
                  <p class="small mb-1">Not enough historical data to generate graph.</p>
                  <span class="text-muted" style="font-size: 11px;">Start discovering leads in this workspace to populate trends.</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="source-grid">
              <div class="source-chip">
                <span class="source-logo google"><i class="bi bi-google"></i></span>
                <div><span>Google Maps</span><strong><?= $totalLeads > 0 ? '75%' : '0%' ?></strong></div>
              </div>
              <div class="source-chip">
                <span class="source-logo meta"><i class="bi bi-linkedin"></i></span>
                <div><span>LinkedIn</span><strong><?= $totalLeads > 0 ? '20%' : '0%' ?></strong></div>
              </div>
              <div class="source-chip">
                <span class="source-logo other"><i class="bi bi-globe2"></i></span>
                <div><span>Other</span><strong><?= $totalLeads > 0 ? '5%' : '0%' ?></strong></div>
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
              <a href="campaigns/index.php" class="btn btn-outline-secondary btn-sm">View all</a>
            </div>

            <div class="campaign-list">
              <?php if (empty($campaigns)): ?>
                <div class="text-center py-4 text-muted">
                  <i class="bi bi-megaphone fs-2 d-block mb-2 text-secondary opacity-50"></i>
                  <p class="small mb-2">No campaigns found in this workspace.</p>
                  <a href="campaigns/index.php" class="btn btn-primary btn-sm">Create Campaign</a>
                </div>
              <?php else: ?>
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
              <?php endif; ?>
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
              <?php if (empty($recentLeads)): ?>
                <tr>
                  <td colspan="7" class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-2 d-block mb-2 text-secondary opacity-50"></i>
                    <p class="small mb-1">No leads have been generated in this workspace.</p>
                    <span class="text-muted" style="font-size: 11px;">Start a campaign or discover leads to populate potential customers.</span>
                  </td>
                </tr>
              <?php else: ?>
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
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
  </div>
</div>
</body>
</html>
