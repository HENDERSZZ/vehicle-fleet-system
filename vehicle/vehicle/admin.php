<?php
ob_start(); // Start output buffering early to safely use header()
define('BASE_URL', '/vehicle');
// Enable detailed error reporting during development (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE)
  session_start();

if (!isset($_SESSION['user'])) {
  $_SESSION['timeout_message'] = "Your session has ended. Please log in again.";
  ob_clean();
  header('Location: auth/login.php');
  exit;
}
require_once __DIR__ . '/includes/config.php';

// Ensure a CSRF token exists
$csrf_token = getCSRFToken();

// Use PDO database connection
$user = $_SESSION['user'];
$userId = $user['id'];
$isAdmin = ($user['group_id'] ?? null) == 1;

// Check if a super admin exists before proceeding
$superAdminCheck = $pdo->query("SELECT id FROM users WHERE group_id = 5 LIMIT 1");
if ($superAdminCheck->rowCount() === 0) {
  ob_clean();
  echo "<h2 style='color:red;text-align:center;margin-top:50px;'>❌ System Locked: No Super Admin found.<br>Please restore a Super Admin user in the database.</h2>";
  exit;
}


// Determine which page to load
$currentPage = $_GET['page'] ?? 'dashboard';
$page = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $currentPage);

// Include function to check if user is exempt from license validation
require_once __DIR__ . '/includes/functions.php';

// License validation (skip if Super Admin, Admin, or activating)
if (!is_license_exempt_user() && $page !== 'license/activate') {
  $domain = $_SERVER['HTTP_HOST'];

  $stmt = $pdo->prepare("SELECT * FROM licenses WHERE domain = ? AND is_used = 1 AND is_revoked = 0 AND expires >= CURDATE()");
  $stmt->execute([$domain]);
  $license = $stmt->fetch(PDO::FETCH_ASSOC);

  // Uncomment below to enforce license
  // if (!$license) {
  //     ob_clean();
  //     header("Location: admin.php?page=license/activate");
  //     exit;
  // }
}

// Update user activity timestamp
$stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
$stmt->execute([$userId]);

// Capture toast message if available
$toastMessage = $_SESSION['license_success'] ?? null;
unset($_SESSION['license_success']);

// Handle direct export requests
if (preg_match('#^(vehicles|maintenance|refuel)/export_#', $page)) {
  $exportPath = __DIR__ . "/pages/{$page}.php";
  if (file_exists($exportPath)) {
    include $exportPath;
    exit;
  } else {
    http_response_code(404);
    echo "Export file not found.";
    exit;
  }
}

// Handle AJAX requests directly
$ajaxPages = [
  'requisitions/search_regno',
  'maintenance/search_regno',
  'refuel/search_regno',
  'users/get_user_status',
  'vehicles/populate_data',
  'users/populate_locations',
];

if (in_array($page, $ajaxPages)) {
  $ajaxPath = __DIR__ . "/pages/{$page}.php";
  if (file_exists($ajaxPath)) {
    include $ajaxPath;
    exit;
  } else {
    http_response_code(404);
    echo "AJAX endpoint not found.";
    exit;
  }
}

// Final fallback: load standard page
$pagePath = __DIR__ . '/pages/' . $page . '.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>VMS - <?= ucfirst(basename($page)) ?></title>
  <?php include __DIR__ . '/includes/head.php'; ?>
</head>

<body class="no-transition">
  <?php include __DIR__ . '/includes/admin_scripts.php'; ?>
  <?php include __DIR__ . '/includes/topnav.php'; ?>
  <div class="d-flex content-wrapper pt-5" style="min-height: 100vh;">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main id="mainContent" class="flex-grow-1 p-3 pt-4 overflow-auto">
      <?php
      if (isset($_SESSION['flash_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show m-3" role="alert">'
          . htmlspecialchars($_SESSION['flash_success'])
          . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['flash_success']);
      }
      ?>
      <div class="row">
        <div class="col-12">
          <?php
          if (file_exists($pagePath)) {
            include $pagePath;
          } else {
            echo "<div class='alert alert-danger'>❌ Page not found: <code>" . htmlspecialchars($page) . "</code></div>";
          }
          ?>
        </div>
      </div>
    </main>
    <?php include __DIR__ . '/includes/scripts.php'; ?>
    <?php if ($toastMessage): ?>
      <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050">
        <div id="licenseToast" class="toast align-items-center text-white bg-success border-0 show" role="alert">
          <div class="d-flex">
            <div class="toast-body">
              <?= htmlspecialchars($toastMessage) ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
          </div>
        </div>
      </div>
      <script>
        const toast = new bootstrap.Toast(document.getElementById('licenseToast'));
        toast.show();
      </script>
    <?php endif; ?>
  </div>
</body>
</html>
<?php ob_end_flush(); ?>