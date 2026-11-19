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

// Fetch workspace creator details
$creatorStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$creatorStmt->execute([$ws['owner_id']]);
$creator = $creatorStmt->fetch(PDO::FETCH_ASSOC);
$creatorName = $creator['name'] ?? 'System / Admin';
$creatorEmail = $creator['email'] ?? '';

// Available users to add/assign
$availUsersStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id NOT IN (SELECT user_id FROM workspace_members WHERE workspace_id = ?) ORDER BY name ASC");
$availUsersStmt->execute([$wsId]);
$availableUsers = $availUsersStmt->fetchAll(PDO::FETCH_ASSOC);

$isCurrent = ((int)$ws['id'] === (int)$currentWorkspaceId);
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
  <style>
    .workspace-view-page .dashboard-card {
      height: auto !important;
      min-height: unset !important;
    }
  </style>
</head>
<body class="workspace-view-page">
<div class="app-shell">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-area">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>
    <main class="content">

      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 py-2 small shadow-sm" role="alert">
          <i class="bi bi-check-circle me-1"></i>
          <?php if ($_GET['success'] === 'updated') echo "Workspace details updated successfully.";
                elseif ($_GET['success'] === 'member_added') echo "Member invited/added to workspace.";
                elseif ($_GET['success'] === 'member_removed') echo "Member removed from workspace.";
                elseif ($_GET['success'] === 'role_updated') echo "Member role updated successfully.";
                else echo "Action completed successfully."; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 py-2 small shadow-sm" role="alert">
          <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($_GET['error']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- HEADER WITH BACK & ACTIONS -->
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
          <a href="index.php" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i> Back to Workspaces
          </a>
          <h2 class="h4 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
            <?= htmlspecialchars($ws['name']) ?>
            <span class="badge bg-light text-dark border small fw-normal"><?= ucfirst($userWsRole) ?></span>
            <?php if ($isCurrent): ?>
              <span class="badge bg-success-subtle text-success border border-success-subtle small fw-normal">Active</span>
            <?php endif; ?>
          </h2>
        </div>
        <div>
          <?php if (!$isCurrent): ?>
            <form method="POST" action="switch.php" class="m-0">
              <?= csrf_field() ?>
              <input type="hidden" name="workspace_id" value="<?= $ws['id'] ?>">
              <button type="submit" class="btn btn-primary btn-sm shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Switch to this Workspace
              </button>
            </form>
          <?php else: ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
              <i class="bi bi-check2-circle me-1"></i> Current Active Workspace
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- MAIN CONTENT GRID -->
      <div class="row g-4 mb-4">
        <!-- LEFT COLUMN: DETAILS & USAGE -->
        <div class="col-12 col-lg-5">
          <!-- WORKSPACE DETAILS CARD -->
          <div class="dashboard-card p-4 mb-4 shadow-sm">
            <h3 class="h6 fw-bold mb-3 border-bottom pb-2">
              <i class="bi bi-info-circle text-primary me-2"></i> Workspace Details
            </h3>
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

              <div class="bg-light p-3 rounded mb-3 small">
                <div class="d-flex justify-content-between mb-1 text-muted">
                  <span>Created By:</span>
                  <strong class="text-dark"><?= htmlspecialchars($creatorName) ?><?php if ($creatorEmail): ?> (<?= htmlspecialchars($creatorEmail) ?>)<?php endif; ?></strong>
                </div>
                <div class="d-flex justify-content-between text-muted">
                  <span>Created On:</span>
                  <span class="text-dark"><?= date('M d, Y', strtotime($ws['created_at'])) ?></span>
                </div>
              </div>

              <?php if ($canManage): ?>
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class="bi bi-check2 me-1"></i> Save Changes
                </button>
              <?php endif; ?>
            </form>
          </div>

          <!-- RESOURCE USAGE CARD -->
          <div class="dashboard-card p-4 shadow-sm">
            <h3 class="h6 fw-bold mb-3 border-bottom pb-2">
              <i class="bi bi-speedometer2 text-primary me-2"></i> Resource Usage
            </h3>
            <div class="mb-3">
              <div class="d-flex justify-content-between mb-1 small">
                <span class="text-muted">Leads Stored</span>
                <strong><?= number_format($stats['leads_used']) ?> / <?= number_format($stats['leads_limit']) ?></strong>
              </div>
              <?php $leadPct = $stats['leads_limit'] > 0 ? min(100, round(($stats['leads_used'] / $stats['leads_limit']) * 100)) : 0; ?>
              <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $leadPct ?>%;"></div>
              </div>
            </div>

            <div class="mb-3">
              <div class="d-flex justify-content-between mb-1 small">
                <span class="text-muted">Campaigns</span>
                <strong><?= number_format($stats['campaigns_used']) ?> / <?= number_format($stats['campaigns_limit']) ?></strong>
              </div>
              <?php $campPct = $stats['campaigns_limit'] > 0 ? min(100, round(($stats['campaigns_used'] / $stats['campaigns_limit']) * 100)) : 0; ?>
              <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-info" role="progressbar" style="width: <?= $campPct ?>%;"></div>
              </div>
            </div>

            <div>
              <div class="d-flex justify-content-between mb-1 small">
                <span class="text-muted">Members</span>
                <strong><?= number_format($stats['users_used']) ?> / <?= number_format($stats['users_limit']) ?></strong>
              </div>
              <?php $userPct = $stats['users_limit'] > 0 ? min(100, round(($stats['users_used'] / $stats['users_limit']) * 100)) : 0; ?>
              <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $userPct ?>%;"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: MEMBERS CARD -->
        <div class="col-12 col-lg-7">
          <div class="dashboard-card p-4 shadow-sm h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 border-bottom pb-2">
              <h3 class="h6 fw-bold mb-0">
                <i class="bi bi-people text-primary me-2"></i> Members (<?= count($members) ?>)
              </h3>
              <?php if ($canManageMembers): ?>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#batchAddMembersModal">
                    <i class="bi bi-people me-1"></i> Batch Assign
                  </button>
                  <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Member
                  </button>
                </div>
              <?php endif; ?>
            </div>

            <div class="table-responsive">
              <table class="table align-middle table-hover mb-0 small">
                <thead class="table-light">
                  <tr>
                    <th>Member</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($members as $m): ?>
                    <tr>
                      <td>
                        <strong><?= htmlspecialchars($m['name']) ?></strong>
                        <?php if ((int)$m['user_id'] === (int)$ws['owner_id']): ?>
                          <span class="badge bg-warning-subtle text-dark border border-warning-subtle ms-1" style="font-size:0.7rem;">Creator</span>
                        <?php endif; ?>
                        <br>
                        <small class="text-muted"><?= htmlspecialchars($m['email']) ?></small>
                      </td>
                      <td>
                        <?php if ($canManageMembers && (int)$m['user_id'] !== $userId && (int)$m['user_id'] !== (int)$ws['owner_id']): ?>
                          <form method="POST" action="member_role.php" class="m-0">
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
                        <?php else: ?>
                          <span class="badge bg-light text-dark border"><?= ucfirst($m['role']) ?></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="badge <?= $m['status']==='active' ? 'bg-success' : 'bg-secondary' ?>">
                          <?= ucfirst($m['status']) ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <?php if ($canManageMembers && (int)$m['user_id'] !== $userId && (int)$m['user_id'] !== (int)$ws['owner_id']): ?>
                          <form method="POST" action="member_remove.php" class="m-0 d-inline" onsubmit="return confirm('Remove user from this workspace?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="workspace_id" value="<?= $wsId ?>">
                            <input type="hidden" name="user_id" value="<?= $m['user_id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm p-1" title="Remove member">
                              <i class="bi bi-trash3"></i>
                            </button>
                          </form>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>

    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>

<!-- MODAL: ADD MEMBER -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Add Workspace Member</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="member_add.php">
        <?= csrf_field() ?>
        <input type="hidden" name="workspace_id" value="<?= $wsId ?>">
        <div class="modal-body">
          <?php if (!empty($availableUsers)): ?>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Select Existing System User</label>
              <select name="user_id" class="form-select" id="existingUserSelect" onchange="autoFillMemberDetails(this)">
                <option value="">— Select an existing user —</option>
                <?php foreach ($availableUsers as $au): ?>
                  <option value="<?= $au['id'] ?>" data-name="<?= htmlspecialchars($au['name']) ?>" data-email="<?= htmlspecialchars($au['email']) ?>">
                    <?= htmlspecialchars($au['name']) ?> (<?= htmlspecialchars($au['email']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="text-center my-2 text-muted small fw-semibold">— OR INVITE BY EMAIL —</div>
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Email Address</label>
            <input type="email" id="memberEmailInput" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Name</label>
            <input type="text" id="memberNameInput" name="name" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Role</label>
            <select name="role" class="form-select">
              <option value="member" selected>Member</option>
              <option value="manager">Manager</option>
              <option value="admin">Administrator</option>
              <option value="viewer">Viewer</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Add Member</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: BATCH ADD MEMBERS -->
<div class="modal fade" id="batchAddMembersModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Batch Assign Multiple Users</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="member_batch_add.php">
        <?= csrf_field() ?>
        <input type="hidden" name="workspace_id" value="<?= $wsId ?>">
        <div class="modal-body">
          <?php if (!empty($availableUsers)): ?>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Select Multiple Users to Assign</label>
              <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                <?php foreach ($availableUsers as $au): ?>
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="user_ids[]" value="<?= $au['id'] ?>" id="au_<?= $au['id'] ?>">
                    <label class="form-check-label small" for="au_<?= $au['id'] ?>">
                      <strong><?= htmlspecialchars($au['name']) ?></strong> (<?= htmlspecialchars($au['email']) ?>)
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Assigned Role for Selected Users</label>
              <select name="role" class="form-select">
                <option value="member" selected>Member</option>
                <option value="manager">Manager</option>
                <option value="admin">Administrator</option>
                <option value="viewer">Viewer</option>
              </select>
            </div>
          <?php else: ?>
            <p class="text-muted small mb-0">No other registered users available to assign.</p>
          <?php endif; ?>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <?php if (!empty($availableUsers)): ?>
            <button type="submit" class="btn btn-primary btn-sm">Assign Selected Users</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function autoFillMemberDetails(select) {
  const opt = select.options[select.selectedIndex];
  if (opt && opt.value) {
    document.getElementById('memberEmailInput').value = opt.getAttribute('data-email') || '';
    document.getElementById('memberNameInput').value  = opt.getAttribute('data-name')  || '';
  }
}
</script>

</body>
</html>