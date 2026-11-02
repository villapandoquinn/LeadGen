<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageEyebrow = 'Workspaces';
$pageTitle = 'Create Workspace';
$activePage = 'workspaces';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $error = 'Workspace name is required.';
    } else {
        try {
            $newId = $workspaceService->createWorkspace($userId, $name, $description);
            $_SESSION['current_workspace_id'] = $newId;
            header('Location: index.php?success=created');
            exit;
        } catch (Throwable $e) {
            $error = 'Failed to create workspace: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Workspace — Primovative Co.</title>
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
        <div class="col-12 col-md-8 col-xl-6">
          <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
              <a href="index.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-arrow-left me-1"></i> Back to Workspaces</a>
              <h2 class="h4 fw-bold mb-0">Create New Workspace</h2>
            </div>
          </div>

          <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small mb-4"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <div class="dashboard-card p-4">
            <form method="POST" action="create.php">
              <?= csrf_field() ?>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Workspace Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Acme Enterprise, European Branch" required autofocus>
                <div class="form-text small">This will be the title displayed on your switcher and reports.</div>
              </div>

              <div class="mb-4">
                <label class="form-label small fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Brief summary of this business workspace or client campaign."></textarea>
              </div>

              <div class="alert bg-light border p-3 small text-muted mb-4">
                <i class="bi bi-info-circle me-1 text-primary"></i> Workspaces maintain strict data isolation. Discovered leads, campaigns, outreach sequences, and tasks will be isolated within this workspace.
              </div>

              <div class="d-flex justify-content-end gap-2">
                <a href="index.php" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Create Workspace</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
</body>
</html>
