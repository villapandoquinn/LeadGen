<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageEyebrow = 'Management';
$pageTitle = 'Tasks';
$activePage = 'tasks';

$statusFilter = $_GET['status'] ?? 'pending';
$priorityFilter = $_GET['priority'] ?? '';
$prefillLeadId = (int)($_GET['lead_id'] ?? 0);

$sql = "
    SELECT t.*, l.name as lead_name, l.company as lead_company, c.name as campaign_name
    FROM tasks t
    LEFT JOIN leads l ON t.lead_id = l.id
    LEFT JOIN campaigns c ON t.campaign_id = c.id
    WHERE t.workspace_id = ?
";
$params = [$currentWorkspaceId];

if ($statusFilter !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
}
if (!empty($priorityFilter)) {
    $sql .= " AND t.priority = ?";
    $params[] = $priorityFilter;
}

$sql .= " ORDER BY (t.status = 'completed') ASC, (t.priority = 'urgent') DESC, (t.priority = 'high') DESC, t.due_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lStmt = $pdo->prepare("SELECT id, name, company FROM leads WHERE workspace_id = ? ORDER BY name ASC");
$lStmt->execute([$currentWorkspaceId]);
$leads = $lStmt->fetchAll(PDO::FETCH_ASSOC);

$cStmt = $pdo->prepare("SELECT id, name FROM campaigns WHERE workspace_id = ? ORDER BY name ASC");
$cStmt->execute([$currentWorkspaceId]);
$campaigns = $cStmt->fetchAll(PDO::FETCH_ASSOC);

$totStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE workspace_id = ?");
$totStmt->execute([$currentWorkspaceId]);
$totalTasks = (int)$totStmt->fetchColumn();

$pStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE workspace_id = ? AND status = 'pending'");
$pStmt->execute([$currentWorkspaceId]);
$pendingTotal = (int)$pStmt->fetchColumn();

$uStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE workspace_id = ? AND status = 'pending' AND priority = 'urgent'");
$uStmt->execute([$currentWorkspaceId]);
$urgentTotal = (int)$uStmt->fetchColumn();

$compStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE workspace_id = ? AND status = 'completed'");
$compStmt->execute([$currentWorkspaceId]);
$completedTotal = (int)$compStmt->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tasks &amp; Follow-ups — Primovative Co.</title>
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
      <!-- FEEDBACK ALERTS -->
      <?php if (isset($_GET['success']) && $_GET['success'] === 'created'): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 py-2 small" role="alert">
          <i class="bi bi-check-circle me-1"></i> Task successfully added.
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php elseif (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 py-2 small" role="alert">
          <i class="bi bi-check-circle me-1"></i> Task removed successfully.
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 py-2 small" role="alert">
          <i class="bi bi-exclamation-triangle me-1"></i>
          <?= $_GET['error'] === 'missing_title' ? 'Task title is required.' : 'An error occurred while saving the task.' ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <!-- STATS -->
      <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-blue"><i class="bi bi-list-task"></i></div>
            <div class="stat-content">
              <span class="stat-label">Total Tasks</span>
              <div class="stat-value-row"><strong><?= number_format($totalTasks) ?></strong></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-amber"><i class="bi bi-clock-history"></i></div>
            <div class="stat-content">
              <span class="stat-label">Pending Tasks</span>
              <div class="stat-value-row"><strong><?= number_format($pendingTotal) ?></strong></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-violet"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-content">
              <span class="stat-label">Urgent Attention</span>
              <div class="stat-value-row"><strong><?= number_format($urgentTotal) ?></strong></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-check2-all"></i></div>
            <div class="stat-content">
              <span class="stat-label">Completed</span>
              <div class="stat-value-row"><strong><?= number_format($completedTotal) ?></strong></div>
            </div>
          </div>
        </div>
      </div>

      <!-- MAIN TASKS CARD -->
      <div class="dashboard-card p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group btn-group-sm" role="group">
              <a href="index.php?status=pending<?= !empty($priorityFilter) ? '&priority=' . urlencode($priorityFilter) : '' ?>" class="btn <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Pending <span class="badge <?= $statusFilter === 'pending' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> ms-1"><?= $pendingTotal ?></span>
              </a>
              <a href="index.php?status=completed<?= !empty($priorityFilter) ? '&priority=' . urlencode($priorityFilter) : '' ?>" class="btn <?= $statusFilter === 'completed' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                Completed <span class="badge <?= $statusFilter === 'completed' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> ms-1"><?= $completedTotal ?></span>
              </a>
              <a href="index.php?status=all<?= !empty($priorityFilter) ? '&priority=' . urlencode($priorityFilter) : '' ?>" class="btn <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                All <span class="badge <?= $statusFilter === 'all' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> ms-1"><?= $totalTasks ?></span>
              </a>
            </div>

            <div class="d-inline-block">
              <select class="form-select form-select-sm" style="width: auto;" onchange="location.href='index.php?status=<?= urlencode($statusFilter) ?>&priority=' + this.value">
                <option value="" <?= empty($priorityFilter) ? 'selected' : '' ?>>All Priorities</option>
                <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>High</option>
                <option value="medium" <?= $priorityFilter === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Low</option>
              </select>
            </div>
          </div>

          <div>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newTaskModal">
              <i class="bi bi-plus-lg me-1"></i> Add Task
            </button>
          </div>
        </div>

        <?php if (empty($tasks)): ?>
          <div class="text-center py-5">
            <div class="stat-icon icon-teal mx-auto mb-3" style="width: 52px; height: 52px; font-size: 24px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;">
              <i class="bi bi-check2-circle"></i>
            </div>
            <h5 class="fw-semibold fs-6 mb-1">No tasks found</h5>
            <p class="text-muted small mb-3">All caught up! No tasks match your current filter.</p>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newTaskModal">
              <i class="bi bi-plus-lg me-1"></i> Create a Task
            </button>
          </div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($tasks as $t): ?>
              <?php
                $isCompleted = ($t['status'] === 'completed');
                $isOverdue = (!$isCompleted) && (strtotime($t['due_date']) < strtotime(date('Y-m-d')));
                $isDueToday = (!$isCompleted) && (date('Y-m-d', strtotime($t['due_date'])) === date('Y-m-d'));
              ?>
              <div class="list-group-item px-0 py-3 d-flex align-items-start justify-content-between gap-3 border-bottom">
                <div class="d-flex align-items-start gap-3 flex-grow-1">
                  <input type="checkbox" class="form-check-input mt-1 task-checkbox flex-shrink-0" data-id="<?= $t['id'] ?>" <?= $isCompleted ? 'checked' : '' ?> style="width: 19px; height: 19px; cursor: pointer;">
                  <div class="flex-grow-1 <?= $isCompleted ? 'task-row completed-task' : 'task-row' ?>" id="task-title-<?= $t['id'] ?>">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <strong class="text-dark fs-6"><?= htmlspecialchars($t['title']) ?></strong>
                      <span class="badge <?= $t['priority'] === 'urgent' ? 'bg-danger' : ($t['priority'] === 'high' ? 'bg-warning text-dark' : ($t['priority'] === 'medium' ? 'bg-primary' : 'bg-secondary')) ?>">
                        <?= ucfirst($t['priority']) ?>
                      </span>
                      <?php if ($isOverdue): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Overdue</span>
                      <?php elseif ($isDueToday): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Due Today</span>
                      <?php endif; ?>
                    </div>
                    <?php if (!empty($t['description'])): ?>
                      <p class="text-secondary small mb-1 mt-1"><?= htmlspecialchars($t['description']) ?></p>
                    <?php endif; ?>
                    <div class="small text-muted mt-1 d-flex flex-wrap align-items-center gap-3">
                      <?php if (!empty($t['lead_name'])): ?>
                        <span><i class="bi bi-person me-1 text-primary"></i><a href="../leads/view.php?id=<?= $t['lead_id'] ?>" class="text-decoration-none text-muted fw-semibold"><?= htmlspecialchars($t['lead_company'] ?: $t['lead_name']) ?></a></span>
                      <?php endif; ?>
                      <?php if (!empty($t['campaign_name'])): ?>
                        <span><i class="bi bi-megaphone me-1 text-info"></i><a href="../campaigns/view.php?id=<?= $t['campaign_id'] ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($t['campaign_name']) ?></a></span>
                      <?php endif; ?>
                      <span><i class="bi bi-calendar3 me-1"></i> Due: <?= date('M d, Y', strtotime($t['due_date'])) ?></span>
                    </div>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                  <form method="POST" action="delete.php" class="m-0" onsubmit="return confirm('Delete this task?');">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Task" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px;">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </main>

    <!-- NEW TASK MODAL -->
    <div class="modal fade" id="newTaskModal" tabindex="-1" aria-labelledby="newTaskModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold" id="newTaskModalLabel">Add Task</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="store.php">
            <?= csrf_field() ?>

            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label small fw-semibold">Task Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="Action item..." required>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Notes, follow-up context, details..."></textarea>
              </div>
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label class="form-label small fw-semibold">Priority</label>
                  <select name="priority" class="form-select">
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="medium" selected>Medium</option>
                    <option value="low">Low</option>
                  </select>
                </div>
                <div class="col-6">
                  <label class="form-label small fw-semibold">Due Date <span class="text-danger">*</span></label>
                  <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+2 days')) ?>" required>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Associated Lead</label>
                <select name="lead_id" class="form-select">
                  <option value="">-- No Lead (General Task) --</option>
                  <?php foreach ($leads as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= $prefillLeadId === (int)$l['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($l['company'] ?: $l['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Associated Campaign</label>
                <select name="campaign_id" class="form-select">
                  <option value="">-- No Campaign --</option>
                  <?php foreach ($campaigns as $c): ?>
                    <option value="<?= $c['id'] ?>">
                      <?= htmlspecialchars($c['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Task</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.querySelectorAll('.task-checkbox').forEach(chk => {
  chk.addEventListener('change', function() {
    const taskId = this.dataset.id;
    const isCompleted = this.checked;
    const titleWrap = document.getElementById('task-title-' + taskId);
    const fd = new FormData();
    fd.append('id', taskId);
    fd.append('completed', isCompleted ? '1' : '');
    fd.append('csrf_token', '<?= csrf_token() ?>');


    fetch('toggle.php', { method: 'POST', body: fd })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          if (isCompleted) {
            titleWrap.classList.add('completed-task');
          } else {
            titleWrap.classList.remove('completed-task');
          }
        } else {
          alert('Failed to update task: ' + (data.message || 'Unknown error'));
          this.checked = !isCompleted;
        }
      })
      .catch(err => {
        alert('Network error while updating task.');
        this.checked = !isCompleted;
      });
  });
});

<?php if (!empty($prefillLeadId)): ?>
const modalEl = document.getElementById('newTaskModal');
if (modalEl && typeof bootstrap !== 'undefined') {
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
}
<?php endif; ?>
</script>
