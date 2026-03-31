<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../classes/Booking.php";
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";

if (isset($_POST['room_id'])) {
    $booking = new Booking($pdo);
    $price = $booking->getRoomPrice($_POST['room_id']);

    echo json_encode(['success' => true, 'price' => $price]);
} else {
    echo json_encode(['success' => false, 'message' => 'Room ID missing']);
}
?>
