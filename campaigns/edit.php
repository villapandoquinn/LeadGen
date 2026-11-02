<?php
require_once __DIR__ . '/../includes/auth_check.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ? AND workspace_id = ?");
$stmt->execute([$id, $currentWorkspaceId]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$campaign) { header('Location: index.php?error=not_found'); exit; }

if (!$workspaceService->hasPermission($workspaceRole, 'manage_campaigns')) {
    header('Location: view.php?id=' . $id . '&error=unauthorized');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');
    $leadLimit = max(1, (int)($_POST['lead_limit'] ?? 100));
    $status = $_POST['status'] ?? 'active';
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error = 'Campaign name is required.';
    } else {
        $uStmt = $pdo->prepare("UPDATE campaigns SET name = ?, industry = ?, location = ?, keywords = ?, lead_limit = ?, status = ?, description = ? WHERE id = ? AND workspace_id = ?");
        $uStmt->execute([$name, $industry, $location, $keywords, $leadLimit, $status, $description, $id, $currentWorkspaceId]);
        header("Location: view.php?id={$id}&success=updated");
        exit;
    }
}

$pageEyebrow = 'Campaigns';
$pageTitle = 'Edit ' . $campaign['name'];
$activePage = 'campaigns';
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Campaign — Primovative Co.</title>
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
      <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
          <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-arrow-left me-1"></i> Cancel</a>
          <h2 class="h4 fw-bold mb-0">Edit Campaign</h2>
        </div>
      </div>

      <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
          <div class="dashboard-card p-4">
            <?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST" action="edit.php?id=<?= $id ?>">
              <?= csrf_field() ?>

              <input type="hidden" name="id" value="<?= $id ?>">
              <div class="mb-3">
                <label class="form-label fw-semibold small">Campaign Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($campaign['name']) ?>" required>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold small">Industry</label>
                  <select name="industry" class="form-select">
                    <?php foreach (['Roofing', 'Solar Installation', 'Dentist & Orthodontist', 'Landscaping', 'Construction', 'Real Estate', 'Restaurants', 'Healthcare', 'Software', 'Other'] as $ind): ?>
                      <option value="<?= $ind ?>" <?= $campaign['industry'] === $ind ? 'selected' : '' ?>><?= $ind ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold small">Status</label>
                  <select name="status" class="form-select">
                    <?php foreach (['active' => 'Active', 'paused' => 'Paused', 'draft' => 'Draft', 'completed' => 'Completed'] as $st => $stLabel): ?>
                      <option value="<?= $st ?>" <?= $campaign['status'] === $st ? 'selected' : '' ?>><?= $stLabel ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold small">Location</label>
                  <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($campaign['location'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold small">Lead Target</label>
                  <input type="number" name="lead_limit" class="form-control" value="<?= (int)($campaign['lead_limit'] ?: 100) ?>" min="1">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold small">Keywords</label>
                <input type="text" name="keywords" class="form-control" value="<?= htmlspecialchars($campaign['keywords'] ?? '') ?>">
              </div>
              <div class="mb-4">
                <label class="form-label fw-semibold small">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($campaign['description'] ?? '') ?></textarea>
              </div>
              <div class="d-flex justify-content-end gap-2">
                <a href="view.php?id=<?= $id ?>" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
