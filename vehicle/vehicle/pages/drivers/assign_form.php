<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../auth/auth.php';

// --- Access Control ---
if (!isset($_SESSION['user'])) {
    echo "<script>window.location.href='/vehicle/auth/login.php';</script>";
    exit;
}

$currentUser = $_SESSION['user'];
$group_id = $currentUser['group_id'] ?? 0;

// Only Admins or Dispatchers can assign
if (!in_array($group_id, [1, 2, 5])) {
    die("❌ Unauthorized access.");
}

// CSRF token setup
getCSRFToken();

$message = '';
$drivers = [];
$vehicles = [];

// --- Fetch drivers (active only) ---
try {
    $stmt = $pdo->query("SELECT id, name FROM drivers WHERE status = 'active' ORDER BY name ASC");
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "⚠️ Error loading drivers: " . $e->getMessage();
}

// --- Fetch available vehicles (active and not currently assigned) ---
try {
    $stmt = $pdo->query("
        SELECT v.id, v.regno, v.make, v.model
        FROM vehicles v
        WHERE v.status = 'active'
        AND v.id NOT IN (
            SELECT vehicle_id FROM vehicle_assignments WHERE released_at IS NULL
        )
        ORDER BY v.regno ASC
    ");
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "⚠️ Error loading vehicles: " . $e->getMessage();
}

// --- Handle assignment form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    if (!verifyCSRF()) {
        $message = "❌ Invalid CSRF token.";
    } else {
        $vehicle_id = $_POST['regno'] ?? null;
        $driver_id = $_POST['driver_id'] ?? null;

        if (empty($vehicle_id) || empty($driver_id)) {
            $message = "⚠️ Please select both vehicle and driver.";
        } else {
            try {
                // Ensure the driver or vehicle isn’t already assigned (active = released_at IS NULL)
                $check = $pdo->prepare("
                    SELECT COUNT(*) FROM vehicle_assignments
                    WHERE (vehicle_id = :vehicle_id OR driver_id = :driver_id)
                    AND released_at IS NULL
                ");
                $check->execute([
                    'vehicle_id' => $vehicle_id,
                    'driver_id' => $driver_id
                ]);

                if ($check->fetchColumn() > 0) {
                    $message = "⚠️ Either the driver or vehicle is already assigned.";
                } else {
                    // Create new assignment
                    $stmt = $pdo->prepare("
                        INSERT INTO vehicle_assignments (vehicle_id, driver_id, assigned_by, assigned_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$vehicle_id, $driver_id, $currentUser['id']]);

                    $message = "✅ Vehicle successfully assigned to driver!";
                }
            } catch (PDOException $e) {
                $message = "❌ Error assigning driver: " . $e->getMessage();
            }
        }
    }
}
?>

<!-- HTML: Form Layout -->
<div class="container mt-4">
    <h4 class="mb-3"><i class="fas fa-car me-2"></i> Assign Driver to Vehicle</h4>

    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (can_add()): ?>
        <form method="POST" class="row g-2 mb-4">
            <div class="col-md-4">
                <label class="form-label">Vehicle <span class="text-danger">*</span></label>
                <select name="regno" id="vehicleSelect" class="form-select select2" required>
                    <option value="">-- Select Vehicle --</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= $v['id'] ?>">
                            <?= htmlspecialchars($v['regno'] . ' - ' . $v['make'] . ' ' . $v['model']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Driver <span class="text-danger">*</span></label>
                <select name="driver_id" class="form-select select2" required>
                    <option value="">-- Select Driver --</option>
                    <?php foreach ($drivers as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <?= csrfInput() ?>
                <button type="submit" name="assign" class="btn btn-success w-100">
                    <i class="fa fa-plus me-1"></i> Assign Vehicle
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>
