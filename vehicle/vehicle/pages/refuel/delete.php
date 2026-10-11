<?php
require_once __DIR__ . '/../../includes/config.php'; // ✅ Use PDO
require_once __DIR__ . '/../../auth/auth.php';

// Ensure user is logged in
if (!isset($_SESSION['user'])) {
    echo "<div class='alert alert-danger m-4'>❌ You must be logged in to delete refuel records.</div>";
    exit;
}

// Only allow administrators (level 1, 0)
if (!can_delete()) {
    echo "<div class='alert alert-danger m-4'>⛔ Access denied. Only administrators can delete refuel records.</div>";
    exit;
}

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "<div class='alert alert-danger m-4'>❌ Invalid request method. Use POST.</div>";
    exit;
}

// ✅ Validate CSRF token using helper
if (!verifyCSRF()) {
    echo "<div class='alert alert-danger m-4'>❌ Invalid or expired CSRF token.</div>";
    exit;
}

// Validate ID
$id = $_POST['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger m-4'>❌ Missing or invalid refuel ID.</div>";
    exit;
}

// Fetch the record
$stmt = $pdo->prepare("SELECT receipt, regno FROM refuel WHERE id = ?");
$stmt->execute([$id]);
$refuel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$refuel) {
    echo "<div class='alert alert-danger m-4'>❌ Refuel record not found.</div>";
    exit;
}

// Delete receipt file if exists
$uploadPath = realpath(__DIR__ . '/../../uploads') . '/';
$receiptFile = $uploadPath . $refuel['receipt'];

if (!empty($refuel['receipt']) && file_exists($receiptFile)) {
    unlink($receiptFile);
}

// Delete from database
$stmt = $pdo->prepare("DELETE FROM refuel WHERE id = ?");
if ($stmt->execute([$id])) {
    $user = $_SESSION['user']['username'] ?? 'unknown';
    error_log("[DELETE] Refuel ID {$id} for {$refuel['regno']} deleted by {$user} on " . date('Y-m-d H:i:s'));

    echo "<div class='alert alert-success m-4'>✅ Refuel record deleted successfully.</div>";
    echo "<a href='?page=refuel/index' class='btn btn-primary ms-4'>← Back to Refuel List</a>";
} else {
    echo "<div class='alert alert-danger m-4'>❌ Failed to delete record. Please try again.</div>";
}
?>
