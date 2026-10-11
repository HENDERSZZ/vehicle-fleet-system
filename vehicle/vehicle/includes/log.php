<?php
require_once __DIR__ . '/config.php'; // defines $pdo

function log_action($pdo, $action, $user_id)
{
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $action]);
}
