<?php
require_once __DIR__ . '/../../includes/config.php'; // Use PDO
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Fetch all regions ordered by id using PDO
$stmt = $pdo->query("SELECT * FROM regions ORDER BY id ASC");
$regions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 gap-2">
        <h2 class="mb-0">Regions</h2>
        <?php if (is_superadmin()): ?>
            <a href="admin.php?page=users/regions_form" class="btn btn-success">
                <i class="fa fa-plus"></i> Add Region
            </a>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Region deleted successfully.</div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Region Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($regions) > 0): ?>
                    <?php foreach ($regions as $region): ?>
                        <tr>
                            <td><?= $region['id'] ?></td>
                            <td><?= htmlspecialchars($region['name']) ?></td>
                            <td>
                                <?php if (is_superadmin()): ?>
                                    <a href="admin.php?page=users/regions_form&id=<?= $region['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="text-center">No regions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
