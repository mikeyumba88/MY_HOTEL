<?php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../classes/AuditLog.php";

// Only allow authenticated admin users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (!isset($_GET['log_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Log ID is required']);
    exit;
}

$log_id = intval($_GET['log_id']);
$auditLog = new AuditLog($pdo);

$log = $auditLog->getAuditLogById($log_id);

if (!$log) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Log not found']);
    exit;
}

// Parse JSON data
$response = [
    'old_values' => $log['old_values'] ? json_decode($log['old_values'], true) : null,
    'new_values' => $log['new_values'] ? json_decode($log['new_values'], true) : null
];

header('Content-Type: application/json');
echo json_encode($response);
?>