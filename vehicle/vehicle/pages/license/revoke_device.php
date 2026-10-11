<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once '../auth/auth.php';

// 🛠️ Helper for JSON errors
function jsonError($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// 🛡️ Validate CSRF token securely
if (!verifyCSRF()) {
    jsonResponse(['success' => false, 'error' => 'Invalid or expired CSRF token'], 403);
}

// 👤 Ensure only Super Admins can revoke
if (($_SESSION['user']['group_id'] ?? 0) != 6) {
    jsonError("Unauthorized access", 403);
}

// 📥 Get input
$deviceId = $_POST['device_id'] ?? null;
$adminId  = $_SESSION['user']['id'] ?? null;

if (!$deviceId || !$adminId) {
    jsonError("Missing required data.", 400);
}

try {
    $pdo->beginTransaction();

    // 🧹 First fetch license_id for audit logging
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

    // ✅ Success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError($e->getMessage(), 500);
}
exit;
