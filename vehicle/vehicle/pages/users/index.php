<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$currentUserId = $_SESSION['user']['id'] ?? null;
$currentUserGroupId = $_SESSION['user']['group_id'] ?? null;

getCSRFToken(); // ensures token is always set

$region = $_GET['region_id'] ?? '';
$county = $_GET['county_id'] ?? '';
$subcounty = $_GET['subcounty_id'] ?? '';
$p = max(1, (int) ($_GET['p'] ?? 1)); // 'p' for pagination
$per_page = 10;
$offset = ($p - 1) * $per_page;

$where = [];
$params = [];

if ($currentUserGroupId != 5) {
  $where[] = 'u.group_id != 5';
}
if ($region !== '') {
  $where[] = 'u.region_id = ?';
  $params[] = $region;
}
if ($county !== '') {
  $where[] = 'u.county_id = ?';
  $params[] = $county;
}
if ($subcounty !== '') {
  $where[] = 'u.subcounty_id = ?';
  $params[] = $subcounty;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// === Count Total Users
$count_sql = "
    SELECT COUNT(*) AS total
    FROM users u
    $where_clause
";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_records / $per_page);
$start_page = max(1, $p - 2);
$end_page = min($total_pages, $start_page + 4);

// === Fetch Users
$user_sql = "
    SELECT u.*, ug.group_name
    FROM users u
    LEFT JOIN user_groups ug ON u.group_id = ug.id
    $where_clause
    LIMIT $per_page OFFSET $offset
";
$stmt_users = $pdo->prepare($user_sql);
$stmt_users->execute($params);
$users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

// === Load lookup maps
$regionMap = $pdo->query("SELECT id, name FROM regions")->fetchAll(PDO::FETCH_KEY_PAIR);
$countyMap = $pdo->query("SELECT id, name FROM counties")->fetchAll(PDO::FETCH_KEY_PAIR);
$subcountyMap = $pdo->query("SELECT id, name FROM subcounties")->fetchAll(PDO::FETCH_KEY_PAIR);
$locationMap = $pdo->query("SELECT id, name FROM locations")->fetchAll(PDO::FETCH_KEY_PAIR);
?>


<!-- HTML output (same as before, with cleaned-up formatting and escaping) -->
<div class="container py-4">
  <div class="d-flex justify-content-between flex-wrap align-items-center mb-3">
    <h2 class="mb-2"><i class="fa fa-users me-2"></i>Users</h2>
    <?php if (can_add()): ?>
      <a href="admin.php?page=users/form" class="btn btn-success"><i class="fa fa-plus me-1"></i> Add User</a>
    <?php endif; ?>
  </div>
  <form method="GET" class="row g-2 mb-3">
    <input type="hidden" name="page" value="users/index">

    <div class="col-md-3">
      <label class="form-label">Region</label>
      <select name="region_id" class="form-select" required>
        <option value="">-- Select Region --</option>
        <?php foreach ($regions as $r): ?>
          <option value="<?= htmlspecialchars($r['id']) ?>" <?= ($r['id'] == $region_id ? 'selected' : '') ?>>
            <?= htmlspecialchars($r['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label">County</label>
      <select name="county_id" id="countySelect" class="form-select select2">
        <option value="">-- Select County --</option>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label">Subcounty</label>
      <select name="subcounty_id" id="subcountySelect" class="form-select select2">
        <option value="">-- Select Subcounty --</option>
      </select>
    </div>

    <!--<div class="col-md-3">
                <label class="form-label">Location</label>
                <select name="location_id" id="locationSelect" class="form-select select2">
                    <option value="">-- Select Location --</option>
                </select>
            </div>-->
    <div class="col-md-3 d-flex align-items-end">
      <div class="d-flex w-100 gap-2">
        <button type="submit" class="btn btn-primary w-50">Filter</button>
        <a href="admin.php?page=users/index" class="btn btn-secondary w-50">Reset</a>
      </div>
    </div>
  </form>


  <div class="table-responsive">
    <table class="table table-bordered align-middle text-center" data-table-type="users-table">
      <thead class="table-light">
        <tr>
          <th>Name</th>
          <th>Group</th>
          <th>Region</th>
          <th>County</th>
          <th>Subcounty</th>
          <th>Location</th>
          <th>Status</th>
          <th>Current/Online</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <?php if ($u['group_id'] == ROLE_SUPER_ADMIN && $currentUserGroupId != ROLE_SUPER_ADMIN)
            continue; ?>
          <tr>
            <td><?= htmlspecialchars($u['name']) ?></td>
            <td><?= htmlspecialchars($u['group_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($regionMap[$u['region_id']] ?? '-') ?></td>
            <td><?= htmlspecialchars($countyMap[$u['county_id']] ?? '-') ?></td>
            <td><?= htmlspecialchars($subcountyMap[$u['subcounty_id']] ?? '-') ?></td>
            <td><?= htmlspecialchars($locationMap[$u['location_id']] ?? '-') ?></td>
            <td>
              <span class="badge <?= $u['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                <?= htmlspecialchars($u['status']) ?>
              </span>
            </td>
            <td id="status-badge-<?= $u['id'] ?>">
              <?= $u['id'] == $currentUserId
                ? '<span class="badge bg-success">You</span>'
                : '<span class="badge bg-light text-muted">Loading...</span>' ?>
            </td>
            <td>
              <?php if (is_superadmin() || is_admin()): ?>
                <a href="admin.php?page=users/form&id=<?= $u['id'] ?>" class="btn btn-warning btn-sm"><i
                    class="fa fa-edit"></i></a>
                <form method="POST" action="admin.php?page=users/delete" onsubmit="return confirm('Delete this user?')"
                  style="display:inline;">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <?= csrfInput() ?>
                  <button class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_pages > 1): ?>
    <nav>
      <ul class="pagination justify-content-center mt-3">
        <?php if ($p > 1): ?>
          <li class="page-item">
            <a class="page-link"
              href="?page=users/index&region_id=<?= $region ?>&county_id=<?= $county ?>&subcounty_id=<?= $subcounty ?>&p=<?= $p - 1 ?>">Previous</a>
          </li>
        <?php endif; ?>
        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
          <li class="page-item <?= $i == $p ? 'active' : '' ?>">
            <a class="page-link"
              href="?page=users/index&region_id=<?= $region ?>&county_id=<?= $county ?>&subcounty_id=<?= $subcounty ?>&p=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <?php if ($p < $total_pages): ?>
          <li class="page-item">
            <a class="page-link"
              href="?page=users/index&region_id=<?= $region ?>&county_id=<?= $county ?>&subcounty_id=<?= $subcounty ?>&p=<?= $p + 1 ?>">Next</a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
  <?php endif; ?>


</div>