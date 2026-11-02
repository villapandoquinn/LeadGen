<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageEyebrow = 'Campaigns';
$pageTitle = 'Outreach';
$activePage = 'outreach';

$prefillLeadId = (int)($_GET['lead_id'] ?? 0);

$leadsStmt = $pdo->prepare("SELECT id, name, company, email FROM leads WHERE workspace_id = ? ORDER BY name ASC");
$leadsStmt->execute([$currentWorkspaceId]);
$leads = $leadsStmt->fetchAll(PDO::FETCH_ASSOC);

$outreachStmt = $pdo->prepare("
    SELECT o.*, l.name as lead_name, l.company as lead_company, l.email as lead_email, c.name as campaign_name
    FROM outreach o
    JOIN leads l ON o.lead_id = l.id
    LEFT JOIN campaigns c ON o.campaign_id = c.id
    WHERE o.workspace_id = ?
    ORDER BY o.created_at DESC
");
$outreachStmt->execute([$currentWorkspaceId]);
$outreachLogs = $outreachStmt->fetchAll(PDO::FETCH_ASSOC);

$totalSent = count($outreachLogs);
$deliveredCount = 0;
$repliedCount = 0;
foreach ($outreachLogs as $log) {
    if (in_array($log['status'], ['delivered', 'opened', 'replied'], true)) $deliveredCount++;
    if ($log['status'] === 'replied') $repliedCount++;
}
$replyRate = $totalSent > 0 ? round(($repliedCount / $totalSent) * 100, 1) : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Outreach Automation — Primovative Co.</title>
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
          <i class="bi bi-check-circle me-1"></i> Outreach message successfully dispatched and logged!
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4 py-2 small" role="alert">
          <i class="bi bi-exclamation-circle me-1"></i>
          <?php if ($_GET['error'] === 'no_email'): ?>
            Selected prospect does not have an email address on file. Please choose SMS/LinkedIn or add an email in their Lead Profile.
          <?php else: ?>
            Could not send message. Please ensure all required fields are filled.
          <?php endif; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- KPI STATS -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-violet"><i class="bi bi-send-fill"></i></div>
            <div class="stat-content">
              <span class="stat-label">Total Outreach</span>
              <div class="stat-value-row"><strong><?= number_format($totalSent) ?></strong></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-check-all"></i></div>
            <div class="stat-content">
              <span class="stat-label">Delivered</span>
              <div class="stat-value-row"><strong><?= number_format($deliveredCount) ?></strong></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-amber"><i class="bi bi-chat-left-dots"></i></div>
            <div class="stat-content">
              <span class="stat-label">Replies</span>
              <div class="stat-value-row"><strong><?= number_format($repliedCount) ?></strong></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="stat-card">
            <div class="stat-icon icon-blue"><i class="bi bi-percent"></i></div>
            <div class="stat-content">
              <span class="stat-label">Response Rate</span>
              <div class="stat-value-row"><strong><?= $replyRate ?>%</strong></div>
            </div>
          </div>
        </div>
      </div>

      <!-- OUTREACH LOGS TABLE -->
      <div class="outreach-card">
        <div class="outreach-toolbar">
          <div>
            <h3 class="h6 fw-bold mb-0">Outreach Deliveries</h3>
            <small class="text-muted">Track outbound communications, channel engagement, and delivery lifecycle</small>
          </div>
          <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: 220px;">
              <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
              <input type="text" id="outreachSearch" class="form-control border-start-0" placeholder="Search deliveries...">
            </div>
            <select id="channelFilter" class="form-select form-select-sm" style="width: 130px;">
              <option value="all">All Channels</option>
              <option value="email">Email</option>
              <option value="linkedin">LinkedIn</option>
              <option value="sms">SMS</option>
            </select>
            <select id="statusFilter" class="form-select form-select-sm" style="width: 130px;">
              <option value="all">All Statuses</option>
              <option value="sent">Sent</option>
              <option value="delivered">Delivered</option>
              <option value="opened">Opened</option>
              <option value="replied">Replied</option>
              <option value="bounced">Bounced</option>
            </select>
            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#composeModal">
              <i class="bi bi-pencil-square"></i> Compose
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table outreach-table" id="outreachTable">
            <thead>
              <tr>
                <th>Recipient / Company</th>
                <th>Campaign</th>
                <th>Channel</th>
                <th>Subject &amp; Message</th>
                <th>Delivery Status</th>
                <th>Sent Date</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($outreachLogs)): ?>
                <tr>
                  <td colspan="7" class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>
                    No outreach messages dispatched yet. Click <strong>Compose</strong> to start!
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($outreachLogs as $log): ?>
                  <?php
                    $leadInitials = strtoupper(substr($log['lead_name'], 0, 1) . substr($log['lead_company'] ?: 'C', 0, 1));
                    $channelIcon = 'envelope-fill';
                    if ($log['channel'] === 'linkedin') $channelIcon = 'linkedin';
                    elseif ($log['channel'] === 'sms') $channelIcon = 'chat-dots-fill';
                  ?>
                  <tr class="outreach-row" data-channel="<?= htmlspecialchars($log['channel']) ?>" data-status="<?= htmlspecialchars($log['status']) ?>">
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <div class="avatar" style="width:34px;height:34px;font-size:12px;"><?= htmlspecialchars($leadInitials) ?></div>
                        <div class="min-w-0">
                          <div class="fw-bold text-dark text-truncate" style="max-width: 180px;"><?= htmlspecialchars($log['lead_company'] ?: $log['lead_name']) ?></div>
                          <div class="text-muted small text-truncate" style="max-width: 180px; font-size: 11px;">
                            <?= htmlspecialchars($log['lead_name']) ?> &bull; <?= htmlspecialchars($log['lead_email']) ?>
                          </div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark border small fw-normal"><?= htmlspecialchars($log['campaign_name'] ?: 'Direct') ?></span>
                    </td>
                    <td>
                      <span class="badge bg-light text-secondary border small">
                        <i class="bi bi-<?= $channelIcon ?> me-1 text-primary"></i> <?= ucfirst($log['channel']) ?>
                      </span>
                    </td>
                    <td>
                      <div class="fw-semibold text-dark text-truncate" style="max-width: 240px; font-size: 13px;"><?= htmlspecialchars($log['subject']) ?></div>
                      <small class="text-muted text-truncate d-block" style="max-width: 240px; font-size: 11px;"><?= htmlspecialchars(substr($log['message'], 0, 60)) ?>...</small>
                    </td>
                    <td>
                      <span class="outreach-status-pill status-<?= htmlspecialchars($log['status']) ?>">
                        <span class="status-pill-dot"></span>
                        <?= ucfirst($log['status']) ?>
                      </span>
                    </td>
                    <td>
                      <span class="text-muted small"><?= date('M d, Y', strtotime($log['sent_at'] ?: $log['created_at'])) ?></span>
                      <small class="text-muted d-block" style="font-size: 10px;"><?= date('g:i A', strtotime($log['sent_at'] ?: $log['created_at'])) ?></small>
                    </td>
                    <td class="text-end">
                      <a href="../conversations/index.php<?= !empty($log['conversation_id']) ? '?id=' . (int)$log['conversation_id'] : '' ?>" class="btn btn-sm btn-outline-primary" title="View thread in Conversations">
                        <i class="bi bi-chat-dots me-1"></i> Thread
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>

    <!-- COMPOSE MODAL -->
    <div class="modal fade" id="composeModal" tabindex="-1" aria-labelledby="composeModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
          <div class="modal-header border-0 pb-0">
            <div>
              <h5 class="modal-title fw-bold" id="composeModalLabel">Send Outreach Communication</h5>
              <p class="text-muted small mb-0">Dispatch a targeted message to your qualified B2B prospect.</p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="send.php" id="composeForm">
            <?= csrf_field() ?>

            <div class="modal-body">
              <div class="row g-3 mb-3">
                <div class="col-md-7">
                  <label class="form-label small fw-semibold">Lead Recipient <span class="text-danger">*</span></label>
                  <select name="lead_id" id="recipientSelect" class="form-select" required>
                    <option value="">-- Choose Lead --</option>
                    <?php foreach ($leads as $l): ?>
                      <option value="<?= $l['id'] ?>" <?= $prefillLeadId === (int)$l['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($l['company'] ?: $l['name']) ?> — <?= htmlspecialchars($l['name']) ?> (<?= htmlspecialchars($l['email'] ?: 'No Email') ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-5">
                  <label class="form-label small fw-semibold">Delivery Channel</label>
                  <select name="channel" class="form-select">
                    <option value="email">Email</option>
                    <option value="linkedin">LinkedIn InMail</option>
                    <option value="sms">SMS Text</option>
                  </select>
                </div>
              </div>

              <!-- TEMPLATE SELECTOR -->
              <div class="mb-3">
                <label class="form-label small fw-semibold d-block">Quick Outreach Templates</label>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="template-picker-pill" data-subject="Exploring synergies with {{company}}" data-body="Hi {{name}},&#10;&#10;I came across {{company}} and was really impressed by your recent growth. We help businesses in your space scale customer acquisition reliably.&#10;&#10;Would you be open to a brief 10-minute introductory call this Thursday?&#10;&#10;Best regards,">
                    <i class="bi bi-lightning-charge"></i> Value Pitch
                  </button>
                  <button type="button" class="template-picker-pill" data-subject="Quick question regarding {{company}}" data-body="Hi {{name}},&#10;&#10;Just checking in following up on my previous note. Wanted to see if streamlining your client pipeline is a priority this quarter?&#10;&#10;Let me know if you have a moment to connect.">
                    <i class="bi bi-clock-history"></i> Follow-up
                  </button>
                  <button type="button" class="template-picker-pill" data-subject="Partnership inquiry for {{name}}" data-body="Hello {{name}},&#10;&#10;We are actively looking to partner with leaders in your sector and noticed {{company}}'s market leadership.&#10;&#10;I'd love to share some relevant benchmark data with your team. What day works best for you?">
                    <i class="bi bi-handshake"></i> Partnership
                  </button>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-semibold">Subject Line <span class="text-danger">*</span></label>
                <input type="text" name="subject" id="subjectInput" class="form-control" placeholder="e.g. Quick question regarding {{company}}" required>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label small fw-semibold mb-0">Message Body <span class="text-danger">*</span></label>
                  <div class="small">
                    <span class="text-muted me-1">Insert Tag:</span>
                    <button type="button" class="btn btn-xs btn-light border py-0 px-1 me-1 tag-btn" data-tag="{{name}}">{{name}}</button>
                    <button type="button" class="btn btn-xs btn-light border py-0 px-1 tag-btn" data-tag="{{company}}">{{company}}</button>
                  </div>
                </div>
                <textarea name="message" id="messageInput" class="form-control" rows="5" placeholder="Write your personalized outreach message..." required></textarea>
                <small class="text-muted">Merge tags will automatically replace with actual recipient values upon dispatch.</small>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary px-4"><i class="bi bi-send me-1"></i> Send Outreach</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Filter Outreach Table by search, channel, and status
const searchInput = document.getElementById('outreachSearch');
const channelSelect = document.getElementById('channelFilter');
const statusSelect = document.getElementById('statusFilter');
const rows = document.querySelectorAll('.outreach-row');

function filterOutreach() {
  const query = (searchInput?.value || '').toLowerCase();
  const channel = channelSelect?.value || 'all';
  const status = statusSelect?.value || 'all';

  rows.forEach(row => {
    const text = row.innerText.toLowerCase();
    const rowChannel = row.dataset.channel;
    const rowStatus = row.dataset.status;

    const matchesQuery = query === '' || text.includes(query);
    const matchesChannel = channel === 'all' || rowChannel === channel;
    const matchesStatus = status === 'all' || rowStatus === status;

    if (matchesQuery && matchesChannel && matchesStatus) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

searchInput?.addEventListener('input', filterOutreach);
channelSelect?.addEventListener('change', filterOutreach);
statusSelect?.addEventListener('change', filterOutreach);

// Template picker pills
document.querySelectorAll('.template-picker-pill').forEach(pill => {
  pill.addEventListener('click', function() {
    document.getElementById('subjectInput').value = this.dataset.subject;
    document.getElementById('messageInput').value = this.dataset.body;
  });
});

// Merge tag inserter
document.querySelectorAll('.tag-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const tag = this.dataset.tag;
    const textarea = document.getElementById('messageInput');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const val = textarea.value;
    textarea.value = val.substring(0, start) + tag + val.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
  });
});
</script>
</body>
</html>
