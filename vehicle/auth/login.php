<?php
require_once __DIR__ . '/../includes/session_init.php';
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

<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - VMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

  <!-- Flash-prevention: set theme early -->
  <script>
    (function () {
      const savedMode = localStorage.getItem('vmsMode');
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      const mode = (savedMode === 'dark' || savedMode === 'light')
        ? savedMode
        : (prefersDark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', mode);
    })();
  </script>
  <style>
    html,
    body {
      height: 100%;
    }

    body {
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--bs-body-bg);
    }

    .form-signin {
      max-width: 330px;
      padding: 1rem;
    }

    .form-signin .form-floating:focus-within {
      z-index: 2;
    }

    .form-signin input[type="text"],
    .form-signin input[type="email"] {
      margin-bottom: -1px;
      border-bottom-right-radius: 0;
      border-bottom-left-radius: 0;
    }

    .form-signin input[type="password"] {
      margin-bottom: 10px;
      border-top-left-radius: 0;
      border-top-right-radius: 0;
    }

    .bd-mode-toggle {
      z-index: 1500;
    }
  </style>
</head>

<body class="bg-body-tertiary">

  <!-- Theme Toggle -->
  <button id="modeToggle" class="btn btn-sm btn-outline-light position-fixed bottom-0 end-0 mb-3 me-3"
    title="Toggle Dark Mode" aria-label="Switch theme">
    <span class="mode-icon" aria-hidden="true">🌙</span>
  </button>

  </div>

  <!-- Login Form -->
  <main class="form-signin w-100 m-auto">
    <form method="POST" novalidate>
      <h1 class="h3 mb-3 fw-normal text-center">🔐 Please sign in</h1>

      <?php if ($message): ?>
        <div class="alert alert-danger text-center py-2"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <div class="form-floating">
        <input type="text" name="username" id="floatingUsername" class="form-control" placeholder="Username" required
          autofocus>
        <label for="floatingUsername">Username</label>
      </div>

      <div class="form-floating">
        <input type="password" name="password" id="floatingPassword" class="form-control" placeholder="Password"
          required>
        <label for="floatingPassword">Password</label>
      </div>

      <div class="form-check text-start my-3">
        <input class="form-check-input" type="checkbox" value="remember-me" id="rememberMe">
        <label class="form-check-label" for="rememberMe">Remember me</label>
      </div>

      <button class="btn btn-primary w-100 py-2" type="submit">Sign in</button>
      <p class="mt-5 mb-3 text-body-secondary text-center">&copy; VMS 2025</p>
    </form>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://getbootstrap.com/docs/5.3/examples/assets/js/color-modes.js"></script>

  <!--Mode Toggle-->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const root = document.documentElement;
      const modeToggle = document.getElementById('modeToggle');
      const icon = modeToggle?.querySelector('.mode-icon');
      const THEME_KEY = 'vmsMode';

      const applyMode = (mode) => {
        if (!mode || !icon) return;

        root.setAttribute('data-bs-theme', mode);
        localStorage.setItem(THEME_KEY, mode);

        icon.textContent = mode === 'dark' ? '☀️' : '🌙';
        modeToggle.setAttribute('aria-label', mode === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
      };

      // Initialize
      const saved = localStorage.getItem(THEME_KEY);
      const initial = saved || root.getAttribute('data-bs-theme') || 'light';
      applyMode(initial);

      // Toggle on click
      modeToggle?.addEventListener('click', () => {
        const current = root.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        applyMode(next);
      });
    });
  </script>
</body>

</html>