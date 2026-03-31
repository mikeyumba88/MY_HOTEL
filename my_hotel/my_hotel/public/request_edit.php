<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";
require_once __DIR__ . "/../config/db.php"; // adjust path if needed

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$booking_id = $_GET['id'] ?? null;
$message = $_POST['message'] ?? null;

if (!$booking_id) {
    $_SESSION['error_message'] = "Invalid booking id.";
    header("Location: my_booking.php");
    exit;
}

// Ensure the booking belongs to this user
$stmt = $pdo->prepare("SELECT id, guest_id, edit_requested FROM bookings WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking || $booking['guest_id'] != $user_id) {
    $_SESSION['error_message'] = "You are not authorized to request edits for that booking.";
    header("Location: my_booking.php");
    exit;
}

// Prevent duplicate requests
if ((int)$booking['edit_requested'] === 1) {
    $_SESSION['error_message'] = "You have already requested an edit for this booking. Please wait for admin review.";
    header("Location: my_booking.php");
    exit;
}

try {
    // Mark booking as requested
    $stmt = $pdo->prepare("UPDATE bookings SET edit_requested = 1 WHERE id = :id");
    $stmt->execute([':id' => $booking_id]);

    // Optionally insert a row in edit_requests table for admin record (if table exists)
    $stmt2 = $pdo->prepare("INSERT INTO edit_requests (booking_id, user_id, message) VALUES (:bid, :uid, :msg)");
    $stmt2->execute([':bid' => $booking_id, ':uid' => $user_id, ':msg' => $message]);

    $_SESSION['success_message'] = "Edit request sent. Admin will review it.";
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error sending request: " . $e->getMessage();
}

header("Location: my_booking.php");
exit;
