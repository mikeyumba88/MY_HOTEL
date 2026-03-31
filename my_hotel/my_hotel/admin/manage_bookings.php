<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../public/login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../classes/Booking.php";
require_once __DIR__ . "/../classes/Room.php";
require_once __DIR__ . "/../helpers/currency.php";

$bookingManager = new Booking($pdo);
$roomManager = new Room($pdo);

$message = '';
$message_type = '';

// Auto-complete past bookings first
$bookingManager->autoCompletePastBookings();

// Get filter status from URL or default to current
$filter_status = $_GET['status'] ?? 'current';

// Get all active bookings (excludes cancelled and deleted rooms)
$allBookings = $bookingManager->getActiveBookings();

// Filter bookings based on status
if ($filter_status === 'all') {
    $bookings = $allBookings;
} elseif ($filter_status === 'completed') {
    $bookings = array_filter($allBookings, function($booking) {
        return $booking['status'] == 'completed';
    });
} elseif ($filter_status === 'cancelled') {
    $bookings = array_filter($allBookings, function($booking) {
        return $booking['status'] == 'cancelled';
    });
} else {
    // Default: show current bookings (confirmed + checked_in)
    $bookings = array_filter($allBookings, function($booking) {
        return in_array($booking['status'], ['confirmed', 'checked_in']);
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
    elseif (isset($_POST['check_in'])) {
        $booking_id = $_POST['booking_id'];
        $room_id = $_POST['room_id'];
        if ($bookingManager->checkInBooking($booking_id, $room_id, $_SESSION['user_id'])) {
            $message = "Guest checked in successfully!";
            $message_type = "success";
            header("Location: manage_bookings.php?status=" . $filter_status);
            exit;
        } else {
            $message = "Failed to check in guest.";
            $message_type = "error";
        }
    }
    elseif (isset($_POST['check_out'])) {
        $booking_id = $_POST['booking_id'];
        $room_id = $_POST['room_id'];
        $final_amount = $_POST['final_amount'];
        if ($bookingManager->checkOutBooking($booking_id, $room_id, $final_amount, 'cash', $_SESSION['user_id'])) {
            $message = "Guest checked out successfully!";
            $message_type = "success";
            header("Location: manage_bookings.php?status=" . $filter_status);
            exit;
        } else {
            $message = "Failed to check out guest.";
            $message_type = "error";
        }
    }
}

// Count different booking types for filter tabs
$currentBookingsCount = count(array_filter($allBookings, function($booking) {
    return in_array($booking['status'], ['confirmed', 'checked_in']);
}));
$completedBookingsCount = count(array_filter($allBookings, function($booking) {
    return $booking['status'] == 'completed';
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
    <title>Manage Bookings - Hotel Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: #333; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .header {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .title { font-size: 2em; margin-bottom: 10px; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: #4CAF50;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn:hover { background: #4CAF50; color: white; transform: translateY(-2px); }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Tabs */
        .tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 10px 20px;
            border-radius: 20px;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .tab:hover { background: #f8f9fa; color: #333; }
        .tab.active { background: #4CAF50; color: white; }
        
        .badge {
            background: rgba(255,255,255,0.3);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
            margin-left: 5px;
        }
        
        /* Bookings Section */
        .bookings {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .section-title { font-size: 1.5em; margin-bottom: 20px; color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        
        .booking-cards {
            display: grid;
            gap: 20px;
        }
        
        .booking-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .booking-card:hover {
            border-color: #4CAF50;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .booking-id { font-size: 1.2em; font-weight: bold; color: #333; }
        .booking-date { color: #666; font-size: 0.9em; }
        
        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-group label { font-weight: 600; color: #666; font-size: 0.9em; }
        .detail-group div { font-weight: 500; }
        
        .status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
            color: white;
        }
        
        .confirmed { background: #28a745; }
        .checked_in { background: #17a2b8; }
        .completed { background: #6c757d; }
        .cancelled { background: #dc3545; }
        
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
        }
        
        .btn-checkin { background: #17a2b8; color: white; }
        .btn-checkout { background: #28a745; color: white; }
        .btn-cancel { background: #dc3545; color: white; }
        
        .btn-action:hover { transform: translateY(-1px); }
        
        .no-bookings {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-bookings i { font-size: 3em; margin-bottom: 15px; opacity: 0.5; }
        
        @media (max-width: 768px) {
            .booking-details { grid-template-columns: 1fr; }
            .booking-header { flex-direction: column; gap: 10px; }
            .actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="title">Manage Bookings</h1>
            <p>View and manage all hotel reservations</p>
            <a href="admin_dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?status=current" class="tab <?php echo $filter_status === 'current' ? 'active' : ''; ?>">
                Current Bookings
                <span class="badge"><?php echo $currentBookingsCount; ?></span>
            </a>
            <a href="?status=completed" class="tab <?php echo $filter_status === 'completed' ? 'active' : ''; ?>">
                Completed
                <span class="badge"><?php echo $completedBookingsCount; ?></span>
            </a>
            <a href="?status=cancelled" class="tab <?php echo $filter_status === 'cancelled' ? 'active' : ''; ?>">
                Cancelled
                <span class="badge"><?php echo $cancelledBookingsCount; ?></span>
            </a>
            <a href="?status=all" class="tab <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                All Bookings
                <span class="badge"><?php echo $totalBookingsCount; ?></span>
            </a>
        </div>

        <!-- Bookings List -->
        <div class="bookings">
            <h2 class="section-title">
                <?php 
                    echo $filter_status === 'current' ? 'Current Bookings' : 
                         ($filter_status === 'completed' ? 'Completed Bookings' : 
                         ($filter_status === 'cancelled' ? 'Cancelled Bookings' : 'All Bookings'));
                ?> 
                (<?php echo count($bookings); ?>)
            </h2>
            
            <?php if (!empty($bookings)): ?>
                <div class="booking-cards">
                    <?php foreach ($bookings as $booking): 
                        $nights = (strtotime($booking['check_out']) - strtotime($booking['check_in'])) / (60 * 60 * 24);
                        $nights = max(1, $nights);
                        $total = $booking['price'] * $nights;
                    ?>
                        <div class="booking-card">
                            <div class="booking-header">
                                <div>
                                    <div class="booking-id">Booking #<?php echo $booking['id']; ?></div>
                                    <div class="booking-date">Created: <?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></div>
                                </div>
                                <span class="status <?php echo $booking['status']; ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </div>
                            
                            <div class="booking-details">
                                <div class="detail-group">
                                    <label>Guest</label>
                                    <div><?php echo htmlspecialchars($booking['guest_name']); ?></div>
                                </div>
                                <div class="detail-group">
                                    <label>Room</label>
                                    <div>Room <?php echo htmlspecialchars($booking['room_number']); ?> (<?php echo ucfirst($booking['room_type']); ?>)</div>
                                </div>
                                <div class="detail-group">
                                    <label>Dates</label>
                                    <div>
                                        <?php echo date('M j, Y', strtotime($booking['check_in'])); ?> 
                                        to 
                                        <?php echo date('M j, Y', strtotime($booking['check_out'])); ?>
                                    </div>
                                </div>
                                <div class="detail-group">
                                    <label>Duration</label>
                                    <div><?php echo $nights; ?> nights</div>
                                </div>
                                <div class="detail-group">
                                    <label>Total</label>
                                    <div style="font-weight: bold; color: #4CAF50;"><?php echo format_currency($total); ?></div>
                                </div>
                            </div>
                            
                            <div class="actions">
                                <?php if ($booking['status'] == 'confirmed'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <input type="hidden" name="room_id" value="<?php echo $booking['room_id']; ?>">
                                        <button type="submit" name="check_in" class="btn-action btn-checkin">
                                            <i class="fas fa-sign-in-alt"></i> Check In
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($booking['status'] == 'checked_in'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <input type="hidden" name="room_id" value="<?php echo $booking['room_id']; ?>">
                                        <input type="hidden" name="final_amount" value="<?php echo $total; ?>">
                                        <button type="submit" name="check_out" class="btn-action btn-checkout">
                                            <i class="fas fa-sign-out-alt"></i> Check Out
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if (in_array($booking['status'], ['confirmed', 'checked_in'])): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <button type="submit" name="cancel_booking" class="btn-action btn-cancel" 
                                                onclick="return confirm('Cancel this booking?');">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-bookings">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Bookings Found</h3>
                    <p>
                        <?php if ($filter_status === 'current'): ?>
                            There are no current bookings.
                        <?php elseif ($filter_status === 'completed'): ?>
                            No completed bookings found.
                        <?php elseif ($filter_status === 'cancelled'): ?>
                            No cancelled bookings found.
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