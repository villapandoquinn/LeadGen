<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageEyebrow = 'Management';
$pageTitle = 'Settings';
$activePage = 'settings';

$settingsFile = __DIR__ . '/../config/settings.json';
$settings = [
    'workspace_name' => 'Primovative Co.',
    'support_email' => 'support@primovative.com',
    'timezone' => 'America/New_York',
    'daily_limit' => 500,
    'enable_tracking' => true,
    'sender_name' => 'Primovative Team',
    'smtp_host' => 'smtp.sendgrid.net',
    'smtp_port' => 587
];
if (file_exists($settingsFile)) {
    $loaded = json_decode(file_get_contents($settingsFile), true);
    if (is_array($loaded)) {
        $settings = array_merge($settings, $loaded);
    }
}

// Merge workspace specific configuration
$settings['workspace_name'] = $currentWorkspace['name'];
if (!empty($currentWorkspace['settings'])) {
    $wsSettings = is_string($currentWorkspace['settings']) ? json_decode($currentWorkspace['settings'], true) : $currentWorkspace['settings'];
    if (is_array($wsSettings)) {
        $settings = array_merge($settings, $wsSettings);
    }
}
$settings['workspace_name'] = $currentWorkspace['name'];

$isSuccess = isset($_GET['success']);
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Settings — Primovative Co.</title>
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
      <div class="row justify-content-center">
        <div class="col-12 col-xl-9">
          <?php if ($isSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4 py-2 small" role="alert">
              <i class="bi bi-check-circle me-1"></i> Settings successfully saved!
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <div class="dashboard-card p-4">
            <h2 class="h5 fw-bold mb-1">Platform Settings</h2>
            <p class="text-muted small mb-4">Configure workspace profile, outreach delivery servers, and tracking defaults.</p>

            <form method="POST" action="save.php">
              <?= csrf_field() ?>

              <h3 class="h6 fw-bold mb-3 border-bottom pb-2">Workspace Profile</h3>
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Workspace Name</label>
                  <input type="text" name="workspace_name" class="form-control" value="<?= htmlspecialchars($settings['workspace_name']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Support / Billing Email</label>
                  <input type="email" name="support_email" class="form-control" value="<?= htmlspecialchars($settings['support_email']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Default Timezone</label>
                  <select name="timezone" class="form-select">
                    <option value="America/New_York" <?= $settings['timezone']==='America/New_York'?'selected':'' ?>>Eastern Time (US &amp; Canada)</option>
                    <option value="America/Chicago" <?= $settings['timezone']==='America/Chicago'?'selected':'' ?>>Central Time (US &amp; Canada)</option>
                    <option value="America/Los_Angeles" <?= $settings['timezone']==='America/Los_Angeles'?'selected':'' ?>>Pacific Time (US &amp; Canada)</option>
                    <option value="UTC" <?= $settings['timezone']==='UTC'?'selected':'' ?>>UTC</option>
                  </select>
                </div>
              </div>

              <h3 class="h6 fw-bold mb-3 border-bottom pb-2">Outreach &amp; Email Delivery</h3>
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Default Sender Name</label>
                  <input type="text" name="sender_name" class="form-control" value="<?= htmlspecialchars($settings['sender_name']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Daily Send Threshold</label>
                  <input type="number" name="daily_limit" class="form-control" value="<?= (int)$settings['daily_limit'] ?>" min="10" max="10000">
                </div>
                <div class="col-md-8">
                  <label class="form-label small fw-semibold">SMTP Host</label>
                  <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($settings['smtp_host']) ?>" placeholder="e.g. smtp.sendgrid.net">
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-semibold">SMTP Port</label>
                  <input type="number" name="smtp_port" class="form-control" value="<?= (int)$settings['smtp_port'] ?>">
                </div>
                <div class="col-12">
                  <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="enable_tracking" id="trackingSwitch" value="1" <?= !empty($settings['enable_tracking']) ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-semibold" for="trackingSwitch">Enable Email Open &amp; Click Tracking Pixel</label>
                  </div>
                </div>
              </div>

              <h3 class="h6 fw-bold mb-3 border-bottom pb-2 mt-4" id="linkedin-api">
                <i class="bi bi-linkedin text-primary me-1"></i> LinkedIn Lead Generation API Integration
              </h3>
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">LinkedIn Client ID</label>
                  <input type="text" name="linkedin_client_id" class="form-control" value="<?= htmlspecialchars($settings['linkedin_client_id'] ?? '') ?>" placeholder="Client ID">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">LinkedIn Client Secret</label>
                  <input type="password" name="linkedin_client_secret" class="form-control" value="<?= htmlspecialchars($settings['linkedin_client_secret'] ?? '') ?>" placeholder="Client Secret">
                </div>
                <div class="col-12">
                  <label class="form-label small fw-semibold">LinkedIn Access Token</label>
                  <div class="input-group">
                    <input type="password" id="liTokenInput" name="linkedin_access_token" class="form-control font-monospace" value="<?= htmlspecialchars($settings['linkedin_access_token'] ?? '') ?>" placeholder="AQV...">
                    <button class="btn btn-outline-primary" type="button" id="testLiTokenBtn">
                      <i class="bi bi-broadcast me-1"></i> Test Connection
                    </button>
                  </div>
                  <div id="testLiFeedback" class="small mt-2" style="display: none;"></div>
                </div>
              </div>


              <h3 class="h6 fw-bold mb-3 border-bottom pb-2" id="google-api">
                <i class="bi bi-geo-alt-fill text-danger me-1"></i> Google Maps &amp; Places API Integration
              </h3>
              <div class="row g-3 mb-4">
                <div class="col-12">
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="form-label small fw-semibold mb-0">Google Maps / Places API Key</label>
                    <?php if (!empty($settings['google_maps_api_key'])): ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i> API Key Configured</span>
                    <?php else: ?>
                      <span class="badge bg-warning-subtle text-dark border border-warning-subtle"><i class="bi bi-exclamation-triangle me-1"></i> Not Configured</span>
                    <?php endif; ?>
                  </div>
                  <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-key text-muted"></i></span>
                    <input type="password" id="googleApiKeyInput" name="google_maps_api_key" class="form-control font-monospace" value="<?= htmlspecialchars($settings['google_maps_api_key'] ?? '') ?>" placeholder="AIzaSy...">
                    <button class="btn btn-outline-secondary" type="button" id="toggleApiKeyVisibility" title="Toggle Visibility">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-outline-primary" type="button" id="testApiKeyBtn">
                      <i class="bi bi-broadcast me-1"></i> Test Key
                    </button>
                  </div>
                  <div id="testKeyFeedback" class="small mt-2" style="display: none;"></div>
                  <div class="small text-muted mt-2">
                    <i class="bi bi-info-circle me-1"></i> Used by <strong>Lead Discovery</strong> to dynamically search real-world businesses via Google Places API (New). Your API key is stored securely server-side and never exposed to the client.
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-end gap-2 border-top pt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
const apiKeyInput = document.getElementById('googleApiKeyInput');
const toggleBtn = document.getElementById('toggleApiKeyVisibility');
const testBtn = document.getElementById('testApiKeyBtn');
const feedback = document.getElementById('testKeyFeedback');

if (toggleBtn && apiKeyInput) {
  toggleBtn.addEventListener('click', function() {
    const isPassword = apiKeyInput.type === 'password';
    apiKeyInput.type = isPassword ? 'text' : 'password';
    this.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
  });
}

if (testBtn && apiKeyInput) {
  testBtn.addEventListener('click', function() {
    const key = apiKeyInput.value.trim();
    if (!key) {
      feedback.style.display = 'block';
      feedback.className = 'small mt-2 text-danger';
      feedback.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Please paste an API key to test.';
      return;
    }
    const origHtml = testBtn.innerHTML;
    testBtn.disabled = true;
    testBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Testing...';
    feedback.style.display = 'none';

    fetch('test-google-api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'api_key=' + encodeURIComponent(key)
    })
    .then(r => r.json())
    .then(data => {
      feedback.style.display = 'block';
      if (data.success) {
        feedback.className = 'small mt-2 text-success fw-semibold';
        feedback.innerHTML = '<i class="bi bi-check-circle me-1"></i> ' + data.message;
      } else {
        feedback.className = 'small mt-2 text-danger fw-semibold';
        feedback.innerHTML = '<i class="bi bi-x-circle me-1"></i> ' + data.message;
      }
    })
    .catch(err => {
      feedback.style.display = 'block';
      feedback.className = 'small mt-2 text-danger';
      feedback.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Server communication error.';
    })
    .finally(() => {
      testBtn.disabled = false;
      testBtn.innerHTML = origHtml;
    });
  });
}
const liTokenInput = document.getElementById('liTokenInput');
const testLiBtn = document.getElementById('testLiTokenBtn');
const feedbackLi = document.getElementById('testLiFeedback');

if (testLiBtn && liTokenInput) {
  testLiBtn.addEventListener('click', function() {
    const token = liTokenInput.value.trim();
    const origHtml = testLiBtn.innerHTML;
    testLiBtn.disabled = true;
    testLiBtn.innerHTML = '<span class=\"spinner-border spinner-border-sm me-1\"></span> Testing...';
    feedbackLi.style.display = 'none';

    fetch('test-linkedin-api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'access_token=' + encodeURIComponent(token)
    })
    .then(r => r.json())
    .then(data => {
      feedbackLi.style.display = 'block';
      if (data.success) {
        feedbackLi.className = 'small mt-2 text-success fw-semibold';
        feedbackLi.innerHTML = '<i class=\"bi bi-check-circle me-1\"></i> ' + data.message;
      } else {
        feedbackLi.className = 'small mt-2 text-danger fw-semibold';
        feedbackLi.innerHTML = '<i class=\"bi bi-x-circle me-1\"></i> ' + data.message;
      }
    })
    .catch(err => {
      feedbackLi.style.display = 'block';
      feedbackLi.className = 'small mt-2 text-danger';
      feedbackLi.innerHTML = '<i class=\"bi bi-exclamation-triangle me-1\"></i> Server communication error.';
    })
    .finally(() => {
      testLiBtn.disabled = false;
      testLiBtn.innerHTML = origHtml;
    });
  });
}

</script>
</body>
</html>
