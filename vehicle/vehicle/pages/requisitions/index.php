<?php
ob_start();
if (session_status() === PHP_SESSION_NONE)
  session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
function getLocationMap(string $table): array
{
  global $pdo; // assuming you're using $pdo for DB access
  $stmt = $pdo->query("SELECT id, name FROM $table ORDER BY name");
  return $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // returns [id => name]
}

$regions = getLocationMap('regions');
$counties = getLocationMap('counties');
$subcounties = getLocationMap('subcounties');
$locations = getLocationMap('locations');


getCSRFToken(); // ensures token is always set

// Handle POST actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['req_id'], $_POST['csrf_token'])) {

  // ✅ Respect config-managed token
  if (!hash_equals(getCSRFToken(), $_POST['csrf_token'])) {
    $_SESSION['error'] = "❌ Invalid CSRF token.";
    header("Location: admin.php?page=requisitions/index");
    exit;
  }

  $id = (int) $_POST['req_id'];
  $action = ($_POST['action'] === 'approve') ? 'approved' : 'rejected';
  $approved_by = $_SESSION['user']['id'];

  // 1. Update requisition status
  $stmt = $pdo->prepare("UPDATE requisitions SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
  $stmt->execute([$action, $approved_by, $id]);

  // 2. On approval: insert into refuel or maintenance
  if ($action === 'approved') {
    $stmt = $pdo->prepare("SELECT * FROM requisitions WHERE id = ?");
    $stmt->execute([$id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($req) {
      if ($req['type'] === 'fuel') {
        // Avoid duplicate refuel
        $check = $pdo->prepare("SELECT COUNT(*) FROM refuel WHERE regno = ? AND date = CURDATE() AND cost = ?");
        $check->execute([$req['vehicle_regno'], $req['amount']]);
        $exists = $check->fetchColumn();

        if ((int) $exists === 0) {
          $insert = $pdo->prepare("
                        INSERT INTO refuel (regno, fuel, cost, odometer, station, remarks, date, requisition_id, type)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)
                    ");
          $insert->execute([
            $req['vehicle_regno'],
            $req['fuel'] ?? 0,
            $req['amount'],
            $req['odometer'] ?? 0,
            $req['station'] ?? '',
            $req['description'] ?? '',
            $req['id'],
            $req['type'] ?? 'refuel'
          ]);
        }
      } else {
        // Avoid duplicate maintenance
        $check = $pdo->prepare("SELECT COUNT(*) FROM maintenance WHERE requisition_id = ?");
        $check->execute([$req['id']]);
        $exists = $check->fetchColumn();

        if ((int) $exists === 0) {
          $insert = $pdo->prepare("
                        INSERT INTO maintenance (regno, description, cost, odometer, date, requisition_id, type)
                        VALUES (?, ?, ?, ?, NOW(), ?, ?)
                    ");
          $insert->execute([
            $req['vehicle_regno'],
            $req['description'],
            $req['amount'],
            $req['odometer'] ?? 0,
            $req['id'],
            $req['type']
          ]);
        }
      }
    }
  }

  $_SESSION['success'] = "✅ Requisition successfully {$action}.";
  header("Location: admin.php?page=requisitions/index");
  exit;
}

// Fetch all requisitions for display
$user_id = $_SESSION['user']['id'] ?? 0;
$group_id = $_SESSION['user']['group_id'] ?? 0;

// ✅ Get the proper scoped clause
$accessClause = getRequisitionAccessClause();

$sql = "
    SELECT 
        r.*, 
        v.make, 
        
        u.name AS requested_by_name,
        u.region_id AS requested_by_region_id,
        u.county_id AS requested_by_county_id,
        u.subcounty_id AS requested_by_subcounty_id,
        u.location_id AS requested_by_location_id,
        
        a.name AS approved_by_name,
        a.region_id AS approved_by_region_id,
        a.county_id AS approved_by_county_id,
        a.subcounty_id AS approved_by_subcounty_id,
        a.location_id AS approved_by_location_id
        
    FROM requisitions r
    LEFT JOIN vehicles v ON r.vehicle_regno = v.regno
    LEFT JOIN locations l ON v.location_id = l.id
    LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
    LEFT JOIN counties co ON v.county_id = co.id
    LEFT JOIN regions rg ON co.region_id = rg.id
    
    JOIN users u ON r.requested_by = u.id
    LEFT JOIN users a ON r.approved_by = a.id
    
    WHERE 1=1 $accessClause
    ORDER BY 
        FIELD(r.status, 'pending', 'approved', 'rejected'),
        r.requested_at DESC
";
$stmt = $pdo->query($sql);
$requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_end_flush();
?>


<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fas fa-file-signature me-2"></i>Requisitions</h2>
    <?php if (in_array($_SESSION['user']['group_id'] ?? -1, [1, 2, 3, 4, 5])): ?>
      <a href="admin.php?page=requisitions/form" class="btn btn-success">New Requisition</a>
    <?php endif; ?>

  </div>

  <table class="table table-bordered table-hover" data-table-type="requisitions-table">
    <thead>
      <tr>
        <th>Type</th>
        <th>Vehicle</th>
        <th>Requested By</th>
        <th>Description</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Approved By</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($requisitions as $req): ?>
        <tr>
          <td><?= htmlspecialchars($req['type']) ?></td>
          <td>
            <?= htmlspecialchars($req['vehicle_regno']) ?>
            (<?= htmlspecialchars($req['make'] ?? 'Unknown') ?>)
          </td>

          <td>
            <?= htmlspecialchars($req['requested_by_name']) ?>
            <small class="text-muted">
              (<?= htmlspecialchars(getUserLocationLabel($req, $regions, $counties, $subcounties, $locations, 'requested_by')) ?>)
            </small>
          </td>


          <td><?= nl2br(htmlspecialchars($req['description'])) ?></td>
          <td>KES <?= number_format($req['amount'], 2) ?></td>
          <td>
            <span
              class="badge bg-<?= $req['status'] === 'approved' ? 'success' : ($req['status'] === 'rejected' ? 'danger' : 'secondary') ?>">
              <?= ucfirst($req['status']) ?>
            </span>
          </td>
          <td>
            <?php if ($req['approved_by_name']): ?>
              <?= htmlspecialchars($req['approved_by_name']) ?>
              <small class="text-muted">
                (<?= htmlspecialchars(getUserLocationLabel($req, $regions, $counties, $subcounties, $locations, 'approved_by')) ?>)
              </small>
            <?php else: ?>
              -
            <?php endif; ?>
          </td>


          <td><?= date('d M Y, H:i', strtotime($req['requested_at'])) ?></td>

          <td>
            <?php
            $canApprove = (
              $req['status'] === 'pending'
              && in_array($_SESSION['user']['group_id'] ?? -1, [1, 2, 5])
              && ($_SESSION['user']['id'] ?? -1) !== $req['requested_by']
            );
            if ($canApprove):
              ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCSRFToken()) ?>">
                <input type="hidden" name="req_id" value="<?= (int) $req['id'] ?>">
                <button name="action" value="approve" class="btn btn-sm btn-success"
                  onclick="return confirm('Approve this requisition?')">Approve</button>
              </form>


              <a href="admin.php?page=requisitions/edit&id=<?= $req['id'] ?>" class="btn btn-sm btn-primary d-inline"
                onclick="return confirm('Edit this requisition?')">Edit</a>

              <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCSRFToken()) ?>">
                <input type="hidden" name="req_id" value="<?= (int) $req['id'] ?>">
                <button name="action" value="reject" class="btn btn-sm btn-danger"
                  onclick="return confirm('Reject this requisition?')">Reject</button>
              </form>

            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>