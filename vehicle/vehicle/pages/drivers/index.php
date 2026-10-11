<?php 
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$currentUserId = $_SESSION['user']['id'] ?? null;

getCSRFToken(); 

$p = max(1, (int) ($_GET['p'] ?? 1));
$per_page = 10;
$offset = ($p - 1) * $per_page;

// Count total drivers
$count_sql = "SELECT COUNT(*) AS total FROM drivers";
$total_records = $pdo->query($count_sql)->fetchColumn();
$total_pages = ceil($total_records / $per_page);
$start_page = max(1, $p - 2);
$end_page = min($total_pages, $start_page + 4);

// Fetch drivers + their active vehicle if any
$sql = "
    SELECT d.*, v.regno AS active_vehicle
    FROM drivers d
    LEFT JOIN vehicle_assignments va ON d.id = va.driver_id AND va.released_at IS NULL
    LEFT JOIN vehicles v ON va.vehicle_id = v.id
    ORDER BY d.name ASC
    LIMIT $per_page OFFSET $offset
";
$drivers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fa fa-id-card me-2"></i>Drivers</h2>
    <?php if (can_add()): ?>
      <a href="admin.php?page=drivers/form" class="btn btn-success"><i class="fa fa-plus me-1"></i> Add Driver</a>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered text-center align-middle">
      <thead class="table-light">
        <tr>
          <th>Name</th>
          <th>Phone</th>
          <th>Status</th>
          <th>Active Vehicle</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($drivers as $d): ?>
          <tr>
            <td><?= htmlspecialchars($d['name']) ?></td>
            <td><?= htmlspecialchars($d['phone'] ?? '-') ?></td>
            <td>
              <span class="badge <?= $d['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                <?= htmlspecialchars($d['status']) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($d['active_vehicle'] ?? '-') ?></td>
            <td>
              <a href="admin.php?page=drivers/form&id=<?= $d['id'] ?>" class="btn btn-warning btn-sm">
                <i class="fa fa-edit"></i>
              </a>
              <form method="POST" action="admin.php?page=drivers/delete" style="display:inline;" 
                onsubmit="return confirm('Delete this driver?')">
                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                <?= csrfInput() ?>
                <button class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></button>
              </form>
              <a href="admin.php?page=drivers/assignments&driver_id=<?= $d['id'] ?>" 
                 class="btn btn-primary btn-sm">
                <i class="fa fa-link"></i> Assign
              </a>
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
          <li class="page-item"><a class="page-link" href="?page=drivers/index&p=<?= $p - 1 ?>">Previous</a></li>
        <?php endif; ?>
        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
          <li class="page-item <?= $i == $p ? 'active' : '' ?>">
            <a class="page-link" href="?page=drivers/index&p=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <?php if ($p < $total_pages): ?>
          <li class="page-item"><a class="page-link" href="?page=drivers/index&p=<?= $p + 1 ?>">Next</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>
