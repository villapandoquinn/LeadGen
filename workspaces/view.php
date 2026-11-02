<?php
require_once __DIR__ . '/../includes/auth_check.php';

$wsId = (int)($_GET['id'] ?? 0);
if ($wsId <= 0) { header('Location: index.php'); exit; }

$mCheck = $pdo->prepare("SELECT role, status FROM workspace_members WHERE workspace_id = ? AND user_id = ? LIMIT 1");
$mCheck->execute([$wsId, $userId]);
$userMember = $mCheck->fetch(PDO::FETCH_ASSOC);

if (!$userMember || $userMember['status'] !== 'active') {
    header('Location: index.php?error=Access+denied');
    exit;
}

$wsStmt = $pdo->prepare("SELECT * FROM workspaces WHERE id = ?");
$wsStmt->execute([$wsId]);
$ws = $wsStmt->fetch(PDO::FETCH_ASSOC);
if (!$ws) { header('Location: index.php?error=Workspace+not+found'); exit; }

$userWsRole = $userMember['role'];
$canManage = $workspaceService->hasPermission($userWsRole, 'manage_workspace');
$canManageMembers = $workspaceService->hasPermission($userWsRole, 'manage_members');

$members = $workspaceService->getMembers($wsId);
$stats = $billingService->getUsageStats($wsId);

$pageEyebrow = 'Workspaces';
$pageTitle = $ws['name'];
$activePage = 'workspaces';
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($ws['name']) ?> — Primovative Co.</title>
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
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <a href="index.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-arrow-left me-1"></i> Back</a>
          <h2 class="h4 fw-bold mb-0"><?= htmlspecialchars($ws['name']) ?> <span class="badge bg-light text-dark border small"><?= ucfirst($userWsRole) ?></span></h2>
        </div>
        <div>
          <?php if ((int)$ws['id'] !== (int)$currentWorkspaceId): ?>
            <form method="POST" action="switch.php">
      <div class="row g-4">
        <div class="col-12 col-lg-5">
          <div class="dashboard-card p-4 mb-4">
            <h3 class="h6 fw-bold mb-3">Workspace Details</h3>
            <form method="POST" action="update.php">
              <?= csrf_field() ?>
              <input type="hidden" name="workspace_id" value="<?= $ws['id'] ?>">
              <div class="mb-3">
                <label class="form-label small fw-semibold">Workspace Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($ws['name']) ?>" <?= $canManage ? 'required' : 'readonly' ?>>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3" <?= $canManage ? '' : 'readonly' ?>><?= htmlspecialchars($ws['description'] ?? '') ?></textarea>
              </div>
              <?php if ($canManage): ?>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2 me-1"></i> Save Changes</button>
              <?php endif; ?>
            </form>
          </div>
          <div class="dashboard-card p-4">
            <h3 class="h6 fw-bold mb-3">Resource Usage</h3>
            <div class="mb-2 small d-flex justify-content-between"><span>Leads</span><strong><?= $stats['leads_used'] ?> / <?= $stats['leads_limit'] ?></strong></div>
            <div class="mb-2 small d-flex justify-content-between"><span>Campaigns</span><strong><?= $stats['campaigns_used'] ?> / <?= $stats['campaigns_limit'] ?></strong></div>
            <div class="small d-flex justify-content-between"><span>Members</span><strong><?= $stats['users_used'] ?> / <?= $stats['users_limit'] ?></strong></div>
          </div>
        </div>
        <div class="col-12 col-lg-7">
          <div class="dashboard-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h3 class="h6 fw-bold mb-0">Members (<?= count($members) ?>)</h3>
              <?php if ($canManageMembers): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal"><i class="bi bi-plus-lg me-1"></i> Add</button>
              <?php endif; ?>
            </div>
            <div class="table-responsive">
              <table class="table align-middle table-hover mb-0">
                <thead class="table-light"><tr><th>Member</th><th>Role</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                  <?php foreach ($members as $m): ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($m['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($m['email']) ?></small></td>
                      <td>
                        <?php if ($canManageMembers && (int)$m['user_id'] !== $userId): ?>
                          <form method="POST" action="member_role.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="workspace_id" value="<?= $wsId ?>">
                            <input type="hidden" name="user_id" value="<?= $m['user_id'] ?>">
                            <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                              <option value="owner" <?= $m['role']==='owner'?'selected':'' ?>>Owner</option>
                              <option value="admin" <?= $m['role']==='admin'?'selected':'' ?>>Admin</option>
                              <option value="manager" <?= $m['role']==='manager'?'selected':'' ?>>Manager</option>
                              <option value="member" <?= $m['role']==='member'?'selected':'' ?>>Member</option>
                              <option value="viewer" <?= $m['role']==='viewer'?'selected':'' ?>>Viewer</option>
                            </select>
                          </form>
                        <?php else: ?><span class="badge bg-light text-dark border"><?= ucfirst($m['role']) ?></span><?php endif; ?>
                      </td>
                      <td><span class="badge <?= $m['status']==='active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($m['status']) ?></span></td>
                      <td class="text-end">
                        <?php if ($canManageMembers && (int)$m['user_id'] !== $userId): ?>
                          <form method="POST" action="member_remove.php" onsubmit="return confirm('Remove user?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="workspace_id" value="<?= $wsId ?>">
                            <input type="hidden" name="user_id" value="<?= $m['user_id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm p-1"><i class="bi bi-trash3"></i></button>
                          </form>
                        <?php else: ?>—<?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
<div class="modal fade" id="addMemberModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Add Workspace Member</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" action="member_add.php">
        <?= csrf_field() ?>
        <input type="hidden" name="workspace_id" value="<?= $wsId ?>">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label small fw-semibold">Email</label><input type="email" name="email" class="form-control" required></div>
          <div class="mb-3"><label class="form-label small fw-semibold">Name</label><input type="text" name="name" class="form-control"></div>
          <div class="mb-3"><label class="form-label small fw-semibold">Role</label>
            <select name="role" class="form-select">
              <option value="member" selected>Member</option>
              <option value="manager">Manager</option>
              <option value="admin">Administrator</option>
              <option value="viewer">Viewer</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0"><button type="submit" class="btn btn-primary">Add Member</button></div>
      </form>
    </div>
  </div>
</div>
</body>
</html>

                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

              <?= csrf_field() ?>
              <input type="hidden" name="workspace_id" value="<?= $ws['id'] ?>">
              <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i> Switch Workspace</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
