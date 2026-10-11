<?php
require_once __DIR__ . '/../../includes/config.php'; // ✅ Use PDO config
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$token = getCSRFToken();

$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
if (!$isEdit || !can_edit()) {
  echo "<div class='alert alert-danger m-4'>⛔ Access denied. Only editing existing maintenance records is allowed.</div>";
  exit;
}

// Fetch the record
$id = (int) $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM maintenance WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
  echo "<div class='alert alert-warning m-4'>⚠️ Record not found.</div>";
  exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        die('Invalid CSRF token.');
    }

  $description = trim($_POST['description'] ?? '');
  $cost = floatval($_POST['cost'] ?? 0);
  $new_receipt_path = $record['receipt']; // fallback

  if (!$description || $cost <= 0) {
    $errors[] = "Description and valid cost are required.";
  }

  // File upload
  if (!empty($_FILES['receipt']['name'])) {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $maxSize = 1.3 * 1024 * 1024;
    $tmp = $_FILES['receipt']['tmp_name'];
    $type = mime_content_type($tmp);
    $size = $_FILES['receipt']['size'];

    if (!in_array($type, $allowed)) {
      $errors[] = "Invalid file type.";
    } elseif ($size > $maxSize) {
      $errors[] = "File too large (max 1.3MB).";
    } else {
      $folder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $record['regno']);
      $uploadDir = dirname(__DIR__, 2) . "/uploads/$folder/";
      if (!is_dir($uploadDir))
        mkdir($uploadDir, 0755, true);

      $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
      $filename = uniqid("receipt_") . "." . $ext;
      $target = $uploadDir . $filename;

      if (!empty($record['receipt'])) {
        $old = dirname(__DIR__, 2) . "/uploads/" . $record['receipt'];
        if (file_exists($old))
          unlink($old);
      }

      if (move_uploaded_file($tmp, $target)) {
        $new_receipt_path = "$folder/$filename";
      } else {
        $errors[] = "Failed to upload receipt.";
      }
    }
  }

  if (empty($errors)) {
    $stmt = $pdo->prepare("UPDATE maintenance SET description = ?, cost = ?, receipt = ?, date = NOW() WHERE id = ?");
    $stmt->execute([$description, $cost, $new_receipt_path, $id]);

    $_SESSION['flash'] = "Maintenance record updated successfully.";
    header("Location: admin.php?page=maintenance/index");
    exit;
  }

  // If error, repopulate
  $record['description'] = $description;
  $record['cost'] = $cost;
  $record['receipt'] = $new_receipt_path;
}
?>

<div class="container mt-4">
  <h3>Edit Maintenance Record</h3>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="row g-3">
    <?= csrfInput() ?>
    <div class="col-md-3">
      <label class="form-label">Vehicle</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars($record['regno']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="3"
        required><?= htmlspecialchars($record['description']) ?></textarea>
    </div>

    <div class="col-md-3">
      <label class="form-label">Cost (KES)</label>
      <input name="cost" type="number" step="0.01" value="<?= htmlspecialchars($record['cost']) ?>" class="form-control"
        required>
    </div>

    <div class="col-md-4">
      <label class="form-label">Receipt (optional)</label>
      <input type="file" name="receipt" class="form-control" accept="image/*,.pdf">
      <?php if (!empty($record['receipt'])): ?>
        <small class="d-block mt-1">Existing:
          <a href="/vehicle/uploads/<?= htmlspecialchars($record['receipt']) ?>" target="_blank">
            <?= htmlspecialchars(basename($record['receipt'])) ?>
          </a>
        </small>
      <?php endif; ?>
    </div>

    <div class="col-12">
      <button class="btn btn-primary">Update</button>
      <a href="admin.php?page=maintenance/index" class="btn btn-secondary ms-2">Back</a>
    </div>
  </form>
</div>