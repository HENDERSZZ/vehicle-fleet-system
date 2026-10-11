<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';

if (!isset($_SESSION['user'])) {
    header('Location: /vehicle/auth/login.php');
    exit;
}

$currentUser = $_SESSION['user'];

// Allow only Admins (1) and Super Admins (5)
if (!in_array($currentUser['group_id'], [1, 5])) {
    die("❌ Unauthorized: Admins only.");
}

// POST request required
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("❌ Invalid request.");
}

// CSRF token validation
if (!verifyCSRF()) {
    $_SESSION['error'] = "❌ Invalid or expired CSRF token.";
    header("Location: /vehicle/admin.php?page=requisitions/index");
    exit;
}

// User ID to delete
$id = $_POST['id'] ?? null;
if (!$id || !is_numeric($id)) {
    die("❌ Invalid user ID.");
}

// Prevent self-deletion
if ((int) $id === (int) $currentUser['id']) {
    die("❌ You cannot delete yourself.");
}

// Fetch target user's group_id
$stmt = $pdo->prepare("SELECT group_id FROM users WHERE id = ?");
$stmt->execute([$id]);
$targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    die("❌ User not found.");
}

// Prevent deletion of Super Admins
if ((int) $targetUser['group_id'] === 5) {
    die("❌ You cannot delete a Super Admin.");
}

// Perform deletion
$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    die("❌ Delete failed.");
}

// Redirect to user list
echo "<script>window.location.href = '/vehicle/admin.php?page=users/index';</script>";
exit;
