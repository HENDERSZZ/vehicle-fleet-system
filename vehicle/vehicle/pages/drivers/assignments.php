<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

getCSRFToken();

$p = max(1, (int) ($_GET['p'] ?? 1));
$per_page = 10;
$offset = ($p - 1) * $per_page;

$filter_driver = $_GET['driver_id'] ?? '';
$filter_vehicle = $_GET['vehicle_id'] ?? '';
$where = [];
$params = [];

if ($filter_driver !== '') {
  $where[] = 'va.driver_id = ?';
  $params[] = $filter_driver;
}
if ($filter_vehicle !== '') {
  $where[] = 'va.vehicle_id = ?';
  $params[] = $filter_vehicle;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total assignments
$count_sql = "SELECT COUNT(*) FROM vehicle_assignments va $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Fetch assignments
$sql = "
  SELECT va.*, 
         d.name AS driver_name, 
         v.regno AS vehicle_regno, 
         u.name AS assigned_by_name
  FROM vehicle_assignments va
  LEFT JOIN drivers d ON va.driver_id = d.id
  LEFT JOIN vehicles v ON va.vehicle_id = v.id
  LEFT JOIN users u ON va.assigned_by = u.id
  $where_clause
  ORDER BY va.assigned_at DESC
  LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lookup data for filters/forms
$drivers = $pdo->query("SELECT id, name FROM drivers WHERE status='active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$vehicles = $pdo->query("SELECT id, regno FROM vehicles ORDER BY regno ASC")->fetchAll(PDO::FETCH_ASSOC);

// Handle new assignment (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
  $driver_id = $_POST['driver_id'] ?? null;
  $vehicle_id = $_POST['vehicle_id'] ?? null;
  $assigned_by = $_SESSION['user']['id'] ?? null;

  if (!$driver_id || !$vehicle_id) {
    $error = "Please select both a driver and a vehicle.";
  } else {
    // Check if the vehicle is already assigned
    $check = $pdo->prepare("SELECT COUNT(*) FROM vehicle_assignments WHERE vehicle_id = ? AND released_at IS NULL");
    $check->execute([$vehicle_id]);

    if ($check->fetchColumn() > 0) {
      $error = "⚠️ Vehicle already assigned to another driver.";
    } else {
      $stmt = $pdo->prepare("
        INSERT INTO vehicle_assignments (vehicle_id, driver_id, assigned_by, assigned_at)
        VALUES (?, ?, ?, NOW())
      ");
      $stmt->execute([$vehicle_id, $driver_id, $assigned_by]);
      header("Location: admin.php?page=drivers/assignments");
      exit;
    }
  }
}

// Handle release (GET)
if (isset($_GET['release'])) {
  $stmt = $pdo->prepare("UPDATE vehicle_assignments SET released_at = NOW() WHERE id = ?");
  $stmt->execute([$_GET['release']]);
  header("Location: admin.php?page=drivers/assignments");
  exit;
}
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa fa-link me-2"></i>Vehicle Assignments</h2>
    <?php if (can_add()): ?>
      <a href="admin.php?page=drivers/assign_form" class="btn btn-success"><i class="fa fa-plus me-1"></i> Assign
        Vehicle</a>
    <?php endif; ?>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Filter Form -->
  <form method="GET" class="row g-2 mb-3">
    <input type="hidden" name="page" value="drivers/assignments">

    <div class="col-md-4">
      <label class="form-label">Driver</label>
      <select name="driver_id" class="form-select select2">
        <option value="">-- All Drivers --</option>
        <?php foreach ($drivers as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $filter_driver == $d['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($d['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Vehicle <span class="text-danger">*</span></label>
      <select name="vehicle_id" id="vehicleSelect" class="form-select select2" required>
        <option value="">-- Select Vehicle --</option>
        <?php foreach ($vehicles as $v): ?>
          <option value="<?= $v['id'] ?>">
            <?= htmlspecialchars($v['regno'] . ' (' . $v['make'] . ')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>


    <div class="col-md-4 d-flex align-items-end">
      <div class="d-flex w-100 gap-2">
        <button type="submit" class="btn btn-primary w-50">Filter</button>
        <a href="admin.php?page=drivers/assignments" class="btn btn-secondary w-50">Reset</a>
      </div>
    </div>
  </form>

  <!-- Assignments Table -->
  <div class="table-responsive">
    <table class="table table-bordered align-middle text-center">
      <thead class="table-light">
        <tr>
          <th>Vehicle</th>
          <th>Driver</th>
          <th>Assigned By</th>
          <th>Assigned At</th>
          <th>Released At</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assignments as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['vehicle_regno'] ?? '-') ?></td>
            <td><?= htmlspecialchars($a['driver_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($a['assigned_by_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($a['assigned_at']) ?></td>
            <td><?= htmlspecialchars($a['released_at'] ?? '-') ?></td>
            <td>
              <span class="badge <?= $a['released_at'] ? 'bg-secondary' : 'bg-success' ?>">
                <?= $a['released_at'] ? 'Released' : 'Active' ?>
              </span>
            </td>
            <td>
              <?php if (!$a['released_at']): ?>
                <a href="admin.php?page=drivers/assignments&release=<?= $a['id'] ?>" class="btn btn-danger btn-sm"
                  onclick="return confirm('Release this assignment?');">
                  <i class="fa fa-unlink"></i> Release
                </a>
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
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <li class="page-item <?= $i == $p ? 'active' : '' ?>">
            <a class="page-link" href="?page=drivers/assignments&p=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>