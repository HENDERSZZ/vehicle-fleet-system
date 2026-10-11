<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';

// ✅ Admin bypass
if ($isAdmin || (isset($isSuperAdmin) && $isSuperAdmin)) {
    echo json_encode(['valid' => true, 'admin_bypass' => true]);
    exit;
}

// 🔒 Secure inputs
$license     = trim($_POST['license_key'] ?? '');
$domain      = trim($_POST['domain'] ?? '');
$fingerprint = trim($_POST['fingerprint'] ?? '');
$secret      = LICENSE_HMAC_SECRET;

// Normalize for comparison
$data = strtoupper(str_replace('-', '', $license));

// Basic format check
if (!preg_match('/^[A-Z0-9\-\.]{10,}$/', $license)) {
    http_response_code(400);
    echo json_encode(['valid' => false, 'error' => 'Invalid license format.']);
    exit;
}

// Require fingerprint for stronger binding
if (empty($fingerprint)) {
    http_response_code(400);
    echo json_encode(['valid' => false, 'error' => 'Missing fingerprint.']);
    exit;
}

// 🔍 Query DB (direct domain filter)
$stmt = $pdo->prepare("SELECT * FROM licenses WHERE domain = ? AND is_used = 1 LIMIT 10");
$stmt->execute([$domain]);

$valid = false;
$row   = null;

while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $decrypted = strtoupper(decryptData($record['license_key']));

    if (hash_equals($decrypted, $data)) {
        // Expired check
        if (strtotime($record['expires']) < time()) {
            http_response_code(403);
            echo json_encode(['valid' => false, 'error' => 'License expired.']);
            exit;
        }

        // Revoked check
        if (!empty($record['is_revoked'])) {
            http_response_code(403);
            echo json_encode(['valid' => false, 'error' => 'License revoked.']);
            exit;
        }

        $row   = $record;
        $valid = true;
        break;
    }
}

if (!$valid) {
    http_response_code(401); // Unauthorized (not valid license)
    echo json_encode(['valid' => false, 'error' => 'License not valid.']);
    exit;
}

// Optional signature
$expectedSig = hash_hmac('sha256', "$data|$domain|{$row['expires']}", $secret);

// Log usage
$pdo->prepare("
    INSERT INTO license_usage_log (license_id, domain, ip_address, user_agent, created_at)
    VALUES (?, ?, ?, ?, NOW())
")->execute([$row['id'], $domain, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

// Device binding hash
$device = hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR'] . $fingerprint);

// ✅ Wrap device binding in transaction to prevent race conditions
try {
    $pdo->beginTransaction();

    $exists = $pdo->prepare("SELECT id FROM license_devices WHERE license_id = ? AND device_hash = ? LIMIT 1");
    $exists->execute([$row['id'], $device]);

    if (!$exists->fetch()) {
        // Count devices
        $count = $pdo->prepare("SELECT COUNT(*) FROM license_devices WHERE license_id = ?");
        $count->execute([$row['id']]);
        $deviceCount = (int) $count->fetchColumn();

        if ($deviceCount >= 3) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['valid' => false, 'error' => 'Device limit reached.']);
            exit;
        }

        // Register device
        $pdo->prepare("
            INSERT INTO license_devices (license_id, device_hash, fingerprint, registered_at, last_seen)
            VALUES (?, ?, ?, NOW(), NOW())
        ")->execute([$row['id'], $device, $fingerprint]);
    } else {
        // Update last_seen
        $pdo->prepare("UPDATE license_devices SET last_seen = NOW() WHERE license_id = ? AND device_hash = ?")
            ->execute([$row['id'], $device]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['valid' => false, 'error' => 'Server error.']);
    exit;
}

// 🎉 Success response
$response = [
    'valid'   => true,
    'expires' => $row['expires'],
    'signature' => $expectedSig
];

echo json_encode($response);
