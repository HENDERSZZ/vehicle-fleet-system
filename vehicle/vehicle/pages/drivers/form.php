<?php 
if (session_status() === PHP_SESSION_NONE)
    session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['user'])) {
    echo "<script>window.location.href = '../../auth/login.php';</script>";
    exit;
}

getCSRFToken();
$current_user = $_SESSION['user'];

$id = $_GET['id'] ?? null;
$name = $username = $password = $phone = '';
$status = $region_id = $county_id = $subcounty_id = $location_id = '';
$county_name = $subcounty_name = $location_name = '';
$message = '';

$regions = $pdo->query("SELECT id, name FROM regions ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// --- Edit mode ---
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM drivers WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $name = $row['name'];
        $username = $row['username'];
        $phone = $row['phone'];
        $status = $row['status'];
        $region_id = $row['region_id'] ?? null;
        $county_id = $row['county_id'] ?? null;
        $subcounty_id = $row['subcounty_id'] ?? null;
        $location_id = $row['location_id'] ?? null;

        $county_name = getNameById($pdo, 'counties', $county_id);
        $subcounty_name = getNameById($pdo, 'subcounties', $subcounty_id);
        $location_name = getNameById($pdo, 'locations', $location_id);
    }
}

function getNameById($pdo, $table, $id)
{
    if (!$id) return null;
    $stmt = $pdo->prepare("SELECT name FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}

// --- Handle submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $region_id = $_POST['region_id'] ?? null;
    $county_id = $_POST['county_id'] ?? null;
    $subcounty_id = $_POST['subcounty_id'] ?? null;
    $location_id = $_POST['location_id'] ?? null;

    if (empty($region_id)) {
        $message = "⚠️ Region is required.";
    } else {
        try {
            if ($id) {
                // --- Update ---
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE drivers SET 
                            name=?, username=?, password=?, phone=?, status=?, 
                            region_id=?, county_id=?, subcounty_id=?, location_id=? 
                        WHERE id=?");
                    $stmt->execute([$name, $username, $hash, $phone, $status, $region_id, $county_id, $subcounty_id, $location_id, $id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE drivers SET 
                            name=?, username=?, phone=?, status=?, 
                            region_id=?, county_id=?, subcounty_id=?, location_id=? 
                        WHERE id=?");
                    $stmt->execute([$name, $username, $phone, $status, $region_id, $county_id, $subcounty_id, $location_id, $id]);
                }
            } else {
                // --- Insert ---
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO drivers (name, username, password, phone, status, region_id, county_id, subcounty_id, location_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $username, $hash, $phone, $status, $region_id, $county_id, $subcounty_id, $location_id]);
            }

            echo "<script>window.location.href = '/vehicle/admin.php?page=drivers/index';</script>";
            exit;
        } catch (PDOException $e) {
            $message = "❌ Error: " . $e->getMessage();
        }
    }
}
?>

<!-- HTML -->
<title><?= $id ? 'Edit' : 'Add' ?> Driver</title>
<div class="container mt-4">
  <h2><?= $id ? 'Edit' : 'Add' ?> Driver</h2>

  <?php if (!empty($message)): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form method="POST">
    <?= csrfInput() ?>
    <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Name</label>
        <input name="name" value="<?= htmlspecialchars($name) ?>" class="form-control" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Username</label>
        <input name="username" value="<?= htmlspecialchars($username) ?>" class="form-control" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Password <?= $id ? '(leave blank to keep current)' : '' ?></label>
        <input name="password" type="password" class="form-control" <?= $id ? '' : 'required' ?>>
      </div>

      <div class="col-md-3">
        <label class="form-label">Phone</label>
        <input name="phone" value="<?= htmlspecialchars($phone) ?>" class="form-control">
      </div>

      <div class="col-md-3">
        <label class="form-label">Region</label>
        <select name="region_id" class="form-select" required>
          <option value="">-- Select Region --</option>
          <?php foreach ($regions as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $r['id'] == $region_id ? 'selected' : '' ?>>
              <?= htmlspecialchars($r['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">County</label>
        <select name="county_id" id="countySelect" class="form-select select2">
          <?php if ($county_id && $county_name): ?>
            <option value="<?= $county_id ?>" selected><?= htmlspecialchars($county_name) ?></option>
          <?php endif; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Subcounty</label>
        <select name="subcounty_id" id="subcountySelect" class="form-select select2">
          <?php if ($subcounty_id && $subcounty_name): ?>
            <option value="<?= $subcounty_id ?>" selected><?= htmlspecialchars($subcounty_name) ?></option>
          <?php endif; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Location</label>
        <select name="location_id" id="locationSelect" class="form-select select2">
          <?php if ($location_id && $location_name): ?>
            <option value="<?= $location_id ?>" selected><?= htmlspecialchars($location_name) ?></option>
          <?php endif; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
          <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
    </div>

    <div class="mt-4">
      <button class="btn btn-primary">💾 Save</button>
      <a href="/vehicle/admin.php?page=drivers/index" class="btn btn-outline-secondary">&larr; Back</a>
    </div>
  </form>
</div>
