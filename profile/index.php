<?php
require_once __DIR__ . '/../includes/auth_check.php';

$pageEyebrow = 'Account';
$pageTitle = 'My Profile';
$activePage = 'profile';

$uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$user = $uStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$successMsg = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'updated') $successMsg = 'Profile and avatar updated successfully!';
    elseif ($_GET['success'] === 'avatar_removed') $successMsg = 'Avatar removed. Default profile avatar restored.';
    else $successMsg = 'Changes saved successfully.';
}

$errorMsg = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'email_taken': $errorMsg = 'That email address is already in use by another account.'; break;
        case 'missing_fields': $errorMsg = 'Please provide both your display name and email address.'; break;
        case 'file_too_large': $errorMsg = 'Uploaded image exceeds the 5MB file size limit.'; break;
        case 'invalid_file_type':
        case 'invalid_mime_type': $errorMsg = 'Invalid file format. Please upload a valid JPG, PNG, or WebP image.'; break;
        case 'corrupt_image': $errorMsg = 'Image file appears corrupt or unreadable. Please choose a different file.'; break;
        case 'upload_failed': $errorMsg = 'Could not save the uploaded image to the server.'; break;
        default: $errorMsg = 'An unexpected error occurred while updating your profile.'; break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/Logo/primovativelogo.png">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Profile — Primovative Co.</title>
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
      <div class="row justify-content-center">
        <div class="col-12 col-xl-8">
          <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4 py-2 small" role="alert">
              <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($successMsg) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php elseif (!empty($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4 py-2 small" role="alert">
              <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($errorMsg) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <div class="dashboard-card p-4">
            <!-- PROFILE HEADER WITH AVATAR PREVIEW -->
            <div class="d-flex flex-column flex-sm-row align-items-center gap-4 mb-4 pb-4 border-bottom text-center text-sm-start">
              <div class="avatar-preview-wrapper" id="avatarPreviewContainer">
                <?php if (!empty($userPicUrl)): ?>
                  <img src="<?= htmlspecialchars($userPicUrl) ?>" alt="Avatar" class="avatar-preview-img" id="avatarPreviewImg">
                <?php else: ?>
                  <div class="avatar-default-large" id="avatarDefaultPlaceholder"><?= htmlspecialchars($initials) ?></div>
                  <img src="" alt="Avatar" class="avatar-preview-img d-none" id="avatarPreviewImg">
                <?php endif; ?>
                <label for="avatarInput" class="avatar-upload-overlay" title="Upload new photo">
                  <i class="bi bi-camera fs-5 mb-1"></i>
                  <span>Change</span>
                </label>
              </div>

              <div class="flex-grow-1">
                <h2 class="h5 fw-bold mb-1"><?= htmlspecialchars($user['name'] ?? $userName) ?></h2>
                <div class="text-muted small mb-2"><?= htmlspecialchars($user['email'] ?? $userEmail) ?></div>
                <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-sm-start gap-2 mb-2">
                  <span class="badge bg-light text-dark border">
                    <i class="bi bi-<?= ($user['auth_provider'] ?? '') === 'google' ? 'google text-danger' : 'envelope' ?> me-1"></i>
                    Signed in via <?= ucfirst($user['auth_provider'] ?? 'Email') ?>
                  </span>
                  <span class="badge bg-success-subtle text-success border border-success-subtle">Active User</span>
                </div>
                <div class="d-flex align-items-center justify-content-center justify-content-sm-start gap-2">
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('avatarInput').click();">
                    <i class="bi bi-upload me-1"></i> Upload Photo
                  </button>
                  <?php if (!empty($user['profile_picture'])): ?>
                    <form method="POST" action="update.php" class="d-inline" onsubmit="return confirm('Remove your current profile photo?');">
                      <input type="hidden" name="action" value="remove_avatar">
                      <?= csrf_field() ?>

                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash3 me-1"></i> Remove
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- PROFILE EDIT FORM -->
            <form method="POST" action="update.php" enctype="multipart/form-data" id="profileForm">
              <?= csrf_field() ?>

              <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp" class="d-none">

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Display Name</label>
                  <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name'] ?? $userName) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Email Address</label>
                  <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? $userEmail) ?>" required>
                </div>
              </div>

              <?php if (($user['auth_provider'] ?? '') !== 'google'): ?>
                <div class="mb-3">
                  <label class="form-label small fw-semibold">Change Password (leave blank to keep current)</label>
                  <input type="password" name="new_password" class="form-control" placeholder="New password (at least 8 chars)" minlength="8">
                </div>
              <?php endif; ?>

              <div class="small text-muted mb-4">
                <i class="bi bi-shield-check me-1"></i> Avatar accepts JPEG, PNG, or WebP up to 5MB. Unique filename hashing and binary MIME verification are performed on upload.
              </div>

              <div class="border-top pt-3 d-flex justify-content-between align-items-center">
                <small class="text-muted">Account created: <?= !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'Recently' ?></small>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Live Image Preview before saving
const avatarInput = document.getElementById('avatarInput');
const avatarPreviewImg = document.getElementById('avatarPreviewImg');
const avatarDefaultPlaceholder = document.getElementById('avatarDefaultPlaceholder');

if (avatarInput) {
  avatarInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
      if (file.size > 5 * 1024 * 1024) {
        alert('File is too large. Maximum allowed size is 5MB.');
        this.value = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = function(evt) {
        avatarPreviewImg.src = evt.target.result;
        avatarPreviewImg.classList.remove('d-none');
        if (avatarDefaultPlaceholder) {
          avatarDefaultPlaceholder.classList.add('d-none');
        }
      };
      reader.readAsDataURL(file);
    }
  });
}
</script>
</body>
</html>
