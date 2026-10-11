<?php
require_once __DIR__ . '/config.php'; // assumes $pdo is defined here

function h($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Add a maintenance record
function addMaintenance($pdo, $data)
{
    $stmt = $pdo->prepare("INSERT INTO maintenance (regno, description, cost, date, receipt)
                           VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([
        $data['regno'],
        $data['description'],
        $data['cost'],
        $data['date'],
        $data['receipt'] ?? null
    ]);
}

// Get all maintenance records
function getMaintenanceRecords($pdo)
{
    $scope = build_scope_condition('v');

    $sql = "SELECT m.*
            FROM maintenance m
            INNER JOIN vehicles v ON v.regno = m.regno
            WHERE {$scope}
            ORDER BY m.date DESC";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get a single maintenance record by ID
function getMaintenanceById($pdo, $id)
{
    $scope = build_scope_condition('v');

    $stmt = $pdo->prepare("
        SELECT m.*
        FROM maintenance m
        INNER JOIN vehicles v ON v.regno = m.regno
        WHERE m.id = ? AND {$scope}
        LIMIT 1
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

// Update a maintenance record
function updateMaintenance($pdo, $id, $data)
{
    if (!empty($data['receipt'])) {
        $stmt = $pdo->prepare("UPDATE maintenance 
                               SET regno = ?, description = ?, cost = ?, date = ?, receipt = ?
                               WHERE id = ?");
        $scope = build_scope_condition('v');
        $stmt = $pdo->prepare("UPDATE maintenance m
                               SET regno = ?, description = ?, cost = ?, date = ?, receipt = ?
                               WHERE m.id = ?
                                 AND EXISTS (
                                     SELECT 1 FROM vehicles v
                                     WHERE v.regno = m.regno AND {$scope}
                                 )");
        return $stmt->execute([
            $data['regno'],
            $data['description'],
            $data['cost'],
            $data['date'],
            $data['receipt'],
            $id
        ]);
    } else {
        $stmt = $pdo->prepare("UPDATE maintenance 
                               SET regno = ?, description = ?, cost = ?, date = ?
                               WHERE id = ?");
        $scope = build_scope_condition('v');
        $stmt = $pdo->prepare("UPDATE maintenance m
                               SET regno = ?, description = ?, cost = ?, date = ?
                               WHERE m.id = ?
                                 AND EXISTS (
                                     SELECT 1 FROM vehicles v
                                     WHERE v.regno = m.regno AND {$scope}
                                 )");
        return $stmt->execute([
            $data['regno'],
            $data['description'],
            $data['cost'],
            $data['date'],
            $id
        ]);
    }
}

// Delete a maintenance record
function deleteMaintenance($pdo, $id)
{
    $scope = build_scope_condition('v');

    $stmt = $pdo->prepare("DELETE m
                           FROM maintenance m
                           INNER JOIN vehicles v ON v.regno = m.regno
                           WHERE m.id = ? AND {$scope}");
    return $stmt->execute([$id]);
}

// Refuel record filtering
function getRefuelRecords($pdo, $regno = '', $from = '', $to = '')
{
    $scope = build_scope_condition('v');

    $sql = "SELECT rf.*
            FROM refuel rf
            INNER JOIN vehicles v ON v.regno = rf.regno
            WHERE {$scope}";
    $params = [];

    if (!empty($regno)) {
        $sql .= " AND regno = ?";
        $params[] = $regno;
    }
    if (!empty($from)) {
        $sql .= " AND DATE(date) >= ?";
        $params[] = $from;
    }
    if (!empty($to)) {
        $sql .= " AND DATE(date) <= ?";
        $params[] = $to;
    }

    $sql .= " ORDER BY date DESC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getVehicleRegList($pdo)
{
    $scope = build_scope_condition('v');

    $sql = "SELECT DISTINCT v.regno
            FROM vehicles v
            WHERE {$scope}
            ORDER BY v.regno";

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// Get all vehicles
function getAllVehicles($pdo)
{
    $scope = build_scope_condition('v');

    $sql = "
        SELECT v.*,
               l.name AS location_name,
               sc.name AS subcounty_name,
               c.name AS county_name,
               r.name AS region_name
        FROM vehicles v
        LEFT JOIN locations l ON v.location_id = l.id
        LEFT JOIN subcounties sc ON l.subcounty_id = sc.id
        LEFT JOIN counties c ON sc.county_id = c.id
        LEFT JOIN regions r ON c.region_id = r.id
        WHERE {$scope}
        ORDER BY v.id DESC
    ";

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// Get a single vehicle by ID
function getVehicleById(PDO $pdo, int $id): array
{
    $scope = build_scope_condition('v');

    $stmt = $pdo->prepare("
        SELECT
            v.*,
            co.name AS county_name,
            sc.name AS subcounty_name,
            l.name AS location_name,
            c.name AS category_name,
            vs.label AS status_name
        FROM vehicles v
        LEFT JOIN locations l ON v.location_id = l.id
        LEFT JOIN subcounties sc ON v.subcounty_id = sc.id
        LEFT JOIN counties co ON v.county_id = co.id
        LEFT JOIN categories c ON v.category_id = c.id
        LEFT JOIN vehicle_statuses vs ON v.status_id = vs.id
        WHERE v.id = ? AND {$scope}
        LIMIT 1
    ");

    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}


// Add vehicle
function addVehicle($pdo, $data)
{
    $scope = build_scope_condition('v');

    // Full-access roles may add vehicles anywhere. Other users may only
    // add a vehicle inside their assigned scope.
    if (!has_full_access()) {
        $user = $_SESSION['user'] ?? [];
        $locationId = (int) ($data['location_id'] ?? 0);

        if (!empty($user['location_id']) && $locationId !== (int) $user['location_id']) {
            throw new RuntimeException('You are not allowed to add a vehicle outside your assigned location.');
        }

        if (empty($user['location_id']) && !empty($user['county_id'])) {
            $check = $pdo->prepare("
                SELECT 1
                FROM locations l
                INNER JOIN subcounties s ON l.subcounty_id = s.id
                WHERE l.id = ? AND s.county_id = ?
                LIMIT 1
            ");
            $check->execute([$locationId, (int) $user['county_id']]);

            if (!$check->fetchColumn()) {
                throw new RuntimeException('You are not allowed to add a vehicle outside your assigned county.');
            }
        }

        if (empty($user['location_id']) && empty($user['county_id']) && !empty($user['region_id'])) {
            $check = $pdo->prepare("
                SELECT 1
                FROM locations l
                INNER JOIN subcounties s ON l.subcounty_id = s.id
                INNER JOIN counties c ON s.county_id = c.id
                WHERE l.id = ? AND c.region_id = ?
                LIMIT 1
            ");
            $check->execute([$locationId, (int) $user['region_id']]);

            if (!$check->fetchColumn()) {
                throw new RuntimeException('You are not allowed to add a vehicle outside your assigned region.');
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO vehicles (
        regno, chassisno, engineno, make, color, designation,
        category_id, status_id, date,
        county_id, subcounty_id, location_id,
        created_by, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    $stmt->execute([
        $data['regno'],
        $data['chassisno'],
        $data['engineno'],
        $data['make'],
        $data['color'],
        $data['designation'],
        $data['category_id'],
        $data['status_id'],
        $data['date'],
        $data['county_id'],
        $data['subcounty_id'],
        $data['location_id'],
        $_SESSION['user']['id']
    ]);
    return $pdo->lastInsertId();
}

// Update vehicle
function updateVehicle($pdo, $id, $data)
{
    $scope = build_scope_condition('v');

    // Prevent restricted users from moving a vehicle into another scope.
    if (!has_full_access()) {
        $user = $_SESSION['user'] ?? [];
        $locationId = (int) ($data['location_id'] ?? 0);

        if (!empty($user['location_id']) && $locationId !== (int) $user['location_id']) {
            throw new RuntimeException('You are not allowed to move a vehicle outside your assigned location.');
        }

        if (empty($user['location_id']) && !empty($user['county_id'])) {
            $check = $pdo->prepare("
                SELECT 1
                FROM locations l
                INNER JOIN subcounties s ON l.subcounty_id = s.id
                WHERE l.id = ? AND s.county_id = ?
                LIMIT 1
            ");
            $check->execute([$locationId, (int) $user['county_id']]);

            if (!$check->fetchColumn()) {
                throw new RuntimeException('You are not allowed to move a vehicle outside your assigned county.');
            }
        }

        if (empty($user['location_id']) && empty($user['county_id']) && !empty($user['region_id'])) {
            $check = $pdo->prepare("
                SELECT 1
                FROM locations l
                INNER JOIN subcounties s ON l.subcounty_id = s.id
                INNER JOIN counties c ON s.county_id = c.id
                WHERE l.id = ? AND c.region_id = ?
                LIMIT 1
            ");
            $check->execute([$locationId, (int) $user['region_id']]);

            if (!$check->fetchColumn()) {
                throw new RuntimeException('You are not allowed to move a vehicle outside your assigned region.');
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE vehicles SET 
        regno = ?, 
        chassisno = ?, 
        engineno = ?, 
        make = ?, 
        color = ?, 
        designation = ?, 
        category_id = ?, 
        status_id = ?,
        date = ?, 
        county_id = ?, 
        subcounty_id = ?, 
        location_id = ?, 
        updated_by = ?, 
        updated_at = NOW()
        WHERE id = ? AND {$scope}
    ");

    return $stmt->execute([
        $data['regno'],
        $data['chassisno'],
        $data['engineno'],
        $data['make'],
        $data['color'],
        $data['designation'],
        $data['category_id'],
        $data['status_id'],
        $data['date'],
        $data['county_id'],
        $data['subcounty_id'],
        $data['location_id'],
        $_SESSION['user']['id'],
        $id
    ]);
}

function canViewUsers($user) {
    return in_array($user['group_id'] ?? -1, [0, 1, 5, 6]) || ($user['is_superadmin'] ?? false);
}


// Location scope & roles
function is_hq()
{
    return ($_SESSION['user']['group_id'] ?? 0) == 1;
}

function get_scope_filter($tableAlias = 'v')
{
    $user = $_SESSION['user'] ?? [];
    $filters = [];

    // Super Admin, Admin and HQ Manager have unrestricted access.
    if (has_full_access()) {
        return '';
    }

    if (!empty($user['county_id'])) {
        $filters[] = "$tableAlias.location_id IN (
            SELECT id FROM locations WHERE subcounty_id IN (
                SELECT id FROM subcounties WHERE county_id = {$user['county_id']}
            )
        )";
    } elseif (!empty($user['region_id'])) {
        $filters[] = "$tableAlias.location_id IN (
            SELECT l.id FROM locations l
            JOIN subcounties s ON l.subcounty_id = s.id
            JOIN counties c ON s.county_id = c.id
            WHERE c.region_id = {$user['region_id']}
        )";
    }

    return $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
}
function userLevel()
{
    return $_SESSION['user']['group_id'] ?? null;
}

function current_user(): ?array
{
    static $user = null;
    if ($user === null) {
        $user = $_SESSION['user'] ?? null;
    }
    return $user;
}

function is_superadmin(): bool
{
    $user = current_user();
    return $user && ((int) ($user['group_id'] ?? 0) === ROLE_SUPER_ADMIN);
}

function is_admin(): bool
{
    $user = current_user();
    return $user && ((int) ($user['group_id'] ?? 0) === ROLE_ADMIN);
}

function is_hq_manager(): bool
{
    $user = current_user();
    return $user && ((int) ($user['group_id'] ?? 0) === ROLE_HQ_MANAGER);
}

function is_manager(): bool
{
    $user = current_user();
    return $user && ((int) ($user['group_id'] ?? 0) === ROLE_MANAGER);
}

function is_staff(): bool
{
    $user = current_user();
    return $user && ((int) ($user['group_id'] ?? 0) === ROLE_STAFF);
}

function is_supermanager(): bool
{
    $user = current_user();
    return $user && ((int) ($user['group_id'] ?? 0) === ROLE_SUPER_MANAGER);
}

function has_full_access(): bool
{
    return is_superadmin() || is_admin() || is_hq_manager();
}

function build_scope_condition($alias = '')
{
    $user = $_SESSION['user'] ?? [];
    $a = $alias ? "$alias." : '';

    if (has_full_access())
        return '1=1';

    if (!empty($user['location_id'])) {
        return "{$a}location_id = " . (int) $user['location_id'];
    }
    if (!empty($user['county_id'])) {
        return "{$a}location_id IN (
            SELECT l.id FROM locations l
            JOIN subcounties s ON l.subcounty_id = s.id
            WHERE s.county_id = " . (int) $user['county_id'] . "
        )";
    }
    if (!empty($user['region_id'])) {
        return "{$a}location_id IN (
            SELECT l.id FROM locations l
            JOIN subcounties s ON l.subcounty_id = s.id
            JOIN counties c ON s.county_id = c.id
            WHERE c.region_id = " . (int) $user['region_id'] . "
        )";
    }
    return '0=1';
}

function esc($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}


//profile handling
function getUserProfile($pdo)
{
    if (!isset($_SESSION['user']['id'])) {
        return ['name' => 'User', 'image' => 'assets/img/default.png'];
    }

    $stmt = $pdo->prepare("SELECT name, image FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'name' => $user['name'] ?? 'User',
        'image' => !empty($user['image']) ? 'uploads/profile/' . $user['image'] : 'assets/img/default.png'
    ];
}
function uploadProfileImage($imageFile, $uploadDir = 'uploads/profile/', $oldFilename = null)
{
    // Ensure upload directory ends with a slash
    $uploadDir = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if ($imageFile['error'] === UPLOAD_ERR_OK) {
        // Delete old image if exists
        if ($oldFilename) {
            $oldFilePath = $uploadDir . $oldFilename;
            if (file_exists($oldFilePath)) {
                @unlink($oldFilePath);
            }
        }

        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate new filename
        $filename = uniqid() . '_' . basename($imageFile['name']);
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($imageFile['tmp_name'], $targetPath)) {
            return $filename;
        }
    }
    return null;
}

//Get Vehicle Access Clause
function getAccessClause(string $regionAlias = 'co', string $vehicleAlias = 'v'): string
{
    $region_id = $_SESSION['region_id'] ?? null;
    $county_id = $_SESSION['county_id'] ?? null;
    $subcounty_id = $_SESSION['subcounty_id'] ?? null;
    $location_id = $_SESSION['location_id'] ?? null;

    $r = $regionAlias ? $regionAlias . '.' : '';
    $v = $vehicleAlias ? $vehicleAlias . '.' : '';

    if (has_full_access()) {
        return '';
    }

    if ((int) $region_id === HQ_REGION_ID || !$region_id)
        return '';

    if ($region_id && !$county_id) {
        return " AND {$r}region_id = " . (int) $region_id;
    }

    if ($region_id && $county_id && !$subcounty_id) {
        return " AND {$r}region_id = " . (int) $region_id .
            " AND {$v}county_id = " . (int) $county_id;
    }

    if ($region_id && $county_id && $subcounty_id && !$location_id) {
        return " AND {$r}region_id = " . (int) $region_id .
            " AND {$v}county_id = " . (int) $county_id .
            " AND {$v}subcounty_id = " . (int) $subcounty_id;
    }

    if ($region_id && $county_id && $subcounty_id && $location_id) {
        return " AND {$r}region_id = " . (int) $region_id .
            " AND {$v}county_id = " . (int) $county_id .
            " AND {$v}subcounty_id = " . (int) $subcounty_id .
            " AND {$v}location_id = " . (int) $location_id;
    }

    return '';
}
// Get requisitions access clause
function getRequisitionAccessClause(): string
{
    $user_id = $_SESSION['user']['id'] ?? 0;
    $group_id = $_SESSION['user']['group_id'] ?? 0;

    $region_id = $_SESSION['region_id'] ?? null;
    $county_id = $_SESSION['county_id'] ?? null;
    $subcounty_id = $_SESSION['subcounty_id'] ?? null;
    $location_id = $_SESSION['location_id'] ?? null;

    if (has_full_access()) {
        return '';
    }

    // HQ-level access
    if ((int) $region_id === HQ_REGION_ID || !$region_id) {
        return ''; // Full access
    }

    // Build region-based conditions
    $conditions = [];

    if ($region_id)
        $conditions[] = "co.region_id = " . (int) $region_id;
    if ($county_id)
        $conditions[] = "v.county_id = " . (int) $county_id;
    if ($subcounty_id)
        $conditions[] = "v.subcounty_id = " . (int) $subcounty_id;
    if ($location_id)
        $conditions[] = "v.location_id = " . (int) $location_id;

    $clause = implode(' AND ', $conditions);

    if (in_array($group_id, [1, 2, 5])) {
        // Approvers: strict — no fallback to requester
        return $clause ? "AND ($clause)" : '';
    }

    // Requester or admin: show own OR accessible
    return $clause
        ? "AND (($clause) OR r.requested_by = $user_id)"
        : "AND r.requested_by = $user_id";
}

//Get Maintenance Access Clause
function getMaintenanceAccessClause(): string
{
    $region_id = $_SESSION['region_id'] ?? null;
    $county_id = $_SESSION['county_id'] ?? null;
    $subcounty_id = $_SESSION['subcounty_id'] ?? null;
    $location_id = $_SESSION['location_id'] ?? null;

    if (has_full_access()) {
        return '';
    }

    if ((int) $region_id === HQ_REGION_ID || !$region_id)
        return '';

    $conditions = [];

    if ($region_id)
        $conditions[] = "co.region_id = " . (int) $region_id;
    if ($county_id)
        $conditions[] = "v.county_id = " . (int) $county_id;
    if ($subcounty_id)
        $conditions[] = "v.subcounty_id = " . (int) $subcounty_id;
    if ($location_id)
        $conditions[] = "v.location_id = " . (int) $location_id;

    // Only return AND if there are conditions
    return $conditions ? "AND " . implode(' AND ', $conditions) : '';
}

// Get Refuel Access Clause
function getRefuelAccessClause(): string
{
    $region_id = $_SESSION['region_id'] ?? null;
    $county_id = $_SESSION['county_id'] ?? null;
    $subcounty_id = $_SESSION['subcounty_id'] ?? null;
    $location_id = $_SESSION['location_id'] ?? null;

    // Full-access roles see all refuel records.
    if (has_full_access()) {
        return '';
    }

    // HQ sees all
    if ((int) $region_id === HQ_REGION_ID || !$region_id) {
        return '';
    }

    $conditions = [];

    if ($region_id)
        $conditions[] = "co.region_id = " . (int) $region_id;
    if ($county_id)
        $conditions[] = "v.county_id = " . (int) $county_id;
    if ($subcounty_id)
        $conditions[] = "v.subcounty_id = " . (int) $subcounty_id;
    if ($location_id)
        $conditions[] = "v.location_id = " . (int) $location_id;

    // Only return AND if there are conditions
    return $conditions ? "AND " . implode(' AND ', $conditions) : '';
}

// Get dashboard access clause

function getDashboardAccessClause(string $prefix = 'v'): string
{
    $region_id = $_SESSION['region_id'] ?? null;
    $county_id = $_SESSION['county_id'] ?? null;
    $subcounty_id = $_SESSION['subcounty_id'] ?? null;
    $location_id = $_SESSION['location_id'] ?? null;

    // Full-access roles are unrestricted.
    if (has_full_access()) {
        return '1';
    }

    // HQ bypass: unrestricted access
    if ((int) $region_id === HQ_REGION_ID) {
        return '1';
    }

    $clauses = [];

    // Most specific scope wins
    if ($location_id) {
        $clauses[] = "{$prefix}.location_id = " . (int) $location_id;
    }
    if ($subcounty_id) {
        $clauses[] = "{$prefix}.subcounty_id = " . (int) $subcounty_id;
    }
    if ($county_id) {
        $clauses[] = "{$prefix}.county_id = " . (int) $county_id;
    }
    if ($region_id) {
        $clauses[] = "co.region_id = " . (int) $region_id;
    }

    return $clauses ? implode(' AND ', $clauses) : '1';
}
function getUserLocationLabel(array $req, array $regions, array $counties, array $subcounties, array $locations, string $prefix): string {
    $locationId   = $req["{$prefix}_location_id"] ?? null;
    $subcountyId  = $req["{$prefix}_subcounty_id"] ?? null;
    $countyId     = $req["{$prefix}_county_id"] ?? null;
    $regionId     = $req["{$prefix}_region_id"] ?? null;

    if ($locationId && isset($locations[$locationId])) {
        return $locations[$locationId];
    }
    if ($subcountyId && isset($subcounties[$subcountyId])) {
        return $subcounties[$subcountyId];
    }
    if ($countyId && isset($counties[$countyId])) {
        return $counties[$countyId];
    }
    if ($regionId && isset($regions[$regionId])) {
        return $regions[$regionId];
    }
    return 'Unknown';
}
