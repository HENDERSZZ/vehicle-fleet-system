<?php
require_once __DIR__ . '/config.php';       // defines $pdo
require_once __DIR__ . '/functions.php';    // includes isLicenseValid() + getRequisitionAccessClause()

if (session_status() === PHP_SESSION_NONE)
  session_start();

// Read essential session info
$_SESSION['user'] = $_SESSION['user'] ?? [];
$user = $_SESSION['user'];
$group_id = $user['group_id'] ?? null;
$isSuperAdmin = $user['is_superadmin'] ?? false;

// CSRF token generation
$csrf_token = getCSRFToken();

// Release lock so other requests (AJAX, forms) don’t hang
session_write_close();

// --- User & License Info ---
$licenseValid = isLicenseValid($pdo);
$user = $_SESSION['user'] ?? [];

if (is_string($user)) {
  $user = json_decode($user, true);
  if (!is_array($user))
    $user = [];
}

$group_id = $user['group_id'] ?? null;
$isAdmin = ($group_id == 1);
$isSuperAdmin = $user['is_superadmin'] ?? false;
$user_level = $user['level'] ?? -1;
$page = $_GET['page'] ?? 'dashboard';

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
<aside id="vmsSidebar" role="navigation" class="bg-dark text-white p-3 sidebar d-flex flex-column">
  <ul class="nav flex-column mt-3 pt-4 flex-grow-1">

    <li class="nav-item">
      <a class="nav-link text-white <?= $page === 'dashboard' ? 'fw-bold active' : '' ?>"
        href="admin.php?page=dashboard">
        <i class="fas fa-tachometer-alt me-2"></i>
        <span>Dashboard</span>
      </a>
    </li>

    <?php if (canViewUsers($user)): ?>
      <li class="nav-item">
        <a class="nav-link text-white <?= str_contains($page, 'users/') ? '' : 'collapsed' ?>" href="#usersSubmenu"
          data-bs-toggle="collapse" aria-expanded="<?= str_contains($page, 'users/') ? 'true' : 'false' ?>">
          <i class="fas fa-users-cog me-2"></i>
          <span>Users</span>
        </a>
        <div class="collapse <?= str_contains($page, 'users/') ? 'show' : '' ?>" id="usersSubmenu">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
            <li>
              <a class="nav-link text-white <?= str_contains($page, 'users/index') ? 'fw-bold active' : '' ?>"
                href="admin.php?page=users/index" aria-current="<?= str_contains($page, 'users/index') ? 'page' : '' ?>">
                <i class="fas fa-users me-2"></i>
                <span>View Users</span>
              </a>
            </li>
            <li>
              <a class="nav-link text-white <?= str_contains($page, 'users/regions_index') ? 'fw-bold active' : '' ?>"
                href="admin.php?page=users/regions_index"
                aria-current="<?= str_contains($page, 'users/regions_index') ? 'page' : '' ?>">
                <i class="fas fa-map me-2"></i>
                <span>View Regions</span>
              </a>
            </li>
          </ul>
        </div>
      </li>
    <?php endif; ?>

    <li class="nav-item">
      <a class="nav-link text-white <?= str_contains($page, 'drivers/') ? '' : 'collapsed' ?>" href="#driversSubmenu"
        data-bs-toggle="collapse" aria-expanded="<?= str_contains($page, 'drivers/') ? 'true' : 'false' ?>">
        <i class="fas fa-id-card me-2"></i>
        <span>Drivers</span>
      </a>
      <div class="collapse <?= str_contains($page, 'drivers/') ? 'show' : '' ?>" id="driversSubmenu">
        <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small ps-4">
          <li>
            <a class="nav-link text-white <?= str_contains($page, 'drivers/index') ? 'fw-bold active' : '' ?>"
              href="admin.php?page=drivers/index"
              aria-current="<?= str_contains($page, 'drivers/index') ? 'page' : '' ?>">
              <i class="fas fa-user-tie me-2"></i>
              <span>View Drivers</span>
            </a>
          </li>

          <?php if (in_array($group_id, [1, 5])): // Admins & Super Admins only ?>
            <li>
              <a class="nav-link text-white <?= str_contains($page, 'drivers/assignments') ? 'fw-bold active' : '' ?>"
                href="admin.php?page=drivers/assignments" aria-current="<?= str_contains($page, 'drivers/assignments') ? 'page' : '' ?>">
                <i class="fas fa-user-plus me-2"></i>
                <span>Assignments</span>
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </li>



    <li class="nav-item">
      <a class="nav-link text-white <?= str_contains($page, 'categories') ? 'fw-bold active' : '' ?>"
        href="admin.php?page=categories/index">
        <i class="fa fa-th-large me-2"></i>
        <span>Categories</span>
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= str_contains($page, 'requisitions') ? 'fw-bold active' : '' ?>"
        href="admin.php?page=requisitions/index">
        <i class="fas fa-file-signature me-2"></i>
        <span>Requisitions</span>
        <?php if (in_array($group_id, [1, 2, 5]) && $pendingRequisitionCount > 0): ?>
          <span class="badge bg-warning text-dark ms-2">
            <?= $pendingRequisitionCount ?>
          </span>
        <?php endif; ?>
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= str_contains($page, 'vehicles') ? 'fw-bold active' : '' ?>"
        href="admin.php?page=vehicles/index">
        <i class="fas fa-car me-2"></i>
        <span>Vehicles</span>
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= str_contains($page, 'maintenance') ? 'fw-bold active' : '' ?>"
        href="admin.php?page=maintenance/index">
        <i class="fas fa-tools me-2"></i>
        <span>Maintenance</span>
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link text-white <?= str_contains($page, 'refuel') ? 'fw-bold active' : '' ?>"
        href="admin.php?page=refuel/index">
        <i class="fas fa-gas-pump me-2"></i>
        <span>Fueling </span>
      </a>
    </li>

    <?php if (canActivateLicense($user)): ?>
      <li class="nav-item">
        <a class="nav-link text-white <?= $page === 'license/activate' ? 'fw-bold active' : '' ?>"
          href="admin.php?page=license/activate"><i class="fas fa-unlock-alt me-2"></i>
          <span> Activate License</span></a>
      </li>
    <?php endif; ?>

    <!-- License Badge -->
    <li class="nav-item mt-3 text-center small" style="opacity: 0.75;">
      <?php if (in_array($user['id'], [1, 5]) || $isSuperAdmin): ?>
        <span class="badge bg-primary">License: Admin</span>
      <?php else: ?>
        <span class="badge bg-<?= $licenseValid ? 'success' : 'danger' ?>">
          License: <?= $licenseValid ? 'Active' : 'Missing' ?>
        </span>
      <?php endif; ?>
    </li>


    <!-- Footer -->
    <li class="nav-item mt-auto text-center text-light small">
      <hr class="border-secondary border-2" />
      &copy; <?= date('Y') ?><span>| LogicSpur |</span>
    </li>
  </ul>
</aside>