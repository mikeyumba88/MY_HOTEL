<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in or not receptionist
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'receptionist') {
    header("Location: ../public/login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../classes/Booking.php";
require_once __DIR__ . "/../classes/Room.php";
require_once __DIR__ . "/../helpers/currency.php";
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";

$bookingManager = new Booking($pdo);
$roomManager = new Room($pdo);

$message = '';
$message_type = '';

// Get filter status from URL or default to active bookings
$filter_status = $_GET['status'] ?? 'active';

// Get all bookings first
$allBookings = $bookingManager->getAllBookings();

// Filter bookings based on status
if ($filter_status === 'all') {
    $bookings = $allBookings;
} elseif ($filter_status === 'cancelled') {
    $bookings = array_filter($allBookings, function($booking) {
        return $booking['status'] == 'cancelled';
    });
} else {
    // Default: show active bookings (confirmed + pending)
    $bookings = array_filter($allBookings, function($booking) {
        return $booking['status'] != 'cancelled';
    });
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_booking'])) {
        $booking_id = $_POST['booking_id'];
        if ($bookingManager->cancelBooking($booking_id)) {
            $message = "Booking cancelled successfully!";
            $message_type = "success";
            // Refresh to show updated list
            header("Location: manage_bookings.php?status=" . $filter_status);
            exit;
        } else {
            $message = "Failed to cancel booking.";
            $message_type = "error";
        }
    }
}

// Count different booking types for filter tabs
$activeBookingsCount = count(array_filter($allBookings, function($booking) {
    return $booking['status'] != 'cancelled';
}));
$cancelledBookingsCount = count(array_filter($allBookings, function($booking) {
    return $booking['status'] == 'cancelled';
}));
$totalBookingsCount = count($allBookings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Hotel Reception System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .reception-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 2.8em;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            margin-top: 10px;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .filter-tab:hover {
            background: #f8f9fa;
            color: #333;
        }

        .filter-tab.active {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }

        .tab-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            margin-left: 5px;
        }

        .bookings-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.6em;
            margin-bottom: 25px;
            color: #333;
            border-bottom: 3px solid #28a745;
            padding-bottom: 15px;
        }

        .table-container {
            overflow-x: auto;
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .bookings-table th,
        .bookings-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .bookings-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #28a745;
        }

        .bookings-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-checked_in {
            background: #cce7ff;
            color: #004085;
        }

        .status-checked_out {
            background: #e2e3e5;
            color: #383d41;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85em;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85em;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-success:hover {
            background: #218838;
        }

        .no-bookings {
            text-align: center;
            padding: 60px;
            color: #666;
        }

        .no-bookings i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .bookings-table {
                font-size: 0.9em;
            }
            
            .bookings-table th,
            .bookings-table td {
                padding: 10px 8px;
            }
            
            .filter-tabs {
                flex-direction: column;
            }
            
            .filter-tab {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="reception-container">
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">Manage Bookings</h1>
            <p>View and manage all hotel bookings</p>
            <a href="reception_dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Reception Dashboard
            </a>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?status=active" class="filter-tab <?php echo $filter_status === 'active' ? 'active' : ''; ?>">
                Active Bookings
                <span class="tab-badge"><?php echo $activeBookingsCount; ?></span>
            </a>
            <a href="?status=cancelled" class="filter-tab <?php echo $filter_status === 'cancelled' ? 'active' : ''; ?>">
                Cancelled Bookings
                <span class="tab-badge"><?php echo $cancelledBookingsCount; ?></span>
            </a>
            <a href="?status=all" class="filter-tab <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                All Bookings
                <span class="tab-badge"><?php echo $totalBookingsCount; ?></span>
            </a>
        </div>

        <!-- Bookings List -->
        <div class="bookings-section">
            <h2 class="section-title">
                <?php 
                    echo $filter_status === 'active' ? 'Active Bookings' : 
                         ($filter_status === 'cancelled' ? 'Cancelled Bookings' : 'All Bookings');
                ?> 
                (<?php echo count($bookings); ?>)
            </h2>
            
            <?php if (!empty($bookings)): ?>
                <div class="table-container">
                    <table class="bookings-table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Guest</th>
                                <th>Room</th>
                                <th>Dates</th>
                                <th>Nights</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): 
                                $nights = (strtotime($booking['check_out']) - strtotime($booking['check_in'])) / (60 * 60 * 24);
                                $room = $roomManager->getRoomById($booking['room_id']);
                                $total = $room['price'] * $nights;
                            ?>
                                <tr>
                                    <td style="font-weight: 600;">#<?php echo $booking['id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['guest_name']); ?></td>
                                    <td>
                                        Room <?php echo htmlspecialchars($booking['room_number']); ?> 
                                        <br><small style="color: #666;">(<?php echo ucfirst($booking['room_type']); ?>)</small>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($booking['check_in'])); ?> 
                                        <br>to<br>
                                        <?php echo date('M j, Y', strtotime($booking['check_out'])); ?>
                                    </td>
                                    <td><?php echo $nights; ?> nights</td>
                                    <td>
                                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 600; color: #28a745;"><?php echo format_currency($total); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <?php if ($booking['status'] == 'confirmed'): ?>
                                                <a href="manage_checkins.php" class="btn-success">
                                                    <i class="fas fa-key"></i> Check In
                                                </a>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <button type="submit" name="cancel_booking" class="btn-danger" 
                                                            onclick="return confirm('Are you sure you want to cancel this booking?');">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </form>
                                            <?php elseif ($booking['status'] == 'checked_in'): ?>
                                                <a href="manage_checkouts.php" class="btn-success">
                                                    <i class="fas fa-sign-out-alt"></i> Check Out
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #666; font-size: 0.9em;">No actions</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-bookings">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No <?php echo $filter_status; ?> bookings found</h3>
                    <p>
                        <?php if ($filter_status === 'active'): ?>
                            There are no active bookings at the moment.
                        <?php elseif ($filter_status === 'cancelled'): ?>
                            No bookings have been cancelled yet.
                        <?php else: ?>
                            No bookings have been made yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>