<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';

if (session_status() === PHP_SESSION_NONE)
  session_start();

$userId = $_SESSION['user']['id'] ?? null;
if (!$userId) {
  die('Session expired. Please log in again.');
}

$stmt = $pdo->prepare("SELECT username, name, status, image FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  die('User not found.');
}

$profileImagePath = $_SERVER['DOCUMENT_ROOT'] . '/vehicle/uploads/profile/' . ($user['image'] ?? '');
$profileImageUrl = (file_exists($profileImagePath) && !empty($user['image']))
  ? "/vehicle/uploads/profile/" . htmlspecialchars($user['image'])
  : null;
?>
<title>My Profile</title>

<style>
  .profile-image {
    width: 100%;
    max-width: 180px;
    height: auto;
    aspect-ratio: 1 / 1;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #0d6efd;
  }

  .profile-icon-placeholder {
    width: 180px;
    height: 180px;
    border-radius: 50%;
    background-color: #6c757d;
    color: white;
    font-size: 5rem;
    display: flex;
    justify-content: center;
    align-items: center;
    border: 3px solid #0d6efd;
    margin: 0 auto;
  }
</style>
</head>

<body>
  <div class="container py-4">
    <h2 class="mb-4 text-center">My Profile</h2>
    <div class="card shadow-sm mx-auto" style="max-width: 400px;">
      <div class="card-body text-center">
        <?php if ($profileImageUrl): ?>
          <img src="<?= $profileImageUrl ?>" alt="Profile Image" class="profile-image mb-4" />
        <?php else: ?>
          <div class="profile-icon-placeholder mb-4">
            <i class="fas fa-user"></i>
          </div>
        <?php endif; ?>

        <table class="table table-borderless text-start">
          <tbody>
            <tr>
              <th scope="row" style="width: 30%;">Username</th>
              <td><?= htmlspecialchars($user['username']) ?></td>
            </tr>
            <tr>
              <th scope="row">Name</th>
              <td><?= htmlspecialchars($user['name']) ?></td>
            </tr>
            <tr>
              <th scope="row">Status</th>
              <td><?= htmlspecialchars($user['status']) ?></td>
            </tr>
            <tr>
              <td colspan="2" class="text-center pt-3">
                <a href="admin.php?page=users/profile_edit" class="btn btn-primary w-100">
                  <i class="fas fa-edit me-2"></i> Edit Profile
                </a>
              </td>
            </tr>
          </tbody>
        </table>

      </div>
    </div>
  </div>