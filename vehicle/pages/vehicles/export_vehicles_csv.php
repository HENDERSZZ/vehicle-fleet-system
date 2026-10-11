<?php
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: text/csv; charset=utf-8');

// === Inputs ===
$status_id = $_GET['status_id'] ?? '';
$search = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$statusLabel = 'All';

// === Fetch Status Label (PDO) ===
if (!empty($status_id)) {
    $stmt = $pdo->prepare("SELECT label FROM vehicle_statuses WHERE id = ?");
    $stmt->execute([$status_id]);
    $statusLabel = $stmt->fetchColumn() ?: 'All';
}

// === Base Query ===
$sql = "SELECT v.regno, v.make AS model, v.color, v.designation,
               r.name AS region, co.name AS county, l.name AS location,
               c.name AS category, s.label AS status, v.date
        FROM vehicles v
        LEFT JOIN categories c ON v.category_id = c.id
        LEFT JOIN locations l ON v.location_id = l.id
        LEFT JOIN subcounties sc ON l.subcounty_id = sc.id
        LEFT JOIN counties co ON sc.county_id = co.id
        LEFT JOIN regions r ON co.region_id = r.id
        LEFT JOIN vehicle_statuses s ON v.status_id = s.id
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (
        v.regno LIKE :search OR v.make LIKE :search OR v.color LIKE :search OR
        v.designation LIKE :search OR r.name LIKE :search OR co.name LIKE :search OR l.name LIKE :search
    )";
    $params['search'] = "%$search%";
}
if (!empty($status_id)) {
    $sql .= " AND s.id = :status_id";
    $params['status_id'] = $status_id;
}
if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND v.date BETWEEN :start_date AND :end_date";
    $params['start_date'] = $startDate;
    $params['end_date'] = $endDate;
}

$sql .= " ORDER BY v.date DESC";

// === Execute ===
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Determine visible columns
$allHeaders = [
    'regno' => 'Reg No',
    'model' => 'Model',
    'color' => 'Color',
    'designation' => 'Designation',
    'region' => 'Region',
    'county' => 'County',
    'location' => 'Location',
    'category' => 'Category',
    'status' => 'Status',
    'date' => 'Date'
];

$nonEmpty = [];
foreach ($rows as $row) {
    foreach ($row as $key => $value) {
        if (trim((string) $value) !== '') {
            $nonEmpty[$key] = true;
        }
    }
}

$visibleKeys = [];
$visibleLabels = ['No.']; // Add list numbering header
foreach ($allHeaders as $key => $label) {
    if (!empty($nonEmpty[$key])) {
        $visibleKeys[] = $key;
        $visibleLabels[] = $label;
    }
}

// === Output CSV
$filename = strtolower(str_replace(' ', '_', $statusLabel)) . '_vehicles_' . date('Ymd') . '.csv';
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputcsv($output, $visibleLabels);

$rowNum = 1;
foreach ($rows as $row) {
    $line = [$rowNum++]; // Add list number
    foreach ($visibleKeys as $key) {
        $line[] = $row[$key];
    }
    fputcsv($output, $line);
}

fclose($output);
exit;
