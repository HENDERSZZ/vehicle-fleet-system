<?php
if (session_status() === PHP_SESSION_NONE)
  session_start();

require_once __DIR__ . '/../../includes/config.php'; // Make sure this sets up $pdo

getCSRFToken(); // ensures token is always set

// Fetch vehicles
$stmt = $pdo->query("SELECT regno FROM vehicles ORDER BY regno ASC");
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$regno_filter = $_GET['regno'] ?? '';
?>

<div class="container mt-4">
  <h2>New Requisition</h2>

  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger">
      <?= match ($_GET['error']) {
        'csrf' => '⛔ Invalid CSRF token.',
        'validation' => '❌ Please fill all required fields correctly.',
        'access' => '⛔ You do not have permission to perform this action.',
        'savefail' => '❌ Failed to save requisition. Try again later.',
        default => '❌ Unknown error occurred.'
      } ?>
    </div>
  <?php elseif (!empty($_GET['success'])): ?>
    <div class="alert alert-success">✅ Requisition submitted successfully.</div>
  <?php endif; ?>


  <form action="admin.php?page=requisitions/save" method="POST" class="row g-3">
    <?= csrfInput() ?>

    <div class="col-md-3">
      <label class="form-label">Requisition Type <span class="text-danger">*</span></label>
      <select name="type" id="typeSelect" class="form-select" required onchange="toggleFuelFields(this.value)">
        <option value="">-- Select --</option>
        <option value="fuel">Fuel</option>
        <option value="repair">Repair</option>
        <option value="parts">Parts</option>
        <option value="service">Service</option>
        <option value="inspection">Inspection</option>
        <option value="upgrade">Upgrade</option>
        <option value="general">General</option>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label">Vehicle <span class="text-danger">*</span></label>
      <select name="regno" id="vehicleSelect" class="form-select select2" required>
        <?php if ($regno_filter): ?>
          <option value="<?= htmlspecialchars($regno_filter) ?>" selected><?= htmlspecialchars($regno_filter) ?></option>
        <?php endif; ?>
      </select>
    </div>

    <!-- Fuel-specific fields -->
    <div class="col-md-2" id="fuelInputGroup" style="display: none;">
      <label class="form-label">Fuel (Litres)</label>
      <input type="number" name="fuel" class="form-control" step="0.01">
    </div>

    <div class="col-md-3" id="stationInputGroup" style="display: none;">
      <label class="form-label">Station</label>
      <input type="text" name="station" class="form-control">
    </div>

    <div class="col-md-2">
      <label class="form-label">Odometer</label>
      <input type="number" name="odometer" class="form-control">
    </div>

    <div class="col-md-4">
      <label class="form-label">Description <span class="text-danger">*</span></label>
      <textarea name="description" class="form-control" rows="3"
        required><?= htmlspecialchars($_GET['desc'] ?? '') ?></textarea>
    </div>

    <div class="col-md-3">
      <label class="form-label">Estimated Amount (KES) <span class="text-danger">*</span></label>
      <input type="number" step="0.01" name="amount" class="form-control"
        value="<?= htmlspecialchars($_GET['amount'] ?? '') ?>" required>
    </div>

    <div class="mt-4">
      <button class="btn btn-primary">💾 Save</button>
      <a href="/vehicle/admin.php?page=requisitions/index" class="btn btn-outline-secondary">&larr; Back</a>
    </div>
  </form>
</div>

<script>
  function toggleFuelFields(type) {
    const fuelGroup = document.getElementById('fuelInputGroup');
    const stationGroup = document.getElementById('stationInputGroup');
    if (type === 'fuel') {
      fuelGroup.style.display = 'block';
      stationGroup.style.display = 'block';
    } else {
      fuelGroup.style.display = 'none';
      stationGroup.style.display = 'none';
    }
  }
</script>