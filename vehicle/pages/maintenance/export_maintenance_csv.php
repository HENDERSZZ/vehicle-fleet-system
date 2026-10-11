<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$regno = $_GET['regno'] ?? '';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Access control
$accessClause = getMaintenanceAccessClause();

// Build query
$sql = "SELECT m.regno, m.type, m.description, m.cost, m.odometer, m.date, s.label AS status
        FROM maintenance m
        LEFT JOIN vehicles v ON m.regno = v.regno
        LEFT JOIN vehicle_statuses s ON v.status_id = s.id
        LEFT JOIN locations l ON v.location_id = l.id
        LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
        LEFT JOIN counties co ON v.county_id = co.id
        LEFT JOIN regions r ON co.region_id = r.id
        WHERE 1=1";

$params = [];

if (!empty($regno)) {
    $sql .= " AND m.regno = :regno";
    $params['regno'] = $regno;
}
if (!empty($type)) {
    $sql .= " AND m.type = :type";
    $params['type'] = $type;
}
if (!empty($date_from)) {
    $sql .= " AND m.date >= :date_from";
    $params['date_from'] = $date_from . ' 00:00:00';
}
if (!empty($date_to)) {
    $sql .= " AND m.date <= :date_to";
    $params['date_to'] = $date_to . ' 23:59:59';
}
if (!empty($status)) {
    $sql .= " AND s.label = :status";
    $params['status'] = $status;
}

$sql .= " $accessClause ORDER BY m.date DESC";

// Execute
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Send headers
$filename_parts = [];

if (!empty($regno)) {
    $filename_parts[] = strtolower(str_replace(' ', '_', $regno));
}

if (!empty($status)) {
    $filename_parts[] = strtolower(str_replace(' ', '_', $status));
}

if (!empty($date_from) || !empty($date_to)) {
    $from = $date_from ?: 'start';
    $to = $date_to ?: 'end';
    $filename_parts[] = "{$from}_to_{$to}";
}

if (empty($filename_parts)) {
    $filename_parts[] = 'all';
}

$filename_parts[] = 'maintenance';
$filename_parts[] = date('Ymd_His');

$filename = implode('_', $filename_parts) . '.csv';

header('Content-Type: text/csv');
header("Content-Disposition: attachment; filename=\"$filename\"");

// Open output stream
$output = fopen('php://output', 'w');

// Output header
if (!empty($records)) {
    fputcsv($output, array_keys($records[0]));
    foreach ($records as $row) {
        fputcsv($output, $row);
    }
} else {
    fputcsv($output, ['No records found']);
}

fclose($output);
exit;
