<?php
$pageEyebrow = $pageEyebrow ?? 'Workspace';
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<header class="topbar">
  <div class="topbar-left">
    <button class="btn btn-icon mobile-menu-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open navigation">
      <i class="bi bi-list"></i>
    </button>

    <button class="desktop-sidebar-toggle btn btn-icon d-none d-lg-inline-flex" type="button" aria-label="Collapse sidebar">
      <i class="bi bi-layout-sidebar-inset"></i>
    </button>

    <div class="page-heading">
      <div class="eyebrow"><?= htmlspecialchars($pageEyebrow) ?></div>
      <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
    </div>
  </div>

  <div class="topbar-actions">
    <?php if (isset($headerActionHtml)) echo $headerActionHtml; ?>

    <div class="dropdown">
      <button class="btn btn-icon notification-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
        <i class="bi bi-bell"></i>
        <?php if (!empty($pendingTasksCount) && $pendingTasksCount > 0): ?>
          <span class="notification-dot"><?= (int)$pendingTasksCount ?></span>
        <?php endif; ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="width: 290px; font-size: 13px;">
        <li class="dropdown-header fw-bold text-dark px-2">Notifications</li>
        <?php if (!empty($pendingTasksCount)): ?>
          <li>
            <a class="dropdown-item rounded p-2 d-flex align-items-center gap-2" href="<?= $appRoot ?>/tasks/index.php">
              <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i></span>
              <div>
                <div class="fw-semibold"><?= (int)$pendingTasksCount ?> Pending Tasks</div>
                <small class="text-muted">Follow-ups scheduled today</small>
              </div>
            </a>
          </li>
        <?php endif; ?>
        <?php if (!empty($unreadConvCount)): ?>
          <li>
            <a class="dropdown-item rounded p-2 d-flex align-items-center gap-2" href="<?= $appRoot ?>/conversations/index.php">
              <span class="badge bg-primary"><i class="bi bi-chat-dots"></i></span>
              <div>
                <div class="fw-semibold"><?= (int)$unreadConvCount ?> Unread Replies</div>
                <small class="text-muted">Leads waiting for response</small>
              </div>
            </a>
          </li>
        <?php endif; ?>
        <?php if (empty($pendingTasksCount) && empty($unreadConvCount)): ?>
          <li class="px-2 py-3 text-center text-muted"><small>All caught up! No alerts.</small></li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="workspace-switcher dropdown">
      <button class="workspace-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="workspace-icon"><i class="bi bi-building"></i></span>
        <span class="workspace-name text-truncate" style="max-width: 140px;"><?= htmlspecialchars($currentWorkspace['name'] ?? 'Workspace') ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width: 240px; font-size: 13px;">
        <li><h6 class="dropdown-header text-uppercase text-muted fw-bold" style="font-size: 11px;">Switch Workspace</h6></li>
        <?php foreach (($userWorkspaces ?? []) as $wsItem): ?>
          <?php $isWsActive = ((int)$wsItem['id'] === (int)$currentWorkspaceId); ?>
          <li>
            <form method="POST" action="<?= $appRoot ?>/workspaces/switch.php" class="m-0 p-0">
              <?= csrf_field() ?>
              <input type="hidden" name="workspace_id" value="<?= (int)$wsItem['id'] ?>">
              <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">
              <button type="submit" class="dropdown-item d-flex align-items-center justify-content-between py-2 <?= $isWsActive ? 'active' : '' ?>">
                <div class="text-truncate me-2">
                  <div class="fw-semibold text-truncate"><?= htmlspecialchars($wsItem['name']) ?></div>
                  <small class="<?= $isWsActive ? 'text-white-50' : 'text-muted' ?>"><?= ucfirst($wsItem['member_role'] ?? 'member') ?></small>
                </div>
                <?php if ($isWsActive): ?>
                  <i class="bi bi-check2 text-white"></i>
                <?php endif; ?>
              </button>
            </form>
          </li>
        <?php endforeach; ?>
        <li><hr class="dropdown-divider my-1"></li>
        <li><a class="dropdown-item py-2" href="<?= $appRoot ?>/workspaces/index.php"><i class="bi bi-kanban me-2"></i> Manage Workspaces</a></li>
        <li><a class="dropdown-item py-2" href="<?= $appRoot ?>/workspaces/create.php"><i class="bi bi-plus-circle me-2"></i> Create New Workspace</a></li>
      </ul>
    </div>

    <div class="dropdown">
      <button class="topbar-user-pill dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <?php if (!empty($userPicUrl)): ?>
          <img src="<?= htmlspecialchars($userPicUrl) ?>" alt="Avatar" class="topbar-user-avatar" onerror="this.onerror=null; this.outerHTML='<span class=\'topbar-user-initials\'>' + '<?= htmlspecialchars($initials) ?>' + '</span>';">
        <?php else: ?>
          <span class="topbar-user-initials"><?= htmlspecialchars($initials) ?></span>
        <?php endif; ?>
        <span class="d-none d-md-inline text-truncate" style="max-width: 120px;"><?= htmlspecialchars($userName) ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="min-width: 210px; font-size: 13px;">
        <li class="px-2 py-1 border-bottom mb-1">
          <div class="fw-semibold text-truncate"><?= htmlspecialchars($userName) ?></div>
          <small class="text-muted text-truncate d-block"><?= htmlspecialchars($userEmail) ?></small>
        </li>
        <li><a class="dropdown-item rounded py-1" href="<?= $appRoot ?>/profile/index.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
        <li><a class="dropdown-item rounded py-1" href="<?= $appRoot ?>/settings/index.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
        <li><hr class="dropdown-divider my-1"></li>
        <li><a class="dropdown-item rounded py-1 text-danger" href="<?= $appRoot ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</header>
