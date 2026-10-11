<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../../includes/config.php'; // Ensure this sets up $pdo

$timeoutSeconds = 10;
$userStatus = [];

$sql = "SELECT id, last_activity FROM users";
$stmt = $pdo->query($sql);
$currentTime = time();

if ($stmt) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastActivity = $row['last_activity'];

        if (empty($lastActivity)) {
            $userStatus[$row['id']] = 'offline';
            continue;
        }

        $lastTimestamp = strtotime($lastActivity);
        $diff = $currentTime - $lastTimestamp;

        $userStatus[$row['id']] = ($diff <= $timeoutSeconds) ? 'online' : 'offline';
    }
}

header('Content-Type: application/json');
echo json_encode($userStatus);
