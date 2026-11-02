<?php
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
require_csrf();


$action = trim($_POST['action'] ?? 'update_profile');

// 1. Handle Avatar Removal
if ($action === 'remove_avatar') {
    try {
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldPic = $stmt->fetchColumn();

        if (!empty($oldPic) && strpos($oldPic, 'uploads/avatars/') === 0) {
            $oldPath = __DIR__ . '/../' . $oldPic;
            if (file_exists($oldPath) && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $pdo->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?")->execute([$userId]);
        $_SESSION['profile_picture'] = null;

        header('Location: index.php?success=avatar_removed');
        exit;
    } catch (Throwable $e) {
        error_log("Failed to remove avatar: " . $e->getMessage());
        header('Location: index.php?error=db_error');
        exit;
    }
}

// 2. Standard Profile & Avatar Update
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$newPassword = $_POST['new_password'] ?? '';

if (empty($name) || empty($email)) {
    header('Location: index.php?error=missing_fields');
    exit;
}

// Basic email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.php?error=invalid_email');
    exit;
}

try {
    // Check for email collision
    $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $chk->execute([$email, $userId]);
    if ($chk->fetch()) {
        header('Location: index.php?error=email_taken');
        exit;
    }

    // Process Avatar Upload if provided
    $newAvatarPath = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['avatar'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            header('Location: index.php?error=upload_error');
            exit;
        }

        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            header('Location: index.php?error=file_too_large');
            exit;
        }

        // Validate extension
        $rawExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($rawExt, $allowedExts, true)) {
            header('Location: index.php?error=invalid_file_type');
            exit;
        }

        // Validate actual MIME type using finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!array_key_exists($mime, $allowedMimes)) {
            header('Location: index.php?error=invalid_mime_type');
            exit;
        }

        // Validate image dimensions / header to verify it's a real image
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            header('Location: index.php?error=corrupt_image');
            exit;
        }

        $targetExt = $allowedMimes[$mime];
        $filename = 'avatar_' . (int)$userId . '_' . bin2hex(random_bytes(8)) . '.' . $targetExt;
        $uploadDir = __DIR__ . '/../uploads/avatars/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            header('Location: index.php?error=upload_failed');
            exit;
        }

        $newAvatarPath = 'uploads/avatars/' . $filename;

        // Clean up old avatar if stored locally
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldPic = $stmt->fetchColumn();
        if (!empty($oldPic) && strpos($oldPic, 'uploads/avatars/') === 0) {
            $oldPath = __DIR__ . '/../' . $oldPic;
            if (file_exists($oldPath) && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }
    }

    // Prepare SQL update
    $fields = ["name = ?", "email = ?"];
    $params = [$name, $email];

    if (!empty($newPassword) && strlen($newPassword) >= 8) {
        $fields[] = "password_hash = ?";
        $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    if ($newAvatarPath !== null) {
        $fields[] = "profile_picture = ?";
        $params[] = $newAvatarPath;
        $_SESSION['profile_picture'] = $newAvatarPath;
    }

    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
    $pdo->prepare($sql)->execute($params);

    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;

    header('Location: index.php?success=updated');
    exit;

} catch (Throwable $e) {
    error_log("Failed to update profile: " . $e->getMessage());
    header('Location: index.php?error=db_error');
    exit;
}

