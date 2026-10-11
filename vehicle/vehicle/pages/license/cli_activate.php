<?php
// cli_activate.php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/license_constants.php';

// CLI options
$options = getopt('', ['license:', 'domain:', 'json::']);
$license = $options['license'] ?? null;
$domain  = $options['domain'] ?? null;
$jsonOut = isset($options['json']);

// Load HMAC secret
$secret = defined('LICENSE_HMAC_SECRET') ? LICENSE_HMAC_SECRET : 'your-secret-key';

// ✅ Validate input
if (!$license || !$domain) {
    $msg = "Usage: php cli_activate.php --license=XXXX-YYYY --domain=your.domain.com [--json]\n";
    exit($jsonOut ? json_encode(['error' => $msg]) : $msg);
}

$license = strtoupper(trim($license));
$domain  = strtolower(trim($domain));
$data    = str_replace('-', '', $license);

// 🔍 Format check
if (!preg_match('/^[A-Z0-9\-\.]{10,}$/', $license)) {
    $msg = "❌ Invalid license format.\n";
    exit($jsonOut ? json_encode(['error' => 'invalid_format']) : $msg);
}

try {
    // 🔍 Direct DB query for unused license candidates
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE is_used = 0");
    $stmt->execute();

    $found = false;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $decrypted = decryptData($row['license_key']);
        if (!$decrypted) {
            continue;
        }

        if (hash_equals($decrypted, $license)) {
            // Expiration check
            if (strtotime($row['expires']) < time()) {
                $msg = "❌ License expired on {$row['expires']}.\n";
                exit($jsonOut ? json_encode(['error' => 'expired', 'expires' => $row['expires']]) : $msg);
            }

            // Generate signature
            $signature = hash_hmac('sha256', "$data|$domain|{$row['expires']}", $secret);

            // Activate license
            $update = $pdo->prepare("
                UPDATE licenses 
                SET domain = ?, is_used = 1, activated_at = NOW() 
                WHERE id = ?
            ");
            $update->execute([$domain, $row['id']]);

            $found = true;

            if ($jsonOut) {
                echo json_encode([
                    'success'   => true,
                    'license'   => $license,
                    'domain'    => $domain,
                    'expires'   => $row['expires'],
                    'signature' => $signature
                ]);
            } else {
                echo "✅ License activated successfully.\n";
                echo "   License:   $license\n";
                echo "   Domain:    $domain\n";
                echo "   Expires:   {$row['expires']}\n";
                echo "   Signature: $signature\n";
            }

            exit(0);
        }
    }

    // ❌ No match found
    $msg = "❌ License not found or already used.\n";
    exit($jsonOut ? json_encode(['error' => 'not_found']) : $msg);

} catch (Exception $e) {
    $msg = "❌ DB Error: " . $e->getMessage() . "\n";
    exit($jsonOut ? json_encode(['error' => 'db_error', 'message' => $e->getMessage()]) : $msg);
}
