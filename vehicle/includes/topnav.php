<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Access user ID from session
$userId = $_SESSION['user']['id'] ?? null;
$group_id = $user['group_id'] ?? null;
$isAdmin = ($group_id == 1);
$isSuperAdmin = $user['is_superadmin'] ?? false;
$user_level = $user['level'] ?? -1;
$page = $_GET['page'] ?? 'dashboard';

// Default values
$userName = 'User';
$userImage = 'uploads/profile/default.png';

// Fetch name and image from DB if ID is available
if ($userId) {
  require_once 'config.php'; // your PDO connection file

  $stmt = $pdo->prepare("SELECT name, image FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  $profile = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($profile) {
    $userName = $profile['name'] ?? $userName;

    $imageFile = $profile['image'] ?? '';
    $imagePath = 'uploads/profile/' . $imageFile;

    if (!empty($imageFile) && file_exists($imagePath)) {
      $userImage = $imagePath;
    } else {
      $userImage = 'uploads/profile/default.png';
    }
  }
}

// --- Access-controlled count for pending requisitions ---
$pendingRequisitionCount = 0;
try {
  $accessClause = getRequisitionAccessClause();
  $sql = "
        SELECT COUNT(*) 
        FROM requisitions r
        LEFT JOIN vehicles v ON r.vehicle_regno = v.regno
        LEFT JOIN locations l ON v.location_id = l.id
        LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
        LEFT JOIN counties co ON v.county_id = co.id
        LEFT JOIN regions rg ON co.region_id = rg.id
        WHERE r.status = 'pending' $accessClause
    ";
  $stmt = $pdo->query($sql);
  $pendingRequisitionCount = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
  error_log("❌ Error counting pending requisitions: " . $e->getMessage());
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
  <div class="container-fluid">
    <!-- Brand -->
    <a class="navbar-brand" href="/vehicle/admin.php?page=dashboard" style="display: flex; align-items: center;">
      <span class="me-2" style="position: relative; width: 24px; height: 24px;">
        <i class="fas fa-car" style="font-size: 1.25rem;"></i>
        <i class="fas fa-wave-square"
          style="position: absolute; top: -6px; right: -10px; font-size: 0.75rem; color: #0d6efd;"></i>
      </span>
      VMS SYSTEM
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topnav"
      aria-controls="topnav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="topnav">
      <ul class="navbar-nav ms-auto align-items-center">

        <!-- Dashboard -->
        <li class="nav-item show-on-collapsed">
          <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="admin.php?page=dashboard">
            <i class="fas fa-tachometer-alt me-1"></i><span>Dashboard</span>
          </a>
        </li>

        <!-- Categories -->
        <li class="nav-item show-on-collapsed">
          <a class="nav-link <?= str_contains($page, 'categories') ? 'active' : '' ?>"
            href="admin.php?page=categories/index">
            <i class="fa fa-th-large me-1"></i><span>Categories</span>
          </a>
        </li>

        <!-- Requisitions -->
        <li class="nav-item show-on-collapsed">
          <a class="nav-link <?= str_contains($page, 'requisitions') ? 'active' : '' ?>"
            href="admin.php?page=requisitions/index">
            <i class="fas fa-file-signature me-1"></i><span>Requisitions</span>
            <?php if (in_array($group_id, [1, 2, 5]) && $pendingRequisitionCount > 0): ?>
              <span class="badge bg-warning text-dark ms-1"><?= $pendingRequisitionCount ?></span>
            <?php endif; ?>
          </a>
        </li>

        <!-- Vehicles -->
        <li class="nav-item show-on-collapsed">
          <a class="nav-link <?= str_contains($page, 'vehicles') ? 'active' : '' ?>"
            href="admin.php?page=vehicles/index">
            <i class="fas fa-car me-1"></i><span>Vehicles</span>
          </a>
        </li>

        <!-- Maintenance -->
        <li class="nav-item show-on-collapsed">
          <a class="nav-link <?= str_contains($page, 'maintenance') ? 'active' : '' ?>"
            href="admin.php?page=maintenance/index">
            <i class="fas fa-tools me-1"></i><span>Maintenance</span>
          </a>
        </li>

        <!-- Fueling -->
        <li class="nav-item show-on-collapsed">
          <a class="nav-link <?= str_contains($page, 'refuel') ? 'active' : '' ?>" href="admin.php?page=refuel/index">
            <i class="fas fa-gas-pump me-1"></i><span>Fueling</span>
          </a>
        </li>

      </ul>

      <!-- Right side: Profile, Dark Mode, Sidebar Toggle -->
      <ul class="navbar-nav ms-auto align-items-center">

        <!-- Profile Dropdown -->
        <li class="nav-item dropdown me-3"> <!-- add me-3 for spacing -->
          <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
            id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="<?= htmlspecialchars($userImage) ?>" alt="Profile" class="rounded-circle me-2" width="32"
              height="32">
            <span><?= htmlspecialchars($userName) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end text-small shadow" aria-labelledby="profileDropdown">
            <li><a class="dropdown-item" href="admin.php?page=users/profile"><i class="fas fa-user me-2"></i> My
                Profile</a></li>
            <li>
              <hr class="dropdown-divider">
            </li>
            <li><a class="dropdown-item" href="auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
          </ul>
        </li>

        <!-- Dark Mode Toggle -->
        <li class="nav-item me-2">
          <button id="modeToggle" class="btn btn-sm btn-outline-light" title="Toggle Dark Mode">
            <span class="mode-icon" aria-hidden="true">🌙</span>
          </button>
        </li>

        <!-- Desktop Sidebar Toggle -->
        <li class="nav-item">
          <button class="btn btn-sm btn-outline-light d-none d-md-inline" id="toggleCollapseBtn" title="Toggle Sidebar"
            aria-label="Toggle sidebar" aria-expanded="true">
            <i class="fas fa-bars"></i>
          </button>
        </li>

      </ul>
    </div>
  </div>
</nav>