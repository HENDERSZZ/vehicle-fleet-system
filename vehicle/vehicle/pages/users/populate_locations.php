<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

// Access control: allow only group_id 5 users
$allowed_groups = [1, 2, 3, 4, 5, 6];
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['group_id'], $allowed_groups)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}


$type = $_GET['type'] ?? '';
$parent_id = $_GET['parent_id'] ?? null;
$search = $_GET['q'] ?? '';

try {
    switch ($type) {
        case 'regions':
            $sql = "SELECT id, name AS text FROM regions";
            $params = [];
            if ($search !== '') {
                $sql .= " WHERE name LIKE ?";
                $params[] = "%$search%";
            }
            break;

        case 'counties':
            $sql = "SELECT id, name AS text FROM counties";
            $params = [];
            if ($parent_id !== null && $parent_id !== '') {
                $sql .= " WHERE region_id = ?";
                $params[] = $parent_id;
            } else {
                // No region selected, return empty results to avoid loading all counties
                echo json_encode(['results' => []]);
                exit;
            }
            if ($search !== '') {
                $sql .= " AND name LIKE ?";
                $params[] = "%$search%";
            }
            break;

        case 'subcounties':
            $sql = "SELECT id, name AS text FROM subcounties";
            $params = [];
            if ($parent_id !== null && $parent_id !== '') {
                $sql .= " WHERE county_id = ?";
                $params[] = $parent_id;
            } else {
                echo json_encode(['results' => []]);
                exit;
            }
            if ($search !== '') {
                $sql .= " AND name LIKE ?";
                $params[] = "%$search%";
            }
            break;

        case 'locations':
            $sql = "SELECT id, name AS text FROM locations";
            $params = [];
            if ($parent_id !== null && $parent_id !== '') {
                $sql .= " WHERE subcounty_id = ?";
                $params[] = $parent_id;
            } else {
                echo json_encode(['results' => []]);
                exit;
            }
            if ($search !== '') {
                $sql .= " AND name LIKE ?";
                $params[] = "%$search%";
            }
            break;

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
