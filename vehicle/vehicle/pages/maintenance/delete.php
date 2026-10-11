<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!can_delete()) {
    echo "<div class='alert alert-danger m-4'>⛔ Access denied. Only administrators can delete maintenance records.</div>";
    exit;
}

// 📌 Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "<div class='alert alert-danger m-4'>❌ Invalid request method.</div>";
    exit;
}

// 🛡️ CSRF Protection
if (!verifyCSRF()) {
    echo "<div class='alert alert-danger m-4'>❌ Invalid or expired CSRF token.</div>";
    exit;
}

// 📥 Validate ID input
$id = $_POST['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger m-4'>❌ Invalid maintenance ID.</div>";
    exit;
}

try {
    $pdo->beginTransaction();

    // 🔍 Fetch record
    $stmt = $pdo->prepare("SELECT receipt, regno FROM maintenance WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new Exception("Maintenance record not found.");
    }

    // 🧹 Remove associated file if exists
    $uploadPath = realpath(__DIR__ . '/../../uploads') . DIRECTORY_SEPARATOR;
    $receipt = $record['receipt'];

    if (!empty($receipt)) {
        $filePath = realpath($uploadPath . $receipt);

        // Prevent deleting outside upload dir
        if ($filePath && strpos($filePath, $uploadPath) === 0 && file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // 🗑️ Delete record
    $deleteStmt = $pdo->prepare("DELETE FROM maintenance WHERE id = ?");
    $deleteStmt->execute([$id]);

    $pdo->commit();

    $user = $_SESSION['user']['username'] ?? 'unknown';
    error_log("[DELETE] User '{$user}' deleted maintenance ID {$id} for {$record['regno']} on " . date('Y-m-d H:i:s'));

    echo "<div class='alert alert-success m-4'>✅ Maintenance record deleted successfully.</div>";
    echo "<a href='?page=maintenance/index' class='btn btn-primary ms-4'>← Back to List</a>";

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("[DELETE-ERROR] " . $e->getMessage());
    echo "<div class='alert alert-danger m-4'>❌ Failed to delete maintenance record.</div>";
}
