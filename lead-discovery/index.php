<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../services/GooglePlacesService.php';
require_once __DIR__ . '/../services/LinkedInLeadService.php';

$pageEyebrow = 'Lead Generation';
$pageTitle = 'Lead Discovery';
$activePage = 'lead-discovery';

$sourceSource = trim($_GET['source'] ?? 'google');
if (!in_array($sourceSource, ['google', 'linkedin'])) {
    $sourceSource = 'google';
}

$placesService = new GooglePlacesService();
$linkedinService = new LinkedInLeadService();
$isGoogleConfigured = $placesService->isConfigured();
$isLinkedinConfigured = $linkedinService->isConfigured();

$isApiConfigured = ($sourceSource === 'linkedin') ? $isLinkedinConfigured : $isGoogleConfigured;

$campaignId = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;
$industryQuery = trim($_GET['industry'] ?? '');
$locationQuery = trim($_GET['location'] ?? '');
$keywordsQuery = trim($_GET['keywords'] ?? '');
$excludeKeywordsQuery = trim($_GET['exclude_keywords'] ?? '');
$relevanceFilter = trim($_GET['relevance'] ?? '');
$minRating = isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : 0.0;
$pageToken = trim($_GET['page_token'] ?? '');

$cStmt = $pdo->prepare("SELECT id, name, industry, location, keywords FROM campaigns WHERE workspace_id = ? ORDER BY name ASC");
$cStmt->execute([$currentWorkspaceId]);
$campaigns = $cStmt->fetchAll(PDO::FETCH_ASSOC);

// If campaign selected and no queries yet, pre-populate
if ($campaignId > 0 && empty($industryQuery) && empty($locationQuery)) {
    foreach ($campaigns as $c) {
        if ($c['id'] === $campaignId) {
            $industryQuery = $c['industry'] ?: '';
            $locationQuery = $c['location'] ?: '';
            $keywordsQuery = $c['keywords'] ?: '';
            break;
        }
    }
}

$prospects = [];
$searchPerformed = false;
$apiError = null;
$returnedPageToken = null;

if ($isApiConfigured && (!empty($industryQuery) || !empty($locationQuery) || !empty($keywordsQuery))) {
    $searchPerformed = true;
    $fullQuery = trim("{$industryQuery} {$keywordsQuery} in {$locationQuery}");
    if (empty($fullQuery)) $fullQuery = 'businesses';

    if ($sourceSource === 'linkedin') {
        $searchResp = $linkedinService->searchLeads($fullQuery, [
            'limit' => 20
        ]);
    } else {
        $searchResp = $placesService->searchPlaces($fullQuery, [
            'min_rating' => $minRating,
            'limit' => 20,
            'page_token' => $pageToken
        ]);
    }

    if (!empty($searchResp['success'])) {
        $prospects = $searchResp['results'] ?? [];
        $returnedPageToken = $searchResp['next_page_token'] ?? null;

        if ($sourceSource === 'linkedin') {
            $linkedinService->enrichDuplicates($prospects, $pdo, $currentWorkspaceId);
            $incKws = array_filter(array_map('trim', explode(',', $keywordsQuery)));
            $exKws = array_filter(array_map('trim', explode(',', $excludeKeywordsQuery)));
            $linkedinService->qualifyProspects($prospects, $incKws, $exKws, $industryQuery);

            if ($relevanceFilter !== '') {
                $prospects = array_values(array_filter($prospects, fn($p) => ($p['qualification'] ?? '') === $relevanceFilter));
            }

            $linkedinService->logDiscovery($pdo, $currentWorkspaceId, $campaignId ?: null, $userId, $fullQuery, count($prospects));
        } else {
            $placesService->enrichDuplicates($prospects, $pdo, $currentWorkspaceId);
            $incKws = array_filter(array_map('trim', explode(',', $keywordsQuery)));
            $exKws = array_filter(array_map('trim', explode(',', $excludeKeywordsQuery)));
            $placesService->qualifyProspects($prospects, $incKws, $exKws, $industryQuery);

            if ($relevanceFilter !== '') {
                $prospects = array_values(array_filter($prospects, fn($p) => ($p['qualification'] ?? '') === $relevanceFilter));
            }

            $placesService->logDiscovery($pdo, $currentWorkspaceId, $campaignId ?: null, $userId, $fullQuery, count($prospects));
        }
    } else {
        $apiError = $searchResp['message'] ?? 'Unable to retrieve businesses from API.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lead Discovery — Primovative Co.</title>
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
      <!-- SOURCE SELECTOR TABS -->
      <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
          <a class="nav-link <?= $sourceSource === 'google' ? 'active' : '' ?>" href="index.php?source=google<?= $campaignId ? '&campaign_id='.$campaignId : '' ?>">
            <i class="bi bi-geo-alt-fill me-1"></i> Google Maps Platform
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $sourceSource === 'linkedin' ? 'active' : '' ?>" href="index.php?source=linkedin<?= $campaignId ? '&campaign_id='.$campaignId : '' ?>">
            <i class="bi bi-linkedin me-1"></i> LinkedIn Lead Gen
          </a>
        </li>
      </ul>


      <!-- API CONFIGURATION NOTICE (if not configured) -->
      <?php if (!$isApiConfigured): ?>
        <div class="card border-warning bg-warning-subtle mb-4 shadow-sm">
          <div class="card-body p-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi <?= $sourceSource === 'facebook' ? 'bi-facebook text-primary' : 'bi-geo-alt-fill text-danger' ?> fs-4"></i>
                <h2 class="h6 fw-bold text-dark mb-0">
                  <?= $sourceSource === 'facebook' ? 'Facebook Meta Access Token Required' : 'Google Maps / Places API Key Required' ?>
                </h2>
              </div>
              <p class="text-dark small mb-0" style="max-width: 680px;">
                <?= $sourceSource === 'facebook' 
                    ? 'Lead Discovery connects dynamically to Facebook Graph API Pages Search to find targeted business pages with live details. Please configure your Facebook Access Token in Settings.'
                    : 'Lead Discovery connects dynamically to official Google Maps Platform APIs (Google Places API New) to find real, verified businesses. Please configure your API key in Settings.' ?>
              </p>
            </div>
            <a href="../settings/index.php#<?= $sourceSource === 'facebook' ? 'facebook-api' : 'google-api' ?>" class="btn btn-warning text-dark fw-bold text-nowrap">
              <i class="bi bi-gear-fill me-1"></i> Configure <?= $sourceSource === 'facebook' ? 'Meta API' : 'Google API' ?>
            </a>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($apiError): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 py-3 small" role="alert">
          <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-octagon-fill fs-5 text-danger"></i>
            <div>
              <strong>API Error:</strong> <?= htmlspecialchars($apiError) ?>
              <div class="mt-1 text-muted">Verify your credentials in <a href="../settings/index.php#<?= $sourceSource === 'facebook' ? 'facebook-api' : 'google-api' ?>" class="alert-link">Settings</a>.</div>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- SEARCH FORM CARD -->
      <div class="discovery-search-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <h2 class="h5 fw-bold mb-1">
              <?= $sourceSource === 'facebook' ? 'Facebook Meta Business Discovery' : 'Google Maps Business Discovery' ?>
            </h2>
            <p class="text-muted small mb-0">
              <?= $sourceSource === 'facebook' ? 'Search and qualify business pages directly via Facebook Graph API.' : 'Search and qualify real businesses directly via Google Places API.' ?>
            </p>
          </div>
          <div class="google-attribution">
            <i class="bi <?= $sourceSource === 'facebook' ? 'bi-facebook text-primary' : 'bi-google text-primary' ?>"></i>
            <span><?= $sourceSource === 'facebook' ? 'Powered by Facebook Graph API' : 'Powered by Google Maps Platform' ?></span>
          </div>
        </div>

        <form method="GET" action="index.php" id="discoveryForm">
          <input type="hidden" name="source" value="<?= htmlspecialchars($sourceSource) ?>">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Assign to Campaign</label>
              <select name="campaign_id" id="campaignSelector" class="form-select">
                <option value="">-- Direct Lead Discovery --</option>
                <?php foreach ($campaigns as $c): ?>
                  <option value="<?= $c['id'] ?>"
                    data-industry="<?= htmlspecialchars($c['industry'] ?? '') ?>"
                    data-location="<?= htmlspecialchars($c['location'] ?? '') ?>"
                    data-keywords="<?= htmlspecialchars($c['keywords'] ?? '') ?>"
                    <?= $campaignId === (int)$c['id'] ? 'selected' : '' ?>
                  >
                    <?= htmlspecialchars($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Industry / Niche <span class="text-danger">*</span></label>
              <input type="text" name="industry" id="industryInput" class="form-control" placeholder="e.g. Solar Contractors, Dentists" value="<?= htmlspecialchars($industryQuery) ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Target Location <span class="text-danger">*</span></label>
              <input type="text" name="location" id="locationInput" class="form-control" placeholder="e.g. Austin, TX or Chicago, IL" value="<?= htmlspecialchars($locationQuery) ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Include Keywords</label>
              <input type="text" name="keywords" id="keywordsInput" class="form-control" placeholder="e.g. Commercial, 24/7" value="<?= htmlspecialchars($keywordsQuery) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Exclude Keywords (Negative)</label>
              <input type="text" name="exclude_keywords" class="form-control" placeholder="e.g. wholesale, parts, repair" value="<?= htmlspecialchars($excludeKeywordsQuery) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Minimum Rating</label>
              <select name="min_rating" class="form-select">
                <option value="0" <?= $minRating == 0 ? 'selected' : '' ?>>Any Rating</option>
                <option value="3.5" <?= $minRating == 3.5 ? 'selected' : '' ?>>3.5+ Stars</option>
                <option value="4.0" <?= $minRating == 4.0 ? 'selected' : '' ?>>4.0+ Stars</option>
                <option value="4.5" <?= $minRating == 4.5 ? 'selected' : '' ?>>4.5+ Stars</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Qualification Filter</label>
              <select name="relevance" class="form-select">
                <option value="" <?= $relevanceFilter === '' ? 'selected' : '' ?>>All Tiers</option>
                <option value="Highly Relevant" <?= $relevanceFilter === 'Highly Relevant' ? 'selected' : '' ?>>Highly Relevant</option>
                <option value="Relevant" <?= $relevanceFilter === 'Relevant' ? 'selected' : '' ?>>Relevant</option>
                <option value="Potentially Relevant" <?= $relevanceFilter === 'Potentially Relevant' ? 'selected' : '' ?>>Potentially Relevant</option>
              </select>
            </div>
            <div class="col-md-3 d-flex align-items-end justify-content-end gap-2 ms-auto">
              <a href="index.php" class="btn btn-light">Reset</a>
              <button type="submit" class="btn btn-primary px-4" <?= !$isApiConfigured ? 'disabled' : '' ?>>
                <i class="bi bi-search me-1"></i> Discover Businesses
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- DISCOVERY RESULTS -->
      <?php if (!$searchPerformed): ?>
        <div class="card border-0 shadow-sm p-5 text-center bg-white mb-4">
          <div class="stat-icon icon-blue mx-auto mb-3" style="width: 56px; height: 56px; font-size: 24px;">
            <i class="bi bi-search"></i>
          </div>
          <h3 class="h6 fw-bold text-dark mb-1">Ready to Discover Targeted B2B Prospects</h3>
          <p class="text-muted small mx-auto mb-0" style="max-width: 460px;">
            Enter your desired industry and target location above and click Discover Businesses to retrieve real, verified business profiles directly from Google Maps.
          </p>
        </div>
      <?php elseif (empty($prospects)): ?>
        <div class="card border-0 shadow-sm p-5 text-center bg-white mb-4">
          <div class="stat-icon icon-amber mx-auto mb-3" style="width: 56px; height: 56px; font-size: 24px;">
            <i class="bi bi-geo"></i>
          </div>
          <h3 class="h6 fw-bold text-dark mb-1">No Businesses Found</h3>
          <p class="text-muted small mx-auto mb-0" style="max-width: 440px;">
            No verified businesses in Google Maps matched your search criteria. Try broadening your location or using more general industry keywords.
          </p>
        </div>
      <?php else: ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <h3 class="h6 fw-bold mb-0">Google Maps Prospects (<?= count($prospects) ?>)</h3>
            <small class="text-muted">Verified live business listings from Google Places API</small>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" id="importAllBtn">
            <i class="bi bi-cloud-arrow-down me-1"></i> Import All Discovered Leads
          </button>
        </div>
      <?php endif; ?>

      <?php if (!empty($prospects)): ?>
      <div class="row g-3">
        <?php foreach ($prospects as $p): ?>
          <div class="col-12 col-md-6 col-xl-4">
            <div class="place-card">
              <div class="place-card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                  <h4 class="h6 fw-bold text-truncate mb-0" style="max-width: 65%;" title="<?= htmlspecialchars($p['company']) ?>">
                    <?= htmlspecialchars($p['company']) ?>
                  </h4>
                  <div class="text-end">
                    <?php
                      $qClass = match($p['qualification'] ?? '') {
                        'Highly Relevant' => 'bg-success text-white',
                        'Relevant' => 'bg-primary text-white',
                        'Potentially Relevant' => 'bg-warning text-dark',
                        default => 'bg-secondary text-white'
                      };
                    ?>
                    <span class="badge <?= $qClass ?> me-1" style="font-size: 10px;"><?= htmlspecialchars($p['qualification'] ?? 'Relevant') ?></span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                      <i class="bi bi-lightning-charge-fill me-1"></i><?= $p['score'] ?>%
                    </span>
                  </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                  <?php if (!empty($p['category'])): ?>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['category']) ?></span>
                  <?php endif; ?>
                  <?php if ($p['rating'] !== null): ?>
                    <span class="place-rating-badge">
                      <i class="bi bi-star-fill text-warning"></i> <?= number_format($p['rating'], 1) ?>
                      <span class="text-muted fw-normal">(<?= number_format($p['reviews_count']) ?>)</span>
                    </span>
                  <?php endif; ?>
                </div>

                <?php if (!empty($p['matched_keywords'])): ?>
                  <div class="small mb-2 text-success">
                    <i class="bi bi-check-circle me-1"></i>Matched: <?= htmlspecialchars(implode(', ', $p['matched_keywords'])) ?>
                  </div>
                <?php endif; ?>

                <div class="small mb-1 text-truncate">
                  <i class="bi bi-geo-alt me-1 text-danger"></i>
                  <a href="<?= htmlspecialchars($p['google_maps_url']) ?>" target="_blank" rel="noopener" class="text-dark" title="<?= htmlspecialchars($p['address']) ?>">
                    <?= htmlspecialchars($p['address'] ?: 'View on Google Maps') ?>
                  </a>
                </div>

                <div class="small mb-1 text-muted text-truncate">
                  <i class="bi bi-telephone me-1 text-primary"></i>
                  <?php if (!empty($p['phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($p['phone']) ?>" class="text-muted"><?= htmlspecialchars($p['phone']) ?></a>
                  <?php else: ?>
                    <span class="fst-italic">Phone not listed</span>
                  <?php endif; ?>
                </div>

                <div class="small mb-2 text-muted text-truncate">
                  <i class="bi bi-globe me-1 text-success"></i>
                  <?php if (!empty($p['website'])): ?>
                    <a href="<?= htmlspecialchars($p['website']) ?>" target="_blank" rel="noopener" class="text-primary text-truncate"><?= htmlspecialchars(preg_replace('~^https?://(www\.)?~i', '', $p['website'])) ?></a>
                  <?php else: ?>
                    <span class="fst-italic">Website not listed</span>
                  <?php endif; ?>
                </div>

                <?php if (!empty($p['place_id'])): ?>
                  <div class="mt-2">
                    <span class="place-id-pill" title="Google Place ID: <?= htmlspecialchars($p['place_id']) ?>">
                      <i class="bi bi-google me-1"></i><?= htmlspecialchars(substr($p['place_id'], 0, 14)) ?>...
                    </span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="place-card-footer">
                <?php if (!empty($p['is_imported'])): ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle w-100 py-2">
                    <i class="bi bi-check2-circle me-1"></i> Already in Leads
                  </span>
                <?php else: ?>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary w-100 import-lead-btn"
                    data-company="<?= htmlspecialchars($p['company']) ?>"
                    data-name="<?= htmlspecialchars($p['name']) ?>"
                    data-phone="<?= htmlspecialchars($p['phone']) ?>"
                    data-website="<?= htmlspecialchars($p['website']) ?>"
                    data-industry="<?= htmlspecialchars($industryQuery ?: $p['category']) ?>"
                    data-location="<?= htmlspecialchars($p['location']) ?>"
                    data-address="<?= htmlspecialchars($p['address']) ?>"
                    data-place-id="<?= htmlspecialchars($p['place_id']) ?>"
                    data-rating="<?= $p['rating'] !== null ? $p['rating'] : '' ?>"
                    data-reviews="<?= $p['reviews_count'] ?>"
                    data-score="<?= $p['score'] ?>"
                  >
                    <i class="bi bi-plus-circle me-1"></i> Import Lead
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($returnedPageToken)): ?>
        <div class="text-center my-4">
          <form method="GET" action="index.php">
            <input type="hidden" name="campaign_id" value="<?= htmlspecialchars($campaignId) ?>">
            <input type="hidden" name="industry" value="<?= htmlspecialchars($industryQuery) ?>">
            <input type="hidden" name="location" value="<?= htmlspecialchars($locationQuery) ?>">
            <input type="hidden" name="keywords" value="<?= htmlspecialchars($keywordsQuery) ?>">
            <input type="hidden" name="exclude_keywords" value="<?= htmlspecialchars($excludeKeywordsQuery) ?>">
            <input type="hidden" name="min_rating" value="<?= htmlspecialchars($minRating) ?>">
            <input type="hidden" name="relevance" value="<?= htmlspecialchars($relevanceFilter) ?>">
            <input type="hidden" name="page_token" value="<?= htmlspecialchars($returnedPageToken) ?>">
            <button type="submit" class="btn btn-outline-primary px-4">
              <i class="bi bi-arrow-down-circle me-1"></i> Retrieve Next Page from Google Places
            </button>
          </form>
        </div>
      <?php endif; ?>

      <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Auto-fill query fields when a campaign is selected
const campaignSelector = document.getElementById('campaignSelector');
if (campaignSelector) {
  campaignSelector.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (opt && opt.value) {
      if (opt.dataset.industry) document.getElementById('industryInput').value = opt.dataset.industry;
      if (opt.dataset.location) document.getElementById('locationInput').value = opt.dataset.location;
      if (opt.dataset.keywords) document.getElementById('keywordsInput').value = opt.dataset.keywords;
    }
  });
}

// Single Lead Import AJAX
function importSingleLead(btn) {
  const origText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importing...';

  const campaignId = document.getElementById('campaignSelector').value;
  const formData = new FormData();
  formData.append('csrf_token', '<?= csrf_token() ?>');
  formData.append('company', btn.dataset.company || '');
  formData.append('name', btn.dataset.name || '');
  formData.append('phone', btn.dataset.phone || '');
  formData.append('website', btn.dataset.website || '');
  formData.append('industry', btn.dataset.industry || '');
  formData.append('location', btn.dataset.location || '');
  formData.append('address', btn.dataset.address || '');
  formData.append('place_id', btn.dataset.placeId || '');
  formData.append('rating', btn.dataset.rating || '');
  formData.append('reviews_count', btn.dataset.reviews || '0');
  formData.append('score', btn.dataset.score || '85');
  formData.append('source', 'google');
  if (campaignId) formData.append('campaign_id', campaignId);

  return fetch('import.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        btn.className = 'badge bg-success-subtle text-success border border-success-subtle w-100 py-2';
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Imported!';
        btn.disabled = true;
        return true;
      } else {
        alert(data.message || 'Error importing lead.');
        btn.disabled = false;
        btn.innerHTML = origText;
        return false;
      }
    })
    .catch(err => {
      alert('Server connection error.');
      btn.disabled = false;
      btn.innerHTML = origText;
      return false;
    });
}

document.querySelectorAll('.import-lead-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    importSingleLead(this);
  });
});

// Bulk Import All Discovered Leads
const importAllBtn = document.getElementById('importAllBtn');
if (importAllBtn) {
  importAllBtn.addEventListener('click', async function() {
    const unimported = Array.from(document.querySelectorAll('.import-lead-btn:not([disabled])'));
    if (unimported.length === 0) {
      alert('All leads on this page have already been imported.');
      return;
    }
    if (!confirm(`Import all ${unimported.length} discovered businesses into your workspace?`)) {
      return;
    }

    const origHtml = this.innerHTML;
    this.disabled = true;
    let count = 0;

    for (const btn of unimported) {
      this.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Importing (${count}/${unimported.length})...`;
      const ok = await importSingleLead(btn);
      if (ok) count++;
    }

    this.innerHTML = `<i class="bi bi-check-all me-1"></i> Imported ${count} Leads!`;
    setTimeout(() => {
      this.innerHTML = origHtml;
      this.disabled = false;
    }, 3000);
  });
}
</script>
</body>
</html>
