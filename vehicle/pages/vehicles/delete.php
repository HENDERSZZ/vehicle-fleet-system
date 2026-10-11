<?php
require_once __DIR__ . '/../../includes/config.php'; // ✅ use PDO
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "<div class='alert alert-danger m-4'>❌ Invalid request method.</div>";
    return;
}

if (!in_array(userLevel(), [ROLE_ADMIN, ROLE_SUPER_ADMIN])) {
    echo "<div class='alert alert-danger m-4'>⛔ Access denied. Only administrators and super admins can delete vehicles.</div>";
    return;
}

// ✅ Centralized CSRF verification
if (!verifyCSRF()) {
    echo "<div class='alert alert-danger m-4'>❌ Invalid CSRF token.</div>";
    return;
}

$id = $_POST['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger m-4'>❌ Invalid vehicle ID.</div>";
    return;
}

// Optional: Fetch vehicle for logging or confirmation
$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
$stmt->execute([$id]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
    echo "<div class='alert alert-danger m-4'>❌ Vehicle not found.</div>";
    return;
}

// Delete vehicle
$stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
if ($stmt->execute([$id])) {
    echo "<div class='alert alert-success m-4'>✅ Vehicle deleted successfully.</div>";
    echo "<a href='?page=vehicles/index' class='btn btn-primary ms-4'>Back to Vehicle List</a>";
} else {
    echo "<div class='alert alert-danger m-4'>❌ Failed to delete vehicle. Please try again.</div>";
}
