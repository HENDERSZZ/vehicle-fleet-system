<?php
require_once __DIR__ . '/../../includes/config.php'; // ✅ PDO
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['user'])) {
  echo "<div class='alert alert-danger m-4'>⛔ Please log in first.</div>";
  return;
}

$user = $_SESSION['user'];
$user_level = $user['level'] ?? 3;
$errors = [];
$success = "";
$receipt_path = null;

$token = getCSRFToken();

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
  echo "<div class='alert alert-danger'>❌ Invalid or missing refuel ID.</div>";
  return;
}

// Fetch refuel record
$stmt = $pdo->prepare("SELECT * FROM refuel WHERE id = ?");
$stmt->execute([$id]);
$refuel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refuel) {
  echo "<div class='alert alert-danger'>❌ Refuel record not found.</div>";
  return;
}

if (!in_array($user_level, [0, 1, 2])) {
  echo "<div class='alert alert-danger m-4'>⛔ You do not have permission to edit refuel records.</div>";
  return;
}

// POST logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verifyCSRF()) {
    $errors[] = "Invalid or expired CSRF token.";
  }

  $fuel = floatval($_POST['fuel'] ?? 0);
  $cost = floatval($_POST['cost'] ?? 0);
  $station = trim($_POST['station'] ?? '');
  $odometer = $_POST['odometer'] ?? null;
  $remarks = trim($_POST['remarks'] ?? '');

  $date = date('Y-m-d H:i:s'); // auto-set to current timestamp
  if ($fuel <= 0 || $cost <= 0) {
    $errors[] = "Fuel and cost are required and must be valid.";
  }


  // Receipt handling
  $filename = $refuel['receipt'] ?? null;
  if (!empty($_FILES['receipt']['name'])) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $maxFileSize = 1.3 * 1024 * 1024;

    $tmpFile = $_FILES['receipt']['tmp_name'];
    $mime = mime_content_type($tmpFile);
    $size = $_FILES['receipt']['size'];

    if (!in_array($mime, $allowed_types)) {
      $errors[] = "Invalid file type. Only JPG, PNG, GIF or PDF allowed.";
    } elseif ($size > $maxFileSize) {
      $errors[] = "File too large. Max size: 1.3MB.";
    } else {
      $vehicleFolder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $refuel['regno']);
      $uploadDir = __DIR__ . '/../../uploads/' . $vehicleFolder . '/';
      if (!is_dir($uploadDir))
        mkdir($uploadDir, 0755, true);

      // Delete old file
      if ($filename && file_exists(__DIR__ . '/../../uploads/' . $filename)) {
        unlink(__DIR__ . '/../../uploads/' . $filename);
      }

      $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
      $filename = $vehicleFolder . '/' . uniqid("receipt_") . "." . strtolower($ext);
      $targetFile = $uploadDir . basename($filename);

      if (!move_uploaded_file($tmpFile, $targetFile)) {
        $errors[] = "Failed to upload file.";
      }
    }
  }


  if (empty($errors)) {
    $date = date('Y-m-d H:i:s'); // Auto-set edit time

    $stmt = $pdo->prepare("UPDATE refuel SET fuel = ?, cost = ?, station = ?, odometer = ?, remarks = ?, date = ?, receipt = ? WHERE id = ?");
    if ($stmt->execute([$fuel, $cost, $station, $odometer, $remarks, $date, $filename, $id])) {
      $_SESSION['flash_success'] = "✅ Refuel record updated.";
      header("Location: admin.php?page=refuel/index");
      exit;
    } else {
      $errors[] = "❌ Database error.";
    }

  }

  // Refresh current values
  $refuel = array_merge($refuel, compact('fuel', 'cost', 'station', 'odometer', 'remarks', 'date'));
  $refuel['receipt'] = $filename;
}
?>


<div class="container mt-4">
  <h4>Edit Refuel Record</h4>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="row g-3">
    <?= csrfInput() ?>

    <div class="col-md-3">
      <label class="form-label">Vehicle (from requisition)</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars($refuel['regno']) ?>" readonly>
    </div>

    <div class="col-md-2">
      <label class="form-label">Fuel (Litres)</label>
      <input type="number" name="fuel" class="form-control" step="0.01" value="<?= htmlspecialchars($refuel['fuel']) ?>"
        required>
    </div>

    <div class="col-md-2">
      <label class="form-label">Cost (KES)</label>
      <input type="number" name="cost" class="form-control" step="0.01" value="<?= htmlspecialchars($refuel['cost']) ?>"
        required>
    </div>

    <div class="col-md-3">
      <label class="form-label">Station</label>
      <input type="text" name="station" class="form-control" value="<?= htmlspecialchars($refuel['station']) ?>">
    </div>

    <div class="col-md-2">
      <label class="form-label">Odometer</label>
      <input type="number" name="odometer" class="form-control" value="<?= htmlspecialchars($refuel['odometer']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Remarks</label>
      <textarea name="remarks" class="form-control"><?= htmlspecialchars($refuel['remarks']) ?></textarea>
    </div>


    <div class="col-md-4">
      <label class="form-label">Upload New Receipt (Image/PDF)</label>
      <input type="file" name="receipt" class="form-control" accept="image/*,.pdf">
      <?php if ($refuel['receipt']): ?>
        <small class="form-text">Current: <a href="uploads/<?= htmlspecialchars($refuel['receipt']) ?>"
            target="_blank"><?= htmlspecialchars(basename($refuel['receipt'])) ?></a></small>
      <?php endif; ?>
    </div>

    <div class="col-12">
      <button class="btn btn-primary">Update</button>
      <a href="admin.php?page=refuel/index" class="btn btn-secondary ms-2">Back</a>
    </div>
  </form>
</div>