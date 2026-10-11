<?php
require_once __DIR__ . '/../../includes/config.php'; // using PDO-based config
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$userLevel = $_SESSION['user']['user_level'] ?? null;
$isEdit = isset($_GET['id']);
$vehicleId = $isEdit ? (int) $_GET['id'] : null;

if ($isEdit && $userLevel > 2) {
  echo "<script>window.location.href = 'admin.php?page=vehicles/index';</script>";
  exit;
}

// Default structure for add
$vehicle = [
  'regno' => '',
  'chassisno' => '',
  'engineno' => '',
  'make' => '',
  'color' => '',
  'designation' => '',
  'county_id' => null,
  'subcounty_id' => null,
  'location_id' => null,
  'date' => '',
  'category_id' => null,
  'status_id' => null
];

// Load data if editing
if ($isEdit) {
  $vehicle = getVehicleById($pdo, $vehicleId);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!verifyCSRF()) {
    header("Location: admin.php?page=vehicles/index&csrf_error=1");
    exit;
  }

  $data = [
    'regno' => strtoupper(trim($_POST['regno'] ?? '')),
    'chassisno' => trim($_POST['chassisno'] ?? ''),
    'engineno' => trim($_POST['engineno'] ?? ''),
    'make' => trim($_POST['make'] ?? ''),
    'color' => trim($_POST['color'] ?? ''),
    'designation' => trim($_POST['designation'] ?? ''),
    'category_id' => $_POST['category_id'] ?? null,
    'date' => $_POST['date'] ?? '',
    'status_id' => $_POST['status_id'] ?? null,
    'location_id' => $_POST['location_id'] ?? null
  ];

  $required = ['regno', 'chassisno', 'engineno', 'make', 'color', 'designation', 'category_id', 'date', 'status_id', 'location_id'];
  $fieldLabels = [
    'regno' => 'Registration Number',
    'chassisno' => 'Chassis Number',
    'engineno' => 'Engine Number',
    'make' => 'Make',
    'color' => 'Color',
    'designation' => 'Designation',
    'category_id' => 'Category',
    'date' => 'Date of Registration',
    'status_id' => 'Status',
    'location_id' => 'Location'
  ];

  $missing = array_filter($required, fn($field) => empty($data[$field]));
  if ($missing) {
    $missingLabels = array_map(fn($f) => $fieldLabels[$f] ?? $f, $missing);
    $errors[] = "Please fill in: " . implode(', ', $missingLabels);
  }

  // Lookup subcounty and county from location
  if (!$errors && !empty($data['location_id'])) {
    $stmt = $pdo->prepare("
            SELECT sc.id AS subcounty_id, co.id AS county_id
            FROM locations l
            JOIN subcounties sc ON l.subcounty_id = sc.id
            JOIN counties co ON sc.county_id = co.id
            WHERE l.id = ?
        ");
    $stmt->execute([$data['location_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
      $data['subcounty_id'] = $result['subcounty_id'];
      $data['county_id'] = $result['county_id'];
    } else {
      $errors[] = "Invalid location selected.";
    }
  }

  // Duplicate field check
  if (!$errors) {
    $conflictFields = [
      'regno' => 'Registration Number',
      'chassisno' => 'Chassis Number',
      'engineno' => 'Engine Number'
    ];

    foreach ($conflictFields as $field => $label) {
      $value = strtoupper(trim($data[$field]));
      if ($isEdit) {
        $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE UPPER($field) = ? AND id != ?");
        $stmt->execute([$value, $vehicleId]);
      } else {
        $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE UPPER($field) = ?");
        $stmt->execute([$value]);
      }

      if ($stmt->fetch()) {
        $errors[] = "$label already exists.";
      }
    }
  }

  // Save
  if (!$errors) {
    if ($isEdit) {
      updateVehicle($pdo, $vehicleId, $data);
    } else {
      addVehicle($pdo, $data);
    }
    echo "<script>window.location.href = 'admin.php?page=vehicles/index&success=1';</script>";
    exit;
  }
}
?>

<div class="container mt-4">
  <div
    class="mb-3 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
    <h2><?= $isEdit ? 'Edit' : 'Add' ?> Vehicle</h2>
    <a href="admin.php?page=vehicles/index" class="btn btn-secondary">
      <i class="fa fa-arrow-left"></i> Back to List
    </a>
  </div>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
  <?php endif; ?>

  <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      ✅ Record saved successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      🗑️ Record deleted successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '0'): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      ❌ Failed to delete record.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['unauthorized'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      ⚠️ You are not authorized to perform that action.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <form method="POST" class="row g-3" novalidate>
    <?= csrfInput() ?>
    <div class="col-md-3">
      <label class="form-label">Registration Number <span class="text-danger">*</span></label>
      <input name="regno" value="<?= htmlspecialchars($vehicle['regno'] ?? '') ?>" class="form-control" required>
    </div>

    <div class="col-md-3">
      <label class="form-label">Chassis Number <span class="text-danger">*</span></label>
      <input name="chassisno" value="<?= htmlspecialchars($vehicle['chassisno'] ?? '') ?>" class="form-control"
        required>
    </div>

    <div class="col-md-3">
      <label class="form-label">Engine Number <span class="text-danger">*</span></label>
      <input name="engineno" value="<?= htmlspecialchars($vehicle['engineno'] ?? '') ?>" class="form-control" required>
    </div>

    <div class="col-md-3">
      <label class="form-label">Model <span class="text-danger">*</span> e.g Toyota-Prado</label>
      <input name="make" value="<?= htmlspecialchars($vehicle['make'] ?? '') ?>" class="form-control" required>
    </div>

    <div class="col-md-2">
      <label class="form-label">Color <span class="text-danger">*</span></label>
      <input name="color" value="<?= htmlspecialchars($vehicle['color'] ?? '') ?>" class="form-control" required>
    </div>

    <!-- Designation -->
    <div class="col-md-3">
      <label class="form-label">Designation <span class="text-danger">*</span></label>
      <select id="vehicleDesignationSelect" name="designation" class="form-select select2" required>
        <?php if (!empty($vehicle['designation'])): ?>
          <option value="<?= htmlspecialchars($vehicle['designation']) ?>" selected>
            <?= htmlspecialchars($vehicle['designation']) ?>
          </option>
        <?php endif; ?>
      </select>
    </div>

    <!-- Location Hierarchy -->
    <div class="col-md-12 location-select-group">
      <div class="row g-2">

        <!-- County -->
        <div class="col-md-3">
          <label for="vehicleCountySelect" class="form-label">County</label>
          <select id="vehicleCountySelect" name="county_id" class="form-select select2" required>
            <?php if (!empty($vehicle['county_id'])): ?>
              <option value="<?= $vehicle['county_id'] ?>" selected>
                <?= htmlspecialchars($vehicle['county_name'] ?? 'Selected County') ?>
              </option>
            <?php endif; ?>
          </select>
        </div>

        <!-- Subcounty -->
        <div class="col-md-3">
          <label for="vehicleSubcountySelect" class="form-label">Subcounty</label>
          <select id="vehicleSubcountySelect" name="subcounty_id" class="form-select select2" required>
            <?php if (!empty($vehicle['subcounty_id'])): ?>
              <option value="<?= $vehicle['subcounty_id'] ?>" selected>
                <?= htmlspecialchars($vehicle['subcounty_name'] ?? 'Selected Subcounty') ?>
              </option>
            <?php endif; ?>
          </select>
        </div>

        <!-- Location -->
        <div class="col-md-3">
          <label for="vehicleLocationSelect" class="form-label">Location</label>
          <select id="vehicleLocationSelect" name="location_id" class="form-select select2" required>
            <?php if (!empty($vehicle['location_id'])): ?>
              <option value="<?= $vehicle['location_id'] ?>" selected>
                <?= htmlspecialchars($vehicle['location_name'] ?? 'Selected Location') ?>
              </option>
            <?php endif; ?>
          </select>
        </div>

        <!-- Category -->
        <div class="col-md-3">
          <label class="form-label">Category <span class="text-danger">*</span></label>
          <select id="vehicleCategorySelect" name="category_id" class="form-select select2" required>
            <?php if (!empty($vehicle['category_id'])): ?>
              <option value="<?= $vehicle['category_id'] ?>" selected>
                <?= htmlspecialchars($vehicle['category_name'] ?? 'Selected Category') ?>
              </option>
            <?php endif; ?>
          </select>
        </div>

      </div>
    </div>

    <!-- Status -->
    <div class="col-md-3">
      <label class="form-label">Status <span class="text-danger">*</span></label>
      <select id="vehicleStatusSelect" name="status_id" class="form-select select2" required>
        <?php if (!empty($vehicle['status_id'])): ?>
          <option value="<?= $vehicle['status_id'] ?>" selected>
            <?= htmlspecialchars($vehicle['status_name'] ?? 'Selected Status') ?>
          </option>
        <?php endif; ?>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label">Date</label>
      <input type="date" name="date" value="<?= htmlspecialchars($vehicle['date'] ?? '') ?>" max="<?= date('Y-m-d') ?>"
        class="form-control">
    </div>

    <div class="mt-4">
      <button class="btn btn-primary" onclick="this.disabled=true;this.form.submit();">💾 Save</button>
    </div>
  </form>
</div>