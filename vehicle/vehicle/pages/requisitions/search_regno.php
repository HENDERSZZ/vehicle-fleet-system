<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$q = trim($_GET['q'] ?? '');

$accessClause = getAccessClause('co', 'v');

$sql = "
    SELECT v.regno, v.make
    FROM vehicles v
    LEFT JOIN locations l ON v.location_id = l.id
    LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
    LEFT JOIN counties co ON v.county_id = co.id
    LEFT JOIN regions r ON co.region_id = r.id
    JOIN vehicle_statuses vs ON v.status_id = vs.id
    WHERE vs.label IN ('serviceable', 'leased')
      AND v.regno LIKE :search
      $accessClause
    ORDER BY v.regno ASC
    LIMIT 20
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':search' => "%{$q}%"]);

$data = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data[] = [
        'id' => $row['regno'],
        'text' => $row['regno'] . ' (' . $row['make'] . ')'
    ];
}

header('Content-Type: application/json');
echo json_encode(['results' => $data]);
exit;
