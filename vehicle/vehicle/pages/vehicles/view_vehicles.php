<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$status_id = $_GET['status_id'] ?? '';
$region_id = $_GET['region_id'] ?? '';
$county_id = $_GET['county_id'] ?? '';
$subcounty_id = $_GET['subcounty_id'] ?? '';

$page_num = (isset($_GET['p']) && is_numeric($_GET['p']) && $_GET['p'] > 0) ? (int) $_GET['p'] : 1;
$limit = 10;
$offset = ($page_num - 1) * $limit;

$queryString = http_build_query([
  'status_id' => $status_id,
  'region_id' => $region_id,
  'county_id' => $county_id,
  'subcounty_id' => $subcounty_id
]);

$params = [];
$countParams = [];
$filterClause = '';
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

// Filters (except region filters will be ignored for region summary)
if ($status_id !== '') {
  $filterClause .= " AND v.status_id = :status_id";
  $params[':status_id'] = $status_id;
  $countParams[':status_id'] = $status_id;
}

if ($region_id !== '') {
  $filterClause .= " AND r.id = :region_id";
  $params[':region_id'] = $region_id;
  $countParams[':region_id'] = $region_id;
}

if ($county_id !== '') {
  $filterClause .= " AND co.id = :county_id";
  $params[':county_id'] = $county_id;
  $countParams[':county_id'] = $county_id;
}

if ($subcounty_id !== '') {
  $filterClause .= " AND sc.id = :subcounty_id";
  $params[':subcounty_id'] = $subcounty_id;
  $countParams[':subcounty_id'] = $subcounty_id;
}

// Access clause
$accessClause = getAccessClause('co', 'v');

// Total count query for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) " . $baseQuery . $filterClause . $accessClause);
$countStmt->execute($countParams);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Main data query with pagination
$query = "
    SELECT 
        v.*, 
        c.name AS category_name, 
        r.name AS region,
        co.name AS county, 
        sc.name AS sub_county, 
        l.name AS location,
        s.label AS status_label
    " . $baseQuery . $filterClause . $accessClause . "
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

// ----------- SUMMARY STATS -------------

$filters_applied = $region_id !== '' || $county_id !== '' || $subcounty_id !== '' || $status_id !== '';

$filter_stats = [
  'Region' => [],
  'County' => [],
  'Subcounty' => [],
  'Status' => []
];

// REGION summary filtered ONLY by region filter and access (ignore county, subcounty, status)
$regionFilterClause = '';
$regionParams = [];

if ($region_id !== '') {
  $regionFilterClause .= " AND r.id = :region_id";
  $regionParams[':region_id'] = $region_id;
}

$regionSummaryQuery = "
    SELECT r.name AS region_name, COUNT(*) AS cnt
    FROM vehicles v
    LEFT JOIN locations l ON v.location_id = l.id
    LEFT JOIN subcounties sc ON l.subcounty_id = sc.id
    LEFT JOIN counties co ON sc.county_id = co.id
    LEFT JOIN regions r ON co.region_id = r.id
    WHERE 1=1
    $regionFilterClause
    $accessClause
    GROUP BY r.name
";

$regionStmt = $pdo->prepare($regionSummaryQuery);
$regionStmt->execute($regionParams);
$regionStats = $regionStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($regionStats as $row) {
  $filter_stats['Region'][$row['region_name']] = (int) $row['cnt'];
}

// Other summaries filtered by all filters
if ($filters_applied) {
  $summaryQuery = "
        SELECT 
            co.name AS county_name,
            sc.name AS subcounty_name,
            s.label AS status_label
        " . $baseQuery . $filterClause . $accessClause;

  $summaryStmt = $pdo->prepare($summaryQuery);
  $summaryStmt->execute($countParams);
  $rows = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $row) {
    if (!empty($row['county_name'])) {
      $filter_stats['County'][$row['county_name']] =
        ($filter_stats['County'][$row['county_name']] ?? 0) + 1;
    }
    if (!empty($row['subcounty_name'])) {
      $filter_stats['Subcounty'][$row['subcounty_name']] =
        ($filter_stats['Subcounty'][$row['subcounty_name']] ?? 0) + 1;
    }
    if (!empty($row['status_label'])) {
      $filter_stats['Status'][$row['status_label']] =
        ($filter_stats['Status'][$row['status_label']] ?? 0) + 1;
    }
  }
} else {
  // If no filters, region summary already done above, other summaries empty
}

// Status dropdown options with "-- All --"
$status_options = array_merge(
  [['id' => '', 'label' => '-- All --']],
  $pdo->query("SELECT id, label FROM vehicle_statuses ORDER BY label ASC")->fetchAll(PDO::FETCH_ASSOC)
);

?>

<div class="container py-4">
  <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
    <h2 class="mb-2">
      <i class="fas fa-list me-2"></i>Fleet Overview
    </h2>

  </div>
  <form method="GET" action="admin.php" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="page" value="vehicles/view_vehicles">
    <div class="col-md-2">
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

    <div class="col-md-2">
      <label class="form-label">County</label>
      <select name="county_id" id="countySelect" class="form-select select2">
        <option value="">-- Select County --</option>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label">Subcounty</label>
      <select name="subcounty_id" id="subcountySelect" class="form-select select2">
        <option value="">-- Select Subcounty --</option>
      </select>
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

    <!-- Filter Button -->
    <div class="col-md-3 d-flex align-items-end">
      <div class="d-flex w-100 gap-2">
        <button type="submit" class="btn btn-primary w-50">Filter</button>
        <a href="admin.php?page=vehicles/view_vehicles" class="btn btn-secondary w-50">Reset</a>
      </div>
    </div>

  </form>
  <?php if (!empty($filters_applied) && !empty($filter_stats)): ?>
    <div class="alert alert-info mb-3" id="summaryFilters" style="display:flex; flex-wrap:wrap; gap:1rem;">
      <strong style="flex-basis:100%;">Filter Summary:</strong>
      <?php foreach ($filter_stats as $type => $items): ?>
        <?php if (!empty($items)): ?>
          <div class="filter-group"
            style="flex:1 1 200px; min-width:150px; max-height:220px; overflow-y:auto; background:#f5f8fa; padding:0.5rem 1rem; border-radius:5px; box-shadow:0 0 6px rgba(0,0,0,0.05);">
            <strong><?= htmlspecialchars($type) ?>:</strong>
            <ul style="margin:0.25rem 0 0; padding-left:1.2rem;">
              <?php foreach ($items as $name => $count): ?>
                <li><?= htmlspecialchars($name) ?> (<?= (int) $count ?>)</li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>


  <!-- Table -->
  <div class="table-responsive">
    <table class="table table-bordered table-hover text-center align-middle" data-table-type="fleet-table">
      <thead class="table-light">
        <tr>
          <th>Registration</th>
          <th>Model</th>
          <th>Designation</th>
          <th>Region</th>
          <th>County</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($vehicles)): ?>
          <tr>
            <td colspan="7" class="text-muted text-center">No vehicles found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($vehicles as $v): ?>
            <tr>
              <td><?= htmlspecialchars($v['regno'] ?? '') ?></td>
              <td><?= htmlspecialchars($v['make'] ?? '') ?></td>
              <td><?= htmlspecialchars($v['designation'] ?? '') ?></td>
              <td><?= htmlspecialchars($v['region'] ?? '') ?></td>
              <td><?= htmlspecialchars($v['county'] ?? '') ?></td>
              <td>
                <?php
                $status_label = strtolower($v['status_label'] ?? '');
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
              <td><?= htmlspecialchars($v['date']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
  <nav aria-label="Page navigation" class="mt-3">
    <ul class="pagination justify-content-center">
      <?php if ($page_num > 1): ?>
        <li class="page-item">
          <a class="page-link"
            href="admin.php?page=vehicles/view_vehicles&<?= $queryString ?>&p=<?= $page_num - 1 ?>">Previous</a>
        </li>
      <?php endif; ?>
      <?php if ($page_num > 3): ?>
        <li class="page-item"><a class="page-link"
            href="admin.php?page=vehicles/view_vehicles&<?= $queryString ?>&p=1">1</a></li>
        <?php if ($page_num > 4): ?>
          <li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
      <?php endif; ?>
      <?php
      $start = max(1, $page_num - 2);
      $end = min($totalPages, $page_num + 2);
      for ($i = $start; $i <= $end; $i++):
        ?>
        <li class="page-item <?= $i == $page_num ? 'active' : '' ?>">
          <a class="page-link" href="admin.php?page=vehicles/view_vehicles&<?= $queryString ?>&p=<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
      <?php if ($page_num < $totalPages - 2): ?>
        <?php if ($page_num < $totalPages - 3): ?>
          <li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link"
            href="admin.php?page=vehicles/view_vehicles&<?= $queryString ?>&p=<?= $totalPages ?>"><?= $totalPages ?></a>
        </li>
      <?php endif; ?>
      <?php if ($page_num < $totalPages): ?>
        <li class="page-item">
          <a class="page-link"
            href="admin.php?page=vehicles/view_vehicles&<?= $queryString ?>&p=<?= $page_num + 1 ?>">Next</a>
        </li>
      <?php endif; ?>
    </ul>
  </nav>
<?php endif; ?>
</div>