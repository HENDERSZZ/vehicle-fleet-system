<?php
session_start();
require_once __DIR__ . '/../includes/config.php';  // sets up $pdo

function log_action($pdo, $action, $user_id)
{
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $action]);
}

$message = '';
if (isset($_SESSION['timeout_message'])) {
    $message = $_SESSION['timeout_message'];
    unset($_SESSION['timeout_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $now = date('Y-m-d H:i:s');
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = ? WHERE id = ?");
        $updateStmt->execute([$now, $user['id']]);

        // Cleanup expired sessions (15 min timeout)
        $pdo->exec("DELETE FROM user_sessions WHERE last_activity < (NOW() - INTERVAL 15 MINUTE)");

        // 🚨 ENFORCE SINGLE SESSION: Delete old sessions for this user
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$user['id']]);

        // If you want only one session PER GROUP, use this instead:
        // $stmt = $pdo->prepare("
        //     DELETE us FROM user_sessions us
        //     INNER JOIN users u ON us.user_id = u.id
        //     WHERE u.group_id = ?
        // ");
        // $stmt->execute([$user['group_id']]);

        // Create new session token
        $sessionToken = bin2hex(random_bytes(32));

        // Insert session into DB
        $insert = $pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, last_activity, created_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $insert->execute([
            $user['id'],
            $sessionToken,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        // Log the login action
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

        // Store session token for validation later
        $_SESSION['session_token'] = $sessionToken;

        // Also store top-level access values
        $_SESSION['region_id'] = $user['region_id'] ?? null;
        $_SESSION['county_id'] = $user['county_id'] ?? null;
        $_SESSION['subcounty_id'] = $user['subcounty_id'] ?? null;
        $_SESSION['location_id'] = $user['location_id'] ?? null;

        // Save last activity time in PHP session
        $_SESSION['last_activity'] = time();

        header("Location: ../admin.php");
        exit;
    } else {
        $message = "Invalid username or password.";
    }
}
?>
<?php
$title = "Admin Login - VMS";
$formTitle = "🔐 Staff Login";
$actionURL = "/vehicle/auth/login.php";
require __DIR__ . '/../includes/login_layout.php';
