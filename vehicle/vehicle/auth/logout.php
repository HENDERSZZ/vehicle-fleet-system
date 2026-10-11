<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php'; // defines $pdo

// Audit log function using PDO
function log_action(PDO $pdo, $action, $user_id)
{
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $action]);
}

if (isset($_SESSION['user']['id'])) {
    $userId = $_SESSION['user']['id'];

    // Delete from user_sessions if token exists
    if (isset($_SESSION['session_token'])) {
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ? AND user_id = ?");
        $stmt->execute([$_SESSION['session_token'], $userId]);
    }

    // Clear last_activity in users table (optional)
    $stmt = $pdo->prepare("UPDATE users SET last_activity = NULL WHERE id = ?");
    $stmt->execute([$userId]);

    // Log the logout action
    log_action($pdo, 'User logout', $userId);
}

// Destroy session safely
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
