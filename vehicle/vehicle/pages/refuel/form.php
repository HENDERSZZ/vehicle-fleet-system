<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['user'])) {
  echo "<div class='alert alert-danger m-4'>⛔ Please log in first.</div>";
  return;
}

$user = $_SESSION['user'];
$user_level = $user['level'] ?? 3;
$editing = false;
$errors = [];
$success = "";
$refuel = [
  'regno' => '',
  'fuel' => '',
  'cost' => '',
  'station' => '',
  'odometer' => '',
  'remarks' => '',
  'date' => date('Y-m-d'),
  'receipt' => '',
];

$id = $_GET['id'] ?? null;
if ($id && is_numeric($id)) {
  $stmt = $conn->prepare("SELECT * FROM refuel WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = $stmt->get_result();
  $refuel = $result->fetch_assoc();
  if ($refuel) {
    $editing = true;
  } else {
    echo "<div class='alert alert-danger'>❌ Refuel record not found.</div>";
    return;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Permission check
  if ($editing && !in_array($user_level, [1, 2])) {
    echo "<div class='alert alert-danger m-4'>⛔ You do not have permission to edit refuel records.</div>";
    return;
  } elseif (!$editing && $user_level > 3) {
    echo "<div class='alert alert-danger m-4'>⛔ You do not have permission to add refuel records.</div>";
    return;
  }

  // ✅ Central CSRF check
  if (!verifyCSRF()) {
    $errors[] = "Invalid CSRF token.";
  }

  // Collect inputs
  $regno = $_POST['regno'] ?? '';
  $fuel = floatval($_POST['fuel'] ?? 0);
  $cost = floatval($_POST['cost'] ?? 0);
  $station = $_POST['station'] ?? '';
  $odometer = $_POST['odometer'] ?? null;
  $remarks = $_POST['remarks'] ?? '';
$datePicked = $_POST['date'] ?? date('Y-m-d');
$date = $datePicked . ' ' . date('H:i:s'); // combine picked date with current server time
  if (!$regno || $fuel <= 0 || $cost <= 0 || !$date) {
    $errors[] = "All required fields must be filled and valid.";
  }

  // Handle receipt upload
  $filename = $refuel['receipt'] ?? null;
  if (empty($errors) && !empty($_FILES['receipt']['name'])) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $maxFileSize = 1.3 * 1024 * 1024;

    $tmpFile = $_FILES['receipt']['tmp_name'];
    $mime = mime_content_type($tmpFile);
    $size = $_FILES['receipt']['size'];

    if (!in_array($mime, $allowed_types)) {
      $errors[] = "Invalid file type. Only JPG, PNG, GIF or PDF allowed.";
    } elseif ($size > $maxFileSize) {
      $errors[] = "File too large. Maximum allowed size is 1.3MB.";
    } else {
      $vehicleFolder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $regno);
      $uploadDir = __DIR__ . '/../../uploads/' . $vehicleFolder . '/';
      if (!is_dir($uploadDir))
        mkdir($uploadDir, 0755, true);

      if ($editing && $filename && file_exists(__DIR__ . '/../../uploads/' . $filename)) {
        unlink(__DIR__ . '/../../uploads/' . $filename);
      }

      $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
      $filename = $vehicleFolder . '/' . uniqid("receipt_") . "." . $ext;
      $targetFile = $uploadDir . basename($filename);

      if (!move_uploaded_file($tmpFile, $targetFile)) {
        $errors[] = "Failed to upload file.";
      }
    }
  }

  // Save to DB if no errors
  if (empty($errors)) {
    if ($editing) {
      $stmt = $conn->prepare("UPDATE refuel SET regno = ?, fuel = ?, cost = ?, station = ?, odometer = ?, remarks = ?, date = ?, receipt = ? WHERE id = ?");
      $stmt->bind_param("sddsdsssi", $regno, $fuel, $cost, $station, $odometer, $remarks, $date, $filename, $id);
    } else {
      $stmt = $conn->prepare("INSERT INTO refuel (regno, fuel, cost, station, odometer, remarks, date, receipt) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("sddsdsss", $regno, $fuel, $cost, $station, $odometer, $remarks, $date, $filename);
    }

    if ($stmt->execute()) {
      $success = $editing ? "✅ Refuel record updated." : "✅ Refuel record added.";
      if (!$editing)
        $_POST = [];
    } else {
      $errors[] = "❌ Database error. Please try again.";
    }
  }
}
// Fetch vehicles
$stmt = $pdo->query("SELECT regno FROM vehicles ORDER BY regno ASC");
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$regno_filter = $_GET['regno'] ?? '';
?>

<div class="container mt-4">
  <h4><?= $editing ? "Edit" : "Add" ?> Refuel Record</h4>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="row g-3">
    <?= csrfInput() ?>
    <div class="col-md-2">
      <label class="form-label">Vehicle <span class="text-danger">*</span></label>
      <select name="regno" id="vehicleSelect" class="form-select select2" required>
        <?php if ($regno_filter): ?>
          <option value="<?= htmlspecialchars($regno_filter) ?>" selected><?= htmlspecialchars($regno_filter) ?></option>
        <?php endif; ?>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label">Fuel (Litres)</label>
      <input type="number" name="fuel" class="form-control" step="0.01"
        value="<?= htmlspecialchars($refuel['fuel'] ?? $_POST['fuel'] ?? '') ?>" required>
    </div>

    <div class="col-md-2">
      <label class="form-label">Cost (KES)</label>
      <input type="number" name="cost" class="form-control" step="0.01"
        value="<?= htmlspecialchars($refuel['cost'] ?? $_POST['cost'] ?? '') ?>" required>
    </div>

    <div class="col-md-3">
      <label class="form-label">Station</label>
      <input type="text" name="station" class="form-control"
        value="<?= htmlspecialchars($refuel['station'] ?? $_POST['station'] ?? '') ?>">
    </div>

    <div class="col-md-2">
      <label class="form-label">Odometer</label>
      <input type="number" name="odometer" class="form-control"
        value="<?= htmlspecialchars($refuel['odometer'] ?? $_POST['odometer'] ?? '') ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Remarks</label>
      <textarea name="remarks"
        class="form-control"><?= htmlspecialchars($refuel['remarks'] ?? $_POST['remarks'] ?? '') ?></textarea>
    </div>

    <div class="col-md-2">
      <label class="form-label">Date</label>
      <input type="date" name="date" class="form-control"
        value="<?= htmlspecialchars(date('Y-m-d', strtotime($refuel['date'] ?? $_POST['date'] ?? date('Y-m-d')))) ?>"
        max="<?= date('Y-m-d') ?>" required>
    </div>


    <div class="col-md-3">
      <label class="form-label"><?= $editing ? 'Upload New Receipt' : 'Upload Receipt' ?> (Image/PDF)</label>
      <input type="file" name="receipt" class="form-control" accept="image/*,.pdf">
      <?php if ($editing && $refuel['receipt']): ?>
        <div class="form-text">Current: <a href="uploads/<?= htmlspecialchars($refuel['receipt']) ?>"
            target="_blank"><?= htmlspecialchars($refuel['receipt']) ?></a></div>
      <?php endif; ?>
    </div>

    <div class="col-12">
      <button class="btn btn-primary"><?= $editing ? 'Update' : 'Save' ?></button>
      <a href="admin.php?page=refuel/index" class="btn btn-secondary ms-2">Back</a>
    </div>
  </form>
</div>