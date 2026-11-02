<?php
require_once __DIR__ . '/../includes/auth_check.php';
$pageEyebrow = 'Management';
$pageTitle = 'Workspaces';
$activePage = 'workspaces';

$workspaces = $workspaceService->getUserWorkspaces($userId);
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Workspaces — Primovative Co.</title>
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
      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 py-2 small" role="alert">
          <i class="bi bi-check-circle me-1"></i>
          <?php if ($_GET['success'] === 'created') echo "Workspace successfully created!";
                elseif ($_GET['success'] === 'updated') echo "Workspace details updated successfully.";
                elseif ($_GET['success'] === 'member_added') echo "Member invited/added to workspace.";
                elseif ($_GET['success'] === 'member_removed') echo "Member removed from workspace.";
                else echo "Action completed successfully."; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 py-2 small" role="alert">
          <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($_GET['error']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
          <h2 class="h5 fw-bold mb-1">Your Workspaces (<?= count($workspaces) ?>)</h2>
          <p class="text-muted small mb-0">Switch between isolated business environments or manage workspace team access.</p>
        </div>
        <a href="create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Create Workspace</a>
      </div>

      <div class="row g-4">
        <?php foreach ($workspaces as $w): ?>
          <?php
            $isCurrent = ((int)$w['id'] === (int)$currentWorkspaceId);
            $wSub = $billingService->getWorkspaceSubscription((int)$w['id']);
            $wPlanName = $wSub['plan_name'] ?? 'Starter';
            $wStatus = $wSub['status'] ?? 'trial';
          ?>
          <div class="col-12 col-md-6 col-xl-4">
            <div class="dashboard-card p-4 h-100 d-flex flex-column justify-content-between <?= $isCurrent ? 'border-primary border-2 shadow-sm' : '' ?>">
              <div>
                <div class="d-flex justify-content-between align-items-start mb-3">
                  <div class="d-flex align-items-center gap-2">
                    <div class="avatar bg-primary text-white" style="width: 40px; height: 40px; font-weight: 700;">
                      <?= strtoupper(substr($w['name'], 0, 2)) ?>
                    </div>
                    <div>
                      <h3 class="h6 fw-bold mb-0 text-dark"><?= htmlspecialchars($w['name']) ?></h3>
                      <small class="text-muted">Role: <span class="badge bg-light text-dark border"><?= ucfirst($w['member_role']) ?></span></small>
                    </div>
                  </div>
                  <?php if ($isCurrent): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                  <?php endif; ?>
                </div>

                <p class="text-secondary small mb-3"><?= htmlspecialchars($w['description'] ?: 'Standard business workspace.') ?></p>

                <div class="border-top pt-3 mb-3 small">
                  <div class="d-flex justify-content-between mb-1 text-muted">
                    <span>Plan:</span>
                    <strong class="text-dark"><?= htmlspecialchars($wPlanName) ?> (<?= ucfirst($wStatus) ?>)</strong>
                  </div>
                  <div class="d-flex justify-content-between mb-1 text-muted">
                    <span>Members:</span>
                    <strong class="text-dark"><?= (int)$w['member_count'] ?> active</strong>
                  </div>
                  <div class="d-flex justify-content-between text-muted">
                    <span>Created:</span>
                    <span><?= date('M d, Y', strtotime($w['created_at'])) ?></span>
                  </div>
                </div>
              </div>

              <div class="d-flex gap-2 pt-2 border-top">
                <?php if (!$isCurrent): ?>
                  <form method="POST" action="switch.php" class="flex-grow-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="workspace_id" value="<?= (int)$w['id'] ?>">
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-box-arrow-in-right me-1"></i> Switch</button>
                  </form>
                <?php else: ?>
                  <span class="btn btn-light btn-sm text-success w-50 disabled"><i class="bi bi-check2 me-1"></i> Current</span>
                <?php endif; ?>
                <a href="view.php?id=<?= (int)$w['id'] ?>" class="btn btn-outline-secondary btn-sm flex-grow-1"><i class="bi bi-gear me-1"></i> Details</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
</body>
</html>
