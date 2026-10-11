<?php
session_start();
require_once __DIR__ . '/../../includes/config.php'; // provides getCSRFToken()

// 🔐 Get CSRF token
$csrfToken = getCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>License Activation</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <div class="card shadow-lg">
        <div class="card-header bg-primary text-white">
            <h4>Activate License</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="activate.php">
                <div class="mb-3">
                    <label for="license_key" class="form-label">License Key</label>
                    <input type="text" class="form-control" name="license_key" id="license_key" required>
                </div>
                <div class="mb-3">
                    <label for="domain" class="form-label">Domain</label>
                    <input type="text" class="form-control" name="domain" id="domain" required>
                </div>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="btn btn-success">Activate</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
