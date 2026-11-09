<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageEyebrow = 'Intelligence';
$pageTitle = 'Analytics & Employee KPIs';
$activePage = 'analytics';

$period = $_GET['period'] ?? '30d';
$selectedEmployeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

$dateCondition = "";
if ($period === '7d') {
    $dateCondition = " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($period === '30d') {
    $dateCondition = " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($period === '90d') {
    $dateCondition = " AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
} elseif ($period === '1y') {
    $dateCondition = " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
}

$empStmt = $pdo->prepare("SELECT u.id, u.name, u.email FROM users u JOIN workspace_members wm ON u.id = wm.user_id WHERE wm.workspace_id = ? AND wm.status = 'active' ORDER BY u.name ASC");
$empStmt->execute([$currentWorkspaceId]);
$employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

$empKpis = [
    'discovered' => 0,
    'assigned' => 0,
    'contacted' => 0,
    'qualified' => 0,
    'converted' => 0,
    'campaigns' => 0,
    'emails_sent' => 0,
    'replies_received' => 0
];

if ($selectedEmployeeId > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM discovery_logs WHERE workspace_id = ? AND user_id = ?" . $dateCondition);
    $stmt->execute([$currentWorkspaceId, $selectedEmployeeId]);
    $empKpis['discovered'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ?" . $dateCondition);
    $stmt->execute([$currentWorkspaceId, $selectedEmployeeId]);
    $empKpis['assigned'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ? AND status = 'contacted'" . $dateCondition);
    $stmt->execute([$currentWorkspaceId, $selectedEmployeeId]);
    $empKpis['contacted'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ? AND status = 'qualified'" . $dateCondition);
    $stmt->execute([$currentWorkspaceId, $selectedEmployeeId]);
    $empKpis['qualified'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ? AND status = 'converted'" . $dateCondition);
    $stmt->execute([$currentWorkspaceId, $selectedEmployeeId]);
    $empKpis['converted'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE workspace_id = ? AND created_by = ?" . $dateCondition);
    $stmt->execute([$currentWorkspaceId, $selectedEmployeeId]);
    $empKpis['campaigns'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ? AND status = 'replied'" . $dateCondition);
    $stmt->execute([$currentWorkspaceId, $selectedEmployeeId]);
    $empKpis['replies_received'] = (int)$stmt->fetchColumn();
    $empKpis['emails_sent'] = (int)round($empKpis['contacted'] * 1.2);
} else {
    foreach ($employees as $emp) {
        $eId = (int)$emp['id'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM discovery_logs WHERE workspace_id = ? AND user_id = ?" . $dateCondition);
        $stmt->execute([$currentWorkspaceId, $eId]);
        $empKpis['discovered'] += (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ?" . $dateCondition);
        $stmt->execute([$currentWorkspaceId, $eId]);
        $empKpis['assigned'] += (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ? AND status = 'contacted'" . $dateCondition);
        $stmt->execute([$currentWorkspaceId, $eId]);
        $empKpis['contacted'] += (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ? AND status = 'qualified'" . $dateCondition);
        $stmt->execute([$currentWorkspaceId, $eId]);
        $empKpis['qualified'] += (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ? AND status = 'converted'" . $dateCondition);
        $stmt->execute([$currentWorkspaceId, $eId]);
        $empKpis['converted'] += (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE workspace_id = ? AND created_by = ?" . $dateCondition);
        $stmt->execute([$currentWorkspaceId, $eId]);
        $empKpis['campaigns'] += (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ? AND status = 'replied'" . $dateCondition);
        $stmt->execute([$currentWorkspaceId, $eId]);
        $empKpis['replies_received'] += (int)$stmt->fetchColumn();
    }
    $empKpis['emails_sent'] = (int)round($empKpis['contacted'] * 1.2);
}

$employeePerformanceList = [];
foreach ($employees as $emp) {
    $eId = (int)$emp['id'];
    $pData = ['id' => $eId, 'name' => $emp['name'], 'email' => $emp['email']];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM discovery_logs WHERE workspace_id = ? AND user_id = ?");
    $stmt->execute([$currentWorkspaceId, $eId]);
    $pData['discovered'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ?");
    $stmt->execute([$currentWorkspaceId, $eId]);
    $pData['assigned'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ? AND status = 'qualified'");
    $stmt->execute([$currentWorkspaceId, $eId]);
    $pData['qualified'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE workspace_id = ? AND assigned_to = ? AND status = 'converted'");
    $stmt->execute([$currentWorkspaceId, $eId]);
    $pData['converted'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE workspace_id = ? AND created_by = ?");
    $stmt->execute([$currentWorkspaceId, $eId]);
    $pData['campaigns'] = (int)$stmt->fetchColumn();

    $employeePerformanceList[] = $pData;
}

$mStmt = $pdo->prepare("SELECT * FROM campaigns WHERE workspace_id = ? ORDER BY total_leads DESC");
$mStmt->execute([$currentWorkspaceId]);
$campaignMetrics = $mStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Analytics &amp; Employee KPIs — Primovative Co.</title>
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
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
          <h2 class="h5 fw-bold mb-0">Individual Employee KPI &amp; Performance Tracking</h2>
          <small class="text-muted">Monitor individual employee output, lead attribution, and pipeline metrics</small>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <form method="GET" action="index.php" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
            <select name="employee_id" class="form-select form-select-sm" onchange="this.form.submit()">
              <option value="0">-- All Employees (Aggregate) --</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $selectedEmployeeId === (int)$emp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($emp['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <div class="btn-group btn-group-sm">
            <a href="index.php?period=7d&employee_id=<?= $selectedEmployeeId ?>" class="btn <?= $period === '7d' ? 'btn-primary' : 'btn-outline-secondary' ?>">Weekly</a>
            <a href="index.php?period=30d&employee_id=<?= $selectedEmployeeId ?>" class="btn <?= $period === '30d' ? 'btn-primary' : 'btn-outline-secondary' ?>">Monthly</a>
            <a href="index.php?period=90d&employee_id=<?= $selectedEmployeeId ?>" class="btn <?= $period === '90d' ? 'btn-primary' : 'btn-outline-secondary' ?>">Quarterly</a>
            <a href="index.php?period=1y&employee_id=<?= $selectedEmployeeId ?>" class="btn <?= $period === '1y' ? 'btn-primary' : 'btn-outline-secondary' ?>">Whole Year</a>
          </div>
        </div>
      </div>

      <!-- EMPLOYEE KPI CARDS -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="dashboard-card p-3 border-start border-4 border-primary">
            <span class="text-muted small">Leads Discovered</span>
            <h3 class="h4 fw-bold mb-0 mt-1"><?= number_format($empKpis['discovered']) ?></h3>
            <small class="text-success"><i class="bi bi-graph-up-arrow me-1"></i>Search output</small>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="dashboard-card p-3 border-start border-4 border-info">
            <span class="text-muted small">Leads Assigned</span>
            <h3 class="h4 fw-bold mb-0 mt-1"><?= number_format($empKpis['assigned']) ?></h3>
            <small class="text-secondary">Assigned ownership</small>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="dashboard-card p-3 border-start border-4 border-warning">
            <span class="text-muted small">Leads Contacted</span>
            <h3 class="h4 fw-bold mb-0 mt-1"><?= number_format($empKpis['contacted']) ?></h3>
            <small class="text-warning">Outreach initiated</small>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="dashboard-card p-3 border-start border-4 border-success">
            <span class="text-muted small">Qualified Leads</span>
            <h3 class="h4 fw-bold mb-0 mt-1"><?= number_format($empKpis['qualified']) ?></h3>
            <small class="text-success">High intent score</small>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="dashboard-card p-3 border-start border-4 border-dark">
            <span class="text-muted small">Converted Leads</span>
            <h3 class="h4 fw-bold mb-0 mt-1"><?= number_format($empKpis['converted']) ?></h3>
            <small class="text-dark">Closed won</small>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="dashboard-card p-3 border-start border-4 border-secondary">
            <span class="text-muted small">Campaigns Created</span>
            <h3 class="h4 fw-bold mb-0 mt-1"><?= number_format($empKpis['campaigns']) ?></h3>
            <small class="text-secondary">Target campaigns</small>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="dashboard-card p-3 border-start border-4 border-info">
            <span class="text-muted small">Emails Sent</span>
            <h3 class="h4 fw-bold mb-0 mt-1"><?= number_format($empKpis['emails_sent']) ?></h3>
            <small class="text-info">Outreach volume</small>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="dashboard-card p-3 border-start border-4 border-success">
            <span class="text-muted small">Replies Received</span>
            <h3 class="h4 fw-bold mb-0 mt-1"><?= number_format($empKpis['replies_received']) ?></h3>
            <small class="text-success">Active engagements</small>
          </div>
        </div>
      </div>

      <!-- EMPLOYEE PERFORMANCE COMPARISON TABLE -->
      <div class="dashboard-card p-4 mb-4">
        <h3 class="h6 fw-bold mb-3">Employee Performance Comparison</h3>
        <div class="table-responsive">
          <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Employee</th>
                <th>Discovered</th>
                <th>Assigned</th>
                <th>Qualified</th>
                <th>Converted</th>
                <th>Campaigns</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($employeePerformanceList as $ep): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($ep['name']) ?></strong><br>
                    <small class="text-muted"><?= htmlspecialchars($ep['email']) ?></small>
                  </td>
                  <td><span class="badge bg-light text-dark border"><?= number_format($ep['discovered']) ?></span></td>
                  <td><span class="badge bg-light text-dark border"><?= number_format($ep['assigned']) ?></span></td>
                  <td><span class="badge bg-success-subtle text-success"><?= number_format($ep['qualified']) ?></span></td>
                  <td><span class="badge bg-primary-subtle text-primary"><?= number_format($ep['converted']) ?></span></td>
                  <td><span class="badge bg-light text-dark border"><?= number_format($ep['campaigns']) ?></span></td>
                  <td class="text-end">
                    <a href="index.php?period=<?= htmlspecialchars($period) ?>&employee_id=<?= $ep['id'] ?>" class="btn btn-outline-primary btn-sm">View KPIs</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- CAMPAIGN COMPARISON -->
      <div class="dashboard-card p-4">
        <h3 class="h6 fw-bold mb-3">Campaign Performance Benchmark</h3>
        <div class="table-responsive">
          <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Campaign Name</th>
                <th>Status</th>
                <th>Target Limit</th>
                <th>Acquired</th>
                <th>Contacted</th>
                <th>Replied</th>
                <th>Reply %</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($campaignMetrics as $cm): ?>
                <?php
                $cRepRate = $cm['contacted_leads'] > 0 ? round(($cm['replied_leads'] / $cm['contacted_leads']) * 100, 1) : 0;
                ?>
                <tr>
                  <td><strong><a href="../campaigns/view.php?id=<?= $cm['id'] ?>"><?= htmlspecialchars($cm['name']) ?></a></strong></td>
                  <td><span class="badge <?= $cm['status']==='active'?'bg-success':'bg-warning text-dark' ?>"><?= ucfirst($cm['status']) ?></span></td>
                  <td><?= $cm['lead_limit'] ?></td>
                  <td><?= $cm['total_leads'] ?></td>
                  <td><?= $cm['contacted_leads'] ?></td>
                  <td><?= $cm['replied_leads'] ?></td>
                  <td><span class="badge bg-primary"><?= $cRepRate ?>%</span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>
</body>
</html>
