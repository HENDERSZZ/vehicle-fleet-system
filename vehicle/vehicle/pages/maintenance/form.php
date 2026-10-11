<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// CSRF Token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isEdit = isset($_GET['id']);
if ($isEdit && !can_edit()) die('Unauthorized');
if (!$isEdit && !can_add()) die('Unauthorized');

$record = ['regno' => '', 'description' => '', 'cost' => '', 'date' => date('Y-m-d'), 'receipt' => ''];
if ($isEdit) $record = getMaintenanceById($_GET['id']);

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    $regno = trim($_POST['regno'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cost = floatval($_POST['cost'] ?? 0);
    $date = $_POST['date'] ?? '';
    $receipt_path = $record['receipt'] ?? null;

    // Validation
    if (!$regno || !$description || !$cost || !$date) {
        $errors[] = "All fields except receipt are required.";
    }

    // Handle file upload
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
            $folder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $regno);
            $uploadDir = dirname(__DIR__, 2) . "/uploads/$folder/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $filename = uniqid("receipt_") . "." . $ext;
            $target = $uploadDir . $filename;

            if ($isEdit && !empty($receipt_path)) {
                $old = dirname(__DIR__, 2) . '/uploads/' . $receipt_path;
                if (file_exists($old)) unlink($old);
            }

            if (move_uploaded_file($tmp, $target)) {
                $receipt_path = "$folder/$filename";
            } else {
                $errors[] = "Failed to upload receipt.";
            }
        }
    }

    if (empty($errors)) {
        $data = compact('regno', 'description', 'cost', 'date');
        $data['receipt'] = $receipt_path;

        $isEdit ? updateMaintenance($_GET['id'], $data) : addMaintenance($data);
        echo "<script>location.href='admin.php?page=maintenance/index&success=1';</script>";
        exit;
    }

    // Repopulate form if error
    $record = compact('regno', 'description', 'cost', 'date');
    $record['receipt'] = $receipt_path;
}
?>

<div class="container mt-4">
  <h3><?= $isEdit ? "Edit" : "Add" ?> Maintenance</h3>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="col-md-2">
      <label class="form-label">Vehicle</label>
      <select name="regno" id="vehicleSelect" class="form-select" required>
        <?php if (!empty($record['regno'])): ?>
          <option value="<?= htmlspecialchars($record['regno']) ?>" selected><?= htmlspecialchars($record['regno']) ?></option>
        <?php endif; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="2" required><?= htmlspecialchars($record['description']) ?></textarea>
    </div>

    <div class="col-md-2">
      <label class="form-label">Cost (KES)</label>
      <input name="cost" type="number" step="0.01" value="<?= htmlspecialchars($record['cost']) ?>" class="form-control" required>
    </div>

    <div class="col-md-2">
      <label class="form-label">Date</label>
      <input type="date" name="date" value="<?= htmlspecialchars($record['date']) ?>" max="<?= date('Y-m-d') ?>" class="form-control" required>
    </div>

    <div class="col-md-3">
      <label class="form-label">Receipt (optional)</label>
      <input type="file" name="receipt" class="form-control" accept="image/*,.pdf">
      <?php if (!empty($record['receipt'])): ?>
        <small>
          <a href="/vehicle/uploads/<?= htmlspecialchars($record['receipt']) ?>" target="_blank">
            <?= htmlspecialchars(basename($record['receipt'])) ?>
          </a>
        </small>
      <?php endif; ?>
    </div>

    <div class="col-12">
      <button class="btn btn-primary"><?= $isEdit ? "Update" : "Save" ?></button>
      <a href="admin.php?page=maintenance/index" class="btn btn-secondary ms-2">Back</a>
    </div>
  </form>
</div>

