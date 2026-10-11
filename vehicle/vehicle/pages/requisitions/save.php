<?php
ob_start();
if (session_status() === PHP_SESSION_NONE)
    session_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';

if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "⛔ Please log in first.";
    header("Location: /vehicle/admin.php?page=requisitions/index");
    exit;
}

if ($_SESSION['user']['level'] > 3) {
    $_SESSION['error'] = "⛔ Access denied.";
    header("Location: /vehicle/admin.php?page=requisitions/index");
    exit;
}

// CSRF check
if (!verifyCSRF()) {
    $_SESSION['error'] = "❌ Invalid or expired CSRF token.";
    header("Location: /vehicle/admin.php?page=requisitions/index");
    exit;
}

// Sanitize and validate input
$type = trim($_POST['type'] ?? '');
$regno = trim($_POST['regno'] ?? '');
$description = trim($_POST['description'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);
$fuel = isset($_POST['fuel']) ? floatval($_POST['fuel']) : null;
$odometer = isset($_POST['odometer']) ? intval($_POST['odometer']) : null;
$station = trim($_POST['station'] ?? '');
$user_id = $_SESSION['user']['id'];

if (!$type || !$regno || $amount <= 0) {
    $_SESSION['error'] = "❌ Please fill all required fields correctly.";
    header("Location: /vehicle/admin.php?page=requisitions/form");
    exit;
}

// Build dynamic SQL
$fields = "type, vehicle_regno, description, amount, requested_by, requested_at, odometer, status";
$placeholders = ":type, :regno, :description, :amount, :requested_by, NOW(), :odometer, 'pending'";
$params = [
    ':type' => $type,
    ':regno' => $regno,
    ':description' => $description,
    ':amount' => $amount,
    ':requested_by' => $user_id,
    ':odometer' => $odometer
];

if ($type === 'fuel') {
    $fields .= ", fuel, station";
    $placeholders .= ", :fuel, :station";
    $params[':fuel'] = $fuel;
    $params[':station'] = $station;
}

$sql = "INSERT INTO requisitions ($fields) VALUES ($placeholders)";
$stmt = $pdo->prepare($sql);

if ($stmt->execute($params)) {
    $_SESSION['success'] = "✅ Requisition saved successfully.";
    header("Location: /vehicle/admin.php?page=requisitions/index");
    exit;
} else {
    $_SESSION['error'] = "❌ Failed to save requisition.";
    header("Location: /vehicle/admin.php?page=requisitions/form");
    exit;
}
?>