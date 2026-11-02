<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageEyebrow = 'Management';
$pageTitle = 'Users & Team';
$activePage = 'users';

$members = $workspaceService->getMembers($currentWorkspaceId);
$canManageMembers = $workspaceService->hasPermission($workspaceRole, 'manage_members');
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Users &amp; Team — Primovative Co.</title>
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
          <i class="bi bi-check-circle me-1"></i> Member action completed successfully.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 py-2 small" role="alert">
          <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($_GET['error']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="dashboard-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h2 class="h5 fw-bold mb-0"><?= htmlspecialchars($currentWorkspace['name']) ?> Members (<?= count($members) ?>)</h2>
            <small class="text-muted">Manage workspace access, assign roles and invite teammates</small>
          </div>
          <?php if ($canManageMembers): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inviteUserModal">
              <i class="bi bi-person-plus me-1"></i> Add Member
            </button>
          <?php endif; ?>
        </div>


        <div class="table-responsive">
          <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>User</th>
                <th>Authentication</th>
                <th>Workspace Role</th>
                <th>Status</th>
                <th>Joined</th>
                <?php if ($canManageMembers): ?><th class="text-end">Actions</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($members as $u): ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="avatar" style="width:32px;height:32px;font-size:12px;"><?= strtoupper(substr($u['name'], 0, 2)) ?></div>
                      <div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($u['name']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($u['email']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border"><?= ucfirst($u['auth_provider'] ?? 'email') ?></span>
                  </td>
                  <td>
                    <?php if ($canManageMembers && (int)$u['user_id'] !== $userId): ?>
                      <form method="POST" action="../workspaces/member_role.php" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="workspace_id" value="<?= $currentWorkspaceId ?>">
                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                        <select name="role" class="form-select form-select-sm" style="width: auto; display: inline-block;" onchange="this.form.submit()">
                          <option value="owner" <?= $u['role']==='owner'?'selected':'' ?>>Owner</option>
                          <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Administrator</option>
                          <option value="manager" <?= $u['role']==='manager'?'selected':'' ?>>Manager</option>
                          <option value="member" <?= $u['role']==='member'?'selected':'' ?>>Member</option>
                          <option value="viewer" <?= $u['role']==='viewer'?'selected':'' ?>>Viewer</option>
                        </select>
                      </form>
                    <?php else: ?>
                      <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= ucfirst($u['role']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge <?= $u['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($u['status']) ?></span></td>
                  <td><small class="text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></small></td>
                  <?php if ($canManageMembers): ?>
                    <td class="text-end">
                      <?php if ((int)$u['user_id'] !== $userId): ?>
                        <form method="POST" action="../workspaces/member_remove.php" class="d-inline" onsubmit="return confirm('Remove user from this workspace?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="workspace_id" value="<?= $currentWorkspaceId ?>">
                          <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                          <button type="submit" class="btn btn-outline-danger btn-sm p-1 px-2" title="Remove Member"><i class="bi bi-trash3"></i></button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted small">You</span>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>

    <!-- INVITE MODAL -->
    <div class="modal fade" id="inviteUserModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold">Add Workspace Member</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST" action="store.php">
            <?= csrf_field() ?>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label small fw-semibold">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="colleague@company.com" required>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Full Name (optional)</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Jordan Lee">
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Workspace Role</label>
                <select name="role" class="form-select">
                  <option value="member" selected>Member (Manage campaigns & leads)</option>
                  <option value="manager">Manager (Can edit records)</option>
                  <option value="admin">Administrator (Workspace admin)</option>
                  <option value="viewer">Viewer (Read-only)</option>
                </select>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Send Invite</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
