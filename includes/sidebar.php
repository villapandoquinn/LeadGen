<?php
$activePage = $activePage ?? 'dashboard';
?>
<aside id="mobileSidebar" class="sidebar offcanvas offcanvas-start" tabindex="-1" aria-labelledby="sidebarLabel" data-bs-scroll="true" data-bs-backdrop="true">
  <div class="sidebar-inner">
    <!-- BRAND -->
    <div class="sidebar-brand">
      <a href="<?= $appRoot ?>/dashboard.php" class="brand-link" aria-label="Primovative Co. home">
        <span class="brand-mark" aria-hidden="true">
          <span></span><span></span><span></span>
        </span>
        <span class="brand-text">
          <strong id="sidebarLabel">Primovative</strong>
          <small>Technology &amp; Innovation</small>
        </span>
      </a>
      <button type="button" class="btn btn-icon sidebar-close d-lg-none" data-bs-dismiss="offcanvas" aria-label="Close navigation">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <!-- NAVIGATION -->
    <nav class="sidebar-nav" aria-label="Primary navigation">
      <div class="nav-section-label">Workspace</div>

      <!-- WORKSPACE QUICK SWITCHER IN SIDEBAR -->
      <div class="px-3 mb-2">
        <div class="dropdown w-100">
          <button class="btn btn-sm btn-outline-light text-start w-100 d-flex align-items-center justify-content-between py-2 border-secondary-subtle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.06); border-radius: 8px;">
            <div class="d-flex align-items-center gap-2 text-truncate me-1">
              <i class="bi bi-building text-primary"></i>
              <span class="text-truncate small fw-semibold text-white"><?= htmlspecialchars($currentWorkspace['name'] ?? 'Workspace') ?></span>
            </div>
            <i class="bi bi-chevron-expand small text-secondary"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-dark shadow border-0 p-1" style="min-width: 230px; font-size: 13px;">
            <li><h6 class="dropdown-header text-uppercase text-muted" style="font-size: 10px;">Your Workspaces</h6></li>
            <?php foreach (($userWorkspaces ?? []) as $wItem): ?>
              <?php $isAct = ((int)$wItem['id'] === (int)$currentWorkspaceId); ?>
              <li>
                <form method="POST" action="<?= $appRoot ?>/workspaces/switch.php" class="m-0 p-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="workspace_id" value="<?= (int)$wItem['id'] ?>">
                  <button type="submit" class="dropdown-item rounded d-flex align-items-center justify-content-between py-2 <?= $isAct ? 'active' : '' ?>">
                    <span class="text-truncate"><?= htmlspecialchars($wItem['name']) ?></span>
                    <?php if ($isAct): ?><i class="bi bi-check2"></i><?php endif; ?>
                  </button>
                </form>
              </li>
            <?php endforeach; ?>
            <li><hr class="dropdown-divider my-1"></li>
            <li><a class="dropdown-item rounded py-1.5" href="<?= $appRoot ?>/workspaces/index.php"><i class="bi bi-kanban me-2"></i> All Workspaces</a></li>
            <li><a class="dropdown-item rounded py-1.5" href="<?= $appRoot ?>/workspaces/create.php"><i class="bi bi-plus-lg me-2"></i> New Workspace</a></li>
          </ul>
        </div>
      </div>

      <a class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= $appRoot ?>/dashboard.php">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>

      <a class="nav-item <?= $activePage === 'campaigns' ? 'active' : '' ?>" href="<?= $appRoot ?>/campaigns/index.php">
        <i class="bi bi-megaphone"></i>
        <span>Campaigns</span>
      </a>

      <a class="nav-item <?= $activePage === 'lead-discovery' ? 'active' : '' ?>" href="<?= $appRoot ?>/lead-discovery/index.php">
        <i class="bi bi-search"></i>
        <span>Lead Discovery</span>
      </a>

      <a class="nav-item <?= $activePage === 'leads' ? 'active' : '' ?>" href="<?= $appRoot ?>/leads/index.php">
        <i class="bi bi-person"></i>
        <span>Leads</span>
      </a>

      <a class="nav-item <?= $activePage === 'outreach' ? 'active' : '' ?>" href="<?= $appRoot ?>/outreach/index.php">
        <i class="bi bi-send"></i>
        <span>Outreach</span>
      </a>

      <a class="nav-item <?= $activePage === 'conversations' ? 'active' : '' ?>" href="<?= $appRoot ?>/conversations/index.php">
        <i class="bi bi-chat-square-dots"></i>
        <span>Conversations</span>
        <?php if (!empty($unreadConvCount) && $unreadConvCount > 0): ?>
          <span class="nav-badge" style="background:#2563eb;"><?= (int)$unreadConvCount ?></span>
        <?php endif; ?>
      </a>

      <div class="nav-section-label nav-section-spacer">Manage</div>

      <a class="nav-item <?= $activePage === 'analytics' ? 'active' : '' ?>" href="<?= $appRoot ?>/analytics/index.php">
        <i class="bi bi-bar-chart"></i>
        <span>Analytics</span>
      </a>

      <a class="nav-item <?= $activePage === 'tasks' ? 'active' : '' ?>" href="<?= $appRoot ?>/tasks/index.php">
        <i class="bi bi-check2-square"></i>
        <span>Tasks</span>
        <?php if (!empty($pendingTasksCount) && $pendingTasksCount > 0): ?>
          <span class="nav-badge"><?= (int)$pendingTasksCount ?></span>
        <?php endif; ?>
      </a>

      <a class="nav-item <?= $activePage === 'settings' ? 'active' : '' ?>" href="<?= $appRoot ?>/settings/index.php">
        <i class="bi bi-gear"></i>
        <span>Settings</span>
      </a>

      <a class="nav-item <?= $activePage === 'workspaces' ? 'active' : '' ?>" href="<?= $appRoot ?>/workspaces/index.php">
        <i class="bi bi-kanban"></i>
        <span>Workspaces</span>
      </a>

      <a class="nav-item <?= $activePage === 'users' ? 'active' : '' ?>" href="<?= $appRoot ?>/users/index.php">
        <i class="bi bi-people"></i>
        <span>Users</span>
      </a>

      <a class="nav-item <?= $activePage === 'billing' ? 'active' : '' ?>" href="<?= $appRoot ?>/billing/index.php">
        <i class="bi bi-credit-card"></i>
        <span>Billing</span>
      </a>
    </nav>

    <!-- ACCOUNT -->
    <div class="sidebar-bottom">
      <div class="dropdown w-100">
        <button type="button" class="account-card w-100 text-start border-0 bg-transparent dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="display:flex;align-items:center;justify-content:space-between;text-decoration:none;">
          <div style="display:flex;align-items:center;gap:12px;min-width:0;">
            <?php if (!empty($userPicUrl)): ?>
              <img src="<?= htmlspecialchars($userPicUrl) ?>" alt="Avatar" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
            <?php else: ?>
              <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            <?php endif; ?>
            <div class="account-copy text-truncate">
              <strong><?= htmlspecialchars($userName) ?></strong>
              <small><?= htmlspecialchars($userRole) ?></small>
            </div>
          </div>
          <div class="account-arrow"><i class="bi bi-chevron-down"></i></div>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width:220px;font-size:13px;">
          <li class="px-3 py-2 border-bottom">
            <div class="fw-semibold text-truncate"><?= htmlspecialchars($userName) ?></div>
            <div class="text-muted small text-truncate"><?= htmlspecialchars($userEmail) ?></div>
          </li>
          <li><a class="dropdown-item py-2" href="<?= $appRoot ?>/profile/index.php"><i class="bi bi-person me-2"></i> Profile</a></li>
          <li><a class="dropdown-item py-2" href="<?= $appRoot ?>/settings/index.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
          <li><hr class="dropdown-divider my-1"></li>
          <li><a class="dropdown-item py-2 text-danger" href="<?= $appRoot ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</aside>
