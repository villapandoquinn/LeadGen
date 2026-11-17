<?php
/**
 * LinkedIn Lead Sync Dashboard
 * linkedin/index.php
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../services/LinkedInLeadService.php';

$pageEyebrow = 'Lead Generation';
$pageTitle   = 'LinkedIn Lead Sync';
$activePage  = 'lead-discovery';

$service  = new LinkedInLeadService();
$connSts  = $service->getConnectionStatus();
$settings = file_exists(__DIR__.'/../config/settings.json')
    ? (json_decode(file_get_contents(__DIR__.'/../config/settings.json'), true) ?? [])
    : [];

$isConfigured = $connSts['is_configured'];
$isConnected  = $connSts['is_connected'];
$isExpired    = $connSts['is_expired'];
$connectedAt  = $connSts['connected_at'];

$oauthUrl = '';
if ($isConfigured && !$isConnected) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['linkedin_oauth_state'] = $state;
    $oauthUrl = $service->getAuthorizationUrl($state);
}

$campStmt = $pdo->prepare("SELECT id, name FROM campaigns WHERE workspace_id=? ORDER BY name ASC");
$campStmt->execute([$currentWorkspaceId]);
$campaigns = $campStmt->fetchAll();

$lastSync = null;
try {
    $ls = $pdo->prepare("SELECT * FROM linkedin_sync_logs WHERE workspace_id=? AND status='completed' ORDER BY created_at DESC LIMIT 1");
    $ls->execute([$currentWorkspaceId]);
    $lastSync = $ls->fetch();
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
</head>
<body>
<div class="app-shell">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-area">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <main class="content">

      <?php if ($alertSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 py-2 small">
          <i class="bi bi-check-circle me-1"></i> <?= $alertSuccess ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      <?php if ($alertError): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 py-2 small">
          <i class="bi bi-exclamation-triangle me-1"></i> <?= $alertError ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- CONNECTION STATUS CARD -->
      <div class="dashboard-card p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;background:#0077B5;border-radius:12px;display:flex;align-items:center;justify-content:center;">
              <i class="bi bi-linkedin text-white fs-4"></i>
            </div>
            <div>
              <h2 class="h5 fw-bold mb-0">LinkedIn Lead Sync</h2>
              <p class="text-muted small mb-0">Lead Sync API v202509 &bull; OAuth 2.0 Member (3-legged)</p>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
<?php if (!$isConfigured): ?>
            <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-3 py-2"><i class="bi bi-exclamation-triangle me-1"></i> Not Configured</span>
            <a href="../settings/index.php#linkedin-api" class="btn btn-sm btn-outline-primary"><i class="bi bi-gear me-1"></i> Configure in Settings</a>
<?php elseif ($isConnected): ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
              <i class="bi bi-check-circle me-1"></i> Connected<?php if ($connectedAt): ?> &bull; <?= date('M j, Y', strtotime($connectedAt)) ?><?php endif; ?>
            </span>
            <form method="POST" action="disconnect.php" class="m-0" onsubmit="return confirm('Disconnect LinkedIn account?')">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-plug me-1"></i> Disconnect</button>
            </form>
<?php elseif (!empty($oauthUrl)): ?>
            <span class="badge <?= $isExpired?'bg-warning-subtle text-dark border-warning-subtle':'bg-secondary-subtle text-secondary border-secondary-subtle' ?> border px-3 py-2">
              <i class="bi bi-<?= $isExpired?'clock':'plug' ?> me-1"></i> <?= $isExpired?'Token Expired':'Not Connected' ?>
            </span>
            <a href="<?= htmlspecialchars($oauthUrl) ?>" class="btn btn-sm btn-primary" style="background:#0077B5;border-color:#0077B5;">
              <i class="bi bi-linkedin me-1"></i> <?= $isExpired?'Re-authorize LinkedIn':'Connect LinkedIn Account' ?>
            </a>
<?php else: ?>
            <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-3 py-2"><i class="bi bi-exclamation-triangle me-1"></i> Configure credentials first</span>
            <a href="../settings/index.php#linkedin-api" class="btn btn-sm btn-outline-primary"><i class="bi bi-gear me-1"></i> Go to Settings</a>
<?php endif; ?>
          </div>
        </div>
        <?php if (!$isConfigured): ?>
        <div class="alert alert-info mt-3 mb-0 small">
          <i class="bi bi-info-circle me-1"></i>
          Configure your <strong>LinkedIn Client ID</strong>, <strong>Client Secret</strong>, and <strong>Redirect URI</strong> in
          <a href="../settings/index.php#linkedin-api" class="alert-link">Settings &rarr; LinkedIn API Integration</a>, then return here to connect.
        </div>
        <?php endif; ?>
      </div>


<?php if ($isConnected): ?>
      <div class="row g-4">
        <div class="col-12 col-lg-7">
          <div class="dashboard-card p-4">
            <h3 class="h6 fw-bold mb-3 border-bottom pb-2"><i class="bi bi-arrow-repeat text-primary me-1"></i> Synchronize Leads</h3>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small fw-semibold">LinkedIn Ad Account <span class="text-danger">*</span></label>
                <div class="input-group">
                  <select id="accountSelect" class="form-select"><option value="">Loading accounts...</option></select>
                  <button class="btn btn-outline-secondary" type="button" id="refreshAccountsBtn" title="Refresh accounts"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Lead Form <span class="text-muted">(leave blank for all forms)</span></label>
                <select id="formSelect" class="form-select" disabled><option value="">— All Forms —</option></select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Assign to Campaign</label>
                <select id="campaignSelect" class="form-select">
                  <option value="">— No Campaign —</option>
                  <?php foreach ($campaigns as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Sync Mode</label>
                <select id="syncModeSelect" class="form-select">
                  <option value="incremental">Incremental (new leads only)</option>
                  <option value="full">Full Sync (all available)</option>
                </select>
              </div>
              <div class="col-12">
                <button id="syncBtn" class="btn btn-primary w-100" disabled>
                  <i class="bi bi-arrow-repeat me-1"></i> Synchronize Leads from LinkedIn
                </button>
              </div>
            </div>
            <div id="syncResults" class="mt-3" style="display:none;">
              <div class="alert mb-2" id="syncAlert"></div>
              <div class="row g-2">
                <div class="col-6 col-md-3"><div class="stat-card text-center p-2"><div class="fw-bold fs-5" id="statRetrieved">0</div><div class="small text-muted">Retrieved</div></div></div>
                <div class="col-6 col-md-3"><div class="stat-card text-center p-2"><div class="fw-bold fs-5 text-success" id="statImported">0</div><div class="small text-muted">Imported</div></div></div>
                <div class="col-6 col-md-3"><div class="stat-card text-center p-2"><div class="fw-bold fs-5 text-warning" id="statSkipped">0</div><div class="small text-muted">Duplicates</div></div></div>
                <div class="col-6 col-md-3"><div class="stat-card text-center p-2"><div class="fw-bold fs-5 text-danger" id="statFailed">0</div><div class="small text-muted">Failed</div></div></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-5">
          <div class="dashboard-card p-4 mb-3">
            <h3 class="h6 fw-bold mb-3"><i class="bi bi-clock-history text-muted me-1"></i> Last Synchronization</h3>
            <?php if ($lastSync): ?>
            <dl class="row mb-0 small">
              <dt class="col-5 text-muted">Date</dt><dd class="col-7"><?= htmlspecialchars($lastSync['created_at']) ?></dd>
              <dt class="col-5 text-muted">Retrieved</dt><dd class="col-7"><?= (int)$lastSync['leads_retrieved'] ?></dd>
              <dt class="col-5 text-muted">Imported</dt><dd class="col-7 text-success fw-semibold"><?= (int)$lastSync['leads_imported'] ?></dd>
              <dt class="col-5 text-muted">Duplicates</dt><dd class="col-7 text-warning"><?= (int)$lastSync['leads_skipped'] ?></dd>
              <dt class="col-5 text-muted">Account</dt><dd class="col-7"><code><?= htmlspecialchars($lastSync['account_id']) ?></code></dd>
            </dl>
            <?php else: ?>
            <p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i>No sync performed yet.</p>
            <?php endif; ?>
          </div>
          <div class="dashboard-card p-4">
            <h3 class="h6 fw-bold mb-3"><i class="bi bi-link-45deg text-muted me-1"></i> Quick Links</h3>
            <div class="d-grid gap-2">
              <a href="../leads/index.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-people me-1"></i> View All Leads</a>
              <a href="../lead-discovery/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search me-1"></i> Lead Discovery</a>
              <a href="../settings/index.php#linkedin-api" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear me-1"></i> LinkedIn Settings</a>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="dashboard-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <h3 class="h6 fw-bold mb-0"><i class="bi bi-list-ul text-muted me-1"></i> Sync Activity Log</h3>
              <button id="refreshLogsBtn" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
            </div>
            <div id="logsContainer"><p class="text-muted small">Loading...</p></div>
          </div>
        </div>
      </div>
<?php endif; ?>


      <!-- API REFERENCE INFO -->
      <div class="dashboard-card p-4 mt-4">
        <h3 class="h6 fw-bold mb-2"><i class="bi bi-info-circle text-primary me-1"></i> LinkedIn Lead Sync API Reference</h3>
        <div class="row g-2 small">
          <div class="col-md-6">
            <table class="table table-sm table-borderless mb-0">
              <tr><td class="text-muted fw-semibold">API Version</td><td><code>202509</code></td></tr>
              <tr><td class="text-muted fw-semibold">Auth Type</td><td>OAuth 2.0 Member (3-legged)</td></tr>
              <tr><td class="text-muted fw-semibold">Required Scopes</td><td><code>r_ads r_ads_leadgen_automation r_basicprofile</code></td></tr>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-sm table-borderless mb-0">
              <tr><td class="text-muted fw-semibold">Lead Sync Endpoint</td><td><code>GET /rest/leadFormSubmissions</code></td></tr>
              <tr><td class="text-muted fw-semibold">Ad Accounts</td><td><code>GET /rest/adAccounts</code> (FINDER)</td></tr>
              <tr><td class="text-muted fw-semibold">Version Header</td><td><code>LinkedIn-Version: 202509</code></td></tr>
            </table>
          </div>
        </div>
      </div>

    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>

<script>
const CSRF = '<?= $csrfToken ?>';
function apiPost(action, extra={}) {
  const fd=new FormData(); fd.append('csrf_token',CSRF); fd.append('action',action);
  for(const [k,v] of Object.entries(extra)) fd.append(k,v);
  return fetch('api.php',{method:'POST',body:fd}).then(r=>r.json());
}
async function loadAccounts() {
  const sel=document.getElementById('accountSelect');
  if(!sel)return;
  sel.innerHTML='<option>Loading accounts...</option>';
  const r=await apiPost('get_accounts');
  sel.innerHTML='<option value="">— Select Ad Account —</option>';
  if(r.success&&r.accounts?.length) r.accounts.forEach(a=>sel.add(new Option(a.name+' ('+a.id+')',a.id)));
  else sel.innerHTML='<option value="">'+(r.message||'No accounts found — verify r_ads scope')+'</option>';
  checkReady();
}
async function loadForms(aid) {
  const sel=document.getElementById('formSelect');
  if(!sel)return;
  sel.disabled=true; sel.innerHTML='<option value="">Loading forms...</option>';
  if(!aid){sel.innerHTML='<option value="">— All Forms —</option>';return;}
  const r=await apiPost('get_forms',{account_id:aid});
  sel.innerHTML='<option value="">— All Forms —</option>';
  if(r.success&&r.forms?.length) r.forms.forEach(f=>sel.add(new Option(f.name+' ('+f.id+')',f.id)));
  sel.disabled=false;
}
function checkReady(){const a=document.getElementById('accountSelect')?.value;const b=document.getElementById('syncBtn');if(b)b.disabled=!a;}
document.getElementById('accountSelect')?.addEventListener('change',function(){loadForms(this.value);checkReady();});
document.getElementById('refreshAccountsBtn')?.addEventListener('click',loadAccounts);
document.getElementById('syncBtn')?.addEventListener('click',async function(){
  const acct=document.getElementById('accountSelect').value;
  const form=document.getElementById('formSelect').value;
  const camp=document.getElementById('campaignSelect').value;
  const mode=document.getElementById('syncModeSelect').value;
  const orig=this.innerHTML; this.disabled=true;
  this.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Synchronizing...';
  document.getElementById('syncResults').style.display='none';
  const r=await apiPost('sync_leads',{account_id:acct,form_id:form,campaign_id:camp,sync_mode:mode});
  this.disabled=false; this.innerHTML=orig;
  const res=document.getElementById('syncResults'); const al=document.getElementById('syncAlert');
  res.style.display='block';
  if(r.success){
    al.className='alert alert-success mb-2';
    al.innerHTML='<i class="bi bi-check-circle me-1"></i>'+r.message;
    document.getElementById('statRetrieved').textContent=r.retrieved??0;
    document.getElementById('statImported').textContent=r.imported??0;
    document.getElementById('statSkipped').textContent=r.skipped??0;
    document.getElementById('statFailed').textContent=r.failed??0;
    loadLogs();
  } else {
    al.className='alert alert-'+(r.oauth_required?'warning':'danger')+' mb-2';
    al.innerHTML='<i class="bi bi-exclamation-triangle me-1"></i>'+r.message;
  }
});
async function loadLogs(){
  const c=document.getElementById('logsContainer'); if(!c)return;
  c.innerHTML='<p class="text-muted small">Loading...</p>';
  const r=await apiPost('get_logs');
  if(!r.success||!r.logs?.length){c.innerHTML='<p class="text-muted small mb-0">No synchronization logs yet.</p>';return;}
  const bm={completed:'bg-success',failed:'bg-danger',started:'bg-primary',partial:'bg-warning text-dark'};
  let h='<div class="table-responsive"><table class="table table-sm table-hover mb-0 small"><thead class="table-light"><tr><th>Date/Time</th><th>Account</th><th>Status</th><th>Retrieved</th><th>Imported</th><th>Skipped</th><th>Failed</th><th>User</th></tr></thead><tbody>';
  r.logs.forEach(l=>{h+=`<tr><td>${l.created_at??''}</td><td><code>${l.account_id??''}</code></td><td><span class="badge ${bm[l.status]||'bg-secondary'}">${l.status}</span></td><td>${l.leads_retrieved??0}</td><td class="text-success fw-semibold">${l.leads_imported??0}</td><td class="text-warning">${l.leads_skipped??0}</td><td class="text-danger">${l.leads_failed??0}</td><td>${l.user_name??'-'}</td></tr>`;});
  h+='</tbody></table></div>'; c.innerHTML=h;
}
document.getElementById('refreshLogsBtn')?.addEventListener('click',loadLogs);
<?php if ($isConnected): ?>loadAccounts();loadLogs();<?php endif; ?>
</script>

</body>
</html>

