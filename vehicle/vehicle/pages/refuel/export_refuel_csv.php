<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: text/csv; charset=utf-8');
header('Pragma: no-cache');
header('Expires: 0');

// === Filters ===
$regno = $_GET['regno'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// === Access Control ===
$accessClause = getRefuelAccessClause();

// === SQL Query ===
$sql = "
    SELECT 
        r.regno, r.fuel, r.cost, r.odometer, r.station, 
        r.remarks, r.date, s.label AS status
    FROM refuel r
    LEFT JOIN vehicles v ON r.regno = v.regno
    LEFT JOIN locations l ON v.location_id = l.id
    LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
    LEFT JOIN counties co ON v.county_id = co.id
    LEFT JOIN regions rg ON co.region_id = rg.id
    LEFT JOIN vehicle_statuses s ON v.status_id = s.id
    WHERE 1=1
";

$params = [];
if (!empty($regno)) {
    $sql .= " AND TRIM(LOWER(r.regno)) = TRIM(LOWER(:regno))";
    $params['regno'] = strtolower(trim($regno));
}
if (!empty($date_from)) {
    $sql .= " AND r.date >= :date_from";
    $params['date_from'] = $date_from . ' 00:00:00';
}
if (!empty($date_to)) {
    $sql .= " AND r.date <= :date_to";
    $params['date_to'] = $date_to . ' 23:59:59';
}
if (!empty($status)) {
    $sql .= " AND LOWER(s.label) = LOWER(:status)";
    $params['status'] = $status;
}

$sql .= " $accessClause ORDER BY r.date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Generate Filename ===
$filename_parts = [];

if (!empty($regno))
    $filename_parts[] = strtolower(str_replace(' ', '_', $regno));
if (!empty($status))
    $filename_parts[] = strtolower(str_replace(' ', '_', $status));
if (!empty($date_from) || !empty($date_to)) {
    $from = $date_from ?: 'start';
    $to = $date_to ?: 'end';
    $filename_parts[] = "{$from}_to_{$to}";
}
if (empty($filename_parts))
    $filename_parts[] = 'all';

$filename_parts[] = 'refuel';
$filename_parts[] = date('Ymd');
$filename = implode('_', $filename_parts) . '.csv';

header("Content-Disposition: attachment; filename=\"$filename\"");

// === Output CSV ===
$output = fopen('php://output', 'w');

// Column headers
if (!empty($rows)) {
    fputcsv($output, array_keys($rows[0]));

    // Data rows
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
} else {
    fputcsv($output, ['No refuel records found for the selected filters.']);
}

fclose($output);
exit;
