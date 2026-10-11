<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();

require_once __DIR__ . '/../../includes/config.php';   // provides $pdo and CSRF functions
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// 🔐 Redirect if not authenticated
if (!isset($_SESSION['user'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM categories WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND name LIKE ?";
    $params[] = "%$search%";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$queryString = http_build_query(['search' => $search]);

// 🔐 Get CSRF token for all forms
$csrfToken = getCSRFToken();

$group = $_SESSION['user']['group_id'] ?? null;
?>

<div class="container py-4">
    <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
        <h2 class="mb-2"><i class="fas fa-th-large"></i> Categories</h2>
        <div>
            <?php if (is_superadmin() || $group === ROLE_ADMIN): ?>
                <a href="admin.php?page=categories/form" class="btn btn-success">
                    <i class="fa fa-plus me-1"></i> Add Category
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="admin.php" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="page" value="categories/index">
        <div class="col-md-4">
            <label class="form-label">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Category name...">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100"><i class="fa fa-search me-1"></i> Filter</button>
        </div>
        <div class="col-md-2">
            <a href="admin.php?page=categories/index" class="btn btn-outline-secondary w-100">
                <i class="fa fa-sync-alt"></i> Reset
            </a>
        </div>
    </form>

    <p class="text-muted mb-3">
        Showing <strong><?= count($rows) ?></strong> result(s)
        <?= $search ? 'for "<em>' . htmlspecialchars($search) . '</em>"' : '' ?>.
    </p>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle text-center">
            <thead class="table-light">
                <tr>
                    <th><i class="fa fa-hashtag"></i> ID</th>
                    <th><i class="fa fa-tag"></i> Name</th>
                    <th><i class="fa fa-gears"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted">No categories found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td>
                                <?php if (is_superadmin() || $group === ROLE_ADMIN): ?>
                                    <a href="admin.php?page=categories/form&id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="fa fa-edit"></i>
                                    </a>

                                    <form method="POST" action="admin.php?page=categories/delete" style="display:inline;" onsubmit="return confirm('Delete this category?')">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <button class="btn btn-danger btn-sm">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
