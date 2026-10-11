<?php
// dashboard.php - Main Dashboard Page
if (session_status() === PHP_SESSION_NONE)
  session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user'])) {
  header('Location: ../auth/login.php');
  exit;
}

date_default_timezone_set('Africa/Nairobi');

$userName = $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? 'User';
$date = date('l, F j, Y, g:i A');

// User info
$currentUserId = $_SESSION['user']['id'] ?? 0;
$currentUserGroupId = $_SESSION['user']['group_id'] ?? null;

// Access Clause (safe for WHERE usage in vehicle-related queries)
$accessClause = trim(getDashboardAccessClause('v'));
if ($accessClause === '') {
  $accessClause = '1'; // Full access fallback (HQ or superadmin)
}

// Decide whether to show Users card:
// Allowed user IDs and group IDs (adjust as per your roles/constants)
$allowedUserIds = [1, 5, 6];
$allowedGroupIds = [ROLE_ADMIN, ROLE_SUPER_ADMIN, ROLE_SUPER_MANAGER];

// Show Users card only if current user is allowed by ID or group
$showUsersCard = in_array($currentUserId, $allowedUserIds) || in_array($currentUserGroupId, $allowedGroupIds);

// === COUNTS ===

// Categories count (no restrictions)
$categories = $pdo->query("SELECT COUNT(*) as total FROM categories")->fetch(PDO::FETCH_ASSOC)['total'];

// Users count and status only if allowed
if ($showUsersCard) {
  $users = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch(PDO::FETCH_ASSOC)['total'];
  $userStatusCounts = $pdo->query("
        SELECT
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive
        FROM users
    ")->fetch(PDO::FETCH_ASSOC);
} else {
  $users = 0;
  $userStatusCounts = ['active' => 0, 'inactive' => 0];
}

// Requisitions counts with access clause
$requisitionCounts = $pdo->query("
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) AS approved,
      SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) AS pending
    FROM requisitions r
    LEFT JOIN vehicles v ON r.vehicle_regno = v.regno
    LEFT JOIN counties co ON v.county_id = co.id
    WHERE $accessClause
")->fetch(PDO::FETCH_ASSOC);

// Vehicle counts with access clause
$vehicleCounts = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN vs.code = 'serviceable' THEN 1 ELSE 0 END) AS serviceable,
        SUM(CASE WHEN vs.code = 'leased' THEN 1 ELSE 0 END) AS leased
    FROM vehicles v
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    LEFT JOIN counties co ON v.county_id = co.id
    WHERE $accessClause
")->fetch(PDO::FETCH_ASSOC);

// Vehicles excluding motorcycles
$vehiclesCounts = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN vs.code = 'serviceable' THEN 1 ELSE 0 END) AS serviceable,
        SUM(CASE WHEN vs.code IN ('leased', 'leased_stored') THEN 1 ELSE 0 END) AS leased
    FROM vehicles v
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    LEFT JOIN categories c ON v.category_id = c.id
    LEFT JOIN counties co ON v.county_id = co.id
    WHERE c.name <> 'Motorcycle'
      AND $accessClause
")->fetch(PDO::FETCH_ASSOC);

// Motorcycles only
$motorcycleCounts = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN vs.code = 'serviceable' THEN 1 ELSE 0 END) AS serviceable,
        SUM(CASE WHEN vs.code IN ('leased', 'leased_stored') THEN 1 ELSE 0 END) AS leased
    FROM vehicles v
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    LEFT JOIN categories c ON v.category_id = c.id
    LEFT JOIN counties co ON v.county_id = co.id
    WHERE c.name = 'Motorcycle'
      AND $accessClause
")->fetch(PDO::FETCH_ASSOC);

// Maintenance counts
$maintenanceCounts = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN vs.code = 'serviceable' THEN 1 ELSE 0 END) AS serviceable,
        SUM(CASE WHEN vs.code IN ('leased', 'leased_stored') THEN 1 ELSE 0 END) AS leased
    FROM maintenance m
    LEFT JOIN vehicles v ON m.regno = v.regno
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    LEFT JOIN counties co ON v.county_id = co.id
    WHERE $accessClause
")->fetch(PDO::FETCH_ASSOC);

// Refuel counts
$refuelCounts = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN vs.code = 'serviceable' THEN 1 ELSE 0 END) AS serviceable,
        SUM(CASE WHEN vs.code IN ('leased', 'leased_stored') THEN 1 ELSE 0 END) AS leased
    FROM refuel r
    LEFT JOIN vehicles v ON r.regno = v.regno
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    LEFT JOIN counties co ON v.county_id = co.id
    WHERE $accessClause
")->fetch(PDO::FETCH_ASSOC);

// Maintenance cost sums
$maintenanceCost = $pdo->query("
    SELECT
        COALESCE(SUM(m.cost), 0) AS total,
        COALESCE(SUM(CASE WHEN vs.code = 'serviceable' THEN m.cost ELSE 0 END), 0) AS serviceable,
        COALESCE(SUM(CASE WHEN vs.code IN ('leased', 'leased_stored') THEN m.cost ELSE 0 END), 0) AS leased
    FROM maintenance m
    LEFT JOIN vehicles v ON m.regno = v.regno
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    LEFT JOIN counties co ON v.county_id = co.id
    WHERE $accessClause
")->fetch(PDO::FETCH_ASSOC);

// Refuel cost sums
$refuelCost = $pdo->query("
    SELECT 
        COALESCE(SUM(r.cost), 0) AS total,
        COALESCE(SUM(CASE WHEN vs.code = 'serviceable' THEN r.cost ELSE 0 END), 0) AS serviceable,
        COALESCE(SUM(CASE WHEN vs.code IN ('leased', 'leased_stored') THEN r.cost ELSE 0 END), 0) AS leased
    FROM refuel r
    LEFT JOIN vehicles v ON r.regno = v.regno
    LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
    LEFT JOIN counties co ON v.county_id = co.id
    WHERE $accessClause
")->fetch(PDO::FETCH_ASSOC);

// Monthly chart data: maintenance
$maintenance_monthly = $pdo->query("
    SELECT DATE_FORMAT(m.date, '%Y-%m') AS month, SUM(m.cost) as total 
    FROM maintenance m 
    JOIN vehicles v ON m.regno = v.regno 
    LEFT JOIN counties co ON v.county_id = co.id
    WHERE $accessClause
    GROUP BY month 
    ORDER BY month ASC
");

// Monthly chart data: refuel
$refuel_monthly = $pdo->query("
    SELECT DATE_FORMAT(r.date, '%Y-%m') AS month, SUM(r.cost) as total 
    FROM refuel r 
    JOIN vehicles v ON r.regno = v.regno 
    LEFT JOIN counties co ON v.county_id = co.id
    WHERE $accessClause
    GROUP BY month 
    ORDER BY month ASC
");

// Prepare chart data arrays
$labels = $data_maintenance = $data_refuel = [];
$months = [];

while ($row = $maintenance_monthly->fetch(PDO::FETCH_ASSOC)) {
  $months[$row['month']] = ['maintenance' => $row['total'], 'refuel' => 0];
}

while ($row = $refuel_monthly->fetch(PDO::FETCH_ASSOC)) {
  if (!isset($months[$row['month']])) {
    $months[$row['month']] = ['maintenance' => 0, 'refuel' => $row['total']];
  } else {
    $months[$row['month']]['refuel'] = $row['total'];
  }
}

ksort($months);
foreach ($months as $month => $data) {
  $labels[] = $month;
  $data_maintenance[] = $data['maintenance'];
  $data_refuel[] = $data['refuel'];
}
?>

<style>
  .card:hover {
    transform: scale(1.02);
    transition: 0.3s ease-in-out;
  }

  .chart-container {
    max-width: 600px;
    margin: auto;
    transition: transform 0.3s;
  }

  .chart-container:hover {
    transform: scale(1.1);
  }
</style>

<div class="container py-4">
  <div class="alert alert-info mt-3 text-center">
    👋 Welcome, <strong><?= htmlspecialchars($userName) ?></strong><br>
    📅 Today is <strong id="dynamic-date"><?= $date ?></strong>
  </div>
  
  <div class="container-fluid px-4">
    <div class="row g-3 mb-4">

      <!-- Categories Card -->
      <?php
      $icon = 'fas fa-th-large';
      $iconColor = 'primary';
      $value = $categories;
      $label = 'Categories';
      $link = 'admin.php?page=categories/index';
      $subStats = [];
      include __DIR__ . '/../includes/widgets/stat_card.php';
      ?>

      <!-- My Profile Card -->
      <?php
      $icon = 'fas fa-id-badge';
      $value = 'My Profile';
      $label = 'Account';
      $link = 'admin.php?page=users/profile';
      include __DIR__ . '/../includes/widgets/stat_card.php';
      ?>

      <!-- Users Card - only if allowed -->
      <?php if ($showUsersCard): ?>
        <?php
        $icon = 'fas fa-user';
        $value = $users;
        $label = 'Users';
        $link = 'admin.php?page=users/index';
        $subStats = [
          ['value' => $userStatusCounts['active'], 'label' => 'Active'],
          ['value' => $userStatusCounts['inactive'], 'label' => 'Inactive'],
        ];
        include __DIR__ . '/../includes/widgets/stat_card.php';
        ?>
      <?php endif; ?>

      <!-- Requisitions Card -->
      <?php
      $icon = 'fas fa-file-signature';
      $value = $requisitionCounts['total'] ?? 0;
      $label = 'Requisitions';
      $link = 'admin.php?page=requisitions/index';
      $subStats = [
        ['value' => $requisitionCounts['approved'] ?? 0, 'label' => 'Approved'],
        ['value' => $requisitionCounts['pending'] ?? 0, 'label' => 'Pending'],
      ];
      include __DIR__ . '/../includes/widgets/stat_card.php';
      ?>

      <!-- Fleet Overview Card -->
      <?php
      $icon = 'fas fa-list';
      $value = $vehicleCounts['total'] ?? 0;
      $label = 'Fleet Overview';
      $link = 'admin.php?page=vehicles/view_vehicles';
      $subStats = [
        ['value' => $vehicleCounts['serviceable'] ?? 0, 'label' => 'Serviceable'],
        ['value' => $vehicleCounts['leased'] ?? 0, 'label' => 'Leased'],
      ];
      include __DIR__ . '/../includes/widgets/stat_card.php';
      ?>

      <!-- Vehicles Card -->
      <?php
      $icon = 'fas fa-car';
      $value = $vehiclesCounts['total'] ?? 0;
      $label = 'Vehicles';
      $link = 'admin.php?page=vehicles/index';
      $subStats = [
        ['value' => $vehiclesCounts['serviceable'] ?? 0, 'label' => 'Serviceable'],
        ['value' => $vehiclesCounts['leased'] ?? 0, 'label' => 'Leased'],
      ];
      include __DIR__ . '/../includes/widgets/stat_card.php';
      ?>

      <!-- Motorcycles Card -->
      <?php
      $icon = 'fas fa-motorcycle';
      $value = $motorcycleCounts['total'] ?? 0;
      $label = 'Motorcycles';
      $link = 'admin.php?page=vehicles/index';
      $subStats = [
        ['value' => $motorcycleCounts['serviceable'] ?? 0, 'label' => 'Serviceable'],
        ['value' => $motorcycleCounts['leased'] ?? 0, 'label' => 'Leased'],
      ];
      include __DIR__ . '/../includes/widgets/stat_card.php'; 
      ?>

      <!-- Maintenance Card -->
      <?php
      $icon = 'fas fa-wrench';
      $value = $maintenanceCounts['total'] ?? 0;
      $label = 'Maintenance';
      $link = 'admin.php?page=maintenance/index';
      $subStats = [
        ['value' => $maintenanceCounts['serviceable'] ?? 0, 'label' => 'Serviceable'],
        ['value' => $maintenanceCounts['leased'] ?? 0, 'label' => 'Leased'],
      ];
      include __DIR__ . '/../includes/widgets/stat_card.php';
      ?>

      <!-- Fueling Card -->
      <?php
      $icon = 'fas fa-gas-pump';
      $value = $refuelCounts['total'] ?? 0;
      $label = 'Fueling';
      $link = 'admin.php?page=refuel/index';
      $subStats = [
        ['value' => $refuelCounts['serviceable'] ?? 0, 'label' => 'Serviceable'],
        ['value' => $refuelCounts['leased'] ?? 0, 'label' => 'Leased'],
      ];
      include __DIR__ . '/../includes/widgets/stat_card.php';
      ?>

      <!-- Maintenance Cost and Fuel Cost Cards (hide for staff group_id = 3) -->
      <?php if ($currentUserGroupId != 3): ?>
        <?php
        $icon = 'fas fa-tools';
        $iconColor = 'success';
        $value = 'KES ' . number_format($maintenanceCost['total'], 2);
        $label = 'Maintenance Cost';
        $link = 'admin.php?page=maintenance/index';
        $subStats = [
          ['value' => number_format($maintenanceCost['serviceable'], 2), 'label' => 'Serviceable'],
          ['value' => number_format($maintenanceCost['leased'], 2), 'label' => 'Leased'],
        ];
        include __DIR__ . '/../includes/widgets/stat_card.php';

        $icon = 'fas fa-gas-pump';
        $value = 'KES ' . number_format($refuelCost['total'], 2);
        $label = 'Fuel Cost';
        $link = 'admin.php?page=refuel/index';
        $subStats = [
          ['value' => number_format($refuelCost['serviceable'], 2), 'label' => 'Serviceable'],
          ['value' => number_format($refuelCost['leased'], 2), 'label' => 'Leased'],
        ];
        include __DIR__ . '/../includes/widgets/stat_card.php';
        ?>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- Monthly Chart -->
<?php if ($_SESSION['user']['group_id'] != 3): ?>
  <div class="text-center mt-5">
    <h5>📈 Monthly Cost Chart</h5>
    <div class="btn-group mb-3">
      <button class="btn btn-outline-primary" onclick="toggleChart('maintenance')">Maintenance</button>
      <button class="btn btn-outline-secondary" onclick="toggleChart('refuel')">Refuel</button>
    </div>
    <div class="chart-container">
      <canvas id="chart" height="150"></canvas>
    </div>
  </div>
<?php endif; ?>
<!-- Chart and Date Script -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    function updateDate() {
      const options = {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
        hour12: true, timeZone: 'Africa/Nairobi'
      };
      const now = new Date();
      document.getElementById('dynamic-date').textContent = now.toLocaleString('en-KE', options);
    }

    updateDate();
    setInterval(updateDate, 1000);

    const ctx = document.getElementById('chart');
    if (!ctx) return;

    const labels = <?= json_encode($labels) ?>;
    const dataMaintenance = <?= json_encode($data_maintenance) ?>;
    const dataRefuel = <?= json_encode($data_refuel) ?>;

    window.currentChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Maintenance (KES)',
          data: dataMaintenance,
          borderColor: 'rgb(75, 192, 192)',
          backgroundColor: 'rgba(75, 192, 192, 0.3)',
          fill: true,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: true }
        }
      }
    });

    window.toggleChart = function (type) {
      const newData = type === 'maintenance' ? dataMaintenance : dataRefuel;
      const newLabel = type === 'maintenance' ? 'Maintenance (KES)' : 'Refuel (KES)';
      const newColor = type === 'maintenance' ? 'rgb(75, 192, 192)' : 'rgb(255, 99, 132)';

      currentChart.data.datasets[0].data = newData;
      currentChart.data.datasets[0].label = newLabel;
      currentChart.data.datasets[0].borderColor = newColor;
      currentChart.data.datasets[0].backgroundColor = newColor + '33';
      currentChart.update();
    };
  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('chart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    const labels = <?= json_encode($monthly_labels ?? []) ?>;
    const dataMonthly = <?= json_encode($monthly_costs ?? []) ?>;

    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const tickColor = isDark ? '#ccc' : '#333';
    const gridColor = isDark ? '#444' : '#ddd';
    const legendColor = isDark ? '#f0f0f0' : '#222';

    if (Array.isArray(labels) && labels.length && Array.isArray(dataMonthly) && dataMonthly.length) {
      window.chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'Monthly Cost',
            data: dataMonthly,
            borderColor: '#4fc3f7',
            backgroundColor: 'rgba(79, 195, 247, 0.1)',
            pointBackgroundColor: '#ffffff',
            pointBorderColor: '#4fc3f7',
            pointRadius: 5,
            pointHoverRadius: 7,
            borderWidth: 3,
            tension: 0.3
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              labels: { color: legendColor, font: { size: 14 } }
            },
            tooltip: {
              backgroundColor: '#333',
              titleColor: '#fff',
              bodyColor: '#ddd',
              borderColor: '#555',
              borderWidth: 1
            }
          },
          scales: {
            x: { ticks: { color: tickColor, font: { size: 12 } }, grid: { color: gridColor } },
            y: { ticks: { color: tickColor, font: { size: 12 } }, grid: { color: gridColor } }
          }
        }
      });
    } else {
      console.warn("📉 Chart skipped: No data available.");
      const noDataMessage = document.createElement('div');
      noDataMessage.textContent = "No data available to display.";
      noDataMessage.style.textAlign = 'center';
      noDataMessage.style.color = tickColor;
      canvas.parentNode.appendChild(noDataMessage);
    }
  });
</script>