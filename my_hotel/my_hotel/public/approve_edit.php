<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";
require_once __DIR__ . "/../config/db.php"; // adjust path if needed

// Only admins allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$booking_id = $_GET['id'] ?? null;
if (!$booking_id) {
    $_SESSION['admin_error'] = "Invalid booking id.";
    header("Location: manage_booking.php");
    exit;
}

try {
    // Give a single edit allowance
    $stmt = $pdo->prepare("UPDATE bookings SET edit_requested = 0, edit_allowed = 1 WHERE id = :id");
    $stmt->execute([':id' => $booking_id]);

    // Update edit_requests table if present
    if ($pdo->query("SHOW TABLES LIKE 'edit_requests'")->rowCount() > 0) {
        $stmt2 = $pdo->prepare("UPDATE edit_requests SET status = 'approved', updated_at = NOW() WHERE booking_id = :id AND status = 'pending'");
        $stmt2->execute([':id' => $booking_id]);
    }

    $_SESSION['admin_success'] = "Edit request approved. User may edit once.";
} catch (PDOException $e) {
    $_SESSION['admin_error'] = "Error approving request: " . $e->getMessage();
}

header("Location: manage_booking.php");
exit;
