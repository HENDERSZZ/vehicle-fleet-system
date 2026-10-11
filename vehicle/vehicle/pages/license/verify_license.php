<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once '../auth/auth.php';

// 🛠️ Helper for JSON responses
function jsonResponse($data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
// 🛡️ Validate CSRF token securely
if (!verifyCSRF()) {
    jsonResponse(['success' => false, 'error' => 'Invalid or expired CSRF token'], 403);
}

// 👤 Only Super Admins can revoke
if (($_SESSION['user']['group_id'] ?? 0) != 6) {
    jsonResponse(['success' => false, 'error' => 'Unauthorized access'], 403);
}

// 📥 Get input
$deviceId = $_POST['device_id'] ?? null;
$adminId  = $_SESSION['user']['id'] ?? null;

if (!$deviceId || !$adminId) {
    jsonResponse(['success' => false, 'error' => 'Missing required data.'], 400);
}

try {
    $pdo->beginTransaction();

    // 🔍 Check device exists & fetch license_id for logging
    $stmt = $pdo->prepare("SELECT license_id FROM license_devices WHERE id = ?");
    $stmt->execute([$deviceId]);
    $licenseId = $stmt->fetchColumn();

    if (!$licenseId) {
        throw new Exception("Device not found.");
    }

    // 🧹 Delete device
    $delete = $pdo->prepare("DELETE FROM license_devices WHERE id = ?");
    $delete->execute([$deviceId]);

    // 📝 Log the deletion
    $log = $pdo->prepare("
        INSERT INTO device_revocation_log (device_id, license_id, admin_id, deleted_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $log->execute([$deviceId, $licenseId, $adminId]);

    $pdo->commit();

    jsonResponse(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
