<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageEyebrow = 'Prospects';
$pageTitle = 'Leads';
$activePage = 'leads';

$campaignFilter = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;
$statusFilter = trim($_GET['status'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

$sql = "SELECT l.*, c.name as campaign_name, u.name as assignee_name FROM leads l LEFT JOIN campaigns c ON l.campaign_id = c.id LEFT JOIN users u ON l.assigned_to = u.id WHERE l.workspace_id = ?";
$params = [$currentWorkspaceId];

if ($campaignFilter > 0) {
    $sql .= " AND l.campaign_id = ?";
    $params[] = $campaignFilter;
}
if (!empty($statusFilter)) {
    $sql .= " AND l.status = ?";
    $params[] = $statusFilter;
}
if (!empty($searchQuery)) {
    $sql .= " AND (l.name LIKE ? OR l.company LIKE ? OR l.email LIKE ? OR l.location LIKE ?)";
    $like = "%{$searchQuery}%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql .= " ORDER BY l.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$campStmt = $pdo->prepare("SELECT id, name FROM campaigns WHERE workspace_id = ? ORDER BY name ASC");
$campStmt->execute([$currentWorkspaceId]);
$campaigns = $campStmt->fetchAll(PDO::FETCH_ASSOC);

$membersStmt = $pdo->prepare("SELECT u.id, u.name, u.email FROM users u JOIN workspace_members wm ON u.id = wm.user_id WHERE wm.workspace_id = ? AND wm.status = 'active' ORDER BY u.name ASC");
$membersStmt->execute([$currentWorkspaceId]);
$workspaceMembers = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

$totStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ?");
$totStmt->execute([$currentWorkspaceId]);
$totalCount = (int)$totStmt->fetchColumn();

$qStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND status = 'qualified'");
$qStmt->execute([$currentWorkspaceId]);
$qualCount = (int)$qStmt->fetchColumn();

$cStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND status = 'contacted'");
$cStmt->execute([$currentWorkspaceId]);
$contactCount = (int)$cStmt->fetchColumn();

$rStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND status = 'replied'");
$rStmt->execute([$currentWorkspaceId]);
$repliedCount = (int)$rStmt->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Leads Management — Primovative Co.</title>
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
      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-violet"><i class="bi bi-people"></i></div>
            <div class="stat-content"><span class="stat-label">Total Leads</span><div class="stat-value-row"><strong><?= number_format($totalCount) ?></strong></div></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-content"><span class="stat-label">Qualified</span><div class="stat-value-row"><strong><?= number_format($qualCount) ?></strong></div></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-amber"><i class="bi bi-envelope"></i></div>
            <div class="stat-content"><span class="stat-label">Contacted</span><div class="stat-value-row"><strong><?= number_format($contactCount) ?></strong></div></div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-blue"><i class="bi bi-reply"></i></div>
            <div class="stat-content"><span class="stat-label">Replied</span><div class="stat-value-row"><strong><?= number_format($repliedCount) ?></strong></div></div>
          </div>
        </div>
      </div>
      <div class="dashboard-card p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
          <form method="GET" action="index.php" class="d-flex flex-wrap gap-2 flex-grow-1" style="max-width: 780px;">
            <div class="input-group" style="max-width: 250px;">
              <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
              <input type="text" name="q" class="form-control border-start-0" placeholder="Search leads..." value="<?= htmlspecialchars($searchQuery) ?>">
            </div>
            <select name="campaign_id" class="form-select" style="max-width: 190px;" onchange="this.form.submit()">
              <option value="">All Campaigns</option>
              <?php foreach ($campaigns as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $campaignFilter === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="status" class="form-select" style="max-width: 150px;" onchange="this.form.submit()">
              <option value="">All Statuses</option>
              <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'unqualified' => 'Unqualified', 'replied' => 'Replied', 'converted' => 'Converted'] as $st => $stLabel): ?>
                <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $stLabel ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-light"><i class="bi bi-filter"></i></button>
          </form>

          <div class="d-flex gap-2">
            <a href="export.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i> Export</a>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newLeadModal"><i class="bi bi-plus-lg me-1"></i> Add Lead</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Company / Contact</th>
                <th>Source</th>
                <th>Campaign</th>
                <th>Assigned Employee</th>
                <th>Location</th>
                <th>Score</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($leads)): ?>
                <tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-person-x fs-1 d-block mb-2"></i>No leads found.</td></tr>
              <?php else: ?>
                <?php foreach ($leads as $l): ?>
                  <tr>
                    <td>
                      <div class="fw-bold text-dark"><?= htmlspecialchars($l['company'] ?: $l['name']) ?></div>
                      <div class="text-muted small"><?= htmlspecialchars($l['name']) ?> &bull; <?= htmlspecialchars($l['email'] ?? '') ?></div>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= ucfirst($l['source']) ?></span></td>
                    <td><small class="text-secondary"><?= htmlspecialchars($l['campaign_name'] ?: 'Unassigned') ?></small></td>
                    <td>
                      <?php if (!empty($l['assignee_name'])): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($l['assignee_name']) ?></span>
                      <?php else: ?>
                        <span class="badge bg-light text-muted border">Unassigned</span>
                      <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= htmlspecialchars($l['location'] ?: 'USA') ?></small></td>
                    <td><span class="badge bg-light text-dark border"><?= $l['score'] ?>/100</span></td>
                    <td>
                      <select class="form-select form-select-sm lead-status-select" data-id="<?= $l['id'] ?>" style="width:auto;min-width:110px;">
                        <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'unqualified' => 'Unqualified', 'replied' => 'Replied', 'converted' => 'Converted'] as $sVal => $sText): ?>
                          <option value="<?= $sVal ?>" <?= $l['status'] === $sVal ? 'selected' : '' ?>><?= $sText ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td class="text-end">
                      <div class="dropdown">
                        <button class="btn btn-icon btn-sm" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="font-size: 13px;">
                          <li><a class="dropdown-item py-2" href="view.php?id=<?= $l['id'] ?>"><i class="bi bi-eye me-2"></i> Details</a></li>
                          <li><a class="dropdown-item py-2" href="../outreach/index.php?lead_id=<?= $l['id'] ?>"><i class="bi bi-send me-2"></i> Outreach</a></li>
                          <li><hr class="dropdown-divider my-1"></li>
                          <li>
                            <form method="POST" action="delete.php" onsubmit="return confirm('Delete this lead?');">
                              <input type="hidden" name="id" value="<?= $l['id'] ?>">
                              <button type="submit" class="dropdown-item py-2 text-danger"><i class="bi bi-trash me-2"></i> Delete</button>
                            </form>
                          </li>
                        </ul>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>

    <!-- NEW LEAD MODAL -->
    <div class="modal fade" id="newLeadModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold">Add New Prospect Lead</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST" action="store.php">
            <?= csrf_field() ?>

            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Contact Person</label>
                  <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Company Name</label>
                  <input type="text" name="company" class="form-control" placeholder="e.g. Acme Roofing LLC">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Email Address</label>
                  <input type="email" name="email" class="form-control" placeholder="john@example.com" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Phone Number</label>
                  <input type="text" name="phone" class="form-control" placeholder="+1 (555) 000-0000">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Assign to Campaign</label>
                  <select name="campaign_id" class="form-select">
                    <option value="">-- No Campaign --</option>
                    <?php foreach ($campaigns as $c): ?>
                      <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Assign to Employee</label>
                  <select name="assigned_to" class="form-select">
                    <option value="">-- Unassigned --</option>
                    <?php foreach ($workspaceMembers as $wm): ?>
                      <option value="<?= $wm['id'] ?>"><?= htmlspecialchars($wm['name']) ?> (<?= htmlspecialchars($wm['email']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label small fw-semibold">Notes</label>
                  <textarea name="notes" class="form-control" rows="2" placeholder="Initial contact notes..."></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Lead</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.querySelectorAll('.lead-status-select').forEach(sel => {
  sel.addEventListener('change', function() {
    const leadId = this.dataset.id;
    const newStatus = this.value;
    const origVal = this.dataset.orig || newStatus;
    this.disabled = true;

    const fd = new FormData();
    fd.append('id', leadId);
    fd.append('status', newStatus);
    fd.append('csrf_token', '<?= csrf_token() ?>');

    fetch('update-status.php', { method: 'POST', body: fd })
      .then(res => res.json())
      .then(data => {
        this.disabled = false;
        if (!data.success) {
          alert('Failed to update status.');
          this.value = origVal;
        } else {
          this.dataset.orig = newStatus;
        }
      })
      .catch(() => {
        this.disabled = false;
        this.value = origVal;
      });
  });
});
</script>
