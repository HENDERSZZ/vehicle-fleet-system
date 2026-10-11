<?php
// -------------------------------
// Add/Edit Category Page
// -------------------------------

if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();

require_once __DIR__ . '/../../includes/config.php'; // includes $pdo and CSRF functions
require_once __DIR__ . '/../../includes/functions.php'; // optional, if needed

// 🔐 Auth check
if (!isset($_SESSION['user'])) {
    header('Location: /vehicle/auth/login.php');
    exit;
}

// Generate CSRF token
$csrfToken = getCSRFToken();

// Get category ID for edit
$id = $_GET['id'] ?? null;
$name = '';
$error = '';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $name = $row['name'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 🔐 Validate CSRF token
    if (!verifyCSRF()) {
        die('❌ Invalid or expired CSRF token.');
    }

    $name = trim($_POST['name']);

    // Duplicate check (case-insensitive)
    $checkStmt = $id
        ? $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND id != ?")
        : $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?)");

    $params = $id ? [$name, $id] : [$name];
    $checkStmt->execute($params);

    if ($checkStmt->rowCount() > 0) {
        $error = "❌ A category with the name '" . htmlspecialchars($name) . "' already exists.";
    } else {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);
        }

        // ✅ Redirect helper
        function redirect($url) {
            if (!headers_sent()) {
                ob_end_clean();
                header("Location: $url");
                exit;
            } else {
                echo "<script>window.location.href='$url';</script>";
                exit;
            }
        }

        redirect('/vehicle/admin.php?page=categories/index');
    }
}

if (ob_get_level()) ob_end_flush();
?>

<!-- HTML Form -->
<title><?= $id ? 'Edit' : 'Add' ?> Category</title>
<div class="container mt-4">
    <div class="mb-3">
        <a href="/vehicle/admin.php?page=categories/index" class="btn btn-outline-secondary">&larr; Back</a>
    </div>

    <h2 class="mb-4"><?= $id ? 'Edit' : 'Add' ?> Category</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" class="col-md-5" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input name="name" value="<?= htmlspecialchars($name) ?>" class="form-control" required>
        </div>
        <button class="btn btn-primary">Save</button>
    </form>
</div>
