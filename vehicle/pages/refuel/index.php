<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$accessClause = getRefuelAccessClause();
// Ensure CSRF token is set
getCSRFToken();
if (!isset($_SESSION['user'])) {
  header('Location: ../../auth/login.php');
  exit;
}

// === Get Filters ===
$regno_filter = $_GET['regno'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page_number'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
// === Build WHERE Clause ===
$where = "WHERE 1=1";
$params = [];

if ($regno_filter) {
  $where .= " AND TRIM(LOWER(r.regno)) = TRIM(LOWER(:regno))";
  $params[':regno'] = trim(strtolower($regno_filter));
}
if ($date_from) {
  $where .= " AND r.date >= :date_from";
  $params[':date_from'] = $date_from . ' 00:00:00';
}
if ($date_to) {
  $where .= " AND r.date <= :date_to";
  $params[':date_to'] = $date_to . ' 23:59:59';
}
if ($status_filter) {
  $where .= " AND vs.label = :status";
  $params[':status'] = $status_filter;
}

// === Total Count ===
$count_sql = "
    SELECT COUNT(*) AS total
    FROM refuel r
    LEFT JOIN vehicles v ON r.regno = v.regno
    LEFT JOIN locations l ON v.location_id = l.id
    LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
    LEFT JOIN counties co ON v.county_id = co.id
    LEFT JOIN regions rgn ON co.region_id = rgn.id
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    $where $accessClause
";

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $perPage);

// === Fetch Paginated Records ===
$records_sql = "
    SELECT 
        r.*, 
        v.regno AS vehicle_regno, 
        vs.label AS status
    FROM refuel r
    LEFT JOIN vehicles v ON r.regno = v.regno
    LEFT JOIN locations l ON v.location_id = l.id
    LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
    LEFT JOIN counties co ON v.county_id = co.id
    LEFT JOIN regions rgn ON co.region_id = rgn.id
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    $where $accessClause
    ORDER BY r.date DESC, r.id DESC
     LIMIT :limit OFFSET :offset
";

$records_stmt = $pdo->prepare($records_sql);
foreach ($params as $key => $val) {
  $records_stmt->bindValue($key, $val);
}
$records_stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$records_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$records_stmt->execute();
$records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);

// === Total Fuel & Cost ===
$total_sql = "
    SELECT 
        SUM(r.fuel) AS total_fuel, 
        SUM(r.cost) AS total_cost
    FROM refuel r
    LEFT JOIN vehicles v ON r.regno = v.regno
    LEFT JOIN locations l ON v.location_id = l.id
    LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
    LEFT JOIN counties co ON v.county_id = co.id
    LEFT JOIN regions rgn ON co.region_id = rgn.id
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    $where $accessClause
";

$total_stmt = $pdo->prepare($total_sql);
$total_stmt->execute($params);
$totals = $total_stmt->fetch(PDO::FETCH_ASSOC);

$total_fuel = $totals['total_fuel'] ?? 0;
$total_cost = $totals['total_cost'] ?? 0;

// === Build Query String for Export Buttons ===
$filters = array_filter([
  'regno' => $regno_filter,
  'date_from' => $date_from,
  'date_to' => $date_to,
  'status' => $status_filter
]);

$queryString = http_build_query($filters);

// === Helper for Pagination URLs ===
function buildPageUrl($page, $query)
{
  $query['page'] = 'refuel/index';
  $query['page_number'] = $page;
  return 'admin.php?' . http_build_query($query);
}
?>


<!-- Refueling Records UI -->
<div class="container py-4">
  <div
    class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <h2 class="mb-0"><i class="fa fa-gas-pump me-2"></i>Fueling Records</h2>
    <div class="d-flex flex-wrap gap-2">
      <div class="d-flex flex-wrap gap-2">
        <?php if (can_export()): ?>
          <a href="admin.php?page=refuel/export_refuel_csv&<?= htmlspecialchars($queryString) ?>"
            class="btn btn-outline-success <?= count($records) ? '' : 'disabled' ?>">
            <i class="fas fa-file-csv me-1"></i> Export CSV
          </a>
          <a href="admin.php?page=refuel/export_refuel_pdf&<?= htmlspecialchars($queryString) ?>"
            class="btn btn-outline-danger <?= count($records) ? '' : 'disabled' ?>">
            <i class="fas fa-file-pdf me-1"></i> Export PDF
          </a>
        <?php endif; ?>
      </div>


    </div>
  </div>
  <form method="GET" action="admin.php" class="row g-3 mb-4">
    <input type="hidden" name="page" value="refuel/index">
    <div class="col-md-2">
      <label class="form-label">Vehicle <span class="text-danger"></span></label>
      <select name="regno" id="refuelSelect" class="form-select">
        <?php if (!empty($regno_filter)): ?>
          <option value="<?= htmlspecialchars($regno_filter) ?>" selected>
            <?= htmlspecialchars($regno_filter) ?>
          </option>
        <?php endif; ?>
      </select>
    </div>

    <?php $today = date('Y-m-d'); ?>
    <div class="col-md-2">
      <label class="form-label">Date From</label>
      <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" max="<?= $today ?>"
        class="form-control">
    </div>

    <div class="col-md-2">
      <label class="form-label">Date To</label>
      <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" max="<?= date('Y-m-d') ?>"
        class="form-control">
    </div>
    <div class="col-md-2">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="">-- All --</option>
        <?php
        $stmt = $pdo->query("
      SELECT label 
      FROM vehicle_statuses 
      WHERE label IN ('Serviceable', 'Leased') 
      ORDER BY FIELD(label, 'Serviceable', 'Leased')
    ");
        $status_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($status_options as $row):
          ?>
          <option value="<?= htmlspecialchars($row['label']) ?>" <?= $status_filter === $row['label'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($row['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>


    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-primary w-100">
        <i class="fas fa-search me-1"></i> Filter
      </button>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <a href="admin.php?page=refuel/index" class="btn btn-outline-secondary w-100">
        <i class="fas fa-sync-alt me-1"></i> Reset
      </a>
    </div>
  </form>
  <?php if (empty($records)): ?>
    <div class="alert alert-info d-flex justify-content-center align-items-center text-center" role="alert"
      style="gap: 0.5rem;">
      <i class="fas fa-exclamation-triangle"></i>
      <span>No fueling records found.</span>
    </div>
  <?php endif; ?>

  <?php if ($total_fuel || $total_cost): ?>
    <div class="alert alert-info d-flex justify-content-between">
      <span><strong>Total Fuel:</strong> <?= number_format($total_fuel, 2) ?> L</span>
      <span><strong>Total Cost:</strong> <?= number_format($total_cost, 2) ?> KES</span>
    </div>
  <?php endif; ?>
  <div class="table-responsive">
    <table class="table table-bordered align-middle text-center" data-table-type="refuel-table">
      <thead class="table-light">
        <tr>
          <th>Registration</th>
          <th>Fuel (L)</th>
          <th>Cost (KES)</th>
          <th>Station</th>
          <th>Odometer</th>
          <th>Remarks</th>
          <th>Date</th>
          <th>Status</th>
          <th>Receipt</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>

        <?php foreach ($records as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['regno'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['fuel'] ?? '') ?></td>
            <td><?= isset($r['cost']) && is_numeric($r['cost']) ? number_format($r['cost'], 2) : '0.00' ?></td>
            <td><?= htmlspecialchars($r['station'] ?? '') ?></td>
            <td><?= isset($r['odometer']) && is_numeric($r['odometer']) ? number_format($r['odometer']) : '0' ?></td>
            <td><?= htmlspecialchars($r['remarks'] ?? '') ?></td>
            <td><?= !empty($r['date']) ? htmlspecialchars($r['date']) : '-' ?></td>
            <td>
              <?php
              $status = strtolower($r['status'] ?? 'unknown');
              $badge = match ($status) {
                'serviceable' => 'bg-success',
                'leased' => 'bg-primary',
                default => 'bg-secondary',
              };

              ?>
              <span class="badge <?= $badge ?> text-uppercase">
                <?= htmlspecialchars($r['status'] ?? '-') ?>
              </span>
            </td>
            <td>
              <?php if (!empty($r['receipt'])): ?>
                <?php
                $receipt = htmlspecialchars($r['receipt']);
                $ext = strtolower(pathinfo($receipt, PATHINFO_EXTENSION));
                $fileUrl = "uploads/$receipt";
                ?>
                <a href="<?= $fileUrl ?>" target="_blank">
                  <?php if ($ext === 'pdf'): ?>
                    <i class="fa fa-file-pdf text-danger fa-lg"></i>
                  <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                    <img src="<?= $fileUrl ?>" alt="Receipt" style="height:40px; object-fit:cover; border-radius:3px;">
                  <?php else: ?>
                    <i class="fa fa-paperclip"></i> View File
                  <?php endif; ?>
                </a>
              <?php else: ?>
                <span class="text-muted">None</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (can_edit()): ?>
                <a href="admin.php?page=refuel/form&id=<?= $r['id'] ?>" class="btn btn-sm btn-warning">
                  <i class="fa fa-edit"></i>
                </a>
              <?php endif; ?>
              <?php if (can_delete()): ?>
                <form method="POST" action="admin.php?page=refuel/delete" style="display:inline;"
                  onsubmit="return confirm('Delete this record?');">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <?= csrfInput() ?>
                  <button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>


  <nav aria-label="Page navigation">
    <ul class="pagination justify-content-center mt-4">
      <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
          <ul class="pagination justify-content-center mt-4">
            <?php if ($page > 1): ?>
              <li class="page-item">
                <a class="page-link" href="<?= buildPageUrl($page - 1, $_GET) ?>">Previous</a>
              </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= buildPageUrl($i, $_GET) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
              <li class="page-item">
                <a class="page-link" href="<?= buildPageUrl($page + 1, $_GET) ?>">Next</a>
              </li>
            <?php endif; ?>
          </ul>
        </nav>
      <?php endif; ?>

    </ul>
  </nav>
</div>