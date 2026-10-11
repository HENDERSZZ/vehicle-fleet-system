<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE)
  session_start();

$userId = $_SESSION['user']['id'] ?? null;

if (!$userId) {
  die('Session expired. Please log in again.');
}

$error = null;

// Define upload directory for profile images
$uploadDir = __DIR__ . '/../../uploads/profile/';

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $newPassword = $_POST['new_password'] ?? '';
  $currentPassword = $_POST['current_password'] ?? '';
  $imageFile = $_FILES['image'] ?? null;

  // Check username uniqueness
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
  $stmt->execute([$username, $userId]);
  if ($stmt->fetchColumn() > 0) {
    $error = 'Username already taken.';
  }

  if (!$error) {
    // Check current password
    $stmt = $pdo->prepare("SELECT password, image FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData || !password_verify($currentPassword, $userData['password'])) {
      $error = 'Incorrect current password.';
    } else {
      try {
        $updates = ['username' => $username];
        $params = [$username];

        // Handle image upload using reusable function
        if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
          // Validate image MIME type and size (max 200KB)
          $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
          if (in_array($imageFile['type'], $allowedTypes) && $imageFile['size'] <= 200 * 1024) {
            $uploadedFilename = uploadProfileImage($imageFile, $uploadDir, $userData['image']);
            if ($uploadedFilename) {
              $updates['image'] = $uploadedFilename;
              $params[] = $uploadedFilename;
            } else {
              $error = 'Failed to save uploaded image.';
            }
          } else {
            $error = 'Invalid image type or file too large (max 200KB).';
          }
        }

        // Handle password update
        if (!empty($newPassword)) {
          $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
          $updates['password'] = $hashedPassword;
          $params[] = $hashedPassword;
        }

        if (!$error) {
          // Build query dynamically
          $setPart = implode(', ', array_map(fn($key) => "$key = ?", array_keys($updates)));
          $params[] = $userId;

          $stmt = $pdo->prepare("UPDATE users SET $setPart WHERE id = ?");
          $stmt->execute($params);

          header("Location: /vehicle/admin.php?page=dashboard&success=profile_updated");
          exit;
        }
      } catch (Exception $e) {
        $error = 'Update failed. Please try again.';
      }
    }
  }
}

// Initial load or form redisplay
$stmt = $pdo->prepare("SELECT name, username, image FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
  die('User not found.');
}
?>

<title>Update Profile</title>

<div class="mb-4 text-center">
  <a href="admin.php?page=users/profile" class="btn btn-secondary">
    <i class="fas fa-arrow-left me-2"></i> Back to My Profile
  </a>
</div>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-4">
      <h3 class="mb-4 text-center">Update Profile</h3>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form action="" method="POST" enctype="multipart/form-data" novalidate>
        <div class="mb-3">
          <label for="username" class="form-label">User Name</label>
          <input id="username" type="text" name="username" class="form-control" required
            value="<?= htmlspecialchars($user['username']) ?>">
        </div>

        <div class="mb-3 text-center">
          <label class="form-label d-block">Current Profile Image</label>
          <?php if (!empty($user['image'])): ?>
            <img src="../../uploads/profile/<?= htmlspecialchars($user['image']) ?>" alt="Profile Image"
              class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
          <?php else: ?>
            <div class="border rounded-circle d-inline-block mb-3"
              style="width: 120px; height: 120px; line-height: 120px; font-size: 48px; color: #888;">
              <i class="fas fa-user"></i>
            </div>
          <?php endif; ?>
          <input type="file" name="image" class="form-control" accept="image/*" aria-describedby="imageHelp">
          <div id="imageHelp" class="form-text">Max size 200KB. Allowed types: jpg, png, gif.</div>
        </div>

        <div class="mb-3">
          <label for="new_password" class="form-label">New Password (leave blank to keep current)</label>
          <input id="new_password" type="password" name="new_password" class="form-control" autocomplete="new-password">
        </div>

        <div class="mb-3">
          <label for="current_password" class="form-label">Confirm Current Password <span
              class="text-danger">*</span></label>
          <input id="current_password" type="password" name="current_password" class="form-control" required
            autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary w-100">Update Profile</button>
      </form>
    </div>
  </div>
</div>