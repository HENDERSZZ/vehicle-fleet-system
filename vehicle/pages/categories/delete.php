<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/config.php';

// 🔐 Auth check
if (!isset($_SESSION['user'])) {
    header('Location: /vehicle/auth/login.php');
    exit;
}

// 🔐 Only Admins/Super Admins
if (!in_array($_SESSION['user']['group_id'], [ROLE_ADMIN, ROLE_SUPER_ADMIN])) {
    http_response_code(403);
    die("⛔ Unauthorized: Only Admins can delete.");
}

// ✅ Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Invalid request method.");

// 🔐 CSRF check
if (!verifyCSRF()) die("Invalid or expired CSRF token.");

// Validate ID
$id = $_POST['id'] ?? null;
if (!$id || !is_numeric($id)) die("Invalid category ID.");

// 🗑️ Delete
$stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) die("Delete failed or category not found.");

// ✅ Redirect
function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}

redirect('/vehicle/admin.php?page=categories/index');
