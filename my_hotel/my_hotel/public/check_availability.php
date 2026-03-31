<?php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../classes/Booking.php";
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$room_id = $_POST['room_id'] ?? null;
$check_in = $_POST['check_in'] ?? null;
$check_out = $_POST['check_out'] ?? null;

if (!$room_id || !$check_in || !$check_out) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

try {
    $bookingManager = new Booking($pdo);
    $available = $bookingManager->isRoomAvailable($room_id, $check_in, $check_out);
    
    echo json_encode(['available' => $available]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>