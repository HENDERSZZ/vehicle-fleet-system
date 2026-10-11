<?php
session_start();
require_once '../../includes/config.php'; // provides $pdo and decryptData()

// 🔐 CSRF validation
$csrf = $_POST['csrf_token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRF()) {
    http_response_code(403);
    exit('❌ Invalid CSRF token.');
}
// ✅ Inputs
$licenseKey = strtoupper(str_replace('-', '', $_POST['license_key'] ?? ''));
$domain     = $_POST['domain'] ?? '';

if (empty($licenseKey) || empty($domain)) {
    http_response_code(400);
    exit('❌ License key and domain are required.');
}

// 🎯 Find an unused license
$stmt = $pdo->prepare("SELECT * FROM licenses WHERE is_used = 0");
$stmt->execute();

$found = false;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $decrypted = decryptData($row['license_key']);
    if ($decrypted && strcasecmp($decrypted, $licenseKey) === 0) {
        $found = true;

        // ✅ Generate HMAC signature
        $signature = generateLicenseHMAC($licenseKey, $domain, $row['expires']);

        // 📝 Mark license as used
        $update = $pdo->prepare("UPDATE licenses SET domain = ?, is_used = 1, activated_at = NOW() WHERE id = ?");
        $update->execute([$domain, $row['id']]);

        echo json_encode([
            'status'    => 'success',
            'message'   => 'License activated successfully.',
            'signature' => $signature
        ]);
        break;
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode([
        'status'  => 'error',
        'message' => 'License not found or already used.'
    ]);
}
