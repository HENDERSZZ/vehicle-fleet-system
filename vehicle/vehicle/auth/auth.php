<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';  // sets up $pdo

function log_action($pdo, $action, $user_id)
{
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $action]);
}

$message = $_SESSION['timeout_message'] ?? '';
unset($_SESSION['timeout_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.password, u.group_id, g.group_level,
               u.region_id, u.county_id, u.subcounty_id, u.location_id
        FROM users u
        LEFT JOIN user_groups g ON u.group_id = g.id
        WHERE u.username = ? AND u.status = 'active'
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        // Update last login
        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
            ->execute([$user['id']]);

        // Cleanup expired sessions
        $pdo->exec("DELETE FROM user_sessions WHERE last_activity < (NOW() - INTERVAL 15 MINUTE)");

        // Delete previous sessions for this user
        $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")
            ->execute([$user['id']]);

        // Generate new session token
        $sessionToken = bin2hex(random_bytes(32));
        $pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, last_activity, created_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ")->execute([
            $user['id'],
            $sessionToken,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        // Log the login
        log_action($pdo, 'User login', $user['id']);

        // Store session
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'group_id' => $user['group_id'],
            'group_level' => $user['group_level'] ?? 0,
            'is_superadmin' => ($user['group_level'] === 0) ? 1 : 0,
            'region_id' => $user['region_id'] ?? null,
            'county_id' => $user['county_id'] ?? null,
            'subcounty_id' => $user['subcounty_id'] ?? null,
            'location_id' => $user['location_id'] ?? null
        ];

        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['last_activity'] = time();

        // CSRF token
        if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) || (time() - $_SESSION['csrf_token_time'] > 3600)) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        header("Location: ../admin.php");
        exit;
    } else {
        $message = "Invalid username or password.";
    }
}
// --- HELPER FUNCTIONS / PERMISSIONS ---
function getUserGroupId()
{
    return $_SESSION['user']['group_id'] ?? null;
}

function can_add(): bool
{
    $group = getUserGroupId();
    $page = $_GET['page'] ?? '';

    if (strpos($page, 'users') === 0) {
        return in_array($group, [ROLE_ADMIN, ROLE_SUPER_ADMIN]);
    }

    return in_array($group, [ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_MANAGER, ROLE_STAFF]);
}

function can_edit(): bool
{
    $group = getUserGroupId();
    $page = $_GET['page'] ?? '';

    if (strpos($page, 'users') === 0) {
        return in_array($group, [ROLE_ADMIN, ROLE_SUPER_ADMIN]);
    }

    return in_array($group, [ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_MANAGER]);
}

function can_delete(): bool
{
    return in_array(getUserGroupId(), [ROLE_ADMIN, ROLE_SUPER_ADMIN]);
}

function can_view_users(): bool
{
    return in_array(getUserGroupId(), [ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_MANAGER]);
}

function can_export(): bool
{
    return in_array(getUserGroupId(), [ROLE_ADMIN, ROLE_SUPER_ADMIN]);
}
