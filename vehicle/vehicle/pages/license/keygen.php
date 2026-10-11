<?php
date_default_timezone_set('Africa/Nairobi');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/license_constants.php';

// ✅ Only Super Admins can access
if ($_SESSION['user']['group_id'] != 6) {
    echo "<div class='alert alert-danger'>Only Super admins can access the license generator.</div>";
    return;
}

// 🔐 Helpers
function encryptPayload($data)
{
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($data, 'AES-256-CBC', LICENSE_CRYPTO_KEY, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ciphertext);
}

function signLicense($encryptedData)
{
    return hash_hmac('sha256', $encryptedData, LICENSE_HMAC_SECRET);
}

// 🏁 Initialize variables
$generatedKey = '';
$expiresAt = '';
$domain = $_SERVER['HTTP_HOST'];
$message = '';
$duration = '';
$createdBy = $_SESSION['user']['name'] ?? 'Admin';

// 🎯 Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['revoke_id']) && ctype_digit($_POST['revoke_id'])) {
        $stmt = $pdo->prepare("UPDATE licenses SET is_revoked = 1 WHERE id = ?");
        $stmt->execute([$_POST['revoke_id']]);
        $message = "❌ License revoked successfully.";
    } elseif (isset($_POST['duration'])) {
        $duration = $_POST['duration'];
        $expiresAt = match ($duration) {
            '1_hour' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            '1_month' => date('Y-m-d H:i:s', strtotime('+1 month')),
            default => date('Y-m-d H:i:s', strtotime('+1 year')),
        };

        $notes = trim($_POST['purpose'] ?? '');
        $payload = json_encode([
            'domain' => $domain,
            'expires' => $expiresAt,
            'issued_at' => date('Y-m-d H:i:s'),
            'issued_by' => $createdBy,
            'purpose' => $notes,
        ]);

        $encryptedPayload = encryptPayload($payload);
        $signature = signLicense($encryptedPayload);
        $finalKey = $encryptedPayload . '.' . $signature;

        // 🔐 Ensure no duplicates
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM licenses WHERE license_key = ?");
        $stmt->execute([$finalKey]);
        if ($stmt->fetchColumn() > 0) {
            $message = "❌ Duplicate license key generated. Try again.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO licenses 
                (license_key, domain, created_by, notes, expires, created_at, is_used, is_revoked) 
                VALUES (?, ?, ?, ?, ?, NOW(), 0, 0)
            ");
            $stmt->execute([$finalKey, $domain, $createdBy, $notes, $expiresAt]);

            $generatedKey = $finalKey;
            $message = "✅ License created successfully. Copy or download it now.";
        }
    }
}

// 📄 Pagination
$perPage = 10;
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

$totalCount = $pdo->query("SELECT COUNT(*) FROM licenses")->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $pdo->prepare("
    SELECT l.*, COUNT(ld.id) AS device_count 
    FROM licenses l 
    LEFT JOIN license_devices ld ON ld.license_id = l.id 
    GROUP BY l.id 
    ORDER BY l.id DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$recentLicenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- 🔑 UI -->
<div class="container mt-4" style="max-width: 700px;">
    <h4 class="mb-4"><i class="fas fa-key"></i> License Key Generator</h4>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- 🧾 Generate License Form -->
    <form method="post" class="card shadow p-4 mb-4">
        <div class="mb-3">
            <label for="duration" class="form-label">Select Duration</label>
            <select name="duration" id="duration" class="form-select" required>
                <option value="1_hour" <?= $duration === '1_hour' ? 'selected' : '' ?>>1 Hour</option>
                <option value="1_month" <?= $duration === '1_month' ? 'selected' : '' ?>>1 Month</option>
                <option value="1_year" <?= $duration === '1_year' ? 'selected' : '' ?>>1 Year</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="purpose" class="form-label">Purpose / Assigned To</label>
            <input type="text" name="purpose" id="purpose" class="form-control" placeholder="e.g. Staff PC Activation">
        </div>
        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-cogs me-1"></i> Generate Key</button>
    </form>

    <?php if ($generatedKey): ?>
        <div class="card mt-4">
            <div class="card-header bg-success text-white">🔐 License Generated</div>
            <div class="card-body">
                <p><strong>License Key:</strong> <code id="key"><?= htmlspecialchars($generatedKey) ?></code></p>
                <p><strong>Domain:</strong> <?= htmlspecialchars($domain) ?></p>
                <p><strong>Expires At:</strong> <?= htmlspecialchars($expiresAt) ?></p>

                <button onclick="copyKey()" class="btn btn-outline-secondary btn-sm me-2"><i class="fas fa-copy"></i> Copy
                    Key</button>
                <button onclick="downloadEnv()" class="btn btn-outline-success btn-sm"><i class="fas fa-download"></i>
                    Download .env</button>
            </div>
        </div>
    <?php endif; ?>

    <!-- 📋 Recent Keys Table -->
    <h5 class="mt-5">🗃️ Recent License Keys</h5>
    <table class="table table-bordered table-sm">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Purpose</th>
                <th>Domain</th>
                <th>Expires</th>
                <th>Created By</th>
                <th>Status</th>
                <th>Devices</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentLicenses as $lic): ?>
                <tr>
                    <td><?= $lic['id'] ?></td>
                    <td><?= htmlspecialchars($lic['notes'] ?? '') ?></td>
                    <td><?= htmlspecialchars($lic['domain']) ?></td>
                    <td><?= $lic['expires'] ?></td>
                    <td><?= htmlspecialchars($lic['created_by'] ?? '') ?></td>
                    <td>
                        <?= $lic['is_revoked'] ? '<span class="badge bg-danger">Revoked</span>' : '<span class="badge bg-success">Active</span>' ?>
                    </td>
                    <td><?= $lic['device_count'] ?> device(s)</td>
                    <td>
                        <?php if (!$lic['is_revoked']): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="revoke_id" value="<?= $lic['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('Revoke this key?')">
                                    Revoke
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- 📌 Pagination -->
    <?php if ($totalPages > 1): ?>
        <?php
        $pagesPerGroup = 5;
        $currentGroup = ceil($page / $pagesPerGroup);
        $startPage = ($currentGroup - 1) * $pagesPerGroup + 1;
        $endPage = min($startPage + $pagesPerGroup - 1, $totalPages);
        ?>
        <nav aria-label="Pagination">
            <ul class="pagination justify-content-center mt-4">
                <li class="page-item <?= $startPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=license/keygen&p=<?= max(1, $startPage - 1) ?>">&laquo; Prev</a>
                </li>
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=license/keygen&p=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $endPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=license/keygen&p=<?= min($totalPages, $endPage + 1) ?>">Next
                        &raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script>
    function copyKey() {
        const key = document.getElementById('key').innerText;
        navigator.clipboard.writeText(key).then(() => alert("License key copied!"));
    }
    function downloadEnv() {
        const key = document.getElementById('key').innerText;
        const blob = new Blob([`LICENSE_KEY="${key}"`], { type: "text/plain" });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = "license.env";
        link.click();
    }
</script>