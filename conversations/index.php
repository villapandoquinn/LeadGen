<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageEyebrow = 'Prospects';
$pageTitle = 'Conversations';
$activePage = 'conversations';

$convStmt = $pdo->prepare("
    SELECT c.*, l.name as lead_name, l.company as lead_company, l.email as lead_email, l.phone as lead_phone, l.status as lead_status, cmp.name as campaign_name
    FROM conversations c
    JOIN leads l ON c.lead_id = l.id
    LEFT JOIN campaigns cmp ON c.campaign_id = cmp.id
    WHERE c.workspace_id = ?
    ORDER BY c.last_message_at DESC
");
$convStmt->execute([$currentWorkspaceId]);
$conversations = $convStmt->fetchAll(PDO::FETCH_ASSOC);

$activeId = isset($_GET['id']) ? (int)$_GET['id'] : (!empty($conversations) ? $conversations[0]['id'] : 0);

$activeConv = null;
$messages = [];

if ($activeId > 0) {
    foreach ($conversations as $c) {
        if ($c['id'] === $activeId) {
            $activeConv = $c;
            break;
        }
    }
    if ($activeConv) {
        if ($activeConv['unread_count'] > 0) {
            $pdo->prepare("UPDATE conversations SET unread_count = 0 WHERE id = ? AND workspace_id = ?")->execute([$activeId, $currentWorkspaceId]);
        }
        $mStmt = $pdo->prepare("SELECT * FROM conversation_messages WHERE conversation_id = ? ORDER BY created_at ASC");
        $mStmt->execute([$activeId]);
        $messages = $mStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Conversations Inbox — Primovative Co.</title>
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
      <div class="conv-container">
        <!-- THREAD LIST SIDEBAR -->
        <div class="conv-sidebar">
          <div class="conv-sidebar-header">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h2 class="h6 fw-bold mb-0">Conversations <span class="badge bg-primary-subtle text-primary ms-1"><?= count($conversations) ?></span></h2>
              <a href="../outreach/index.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil-square me-1"></i> New</a>
            </div>
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
              <input type="text" id="convSearch" class="form-control border-start-0" placeholder="Filter prospects...">
            </div>
          </div>

          <div class="conv-list" id="convList">
            <?php if (empty($conversations)): ?>
              <div class="text-center py-5 text-muted px-3">
                <i class="bi bi-chat-square-dots fs-3 d-block mb-2 text-secondary opacity-50"></i>
                <small>No active conversation threads. Dispatch an outreach to begin receiving replies!</small>
              </div>
            <?php else: ?>
              <?php foreach ($conversations as $conv): ?>
                <?php
                  $convInitials = strtoupper(substr($conv['lead_name'], 0, 1) . substr($conv['lead_company'] ?: 'C', 0, 1));
                  $isCurrent = ($conv['id'] === $activeId);
                  $hasUnread = ((int)$conv['unread_count'] > 0);
                ?>
                <a href="index.php?id=<?= $conv['id'] ?>" class="conv-item <?= $isCurrent ? 'active' : '' ?>" data-company="<?= htmlspecialchars(strtolower($conv['lead_company'])) ?>" data-name="<?= htmlspecialchars(strtolower($conv['lead_name'])) ?>">
                  <div class="avatar" style="width: 38px; height: 38px; font-size: 13px; flex-shrink: 0;"><?= htmlspecialchars($convInitials) ?></div>
                  <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <strong class="text-dark text-truncate small <?= $hasUnread ? 'fw-bold' : 'fw-semibold' ?>"><?= htmlspecialchars($conv['lead_company'] ?: $conv['lead_name']) ?></strong>
                      <small class="text-muted" style="font-size: 11px;"><?= date('M d', strtotime($conv['last_message_at'])) ?></small>
                    </div>
                    <div class="text-secondary small text-truncate mb-1" style="font-size: 11.5px;"><?= htmlspecialchars($conv['lead_name']) ?></div>
                    <div class="d-flex justify-content-between align-items-center">
                      <p class="text-muted text-truncate mb-0 small" style="font-size: 12px; max-width: 210px;"><?= htmlspecialchars($conv['last_message']) ?></p>
                      <?php if ($hasUnread): ?>
                        <span class="badge bg-primary rounded-pill px-1.5 py-0.5" style="font-size: 10px;"><?= (int)$conv['unread_count'] ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <!-- ACTIVE CHAT VIEW -->
        <div class="conv-main">
          <?php if ($activeConv): ?>
            <?php
              $leadInitials = strtoupper(substr($activeConv['lead_name'], 0, 1) . substr($activeConv['lead_company'] ?: 'C', 0, 1));
            ?>
            <!-- HEADER -->
            <div class="conv-main-header">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar" style="width: 42px; height: 42px; font-size: 15px;"><?= htmlspecialchars($leadInitials) ?></div>
                <div>
                  <h3 class="h6 fw-bold mb-0 text-dark"><?= htmlspecialchars($activeConv['lead_company'] ?: $activeConv['lead_name']) ?></h3>
                  <div class="d-flex flex-wrap align-items-center gap-2 text-muted small mt-0.5">
                    <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($activeConv['lead_name']) ?></span>
                    <?php if (!empty($activeConv['lead_email'])): ?>
                      <span>&bull;</span>
                      <a href="mailto:<?= htmlspecialchars($activeConv['lead_email']) ?>" class="text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($activeConv['lead_email']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($activeConv['lead_phone'])): ?>
                      <span>&bull;</span>
                      <a href="tel:<?= htmlspecialchars($activeConv['lead_phone']) ?>" class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($activeConv['lead_phone']) ?></a>
                    <?php endif; ?>
                    <span>&bull;</span>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($activeConv['campaign_name'] ?: 'Direct Campaign') ?></span>
                  </div>
                </div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <a href="../leads/view.php?id=<?= $activeConv['lead_id'] ?>" class="btn btn-outline-secondary btn-sm" title="View Full Prospect Profile">
                  <i class="bi bi-person-badge me-1"></i> Lead Profile
                </a>
              </div>
            </div>

            <!-- MESSAGES TIMELINE -->
            <div class="conv-messages" id="messagesContainer">
              <div class="conv-date-divider">
                <span>Conversation Started &bull; <?= date('M d, Y', strtotime($activeConv['created_at'])) ?></span>
              </div>

              <?php foreach ($messages as $msg): ?>
                <?php $isUser = ($msg['sender_type'] === 'user'); ?>
                <div class="chat-row <?= $isUser ? 'chat-row-user' : 'chat-row-lead' ?>">
                  <?php if (!$isUser): ?>
                    <div class="avatar" style="width: 30px; height: 30px; font-size: 11px; flex-shrink: 0;" title="<?= htmlspecialchars($msg['sender_name']) ?>"><?= htmlspecialchars($leadInitials) ?></div>
                  <?php endif; ?>

                  <div class="<?= $isUser ? 'chat-bubble-user' : 'chat-bubble-lead' ?>">
                    <div class="small fw-semibold mb-1" style="font-size: 11px; opacity: <?= $isUser ? '0.85' : '0.75' ?>;">
                      <?= htmlspecialchars($msg['sender_name']) ?>
                    </div>
                    <div style="white-space: pre-wrap; word-break: break-word;"><?= htmlspecialchars($msg['message']) ?></div>
                    <div class="chat-time d-flex align-items-center <?= $isUser ? 'justify-content-end' : 'justify-content-start' ?> gap-1">
                      <span><?= date('g:i A', strtotime($msg['created_at'])) ?></span>
                      <?php if ($isUser): ?>
                        <i class="bi bi-check2-all" style="font-size: 13px;"></i>
                      <?php endif; ?>
                    </div>
                  </div>

                  <?php if ($isUser): ?>
                    <?php if (!empty($userPicUrl)): ?>
                      <img src="<?= htmlspecialchars($userPicUrl) ?>" alt="Avatar" class="rounded-circle" style="width: 30px; height: 30px; object-fit: cover; flex-shrink: 0;">
                    <?php else: ?>
                      <div class="avatar" style="width: 30px; height: 30px; font-size: 11px; flex-shrink: 0;"><?= htmlspecialchars($initials) ?></div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>

                <?= csrf_field() ?>

            <!-- CHAT COMPOSER -->
            <div class="conv-composer">
              <form method="POST" action="send.php" id="replyForm" class="d-flex flex-column gap-2">
                <input type="hidden" name="conversation_id" value="<?= $activeConv['id'] ?>">
                <div class="position-relative">
                  <textarea name="message" id="replyTextarea" class="form-control" rows="3" placeholder="Type your response to <?= htmlspecialchars($activeConv['lead_name']) ?>... (Press Ctrl + Enter to send)" required style="resize: none;"></textarea>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                  <small class="text-muted"><kbd class="bg-light text-muted border px-1">Ctrl + Enter</kbd> to quick-send</small>
                  <button type="submit" class="btn btn-primary d-flex align-items-center gap-1 px-4">
                    <i class="bi bi-send-fill"></i> Send Reply
                  </button>
                </div>
              </form>
            </div>
          <?php else: ?>
            <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted p-5 text-center">
              <div class="stat-icon icon-violet mb-3" style="width: 64px; height: 64px; font-size: 28px;">
                <i class="bi bi-chat-dots"></i>
              </div>
              <h3 class="h5 fw-bold text-dark mb-1">Select a Conversation</h3>
              <p class="text-muted small" style="max-width: 380px;">Choose a prospect thread from the inbox on the left to read previous interactions and send real-time responses.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Auto-scroll messages to bottom
const messagesContainer = document.getElementById('messagesContainer');
if (messagesContainer) {
  messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Ctrl + Enter shortcut to send reply
const replyTextarea = document.getElementById('replyTextarea');
const replyForm = document.getElementById('replyForm');
if (replyTextarea && replyForm) {
  replyTextarea.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      if (this.value.trim() !== '') {
        replyForm.submit();
      }
    }
  });
}

// Client-side search in conversation thread list
const convSearch = document.getElementById('convSearch');
if (convSearch) {
  convSearch.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.conv-item').forEach(item => {
      const company = item.dataset.company || '';
      const name = item.dataset.name || '';
      const matches = q === '' || company.includes(q) || name.includes(q);
      item.style.display = matches ? 'flex' : 'none';
    });
  });
}
</script>
</body>
</html>
