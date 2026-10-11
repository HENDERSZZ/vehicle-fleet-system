<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';

if (!isset($_SESSION['user'])) {
    header('Location: /vehicle/auth/login.php');
    exit;
}

$currentUser = $_SESSION['user'];

// Only Admins (group_id 1) and Super Admins (group_id 5) can delete
if (!in_array($currentUser['group_id'], [1, 5])) {
    die("❌ Unauthorized: Admins only.");
}

// Must be a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("❌ Invalid request method.");
}

// CSRF validation
if (!verifyCSRF()) {
    $_SESSION['error'] = "❌ Invalid or expired CSRF token.";
    header("Location: /vehicle/admin.php?page=drivers/index");
    exit;
}

// Driver ID
$id = $_POST['id'] ?? null;
if (!$id || !is_numeric($id)) {
    die("❌ Invalid driver ID.");
}

// Check if driver exists
$stmt = $pdo->prepare("SELECT id, name FROM drivers WHERE id = ?");
$stmt->execute([$id]);
$driver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$driver) {
    die("❌ Driver not found.");
}

// Prevent deletion if driver is actively assigned
$check = $pdo->prepare("SELECT COUNT(*) FROM vehicle_assignments WHERE driver_id = ? AND released_at IS NULL");
$check->execute([$id]);
if ($check->fetchColumn() > 0) {
    die("❌ Cannot delete: Driver currently assigned to a vehicle.");
}

// Perform deletion
$stmt = $pdo->prepare("DELETE FROM drivers WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    die("❌ Delete failed.");
}

// Redirect to driver list
echo "<script>window.location.href = '/vehicle/admin.php?page=drivers/index';</script>";
exit;
