<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$search = trim($_GET['search'] ?? '');
$status_id = $_GET['status_id'] ?? '';
$page_num = (isset($_GET['p']) && is_numeric($_GET['p']) && $_GET['p'] > 0) ? (int) $_GET['p'] : 1;
$limit = 10;
$offset = ($page_num - 1) * $limit;

$queryString = http_build_query([
  'search' => $search,
  'status_id' => $status_id
]);

$params = [];
$countParams = [];

// Base query with JOINs
$baseQuery = "
    FROM vehicles v
    LEFT JOIN categories c ON v.category_id = c.id
    LEFT JOIN locations l ON v.location_id = l.id
    LEFT JOIN subcounties sc ON l.subcounty_id = sc.id
    LEFT JOIN counties co ON sc.county_id = co.id
    LEFT JOIN regions r ON co.region_id = r.id
    LEFT JOIN vehicle_statuses s ON v.status_id = s.id
    WHERE 1=1
";

// Search clause
$searchClause = '';
if ($search) {
  $term = "%$search%";
  $searchClause = "
        AND (
            v.regno LIKE :term OR 
            v.chassisno LIKE :term OR
            v.engineno LIKE :term OR
            v.make LIKE :term OR
            v.color LIKE :term OR
            v.designation LIKE :term OR
            DATE(v.date) LIKE :term OR
            s.label LIKE :term OR
            c.name LIKE :term OR
            co.name LIKE :term OR
            sc.name LIKE :term OR
            l.name LIKE :term OR
            r.name LIKE :term
        )
    ";
  $params[':term'] = $term;
  $countParams[':term'] = $term;
}

// Status filter clause
$statusClause = '';
if ($status_id !== '') {
  $statusClause = " AND v.status_id = :status_id";
  $params[':status_id'] = $status_id;
  $countParams[':status_id'] = $status_id;
}

// ✅ Access control clause
$accessClause = getAccessClause('co', 'v');


// Total count query
$countStmt = $pdo->prepare("SELECT COUNT(*) " . $baseQuery . $searchClause . $statusClause . $accessClause);
$countStmt->execute($countParams);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Main data query
$query = "
    SELECT 
        v.*, 
        c.name AS category_name, 
        r.name AS region,
        co.name AS county, 
        sc.name AS sub_county, 
        l.name AS location,
        s.label AS status_label
    " . $baseQuery . $searchClause . $statusClause . $accessClause . "
    ORDER BY v.date DESC 
    LIMIT :limit OFFSET :offset
";

$params[':limit'] = (int) $limit;
$params[':offset'] = (int) $offset;

$stmt = $pdo->prepare($query);
foreach ($params as $key => &$val) {
  $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Status dropdown
$status_options = array_merge(
  [['id' => '', 'label' => '-- All --']],
  $pdo->query("SELECT id, label FROM vehicle_statuses ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC)
);
?>

<div class="container py-4">
  <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
    <h2 class="mb-2"><i class="fas fa-car me-2"></i>Vehicles</h2>
    <div class="d-flex flex-wrap gap-2">
      <?php if (can_add()): ?>
        <a href="admin.php?page=vehicles/form" class="btn btn-success">
          <i class="fas fa-plus me-1"></i> Add Vehicle
        </a>
      <?php endif; ?>
      <?php if (can_export()): ?>
        <div class="d-flex flex-wrap gap-2">
          <a href="admin.php?page=vehicles/export_vehicles_csv&<?= htmlspecialchars($queryString) ?>"
            class="btn btn-outline-success <?= count($vehicles) ? '' : 'disabled' ?>">
            <i class="fas fa-file-csv me-1"></i> Export CSV
          </a>
          <a href="admin.php?page=vehicles/export_vehicles_pdf&<?= htmlspecialchars($queryString) ?>"
            class="btn btn-outline-danger <?= count($vehicles) ? '' : 'disabled' ?>">
            <i class="fas fa-file-pdf me-1"></i> Export PDF
          </a>
        </div>
      <?php endif; ?>


    </div>
  </div>
  <form method="GET" action="admin.php" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="page" value="vehicles/index">

    <div class="col-md-4">
      <label class="form-label">Search</label>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control"
        placeholder="Search...">
    </div>

    <div class="col-md-3">
      <label class="form-label">Status / Condition / Ownership</label>
      <select id="vehicleStatusSelect" name="status_id" class="form-select">
        <?php foreach ($status_options as $opt): ?>
          <option value="<?= htmlspecialchars($opt['id']) ?>" <?= ($status_id === (string) $opt['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($opt['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2">
      <button class="btn btn-primary w-100">
        <i class="fas fa-search me-1"></i> Filter
      </button>
    </div>

    <div class="col-md-2">
      <a href="admin.php?page=vehicles/index" class="btn btn-outline-secondary w-100">
        <i class="fas fa-sync-alt me-1"></i> Reset
      </a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-bordered table-hover text-center align-middle" data-table-type="vehicles-table">
      <thead class="table-light">
        <tr>
          <th>Registration</th>
          <th>Model</th>
          <th>Designation</th>
          <th>Region</th>
          <th>County</th>
          <th>Sub-County</th>
          <th>Location</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($vehicles)): ?>
          <tr>
            <td colspan="10" class="text-muted text-center">No vehicles found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($vehicles as $v): ?>
            <tr>
              <td><?= htmlspecialchars($v['regno'] ?? '') ?></td>
              <td><?= htmlspecialchars($v['make'] ?? '') ?></td>
              <td><?= htmlspecialchars($v['designation'] ?? '') ?></td>
              <td><?= htmlspecialchars($v['region'] ?? '') ?></td>
              <td><?= htmlspecialchars($v['county'] ?? '') ?></td>
              <td><?= htmlspecialchars($v['sub_county'] ?? '') ?></td>
              <td><?= htmlspecialchars($v['location'] ?? '') ?></td>

              <td>
                <?php
                $status_label = strtolower($v['status_label'] ?? '');  // Use null coalescing in case it's missing
                $badge = match ($status_label) {
                  'grounded' => 'bg-warning text-dark',
                  'disposed' => 'bg-danger',
                  'disposable' => 'bg-dark text-light',
                  'leased' => 'bg-primary',
                  'off-lease' => 'bg-info text-dark',
                  'leased stored' => 'bg-primary-subtle text-dark',
                  'serviceable' => 'bg-success',
                  'unserviceable' => 'bg-danger-subtle text-dark',
                  'returned' => 'bg-secondary',
                  default => 'bg-secondary text-light',
                };
                ?>
                <span class="badge <?= $badge ?> text-uppercase">
                  <?= htmlspecialchars($v['status_label'] ?? 'Unknown') ?>
                </span>
              </td>

              </td>
              <td><?= htmlspecialchars($v['date']) ?></td>
              <td>
                <?php if (can_edit()): ?>
                  <a href="admin.php?page=vehicles/form&id=<?= $v['id'] ?>" class="btn btn-sm btn-warning">
                    <i class="fas fa-edit"></i>
                  </a>
                <?php endif; ?>
                <?php if (can_delete()): ?>
                  <form method="POST" action="admin.php?page=vehicles/delete" style="display:inline;"
                    onsubmit="return confirm('Delete this vehicle?')">
                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                    <?= csrfInput() ?>
                    <button class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mt-3">
      <ul class="pagination justify-content-center">
        <?php if ($page_num > 1): ?>
          <li class="page-item">
            <a class="page-link"
              href="admin.php?page=vehicles/index&<?= $queryString ?>&p=<?= $page_num - 1 ?>">Previous</a>
          </li>
        <?php endif; ?>

        <?php if ($page_num > 3): ?>
          <li class="page-item">
            <a class="page-link" href="admin.php?page=vehicles/index&<?= $queryString ?>&p=1">1</a>
          </li>
          <?php if ($page_num > 4): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
          <?php endif; ?>
        <?php endif; ?>

        <?php
        $start = max(1, $page_num - 2);
        $end = min($totalPages, $page_num + 2);
        for ($i = $start; $i <= $end; $i++):
          ?>
          <li class="page-item <?= $i == $page_num ? 'active' : '' ?>">
            <a class="page-link" href="admin.php?page=vehicles/index&<?= $queryString ?>&p=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>

        <?php if ($page_num < $totalPages - 2): ?>
          <?php if ($page_num < $totalPages - 3): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
          <?php endif; ?>
          <li class="page-item">
            <a class="page-link"
              href="admin.php?page=vehicles/index&<?= $queryString ?>&p=<?= $totalPages ?>"><?= $totalPages ?></a>
          </li>
        <?php endif; ?>

        <?php if ($page_num < $totalPages): ?>
          <li class="page-item">
            <a class="page-link" href="admin.php?page=vehicles/index&<?= $queryString ?>&p=<?= $page_num + 1 ?>">Next</a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>