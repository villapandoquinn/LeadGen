<?php
/**
 * LinkedIn Lead Sync Dashboard
 * linkedin/index.php
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../services/LinkedInLeadService.php';

$pageEyebrow = 'Lead Generation';
$pageTitle   = 'LinkedIn Lead Sync';
$activePage  = 'linkedin-sync';

$service  = new LinkedInLeadService();
$connSts  = $service->getConnectionStatus();
$settings = file_exists(__DIR__ . '/../config/settings.json')
    ? (json_decode(file_get_contents(__DIR__ . '/../config/settings.json'), true) ?? [])
    : [];

$isConfigured = !empty($connSts['is_configured']);
$isConnected  = !empty($connSts['is_connected']);
$isExpired    = !empty($connSts['is_expired']);
$connectedAt  = $connSts['connected_at'] ?? null;

$oauthUrl = '';
if ($isConfigured && !$isConnected) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['linkedin_oauth_state'] = $state;
    $oauthUrl = $service->getAuthorizationUrl($state);
}

// Fetch user profile info if connected
$connectedUserName = null;
if ($isConnected) {
    try {
        $me = $service->testKey();
        if (!empty($me['success'])) {
            $connectedUserName = str_replace('LinkedIn connected! Account: ', '', $me['message']);
        }
    } catch (Throwable $e) {}
}

// Campaigns for assignment
$campStmt = $pdo->prepare("SELECT id, name FROM campaigns WHERE workspace_id=? ORDER BY name ASC");
$campStmt->execute([$currentWorkspaceId]);
$campaigns = $campStmt->fetchAll();

// Last sync entry
$lastSync = null;
try {
    $ls = $pdo->prepare("SELECT * FROM linkedin_sync_logs WHERE workspace_id=? AND status='completed' ORDER BY created_at DESC LIMIT 1");
    $ls->execute([$currentWorkspaceId]);
    $lastSync = $ls->fetch();
} catch (Throwable $e) {}

// Overall sync totals
$syncTotals = ['retrieved' => 0, 'imported' => 0, 'skipped' => 0, 'failed' => 0];
try {
    $totStmt = $pdo->prepare("SELECT COALESCE(SUM(leads_retrieved),0) tr, COALESCE(SUM(leads_imported),0) ti, COALESCE(SUM(leads_skipped),0) ts, COALESCE(SUM(leads_failed),0) tf FROM linkedin_sync_logs WHERE workspace_id=?");
    $totStmt->execute([$currentWorkspaceId]);
    $totRow = $totStmt->fetch();
    if ($totRow) {
        $syncTotals['retrieved'] = (int)$totRow['tr'];
        $syncTotals['imported']  = (int)$totRow['ti'];
        $syncTotals['skipped']   = (int)$totRow['ts'];
        $syncTotals['failed']    = (int)$totRow['tf'];
    }
} catch (Throwable $e) {}

$alertSuccess = !empty($_GET['success']) ? htmlspecialchars($_GET['success']) : null;
$alertError   = !empty($_GET['error'])   ? htmlspecialchars($_GET['error'])   : null;
$csrfToken    = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LinkedIn Lead Sync — Primovative Co.</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    /* Scoped fix for layout and card height */
    .linkedin-sync-page .dashboard-card {
      height: auto !important;
      min-height: unset !important;
      display: flex;
      flex-direction: column;
      margin-bottom: 0;
    }
    .linkedin-sync-page .stat-card {
      height: auto !important;
      min-height: 96px !important;
    }
    .stat-kpi-num {
      font-size: 1.65rem;
      font-weight: 700;
      line-height: 1.2;
    }
    .li-brand-bg {
      background: #0077B5;
    }
    .li-brand-color {
      color: #0077B5;
    }
    .endpoint-badge {
      font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size: 0.8rem;
    }
  </style>
</head>
<body class="linkedin-sync-page">
<div class="app-shell">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-area">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <main class="content">

      <?php if ($alertSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 py-2 small shadow-sm">
          <i class="bi bi-check-circle me-1"></i> <?= $alertSuccess ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      <?php if ($alertError): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 py-2 small shadow-sm">
          <i class="bi bi-exclamation-triangle me-1"></i> <?= $alertError ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- TOP CONNECTION STATUS CARD -->
      <div class="dashboard-card p-4 mb-4 shadow-sm">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;" class="li-brand-bg text-white shadow-sm">
              <i class="bi bi-linkedin fs-4"></i>
            </div>
            <div>
              <div class="d-flex align-items-center gap-2">
                <h2 class="h5 fw-bold mb-0">LinkedIn Lead Sync</h2>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle endpoint-badge">RestLi 2.0 &bull; v202601</span>
              </div>
              <p class="text-muted small mb-0">Direct 3-Legged OAuth integration with LinkedIn Campaign Manager &amp; Lead Gen Forms</p>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if (!$isConfigured): ?>
              <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-3 py-2">
                <i class="bi bi-exclamation-triangle me-1"></i> Not Configured
              </span>
              <a href="../settings/index.php#linkedin-api" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-gear me-1"></i> Configure in Settings
              </a>
            <?php elseif ($isConnected): ?>
              <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                <i class="bi bi-check-circle-fill me-1"></i> Connected
                <?php if ($connectedUserName): ?> (<?= htmlspecialchars($connectedUserName) ?>)<?php endif; ?>
              </span>
              <a href="<?= htmlspecialchars($service->getAuthorizationUrl(bin2hex(random_bytes(16)))) ?>" class="btn btn-sm btn-outline-secondary" title="Reauthorize or switch LinkedIn account">
                <i class="bi bi-arrow-repeat me-1"></i> Re-connect
              </a>
              <form method="POST" action="disconnect.php" class="m-0 d-inline" onsubmit="return confirm('Disconnect LinkedIn account? Lead sync will pause until reconnected.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger">
                  <i class="bi bi-plug me-1"></i> Disconnect
                </button>
              </form>
            <?php elseif (!empty($oauthUrl)): ?>
              <span class="badge <?= $isExpired ? 'bg-warning-subtle text-dark border-warning-subtle' : 'bg-secondary-subtle text-secondary border-secondary-subtle' ?> border px-3 py-2">
                <i class="bi bi-<?= $isExpired ? 'clock' : 'plug' ?> me-1"></i> <?= $isExpired ? 'Token Expired' : 'Not Connected' ?>
              </span>
              <a href="<?= htmlspecialchars($oauthUrl) ?>" class="btn btn-sm btn-primary li-brand-bg border-0">
                <i class="bi bi-linkedin me-1"></i> <?= $isExpired ? 'Re-authorize LinkedIn' : 'Connect LinkedIn Account' ?>
              </a>
            <?php else: ?>
              <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-3 py-2">
                <i class="bi bi-exclamation-triangle me-1"></i> Credentials required
              </span>
              <a href="../settings/index.php#linkedin-api" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-gear me-1"></i> Go to Settings
              </a>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!$isConfigured): ?>
          <div class="alert alert-info mt-3 mb-0 small">
            <i class="bi bi-info-circle me-1"></i>
            Configure your <strong>LinkedIn Client ID</strong>, <strong>Client Secret</strong>, and <strong>Redirect URI</strong> in
            <a href="../settings/index.php#linkedin-api" class="alert-link">Settings &rarr; LinkedIn API Integration</a>, then return here to connect your account.
          </div>
        <?php endif; ?>
      </div>

      <?php if ($isConnected): ?>
        <!-- KPI SUMMARY TILES -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="dashboard-card stat-card p-3 border-start border-4 border-primary shadow-sm">
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-semibold">Leads Retrieved</span>
                <i class="bi bi-cloud-arrow-down text-primary fs-5"></i>
              </div>
              <div class="stat-kpi-num text-dark mt-1" id="kpiRetrieved"><?= number_format($syncTotals['retrieved']) ?></div>
              <span class="text-muted small">From Lead Gen Forms</span>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="dashboard-card stat-card p-3 border-start border-4 border-success shadow-sm">
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-semibold">Leads Imported</span>
                <i class="bi bi-person-check-fill text-success fs-5"></i>
              </div>
              <div class="stat-kpi-num text-success mt-1" id="kpiImported"><?= number_format($syncTotals['imported']) ?></div>
              <span class="text-muted small">Saved to workspace</span>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="dashboard-card stat-card p-3 border-start border-4 border-warning shadow-sm">
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-semibold">Duplicates Skipped</span>
                <i class="bi bi-shield-check text-warning fs-5"></i>
              </div>
              <div class="stat-kpi-num text-warning mt-1" id="kpiSkipped"><?= number_format($syncTotals['skipped']) ?></div>
              <span class="text-muted small">Prevented re-imports</span>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="dashboard-card stat-card p-3 border-start border-4 border-danger shadow-sm">
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-semibold">Sync Errors</span>
                <i class="bi bi-exclamation-octagon text-danger fs-5"></i>
              </div>
              <div class="stat-kpi-num text-danger mt-1" id="kpiFailed"><?= number_format($syncTotals['failed']) ?></div>
              <span class="text-muted small">Failed submissions</span>
            </div>
          </div>
        </div>

        <!-- MAIN WORKSPACE ROW -->
        <div class="row g-4 mb-4">
          <!-- LEFT COLUMN: SYNC CONTROL CARD -->
          <div class="col-12 col-lg-7">
            <div class="dashboard-card p-4 shadow-sm h-100">
              <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h3 class="h6 fw-bold mb-0">
                  <i class="bi bi-arrow-repeat text-primary me-1"></i> Synchronize Leads
                </h3>
                <span class="badge bg-light text-muted border small">Incremental &amp; Full Sync</span>
              </div>

              <div class="row g-3">
                <!-- LinkedIn Ad Account -->
                <div class="col-12">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small fw-semibold mb-0">
                      LinkedIn Ad Account <span class="text-danger">*</span>
                    </label>
                    <button type="button" id="toggleManualAccountBtn" class="btn btn-link btn-sm p-0 text-decoration-none small">
                      <i class="bi bi-pencil-square me-1"></i><span id="toggleAccountLabel">Enter ID manually</span>
                    </button>
                  </div>

                  <!-- Dropdown Mode -->
                  <div id="accountSelectGroup" class="input-group">
                    <select id="accountSelect" class="form-select">
                      <option value="">Loading accounts...</option>
                    </select>
                    <button class="btn btn-outline-secondary" type="button" id="refreshAccountsBtn" title="Refresh accounts from LinkedIn">
                      <i class="bi bi-arrow-clockwise"></i>
                    </button>
                  </div>

                  <!-- Manual Input Mode -->
                  <div id="accountManualGroup" class="input-group" style="display:none;">
                    <span class="input-group-text small font-monospace"><i class="bi bi-hash"></i></span>
                    <input type="text" id="manualAccountId" class="form-control font-monospace" placeholder="e.g. 500123456 or urn:li:sponsoredAccount:500123456">
                    <button class="btn btn-outline-primary" type="button" id="loadFormsForManualBtn">
                      <i class="bi bi-arrow-right-short"></i> Load Forms
                    </button>
                  </div>
                  <div id="accountFeedback" class="small mt-1 text-muted"></div>
                </div>

                <!-- Lead Form Filter -->
                <div class="col-12">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small fw-semibold mb-0">
                      Lead Form <span class="text-muted">(leave blank for all forms)</span>
                    </label>
                    <button type="button" id="toggleManualFormBtn" class="btn btn-link btn-sm p-0 text-decoration-none small text-muted">
                      <i class="bi bi-pencil me-1"></i><span id="toggleFormLabel">Custom Form ID</span>
                    </button>
                  </div>
                  <select id="formSelect" class="form-select" disabled>
                    <option value="">— All Forms (Retrieve All Leads) —</option>
                  </select>
                  <div id="formManualGroup" class="input-group mt-2" style="display:none;">
                    <span class="input-group-text small font-monospace"><i class="bi bi-card-checklist"></i></span>
                    <input type="text" id="manualFormId" class="form-control font-monospace" placeholder="Lead Form ID (e.g. 12345678)">
                  </div>
                </div>

                <!-- Campaign Assignment -->
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Assign to Campaign</label>
                  <select id="campaignSelect" class="form-select">
                    <option value="">— No Campaign (General) —</option>
                    <?php foreach ($campaigns as $c): ?>
                      <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Sync Mode -->
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Sync Mode</label>
                  <select id="syncModeSelect" class="form-select">
                    <option value="incremental">Incremental (Only new leads since last sync)</option>
                    <option value="full">Full Sync (All available form responses)</option>
                  </select>
                </div>

                <!-- Sync Action Button -->
                <div class="col-12 mt-2">
                  <button id="syncBtn" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm" disabled>
                    <i class="bi bi-cloud-arrow-down me-1"></i> Synchronize Leads from LinkedIn
                  </button>
                </div>
              </div>

              <!-- Real-time Sync Execution Results Alert -->
              <div id="syncResults" class="mt-3" style="display:none;">
                <div class="alert mb-3" id="syncAlert"></div>
                <div class="row g-2">
                  <div class="col-6 col-md-3">
                    <div class="p-2 border rounded text-center bg-light">
                      <div class="fw-bold fs-5 text-dark" id="statRetrieved">0</div>
                      <div class="small text-muted">Retrieved</div>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="p-2 border rounded text-center bg-light">
                      <div class="fw-bold fs-5 text-success" id="statImported">0</div>
                      <div class="small text-muted">Imported</div>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="p-2 border rounded text-center bg-light">
                      <div class="fw-bold fs-5 text-warning" id="statSkipped">0</div>
                      <div class="small text-muted">Duplicates</div>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="p-2 border rounded text-center bg-light">
                      <div class="fw-bold fs-5 text-danger" id="statFailed">0</div>
                      <div class="small text-muted">Failed</div>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- RIGHT COLUMN: STATUS, LAST SYNC & QUICK ACTIONS -->
          <div class="col-12 col-lg-5">
            <div class="d-flex flex-column gap-3">

              <!-- ACCOUNT CONNECTION DETAILS -->
              <div class="dashboard-card p-4 shadow-sm">
                <h3 class="h6 fw-bold mb-3 d-flex align-items-center">
                  <i class="bi bi-person-badge text-primary me-2"></i> Account Connection
                </h3>
                <dl class="row mb-0 small">
                  <dt class="col-5 text-muted">Authorized User</dt>
                  <dd class="col-7 fw-semibold text-dark mb-2">
                    <?= htmlspecialchars($connectedUserName ?: 'Verified Member') ?>
                  </dd>
                  <dt class="col-5 text-muted">Client ID</dt>
                  <dd class="col-7 font-monospace text-muted mb-2">
                    <?= htmlspecialchars(substr($settings['linkedin_client_id'] ?? '', 0, 8) . '...') ?>
                  </dd>
                  <dt class="col-5 text-muted">Lead Sync API</dt>
                  <dd class="col-7 mb-2">
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                      <i class="bi bi-check2 me-1"></i> Active (v202601)
                    </span>
                  </dd>
                  <dt class="col-5 text-muted">OAuth Scope</dt>
                  <dd class="col-7 mb-0">
                    <span class="badge bg-light text-dark border">r_ads_leadgen_automation</span>
                  </dd>
                </dl>
              </div>

              <!-- LAST SYNCHRONIZATION SUMMARY -->
              <div class="dashboard-card p-4 shadow-sm">
                <h3 class="h6 fw-bold mb-3 d-flex align-items-center">
                  <i class="bi bi-clock-history text-primary me-2"></i> Last Synchronization
                </h3>
                <?php if ($lastSync): ?>
                  <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Completed</dt>
                    <dd class="col-7 mb-2"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($lastSync['created_at']))) ?></dd>
                    <dt class="col-5 text-muted">Retrieved</dt>
                    <dd class="col-7 mb-2 fw-semibold text-dark"><?= (int)$lastSync['leads_retrieved'] ?> lead(s)</dd>
                    <dt class="col-5 text-muted">Imported</dt>
                    <dd class="col-7 mb-2 text-success fw-bold"><?= (int)$lastSync['leads_imported'] ?> new lead(s)</dd>
                    <dt class="col-5 text-muted">Duplicates</dt>
                    <dd class="col-7 mb-2 text-warning"><?= (int)$lastSync['leads_skipped'] ?> skipped</dd>
                    <dt class="col-5 text-muted">Account ID</dt>
                    <dd class="col-7 mb-0 font-monospace"><code><?= htmlspecialchars($lastSync['account_id']) ?></code></dd>
                  </dl>
                <?php else: ?>
                  <div class="p-3 bg-light rounded text-center small text-muted">
                    <i class="bi bi-info-circle me-1"></i> No leads synchronized yet. Select an Ad Account on the left to start.
                  </div>
                <?php endif; ?>
              </div>

              <!-- QUICK LINKS -->
              <div class="dashboard-card p-4 shadow-sm">
                <h3 class="h6 fw-bold mb-3 d-flex align-items-center">
                  <i class="bi bi-link-45deg text-primary me-2"></i> Quick Actions
                </h3>
                <div class="d-grid gap-2">
                  <a href="../leads/index.php?source=linkedin" class="btn btn-sm btn-outline-primary text-start">
                    <i class="bi bi-people me-2"></i> View Synchronized LinkedIn Leads
                  </a>
                  <a href="../lead-discovery/index.php" class="btn btn-sm btn-outline-secondary text-start">
                    <i class="bi bi-compass me-2"></i> Google Maps Lead Discovery
                  </a>
                  <a href="../settings/index.php#linkedin-api" class="btn btn-sm btn-outline-secondary text-start">
                    <i class="bi bi-gear me-2"></i> LinkedIn API &amp; OAuth Settings
                  </a>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- FULL-WIDTH SYNC ACTIVITY LOG CARD -->
        <div class="dashboard-card p-4 mb-4 shadow-sm">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
              <h3 class="h6 fw-bold mb-0"><i class="bi bi-list-ul text-primary me-2"></i> Synchronization Activity Log</h3>
              <p class="text-muted small mb-0">Audit history of manual and scheduled lead retrieval events</p>
            </div>
            <button id="refreshLogsBtn" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-arrow-clockwise me-1"></i> Refresh Logs
            </button>
          </div>
          <div id="logsContainer">
            <p class="text-muted small">Loading sync activity...</p>
          </div>
        </div>
      <?php endif; ?>

      <!-- COLLAPSIBLE API REFERENCE CARD -->
      <div class="dashboard-card p-4 shadow-sm">
        <div class="d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#apiRefCollapse" aria-expanded="false">
          <h3 class="h6 fw-bold mb-0">
            <i class="bi bi-info-circle text-primary me-2"></i> LinkedIn Lead Sync API Reference &amp; Protocol
          </h3>
          <i class="bi bi-chevron-down text-muted"></i>
        </div>
        <div class="collapse show mt-3" id="apiRefCollapse">
          <div class="row g-3 small">
            <div class="col-md-6">
              <table class="table table-sm table-borderless mb-0">
                <tr><td class="text-muted fw-semibold" style="width:140px;">Protocol</td><td>RestLi 2.0</td></tr>
                <tr><td class="text-muted fw-semibold">Active Version</td><td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">202601</span> (auto-fallback supported)</td></tr>
                <tr><td class="text-muted fw-semibold">Auth Type</td><td>OAuth 2.0 Member (3-legged)</td></tr>
                <tr><td class="text-muted fw-semibold">Scopes</td><td><code>r_ads</code> <code>r_ads_leadgen_automation</code> <code>r_basicprofile</code></td></tr>
              </table>
            </div>
            <div class="col-md-6">
              <table class="table table-sm table-borderless mb-0">
                <tr><td class="text-muted fw-semibold" style="width:140px;">Lead Sync Endpoint</td><td><code>GET /rest/leadFormResponses</code></td></tr>
                <tr><td class="text-muted fw-semibold">Lead Forms Endpoint</td><td><code>GET /rest/leadForms</code></td></tr>
                <tr><td class="text-muted fw-semibold">Ad Accounts</td><td><code>GET /rest/adAccounts?q=search</code></td></tr>
                <tr><td class="text-muted fw-semibold">Version Header</td><td><code>LinkedIn-Version: 202601</code></td></tr>
              </table>
            </div>
          </div>
        </div>
      </div>

    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>

<script>
const CSRF = '<?= $csrfToken ?>';

function apiPost(action, extra = {}) {
  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  fd.append('action', action);
  for (const [k, v] of Object.entries(extra)) {
    fd.append(k, v);
  }
  return fetch('api.php', { method: 'POST', body: fd }).then(r => r.json());
}

let isManualAccountMode = false;
let isManualFormMode = false;

function getSelectedAccountId() {
  if (isManualAccountMode) {
    return document.getElementById('manualAccountId')?.value.trim() || '';
  }
  return document.getElementById('accountSelect')?.value || '';
}

function getSelectedFormId() {
  if (isManualFormMode) {
    return document.getElementById('manualFormId')?.value.trim() || '';
  }
  return document.getElementById('formSelect')?.value || '';
}

function checkReady() {
  const aid = getSelectedAccountId();
  const btn = document.getElementById('syncBtn');
  if (btn) btn.disabled = !aid;
}

// Toggle manual account ID input
const toggleAccountBtn = document.getElementById('toggleManualAccountBtn');
const toggleAccountLabel = document.getElementById('toggleAccountLabel');
const accountSelectGroup = document.getElementById('accountSelectGroup');
const accountManualGroup = document.getElementById('accountManualGroup');

toggleAccountBtn?.addEventListener('click', () => {
  isManualAccountMode = !isManualAccountMode;
  if (isManualAccountMode) {
    accountSelectGroup.style.display = 'none';
    accountManualGroup.style.display = 'flex';
    toggleAccountLabel.textContent = 'Choose from list';
  } else {
    accountSelectGroup.style.display = 'flex';
    accountManualGroup.style.display = 'none';
    toggleAccountLabel.textContent = 'Enter ID manually';
  }
  checkReady();
});

// Toggle manual form ID input
const toggleFormBtn = document.getElementById('toggleManualFormBtn');
const toggleFormLabel = document.getElementById('toggleFormLabel');
const formSelect = document.getElementById('formSelect');
const formManualGroup = document.getElementById('formManualGroup');

toggleFormBtn?.addEventListener('click', () => {
  isManualFormMode = !isManualFormMode;
  if (isManualFormMode) {
    formSelect.style.display = 'none';
    formManualGroup.style.display = 'flex';
    toggleFormLabel.textContent = 'Choose from list';
  } else {
    formSelect.style.display = 'block';
    formManualGroup.style.display = 'none';
    toggleFormLabel.textContent = 'Custom Form ID';
  }
});

document.getElementById('manualAccountId')?.addEventListener('input', checkReady);

async function loadAccounts() {
  const sel = document.getElementById('accountSelect');
  const fb = document.getElementById('accountFeedback');
  if (!sel) return;

  sel.innerHTML = '<option value="">Loading ad accounts...</option>';
  if (fb) fb.innerHTML = '';

  try {
    const r = await apiPost('get_accounts');
    sel.innerHTML = '';

    if (r.success && r.accounts && r.accounts.length > 0) {
      sel.add(new Option('— Select Ad Account —', ''));
      r.accounts.forEach(a => {
        sel.add(new Option((a.name || 'Account') + ' (' + a.id + ')', a.id));
      });
      if (fb) fb.innerHTML = '<span class="text-success"><i class="bi bi-check2 me-1"></i> ' + r.accounts.length + ' ad account(s) loaded.</span>';
    } else {
      sel.add(new Option('— No Ad Accounts Found —', ''));
      if (fb) {
        fb.innerHTML = '<span class="text-muted"><i class="bi bi-info-circle me-1"></i> No ad accounts automatically listed for this LinkedIn profile. <a href="javascript:void(0)" id="switchToManualLink" class="text-primary fw-semibold">Enter your Sponsored Account ID manually</a> from LinkedIn Campaign Manager.</span>';
        document.getElementById('switchToManualLink')?.addEventListener('click', () => {
          toggleAccountBtn?.click();
        });
      }
    }
  } catch (e) {
    sel.innerHTML = '<option value="">Unable to connect to LinkedIn API</option>';
    if (fb) fb.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i> API connection error. Click refresh or check settings.</span>';
  }
  checkReady();
}

async function loadForms(aid) {
  const sel = document.getElementById('formSelect');
  if (!sel) return;

  sel.disabled = true;
  sel.innerHTML = '<option value="">Loading lead forms...</option>';

  if (!aid) {
    sel.innerHTML = '<option value="">— All Forms (Retrieve All Leads) —</option>';
    return;
  }

  try {
    const r = await apiPost('get_forms', { account_id: aid });
    sel.innerHTML = '<option value="">— All Forms (Retrieve All Leads) —</option>';
    if (r.success && r.forms && r.forms.length > 0) {
      r.forms.forEach(f => {
        sel.add(new Option((f.name || 'Form') + ' (' + f.id + ')', f.id));
      });
    }
  } catch (e) {
    sel.innerHTML = '<option value="">— All Forms (Default) —</option>';
  }
  sel.disabled = false;
}

document.getElementById('accountSelect')?.addEventListener('change', function() {
  loadForms(this.value);
  checkReady();
});

document.getElementById('loadFormsForManualBtn')?.addEventListener('click', function() {
  const aid = document.getElementById('manualAccountId')?.value.trim();
  if (aid) loadForms(aid);
});

document.getElementById('refreshAccountsBtn')?.addEventListener('click', loadAccounts);

document.getElementById('syncBtn')?.addEventListener('click', async function() {
  const acct = getSelectedAccountId();
  const form = getSelectedFormId();
  const camp = document.getElementById('campaignSelect')?.value || '';
  const mode = document.getElementById('syncModeSelect')?.value || 'incremental';

  if (!acct) {
    alert('Please select or enter a LinkedIn Ad Account ID.');
    return;
  }

  const origHtml = this.innerHTML;
  this.disabled = true;
  this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Synchronizing Leads from LinkedIn...';

  const resBox = document.getElementById('syncResults');
  const al = document.getElementById('syncAlert');
  if (resBox) resBox.style.display = 'none';

  try {
    const r = await apiPost('sync_leads', {
      account_id: acct,
      form_id: form,
      campaign_id: camp,
      sync_mode: mode
    });

    this.disabled = false;
    this.innerHTML = origHtml;

    if (resBox && al) {
      resBox.style.display = 'block';
      if (r.success) {
        al.className = 'alert alert-success mb-2';
        al.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ' + r.message;
        document.getElementById('statRetrieved').textContent = r.retrieved ?? 0;
        document.getElementById('statImported').textContent  = r.imported  ?? 0;
        document.getElementById('statSkipped').textContent   = r.skipped   ?? 0;
        document.getElementById('statFailed').textContent    = r.failed    ?? 0;

        // Update top KPI cards if present
        const kpiImp = document.getElementById('kpiImported');
        if (kpiImp) {
          const cur = parseInt(kpiImp.textContent.replace(/,/g, ''), 10) || 0;
          kpiImp.textContent = (cur + (r.imported ?? 0)).toLocaleString();
        }
        loadLogs();
      } else {
        al.className = 'alert alert-' + (r.oauth_required ? 'warning' : 'danger') + ' mb-2';
        al.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + (r.message || 'Synchronization failed.');
      }
    }
  } catch (err) {
    this.disabled = false;
    this.innerHTML = origHtml;
    if (resBox && al) {
      resBox.style.display = 'block';
      al.className = 'alert alert-danger mb-2';
      al.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Unexpected error executing synchronization.';
    }
  }
});

async function loadLogs() {
  const c = document.getElementById('logsContainer');
  if (!c) return;
  c.innerHTML = '<p class="text-muted small">Loading synchronization logs...</p>';

  try {
    const r = await apiPost('get_logs');
    if (!r.success || !r.logs || r.logs.length === 0) {
      c.innerHTML = '<div class="p-3 bg-light rounded text-center small text-muted"><i class="bi bi-info-circle me-1"></i> No synchronization activity recorded yet. Run a lead sync to see history here.</div>';
      return;
    }

    const badgeMap = {
      completed: 'bg-success-subtle text-success border border-success-subtle',
      failed:    'bg-danger-subtle text-danger border border-danger-subtle',
      started:   'bg-primary-subtle text-primary border border-primary-subtle'
    };

    let html = '<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">';
    html += '<thead class="table-light"><tr>';
    html += '<th>Date / Time</th><th>Account</th><th>Form</th><th>Status</th><th>Retrieved</th><th>Imported</th><th>Skipped</th><th>Failed</th><th>Initiated By</th>';
    html += '</tr></thead><tbody>';

    r.logs.forEach(l => {
      const bClass = badgeMap[l.status] || 'bg-light text-dark border';
      html += `<tr>
        <td class="text-nowrap">${l.created_at ?? ''}</td>
        <td><code>${l.account_id ? l.account_id : 'All'}</code></td>
        <td><span class="text-muted">${l.form_id ? l.form_id : 'All Forms'}</span></td>
        <td><span class="badge ${bClass} text-uppercase px-2 py-1">${l.status}</span></td>
        <td class="fw-semibold">${l.leads_retrieved ?? 0}</td>
        <td class="text-success fw-bold">+${l.leads_imported ?? 0}</td>
        <td class="text-warning">${l.leads_skipped ?? 0}</td>
        <td class="${(l.leads_failed > 0) ? 'text-danger fw-bold' : 'text-muted'}">${l.leads_failed ?? 0}</td>
        <td><span class="text-muted">${l.user_name || 'System'}</span></td>
      </tr>`;
    });

    html += '</tbody></table></div>';
    c.innerHTML = html;
  } catch (e) {
    c.innerHTML = '<p class="text-danger small mb-0"><i class="bi bi-exclamation-circle me-1"></i> Error loading logs.</p>';
  }
}

document.getElementById('refreshLogsBtn')?.addEventListener('click', loadLogs);

<?php if ($isConnected): ?>
  loadAccounts();
  loadLogs();
<?php endif; ?>
</script>

</body>
</html>