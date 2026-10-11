<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$parent_id = $_GET['parent_id'] ?? null;
$search = $_GET['q'] ?? '';

try {
    $sql = '';
    $params = [];

    switch ($type) {
        case 'counties':
            $sql = "SELECT id, name AS text FROM counties";
            if ($search) {
                $sql .= " WHERE name LIKE ?";
                $params[] = "%$search%";
            }
            break;

        case 'subcounties':
            if (!$parent_id) {
                echo json_encode(['results' => []]);
                exit;
            }
            $sql = "SELECT id, name AS text FROM subcounties WHERE county_id = ?";
            $params[] = $parent_id;
            if ($search) {
                $sql .= " AND name LIKE ?";
                $params[] = "%$search%";
            }
            break;

        case 'locations':
            if (!$parent_id) {
                echo json_encode(['results' => []]);
                exit;
            }
            $sql = "SELECT id, name AS text FROM locations WHERE subcounty_id = ?";
            $params[] = $parent_id;
            if ($search) {
                $sql .= " AND name LIKE ?";
                $params[] = "%$search%";
            }
            break;

        case 'categories':
            $sql = "SELECT id, name AS text FROM categories";
            if ($search) {
                $sql .= " WHERE name LIKE ?";
                $params[] = "%$search%";
            }
            break;

        case 'statuses':
            $sql = "SELECT id, label AS text FROM vehicle_statuses";
            if ($search) {
                $sql .= " WHERE label LIKE ?";
                $params[] = "%$search%";
            }
            break;

        case 'designations':
            // Static list
            $all = [
                "Headquarters",
                "Regional",
                "County Commissioner",
                "Deputy County Commissioner",
                "Assistant County Commissioner",
                "Chief",
                "Sub-Chief"
            ];

            $filtered = $search
                ? array_filter($all, fn($item) => stripos($item, $search) !== false)
                : $all;

            $results = array_map(fn($item) => ['id' => $item, 'text' => $item], $filtered);

            echo json_encode(['results' => array_values($results)]);
            exit;

        default:
            echo json_encode(['results' => []]);
            exit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['results' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
