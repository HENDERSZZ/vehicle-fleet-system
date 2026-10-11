<?php
ob_start();
if (session_status() === PHP_SESSION_NONE)
  session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['level'] > 2) {
  $_SESSION['error'] = "⛔ Access denied.";
  header("Location: /vehicle/admin.php?page=requisitions/index");
  exit;
}
// Ensure CSRF token is set
getCSRFToken();
$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
  $_SESSION['error'] = "❌ Invalid requisition ID.";
  header("Location: /vehicle/admin.php?page=requisitions/index");
  exit;
}

// Fetch current data
$stmt = $pdo->prepare("SELECT * FROM requisitions WHERE id = ?");
$stmt->execute([$id]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) {
  $_SESSION['error'] = "❌ Requisition not found.";
  header("Location: /vehicle/admin.php?page=requisitions/index");
  exit;
}

if ($req['status'] !== 'pending') {
  $_SESSION['error'] = "⚠️ Only pending requisitions can be edited.";
  header("Location: /vehicle/admin.php?page=requisitions/index");
  exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $_SESSION['error'] = "❌ Invalid or expired CSRF token.";
        header("Location: /vehicle/admin.php?page=requisitions/index");
        exit;
    }


  $type = trim($_POST['type'] ?? '');
  $regno = trim($_POST['regno'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $amount = floatval($_POST['amount'] ?? 0);

  if (!$type || !$regno || $amount <= 0) {
    $_SESSION['error'] = "❌ Fill all required fields correctly.";
  } else {
    if ($type === 'fuel') {
      $fuel = isset($_POST['fuel']) ? floatval($_POST['fuel']) : 0.0;
      $odometer = isset($_POST['odometer']) ? intval($_POST['odometer']) : 0;
      $station = trim($_POST['station'] ?? '');

      $stmt = $pdo->prepare("UPDATE requisitions SET type = ?, vehicle_regno = ?, description = ?, amount = ?, fuel = ?, odometer = ?, station = ? WHERE id = ?");
      $success = $stmt->execute([$type, $regno, $description, $amount, $fuel, $odometer, $station, $id]);
    } else {
      $stmt = $pdo->prepare("UPDATE requisitions SET type = ?, vehicle_regno = ?, description = ?, amount = ?, fuel = NULL, odometer = NULL, station = NULL WHERE id = ?");
      $success = $stmt->execute([$type, $regno, $description, $amount, $id]);
    }

    if ($success) {
      $_SESSION['success'] = "✅ Requisition updated successfully.";
      header("Location: /vehicle/admin.php?page=requisitions/index");
      exit;
    } else {
      $_SESSION['error'] = "❌ Update failed.";
    }
  }
}
?>

<form action="admin.php?page=requisitions/edit&id=<?= $id ?>" method="POST" class="row g-3">
  <?= csrfInput() ?>

  <div class="col-md-3">
    <label class="form-label">Requisition Type <span class="text-danger">*</span></label>
    <select name="type" class="form-select" required>
      <option value="">-- Select --</option>
      <?php
      $types = ['fuel', 'repair', 'parts', 'service', 'inspection', 'upgrade', 'general'];
      foreach ($types as $t) {
        $selected = $req['type'] === $t ? 'selected' : '';
        echo "<option value=\"$t\" $selected>" . ucfirst($t) . "</option>";
      }
      ?>
    </select>
  </div>

  <div class="col-md-3">
    <label class="form-label">Vehicle <span class="text-danger">*</span></label>
    <select name="regno" id="vehicleSelect" class="form-select" required>
      <option value="<?= htmlspecialchars($req['vehicle_regno']) ?>" selected>
        <?= htmlspecialchars($req['vehicle_regno']) ?>
      </option>
    </select>
  </div>

  <div class="col-md-2" id="fuelInputGroup" style="<?= $req['type'] === 'fuel' ? '' : 'display: none;' ?>">
    <label class="form-label">Fuel (Litres)</label>
    <input type="number" name="fuel" class="form-control" step="0.01"
      value="<?= htmlspecialchars($req['fuel'] ?? '') ?>">
  </div>

  <div class="col-md-3" id="stationInputGroup" style="<?= $req['type'] === 'fuel' ? '' : 'display: none;' ?>">
    <label class="form-label">Station</label>
    <input type="text" name="station" class="form-control" value="<?= htmlspecialchars($req['station'] ?? '') ?>">
  </div>

  <div class="col-md-2">
    <label class="form-label">Odometer</label>
    <input type="number" name="odometer" class="form-control" value="<?= htmlspecialchars($req['odometer'] ?? '') ?>">
  </div>

  <div class="col-md-4">
    <label class="form-label">Description <span class="text-danger">*</span></label>
    <textarea name="description" class="form-control" rows="3"
      required><?= htmlspecialchars($req['description'] ?? '') ?></textarea>
  </div>

  <div class="col-md-3">
    <label class="form-label">Estimated Amount (KES) <span class="text-danger">*</span></label>
    <input type="number" step="0.01" name="amount" class="form-control"
      value="<?= htmlspecialchars($req['amount'] ?? '') ?>" required>
  </div>

  <div class="mt-4">
    <button class="btn btn-primary">💾 Update</button>
    <a href="/vehicle/admin.php?page=requisitions/index" class="btn btn-outline-secondary">&larr; Back</a>
  </div>
</form>