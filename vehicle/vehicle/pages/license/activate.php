<?php
date_default_timezone_set('Africa/Nairobi');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/license_constants.php';

function decryptPayload($input)
{
    $raw = base64_decode($input);
    $iv = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);
    return openssl_decrypt($ciphertext, 'AES-256-CBC', LICENSE_CRYPTO_KEY, OPENSSL_RAW_DATA, $iv);
}

function getDeviceHash()
{
    return hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
}

if (!isset($_SESSION['user'])) {
    echo "<div class='alert alert-danger'>You must be logged in to activate a license.</div>";
    return;
}

$isAdmin = $_SESSION['user']['group_id'] == 1;
$domain = $isAdmin ? ($_POST['domain'] ?? '') : $_SERVER['HTTP_HOST'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF()) {
    $key = trim($_POST['license_key']);

    if (!$domain) {
        $errors[] = "Domain is required.";
    }

    if (strlen($key) < 10 || !str_contains($key, '.')) {
        $errors[] = "Invalid license format.";
    } else {
        list($encrypted, $sig) = explode('.', $key, 2);
        $expectedSig = hash_hmac('sha256', $encrypted, LICENSE_HMAC_SECRET);

        if (!hash_equals($expectedSig, $sig)) {
            $errors[] = "Invalid license signature.";
        } else {
            $payloadJson = decryptPayload($encrypted);
            $payload = json_decode($payloadJson, true);

            if (!$payload || !isset($payload['domain'], $payload['expires'])) {
                $errors[] = "Corrupted license payload.";
            } elseif ($payload['domain'] !== $domain) {
                $errors[] = "License domain mismatch.";
            } elseif (strtotime($payload['expires']) < time()) {
                $errors[] = "License has expired.";
            } else {
                // ✅ Valid license payload, now check in DB
                $stmt = $pdo->prepare("SELECT id, is_used, is_revoked FROM licenses WHERE license_key = ? LIMIT 1");
                $stmt->execute([$key]);
                $license = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$license) {
                    $errors[] = "❌ License not found.";
                } elseif ($license['is_revoked']) {
                    $errors[] = "❌ License has been revoked.";
                } else {
                    $deviceHash = getDeviceHash();

                    // Check if this device is already registered
                    $checkDevice = $pdo->prepare("SELECT id FROM license_devices WHERE license_id = ? AND device_hash = ?");
                    $checkDevice->execute([$license['id'], $deviceHash]);
                    $existingDevice = $checkDevice->fetch();

                    if ($license['is_used']) {
                        if ($existingDevice) {
                            // Update last_seen
                            $pdo->prepare("UPDATE license_devices SET last_seen = NOW() WHERE id = ?")
                                ->execute([$existingDevice['id']]);

                            echo "<div class='alert alert-info'>✅ License already active on this device. <a href='admin.php'>Continue</a></div>";
                            return;
                        } else {
                            $errors[] = "❌ License already used on another device.";
                        }
                    } else {
                        // ✅ First activation — bind to this device
                        $pdo->prepare("
                            UPDATE licenses 
                            SET is_used = 1, activated_at = NOW(), domain = ?, expires = ? 
                            WHERE id = ?
                        ")->execute([$domain, $payload['expires'], $license['id']]);

                        // ✅ Register the current device
                        $pdo->prepare("
                            INSERT INTO license_devices (license_id, device_hash, registered_at, last_seen)
                            VALUES (?, ?, NOW(), NOW())
                        ")->execute([$license['id'], $deviceHash]);

                        $_SESSION['license_success'] = "✅ License activated successfully.";
                        echo "<script>location.href = 'admin.php';</script>";
                        return;
                    }
                }
            }
        }
    }
}
?>

<div class="container mt-5">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mx-auto" style="max-width: 500px;">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e ?? '') ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card mx-auto shadow" style="max-width: 500px;">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">🔐 License Activation
                <?= $isAdmin ? "for Domain" : "for " . htmlspecialchars($domain ?? '') ?></h5>
        </div>
        <div class="card-body">
            <form method="post">
                <?= csrfInput() ?>
                <?php if ($isAdmin): ?>
                    <div class="mb-3">
                        <label for="domain" class="form-label">Domain</label>
                        <input type="text" name="domain" id="domain" class="form-control"
                            value="<?= htmlspecialchars($domain ?? '') ?>" required>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="license_key" class="form-label">License Key</label>
                    <input type="text" name="license_key" value="<?= htmlspecialchars($_POST['license_key'] ?? '') ?>"
                        class="form-control" required>

                </div>

                <button class="btn btn-success w-100">Activate</button>
            </form>
        </div>
    </div>
</div>