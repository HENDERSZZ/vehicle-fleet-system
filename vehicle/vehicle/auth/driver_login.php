<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

function log_driver_action($pdo, $action, $driver_id)
{
    $stmt = $pdo->prepare("INSERT INTO driver_audit_log (driver_id, action, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$driver_id, $action]);
}

$message = '';
if (isset($_SESSION['timeout_message'])) {
    $message = $_SESSION['timeout_message'];
    unset($_SESSION['timeout_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("
        SELECT id, name, username, password, status
        FROM drivers
        WHERE username = ? AND status = 'active'
    ");
    $stmt->execute([$username]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($driver && password_verify($password, $driver['password'])) {
        session_regenerate_id(true);

        // Update last login
        $now = date('Y-m-d H:i:s');
        $update = $pdo->prepare("UPDATE drivers SET last_login = ? WHERE id = ?");
        $update->execute([$now, $driver['id']]);

        // Enforce single session for each driver
        $pdo->exec("DELETE FROM driver_sessions WHERE last_activity < (NOW() - INTERVAL 15 MINUTE)");
        $delOld = $pdo->prepare("DELETE FROM driver_sessions WHERE driver_id = ?");
        $delOld->execute([$driver['id']]);

        // Create and store new session
        $sessionToken = bin2hex(random_bytes(32));
        $insert = $pdo->prepare("
            INSERT INTO driver_sessions (driver_id, session_token, ip_address, user_agent, last_activity, created_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $insert->execute([
            $driver['id'],
            $sessionToken,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        log_driver_action($pdo, 'Driver login', $driver['id']);

        $_SESSION['driver'] = [
            'id'       => $driver['id'],
            'name'     => $driver['name'],
            'username' => $driver['username']
        ];
        $_SESSION['driver_session_token'] = $sessionToken;
        $_SESSION['last_activity'] = time();

        header('Location: /vehicle/driver_dashboard.php');
        exit;
    } else {
        $message = "Invalid username or password.";
    }
}
?>
<?php
$title = "Driver Login - VMS";
$formTitle = "🚗 Driver Login";
$actionURL = "/vehicle/auth/driver_login.php";
require __DIR__ . '/../includes/login_layout.php';
