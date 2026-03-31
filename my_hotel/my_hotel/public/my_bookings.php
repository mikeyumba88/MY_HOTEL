<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in or not a guest
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guest') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../classes/Booking.php";
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";

$bookingManager = new Booking($pdo);
$user_id = $_SESSION['user_id'];
$username = $_SESSION['name'] ?? 'Guest';

// Get user bookings
$bookings = $bookingManager->getBookingsByUser($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Hotel Booking System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bookings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }

        .page-title {
            font-size: 2em;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .bookings-table th, .bookings-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .bookings-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .bookings-table tr:hover {
            background: #f1f3f5;
        }

        .status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            display: inline-block;
        }

        .status.pending { background: #fff3cd; color: #856404; }
        .status.confirmed { background: #d4edda; color: #155724; }
        .status.cancelled { background: #f8d7da; color: #721c24; }

        .no-bookings {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .btn-back {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .btn-back:hover {
            background: #5a6fd8;
        }
    </style>
</head>
<body>
    <div class="bookings-container">
        <div class="page-header">
            <h1 class="page-title">My Bookings</h1>
            <p>Hello, <?php echo htmlspecialchars($username); ?>. Here are your reservations.</p>
            <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if (!empty($bookings)): ?>
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Room Number</th>
                        <th>Type</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Status</th>
                        <th>Booked On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($booking['type'])); ?></td>
                            <td><?php echo htmlspecialchars($booking['check_in']); ?></td>
                            <td><?php echo htmlspecialchars($booking['check_out']); ?></td>
                            <td>
                                <span class="status <?php echo strtolower($booking['status']); ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($booking['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-bookings">
                <i class="fas fa-calendar-times" style="font-size: 3em; margin-bottom: 20px;"></i>
                <h3>No bookings found</h3>
                <p>You haven’t booked any rooms yet.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
