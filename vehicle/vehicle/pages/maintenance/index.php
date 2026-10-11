<?php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$accessClause = getMaintenanceAccessClause();

if (!isset($_SESSION['user'])) {
  header('Location: ../../auth/login.php');
  exit;
}
// Ensure CSRF token is set
getCSRFToken();

// Trim and sanitize filter inputs
$regno_filter = trim($_GET['regno'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['p'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$where = "WHERE 1=1";
$params = [];

// Apply filters
if ($regno_filter !== '') {
  $where .= " AND m.regno LIKE ?";
  $params[] = '%' . $regno_filter . '%';
}
if ($date_from !== '') {
  $where .= " AND m.date >= ?";
  $params[] = $date_from . ' 00:00:00';
}
if ($date_to !== '') {
  $where .= " AND m.date <= ?";
  $params[] = $date_to . ' 23:59:59';
}
if ($status_filter !== '') {
  $where .= " AND vs.label = ?";
  $params[] = $status_filter;
}

// Count total matching records
$count_sql = "
    SELECT COUNT(*) AS total
    FROM maintenance m
    LEFT JOIN vehicles v ON m.regno = v.regno
    LEFT JOIN locations l ON v.location_id = l.id
    LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
    LEFT JOIN counties co ON v.county_id = co.id
    LEFT JOIN regions r ON co.region_id = r.id
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    $where $accessClause
";

$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_records / $per_page);

// Fetch paginated records
$query = "
    SELECT m.*, vs.label AS status, r.type, r.odometer
    FROM maintenance m
    LEFT JOIN vehicles v ON m.regno = v.regno
    LEFT JOIN locations l ON v.location_id = l.id
    LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
    LEFT JOIN counties co ON v.county_id = co.id
    LEFT JOIN regions rgn ON co.region_id = rgn.id
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    LEFT JOIN requisitions r ON m.requisition_id = r.id
    $where $accessClause
    ORDER BY m.date DESC, m.id DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total jobs and cost summary
$total_sql = "
    SELECT COUNT(*) AS total_jobs, SUM(m.cost) AS total_cost
    FROM maintenance m
    LEFT JOIN vehicles v ON m.regno = v.regno
    LEFT JOIN locations l ON v.location_id = l.id
    LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
    LEFT JOIN counties co ON v.county_id = co.id
    LEFT JOIN regions r ON co.region_id = r.id
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    $where $accessClause
";

$stmt = $pdo->prepare($total_sql);
$stmt->execute($params);
$totals = $stmt->fetch(PDO::FETCH_ASSOC);

$total_jobs = $totals['total_jobs'] ?? 0;
$total_cost = $totals['total_cost'] ?? 0;

// Preserve filters for pagination links
$queryString = http_build_query([
  'regno' => $regno_filter,
  'date_from' => $date_from,
  'date_to' => $date_to,
  'status' => $status_filter
]);

// Helper for older PHP versions (if necessary)
if (!function_exists('str_ends_with')) {
  function str_ends_with($haystack, $needle)
  {
    return substr($haystack, -strlen($needle)) === $needle;
  }
}

// Pagination helper
function buildPageUrl($page, $params)
{
  $params['p'] = $page;
  return 'admin.php?' . http_build_query($params);
}
?>

<!-- HTML output remains unchanged. Just ensure status dropdown is populated using PDO as well. -->

<div class="container py-4">
  <div
    class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <h2 class="mb-0"><i class="fa fa-tools me-2"></i>Maintenance Records</h2>
    <div class="d-flex flex-wrap gap-2">
      <div class="d-flex flex-wrap gap-2">
        <?php if (can_add()): ?>
          <a href="admin.php?page=maintenance/form" class="btn btn-success">
            <i class="fas fa-plus me-1"></i> Add Record
          </a>
        <?php endif; ?>
        <?php if (can_export()): ?>
          <a href="admin.php?page=maintenance/export_maintenance_csv&<?= htmlspecialchars($queryString) ?>"
            class="btn btn-outline-success <?= count($records) ? '' : 'disabled' ?>">
            <i class="fas fa-file-csv me-1"></i> Export CSV
          </a>
          <a href="admin.php?page=maintenance/export_maintenance_pdf&<?= htmlspecialchars($queryString) ?>"
            class="btn btn-outline-danger <?= count($records) ? '' : 'disabled' ?>">
            <i class="fas fa-file-pdf me-1"></i> Export PDF
          </a>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <form method="GET" action="admin.php" class="row g-3 mb-4">
    <input type="hidden" name="page" value="maintenance/index">
    <div class="col-md-2">
      <label class="form-label">Vehicle <span class="text-danger"></span></label>
      <select name="regno" id="maintenanceSelect" class="form-select">
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
      <a href="admin.php?page=maintenance/index" class="btn btn-outline-secondary w-100">
        <i class="fas fa-sync-alt me-1"></i> Reset
      </a>
    </div>
  </form>

  <?php if ($total_jobs == 0): ?>
    <div class="alert alert-info d-flex justify-content-center align-items-center text-center" role="alert"
      style="gap: 0.5rem;">
      <i class="fas fa-exclamation-triangle"></i>
      <span>No maintenance jobs found.</span>
    </div>
  <?php endif; ?>


  <?php if ($total_jobs || $total_cost): ?>
    <div class="alert alert-info d-flex justify-content-between">
      <span><strong>Total Jobs:</strong> <?= number_format($total_jobs) ?></span>
      <span><strong>Total Cost:</strong> <?= number_format($total_cost, 2) ?> KES</span>
    </div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-striped table-bordered table-hover maintenance-table" data-table-type="maintenance-table">
      <thead class="table-light">
        <tr>
          <th>Registration</th>
          <th>Type</th>
          <th>Description</th>
          <th>Odometer</th>
          <th>Cost</th>
          <th>Date</th>
          <th>Status</th>
          <th>Receipt</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($records)): ?>
          <?php foreach ($records as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['regno'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['type'] ?? '-') ?></td>
              <td><?= htmlspecialchars($r['description'] ?? '') ?></td>
              <td><?= isset($r['odometer']) ? number_format($r['odometer']) : '0' ?></td>
              <td><?= isset($r['cost']) ? number_format($r['cost'], 2) : '0.00' ?></td>
              <td><?= htmlspecialchars($r['date'] ?? '') ?></td>
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
                  <a href="/vehicle/uploads/<?= htmlspecialchars($r['receipt']) ?>" target="_blank">
                    <?= str_ends_with(strtolower($r['receipt']), '.pdf')
                      ? '<i class="fa fa-file-pdf text-danger fa-lg"></i> View PDF'
                      : '<img src="/vehicle/uploads/' . htmlspecialchars($r['receipt']) . '" alt="Receipt" style="height:40px;">' ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted">None</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (can_edit()): ?>
                  <a href="admin.php?page=maintenance/form&id=<?= $r['id'] ?>" class="btn btn-sm btn-warning">
                    <i class="fa fa-edit"></i>
                  </a>
                <?php endif; ?>
                <?php if (can_delete()): ?>
                  <form method="POST" action="admin.php?page=maintenance/delete" style="display:inline;"
                    onsubmit="return confirm('Delete this record?');">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <?= csrfInput() ?>
                    <button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                  </form>

                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
    <?php
    $max_visible = 10;
    $start = max(1, $page - floor($max_visible / 2));
    $end = min($total_pages, $start + $max_visible - 1);
    if ($end - $start + 1 < $max_visible) {
      $start = max(1, $end - $max_visible + 1);
    }
    ?>
    <nav class="mt-4">
      <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
          <li class="page-item"><a class="page-link" href="<?= buildPageUrl($page - 1, $_GET) ?>">Previous</a></li>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link"
              href="<?= buildPageUrl($i, $_GET) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
          <li class="page-item"><a class="page-link" href="<?= buildPageUrl($page + 1, $_GET) ?>">Next</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>