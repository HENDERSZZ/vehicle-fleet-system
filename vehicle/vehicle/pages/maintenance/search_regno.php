<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$params = [];

// Base query — pick vehicles from maintenance table
$sql = "
    SELECT DISTINCT v.regno AS id, v.regno AS text
    FROM maintenance m
    JOIN vehicles v ON v.regno = m.regno
    JOIN counties co ON co.id = v.county_id
    WHERE 1=1
";

// Apply search term
if ($q !== '') {
    $sql .= " AND v.regno LIKE ?";
    $params[] = "%{$q}%";
}

// Apply location-based access control
$accessClause = getMaintenanceAccessClause();
if ($accessClause) {
    $sql .= " " . $accessClause;
}

$sql .= " ORDER BY v.regno ASC LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['results' => $data]);
exit;
