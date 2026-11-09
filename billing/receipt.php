<?php
require_once __DIR__ . '/../includes/auth_check.php';

$txnId = (int)($_GET['id'] ?? 0);
if ($txnId <= 0) { die("Invalid invoice ID."); }

// Workspace isolation: check transaction belongs to current active workspace or authorized workspace
$stmt = $pdo->prepare("
    SELECT t.*, w.name as workspace_name, w.description as workspace_desc
    FROM payment_transactions t
    JOIN workspaces w ON t.workspace_id = w.id
    WHERE t.id = ? AND t.workspace_id = ?
");
$stmt->execute([$txnId, $currentWorkspaceId]);
$txn = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$txn) {
    http_response_code(403);
    die("Access denied: You do not have permission to view this receipt.");
}
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <title>Receipt #<?= htmlspecialchars($txn['invoice_number']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; color: #0f172a; font-family: system-ui, sans-serif; padding: 40px 20px; }
    .receipt-box { max-width: 650px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; border: 1px solid #e2e8f0; }
  </style>
</head>
<body>
<div class="receipt-box shadow-sm">
  <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
    <div>
      <h2 class="h4 fw-bold text-primary mb-1">Primovative Co.</h2>
      <div class="small text-muted">Lead Generation &amp; Discovery SaaS Platform</div>
    </div>
    <div class="text-end">
      <span class="badge bg-success-subtle text-success border border-success-subtle fs-6 px-3 py-1"><?= ucfirst($txn['status']) ?></span>
      <div class="text-muted small mt-1">Invoice: <?= htmlspecialchars($txn['invoice_number']) ?></div>
    </div>
  </div>

  <div class="row mb-4 small">
    <div class="col-6">
      <div class="text-muted">Billed To:</div>
      <strong><?= htmlspecialchars($txn['billing_name'] ?: $txn['workspace_name']) ?></strong>
      <div class="text-muted"><?= htmlspecialchars($txn['billing_email'] ?: $userEmail) ?></div>
      <div class="text-muted">Workspace: <?= htmlspecialchars($txn['workspace_name']) ?></div>
    </div>
    <div class="col-6 text-end">
      <div class="text-muted">Payment Date:</div>
      <strong><?= date('F d, Y - h:i A', strtotime($txn['created_at'])) ?></strong>
      <div class="text-muted">Transaction Ref: <?= htmlspecialchars($txn['transaction_ref']) ?></div>
      <div class="text-muted">Method: <?= htmlspecialchars($txn['payment_method']) ?> (•••• <?= htmlspecialchars($txn['card_last4'] ?? '4242') ?>)</div>
    </div>
  </div>

  <table class="table border mb-4">
    <thead class="table-light small">
      <tr><th>Description</th><th class="text-end">Amount</th></tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <strong><?= htmlspecialchars($txn['notes'] ?: 'Subscription Plan Renewal') ?></strong>
          <div class="small text-muted">Official ProspectHunt Platform Services</div>
        </td>
        <td class="text-end fw-bold">PHP <?= number_format($txn['amount'], 2) ?></td>
      </tr>
      <tr>
        <td class="text-end fw-semibold">Subtotal:</td>
        <td class="text-end">PHP <?= number_format($txn['amount'], 2) ?></td>
      </tr>
      <tr>
        <td class="text-end fw-semibold">Tax (0%):</td>
        <td class="text-end">PHP 0.00</td>
      </tr>
      <tr class="table-light">
        <td class="text-end fw-bold">Total Paid:</td>
        <td class="text-end fw-bold fs-5 text-primary">PHP <?= number_format($txn['amount'], 2) ?></td>
      </tr>
    </tbody>
  </table>

  <div class="text-center text-muted small border-top pt-3">
    Thank you for your business. For billing inquiries, contact support@primovative.com.
  </div>
</div>
</body>
</html>
