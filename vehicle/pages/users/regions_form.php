<?php
require_once __DIR__ . '/../../includes/config.php'; // Use PDO
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!is_superadmin() && !is_admin()) {
    echo "<script>alert('Unauthorized'); window.location.href = 'admin.php';</script>";
    exit;
}

$id = $_GET['id'] ?? '';
$name = '';
$error = '';

// === Fetch existing region if editing ===
if ($id) {
    $stmt = $pdo->prepare("SELECT name FROM regions WHERE id = ?");
    $stmt->execute([$id]);
    $region = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($region) {
        $name = $region['name'];
    }
}

// === Handle form submission ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    if ($name !== '') {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE regions SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO regions (name) VALUES (?)");
            $stmt->execute([$name]);
        }
        header("Location: admin.php?page=users/regions_index");
        exit;
    } else {
        $error = "Region name is required.";
    }
}
?>

<div class="container mt-4">
    <div
        class="mb-3 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
        <h2 class="mb-0"><?= $id ? 'Edit Region' : 'Add Region' ?></h2>
        <a href="admin.php?page=users/regions_index" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Back to Regions
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" class="card p-3 shadow-sm rounded" style="max-width: 600px; margin: 0 auto;">
        <div class="mb-3">
            <label for="name" class="form-label">Region Name</label>
            <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($name) ?>"
                required>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-save"></i> Save
            </button>
            <a href="admin.php?page=users/regions_index" class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>