<?php
require_once __DIR__ . '/../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// Workspace isolation
$stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ? AND workspace_id = ?");
$stmt->execute([$id, $currentWorkspaceId]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$campaign) { header('Location: index.php?error=not_found'); exit; }

$leads = $pdo->prepare("SELECT * FROM leads WHERE campaign_id = ? AND workspace_id = ? ORDER BY created_at DESC");
$leads->execute([$id, $currentWorkspaceId]);
$leadsList = $leads->fetchAll(PDO::FETCH_ASSOC);

$pageEyebrow = 'Campaigns';
$pageTitle = $campaign['name'];
$activePage = 'campaigns';

$limit = (int)($campaign['lead_limit'] ?: 100);
$total = (int)$campaign['total_leads'];
$pct = min(100, round(($total / max(1, $limit)) * 100));
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($campaign['name']) ?> — Primovative Co.</title>
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
      <!-- CAMPAIGN HEADER -->
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
          <a href="index.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-arrow-left me-1"></i> Back to Campaigns</a>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <h2 class="h4 mb-0 fw-bold text-dark"><?= htmlspecialchars($campaign['name']) ?></h2>
            <span class="badge <?= $campaign['status'] === 'active' ? 'bg-success' : ($campaign['status'] === 'paused' ? 'bg-warning text-dark' : 'bg-secondary') ?>"><?= ucfirst($campaign['status']) ?></span>
          </div>
          <div class="text-muted small mt-1">
            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($campaign['location'] ?: 'National') ?> &bull;
            <i class="bi bi-tag me-1"></i><?= htmlspecialchars($campaign['industry'] ?: 'General') ?>
            <?php if (!empty($campaign['created_at'])): ?>
              &bull; <i class="bi bi-calendar3 me-1"></i>Created <?= date('M d, Y', strtotime($campaign['created_at'])) ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
          <a href="../lead-discovery/index.php?campaign_id=<?= $campaign['id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i> Discover Leads</a>
          <a href="edit.php?id=<?= $campaign['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i> Edit Campaign</a>
        </div>
      </div>

      <?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 py-2 small" role="alert">
          <i class="bi bi-check-circle me-1"></i> Campaign details updated successfully.
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>
      <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-violet"><i class="bi bi-people"></i></div>
            <div class="stat-content">
              <span class="stat-label">Total Leads</span>
              <div class="stat-value-row"><strong><?= $campaign['total_leads'] ?></strong><small class="text-muted">/ <?= $limit ?></small></div>
              <div class="progress mt-2" style="height:5px;"><div class="progress-bar bg-primary" style="width:<?= $pct ?>%;"></div></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-content">
              <span class="stat-label">Qualified</span>
              <div class="stat-value-row"><strong><?= $campaign['qualified_leads'] ?></strong></div>
              <small class="text-muted">High intent score</small>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-amber"><i class="bi bi-envelope"></i></div>
            <div class="stat-content">
              <span class="stat-label">Contacted</span>
              <div class="stat-value-row"><strong><?= $campaign['contacted_leads'] ?></strong></div>
              <small class="text-muted">Outreach sent</small>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-blue"><i class="bi bi-reply"></i></div>
            <div class="stat-content">
              <span class="stat-label">Replies</span>
              <div class="stat-value-row"><strong><?= $campaign['replied_leads'] ?></strong></div>
              <small class="text-muted">Active threads</small>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-12 col-lg-8">
          <div class="dashboard-card p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
              <div>
                <h3 class="h6 fw-bold mb-0">Enrolled Leads (<?= count($leadsList) ?>)</h3>
                <small class="text-muted">Prospects attached to this campaign</small>
              </div>
              <?php if (!empty($leadsList)): ?>
                <a href="../leads/index.php?campaign_id=<?= $campaign['id'] ?>" class="btn btn-outline-secondary btn-sm">
                  <i class="bi bi-box-arrow-up-right me-1"></i> View in Leads
                </a>
              <?php endif; ?>
            </div>

            <?php if (empty($leadsList)): ?>
              <div class="text-center py-5">
                <div class="stat-icon icon-violet mx-auto mb-3" style="width: 52px; height: 52px; font-size: 22px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;">
                  <i class="bi bi-people"></i>
                </div>
                <h5 class="fw-semibold fs-6 mb-1">No Leads Assigned Yet</h5>
                <p class="text-muted small mb-3">Discover relevant prospects using Google Maps &amp; Places search and assign them here.</p>
                <a href="../lead-discovery/index.php?campaign_id=<?= $campaign['id'] ?>" class="btn btn-primary btn-sm">
                  <i class="bi bi-search me-1"></i> Discover Leads
                </a>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Company</th>
                      <th>Contact</th>
                      <th>Score</th>
                      <th>Status</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($leadsList as $lead): ?>
                      <tr>
                        <td>
                          <div class="fw-semibold text-dark"><?= htmlspecialchars($lead['company'] ?: 'N/A') ?></div>
                          <?php if (!empty($lead['location'])): ?>
                            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($lead['location']) ?></small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <div><?= htmlspecialchars($lead['name']) ?></div>
                          <small class="text-muted"><?= htmlspecialchars($lead['email'] ?: 'No email') ?></small>
                        </td>
                        <td>
                          <span class="badge bg-light text-dark border"><?= (int)$lead['score'] ?>/100</span>
                        </td>
                        <td>
                          <?php
                            $st = $lead['status'] ?? 'new';
                            $badgeClass = match($st) {
                              'qualified' => 'bg-success',
                              'contacted' => 'bg-warning text-dark',
                              'replied' => 'bg-info text-dark',
                              'converted' => 'bg-primary',
                              default => 'bg-secondary'
                            };
                          ?>
                          <span class="badge <?= $badgeClass ?>"><?= ucfirst($st) ?></span>
                        </td>
                        <td class="text-end">
                          <a href="../leads/view.php?id=<?= $lead['id'] ?>" class="btn btn-outline-primary btn-sm py-1 px-2" title="View Lead Details">
                            <i class="bi bi-eye"></i>
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Campaign Details & Aside -->
        <aside class="col-12 col-lg-4" aria-label="Campaign Details and Actions">
          <div class="dashboard-card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h3 class="h6 fw-bold mb-0">Campaign Details</h3>
              <a href="edit.php?id=<?= $campaign['id'] ?>" class="btn btn-outline-secondary btn-sm py-1 px-2" title="Edit Campaign">
                <i class="bi bi-pencil"></i>
              </a>
            </div>

            <div class="mb-3">
              <div class="text-muted small fw-semibold">Keywords</div>
              <div class="mt-1 d-flex flex-wrap gap-1">
                <?php
                  $rawKeywords = trim($campaign['keywords'] ?? '');
                  if ($rawKeywords !== ''):
                    $kws = array_filter(array_map('trim', explode(',', $rawKeywords)));
                    foreach ($kws as $kw):
                ?>
                  <span class="badge bg-light text-dark border"><?= htmlspecialchars($kw) ?></span>
                <?php
                    endforeach;
                  else:
                ?>
                  <span class="text-muted small fst-italic">None specified</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="mb-3">
              <div class="text-muted small fw-semibold">Target Industry</div>
              <div class="small fw-semibold mt-1 text-dark"><?= htmlspecialchars($campaign['industry'] ?: 'General') ?></div>
            </div>

            <div class="mb-3">
              <div class="text-muted small fw-semibold">Target Location</div>
              <div class="small fw-semibold mt-1 text-dark"><i class="bi bi-geo-alt me-1 text-muted"></i><?= htmlspecialchars($campaign['location'] ?: 'National') ?></div>
            </div>

            <div class="mb-3">
              <div class="text-muted small fw-semibold">Description</div>
              <p class="small text-secondary mb-0 mt-1"><?= !empty($campaign['description']) ? nl2br(htmlspecialchars($campaign['description'])) : '<span class="text-muted fst-italic">None</span>' ?></p>
            </div>

            <div class="border-top pt-3">
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Lead Limit Progress</span>
                <span class="fw-semibold text-dark"><?= $total ?> / <?= $limit ?> (<?= $pct ?>%)</span>
              </div>
              <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pct ?>%;"></div>
              </div>
            </div>

            <div class="border-top pt-3 mt-3 small text-muted d-flex justify-content-between">
              <span>Created</span>
              <span class="fw-medium text-dark"><?= date('M d, Y', strtotime($campaign['created_at'])) ?></span>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="dashboard-card p-4">
            <h3 class="h6 fw-bold mb-3">Quick Actions</h3>
            <div class="d-grid gap-2">
              <a href="../lead-discovery/index.php?campaign_id=<?= $campaign['id'] ?>" class="btn btn-outline-primary btn-sm text-start py-2">
                <i class="bi bi-search me-2"></i> Discover Leads for Campaign
              </a>
              <a href="../leads/index.php?campaign_id=<?= $campaign['id'] ?>" class="btn btn-outline-secondary btn-sm text-start py-2">
                <i class="bi bi-people me-2"></i> Manage Enrolled Leads
              </a>
              <a href="../outreach/index.php" class="btn btn-outline-secondary btn-sm text-start py-2">
                <i class="bi bi-send me-2"></i> Launch Outreach
              </a>
            </div>
          </div>
        </aside>
      </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
