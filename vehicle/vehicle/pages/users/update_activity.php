<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../../includes/config.php'; // ✅ Use PDO

if (isset($_SESSION['user']['id'])) {
    $userId = $_SESSION['user']['id'];

    $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
    $stmt->execute([$userId]);

    http_response_code(204); // No content, success
    exit;
}

// If user is not logged in, return 401 (unauthorized)
http_response_code(401);
exit;
