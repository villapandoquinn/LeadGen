<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageEyebrow = 'Management';
$pageTitle = 'Billing & Subscriptions';
$activePage = 'billing';

$plans = $billingService->getAllPlans();
$sub = $billingService->getWorkspaceSubscription($currentWorkspaceId);
$usage = $billingService->getUsageStats($currentWorkspaceId);
$history = $billingService->getPaymentHistory($currentWorkspaceId);

$currentPlanId = (int)($sub['plan_id'] ?? 1);
$currentSlug = $sub['plan_slug'] ?? 'basic';
$currentStatus = $sub['status'] ?? 'active';
$currentCycle = $sub['billing_cycle'] ?? 'monthly';
$canManageBilling = $workspaceService->hasPermission($workspaceRole, 'billing') || ($workspaceRole === 'owner');

$upgradeMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_upgrade') {
    require_csrf();
    $targetPlanId = (int)($_POST['target_plan_id'] ?? 2);
    $res = $billingService->submitUpgradeRequest($currentWorkspaceId, $currentPlanId, $targetPlanId, $userId);
    $upgradeMsg = $res['message'];
}

$adminMsg = '';
if ($canManageBilling && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_update_sub') {
    require_csrf();
    $newPlanId = (int)($_POST['plan_id'] ?? 1);
    $prodActDate = trim($_POST['production_activated_at'] ?? date('Y-m-d'));
    $incEnds = date('Y-m-d H:i:s', strtotime($prodActDate . ' +3 months'));
    
    $upd = $pdo->prepare("UPDATE workspace_subscriptions SET plan_id = ?, production_activated_at = ?, included_ends_at = ? WHERE workspace_id = ?");
    $upd->execute([$newPlanId, $prodActDate, $incEnds, $currentWorkspaceId]);
    $adminMsg = "Subscription and activation schedule updated successfully.";
    $sub = $billingService->getWorkspaceSubscription($currentWorkspaceId);
}

$totalPaid = 0.00;
foreach ($history as $h) {
    if ($h['status'] === 'successful') {
        $totalPaid += (float)$h['amount'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Billing &amp; Subscriptions — Primovative Co.</title>
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
      <?php if (isset($_GET['alert']) && $_GET['alert'] === 'subscription_required'): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4 py-3" role="alert">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-shield-exclamation fs-4 text-warning"></i>
            <div><strong>Active Subscription Required:</strong> Your workspace subscription is pending payment or expired. Please select a plan or renew below to proceed.</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 py-2 small" role="alert">
          <i class="bi bi-check-circle me-1"></i>
          <?php if ($_GET['success'] === 'paid') echo "Payment processed successfully! Your subscription is active.";
                elseif ($_GET['success'] === 'cancelled') echo "Subscription cancellation scheduled.";
                else echo "Updated successfully."; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 py-2 small" role="alert">
          <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($_GET['error']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- PLAN CARD & USAGE -->
      <div class="row g-4 mb-4">
        <div class="col-12 col-lg-5">
          <div class="dashboard-card p-4 h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                  <span class="badge <?= in_array($currentStatus, ['active', 'trial']) ? 'bg-primary' : 'bg-danger' ?> mb-2"><?= ucfirst($currentStatus) ?> Plan</span>
                  <h2 class="h4 fw-bold mb-0"><?= htmlspecialchars($sub['plan_name'] ?? 'Starter') ?></h2>
                  <small class="text-muted">Workspace: <?= htmlspecialchars($currentWorkspace['name']) ?></small>
                </div>
                <div class="text-end">
                  <div class="fs-4 fw-bold text-dark">$<?= number_format($currentCycle === 'annual' ? ($sub['price_annual'] ?? 0) : ($sub['price_monthly'] ?? 0), 2) ?><small class="text-muted fs-6">/<?= $currentCycle === 'annual' ? 'yr' : 'mo' ?></small></div>
                  <small class="text-success"><?= !empty($sub['ends_at']) ? 'Expires ' . date('M d, Y', strtotime($sub['ends_at'])) : 'Ongoing' ?></small>
                </div>
              </div>
              <p class="text-muted small"><?= htmlspecialchars($sub['plan_description'] ?? 'Prospecting and lead generation pipeline.') ?></p>
            </div>
            <div class="pt-3 border-top d-flex gap-2">
              <?php if ($canManageBilling): ?>
                <a href="#plansSection" class="btn btn-primary btn-sm w-100"><i class="bi bi-arrow-up-circle me-1"></i> Change Plan</a>
                <?php if ($currentStatus === 'active'): ?>
                  <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelSubModal">Cancel</button>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-muted small">Admin access required to alter subscription.</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-7">
          <div class="dashboard-card p-4 h-100">
            <h3 class="h6 fw-bold mb-3">Workspace Quotas &amp; Limits</h3>
            <div class="mb-3">
              <div class="d-flex justify-content-between small mb-1">
                <span>Leads Discovery Limit</span>
                <strong><?= number_format($usage['leads_used']) ?> / <?= number_format($usage['leads_limit']) ?> <small class="text-muted">(<?= round(($usage['leads_used']/max(1, $usage['leads_limit']))*100, 1) ?>%)</small></strong>
              </div>
              <div class="progress" style="height: 8px;"><div class="progress-bar bg-primary" style="width: <?= min(100, ($usage['leads_used']/max(1, $usage['leads_limit']))*100) ?>%;"></div></div>
            </div>
            <div class="mb-3">
              <div class="d-flex justify-content-between small mb-1">
                <span>Active Campaigns Quota</span>
                <strong><?= $usage['campaigns_used'] ?> / <?= $usage['campaigns_limit'] ?> <small class="text-muted">(<?= round(($usage['campaigns_used']/max(1, $usage['campaigns_limit']))*100) ?>%)</small></strong>
              </div>
              <div class="progress" style="height: 8px;"><div class="progress-bar bg-info" style="width: <?= min(100, ($usage['campaigns_used']/max(1, $usage['campaigns_limit']))*100) ?>%;"></div></div>
            </div>
            <div>
              <div class="d-flex justify-content-between small mb-1">
                <span>Team Seats Assigned</span>
                <strong><?= $usage['users_used'] ?> / <?= $usage['users_limit'] ?> <small class="text-muted">(<?= round(($usage['users_used']/max(1, $usage['users_limit']))*100) ?>%)</small></strong>
              </div>
              <div class="progress" style="height: 8px;"><div class="progress-bar bg-success" style="width: <?= min(100, ($usage['users_used']/max(1, $usage['users_limit']))*100) ?>%;"></div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- AVAILABLE SUBSCRIPTION PLANS -->
      <div id="plansSection" class="dashboard-card p-4 mb-4">
        <h3 class="h6 fw-bold mb-3">Subscription Plans</h3>
        <div class="row g-3">
          <?php foreach ($plans as $p): ?>
            <?php $isCurrentPlan = ((int)$p['id'] === $currentPlanId); ?>
            <div class="col-12 col-md-4">
              <div class="card p-3 h-100 border <?= $isCurrentPlan ? 'border-primary' : '' ?>" style="border-radius: 10px;">
                <div class="d-flex justify-content-between mb-1">
                  <h4 class="h6 fw-bold mb-0"><?= htmlspecialchars($p['name']) ?></h4>
                  <?php if ($isCurrentPlan): ?><span class="badge bg-primary">Active</span><?php endif; ?>
                </div>
                <div class="fs-4 fw-bold mb-2">$<?= number_format($p['price_monthly'], 0) ?><small class="text-muted fs-6">/mo</small></div>
                <p class="small text-muted mb-3"><?= htmlspecialchars($p['description']) ?></p>
                <ul class="list-unstyled small mb-3">
                  <li><i class="bi bi-check2 text-primary me-1"></i> <?= number_format($p['max_leads']) ?> Monthly Leads</li>
                  <li><i class="bi bi-check2 text-primary me-1"></i> <?= $p['max_campaigns'] ?> Active Campaigns</li>
                  <li><i class="bi bi-check2 text-primary me-1"></i> <?= $p['max_users'] ?> Team Seats</li>
                  <li><i class="bi bi-check2 text-primary me-1"></i> <?= number_format($p['max_outreach']) ?> Outreach Messages</li>
                </ul>
                <?php if ($canManageBilling): ?>
                  <button type="button" class="btn btn-sm btn-primary w-100 mt-auto" data-bs-toggle="modal" data-bs-target="#payModal" data-plan-id="<?= $p['id'] ?>" data-plan-name="<?= htmlspecialchars($p['name']) ?>" data-plan-monthly="<?= $p['price_monthly'] ?>" data-plan-annual="<?= $p['price_annual'] ?>">
                    <?= $isCurrentPlan ? 'Renew / Update' : 'Select Plan' ?>
                  </button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- INVOICES -->
      <div class="dashboard-card p-4">
        <h3 class="h6 fw-bold mb-3">Invoicing &amp; Payment History (<?= count($history) ?>)</h3>
        <div class="table-responsive">
          <table class="table align-middle table-hover mb-0">
            <thead class="table-light"><tr><th>Invoice #</th><th>Reference</th><th>Billing Date</th><th>Amount</th><th>Status</th><th class="text-end">Receipt</th></tr></thead>
            <tbody>
              <?php if (empty($history)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted small">No payment transactions recorded for this workspace.</td></tr>
              <?php else: ?>
                <?php foreach ($history as $txn): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($txn['invoice_number']) ?></strong></td>
                    <td><small class="text-muted font-monospace"><?= htmlspecialchars($txn['transaction_ref']) ?></small></td>
                    <td><?= date('M d, Y', strtotime($txn['created_at'])) ?></td>
                    <td><strong>$<?= number_format($txn['amount'], 2) ?> USD</strong></td>
                    <td><span class="badge <?= $txn['status'] === 'successful' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($txn['status']) ?></span></td>
                    <td class="text-end"><a href="receipt.php?id=<?= $txn['id'] ?>" target="_blank" class="btn btn-sm btn-link p-0"><i class="bi bi-file-earmark-pdf me-1"></i> Receipt</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
  </div>
</div>

<!-- PAY MODAL -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Upgrade Subscription</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" action="process.php">
        <?= csrf_field() ?>
        <input type="hidden" name="plan_id" id="modalPlanId" value="">
        <div class="modal-body">
          <div class="alert bg-light border p-2 small mb-3">Target Workspace: <strong><?= htmlspecialchars($currentWorkspace['name']) ?></strong></div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Billing Frequency</label>
            <div class="d-flex gap-2">
              <label class="border rounded p-2 flex-grow-1 small"><input type="radio" name="billing_cycle" value="monthly" checked> Monthly</label>
              <label class="border rounded p-2 flex-grow-1 small"><input type="radio" name="billing_cycle" value="annual"> Annual (Save 15%)</label>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Card Number (Simulated Gateway)</label>
            <input type="text" name="card_number" class="form-control" value="4242 •••• •••• 4242" required>
          </div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label small fw-semibold">Exp</label><input type="text" name="card_exp" class="form-control" value="12/28" required></div>
            <div class="col-6"><label class="form-label small fw-semibold">CVV</label><input type="password" name="card_cvv" class="form-control" value="123" required></div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0"><button type="submit" class="btn btn-primary"><i class="bi bi-lock-fill me-1"></i> Confirm Payment</button></div>
      </form>
    </div>
  </div>
</div>

<!-- CANCEL MODAL -->
<div class="modal fade" id="cancelSubModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold text-danger">Cancel Subscription</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" action="cancel.php">
        <?= csrf_field() ?>
        <div class="modal-body">
          <p class="small text-muted mb-3">Cancel subscription for <strong><?= htmlspecialchars($currentWorkspace['name']) ?></strong>? Access will remain active through the current cycle.</p>
          <div class="mb-3"><label class="form-label small fw-semibold">Reason (optional)</label><textarea name="cancel_reason" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer border-0 pt-0"><button type="submit" class="btn btn-danger">Confirm Cancellation</button></div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const payModal = document.getElementById('payModal');
  if (payModal) {
    payModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      if (button) document.getElementById('modalPlanId').value = button.getAttribute('data-plan-id');
    });
  }
});
</script>
