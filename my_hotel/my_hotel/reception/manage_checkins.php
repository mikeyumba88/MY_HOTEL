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
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";

$bookingManager = new Booking($pdo);
$roomManager = new Room($pdo);

// Get today's check-ins
$today = date('Y-m-d');
$todaysCheckins = $bookingManager->getCheckinsByDate($today);

// Fallback if method doesn't exist
if ($todaysCheckins === null) {
    $allBookings = $bookingManager->getAllBookings();
    $todaysCheckins = array_filter($allBookings, function($booking) use ($today) {
        return $booking['check_in'] == $today && $booking['status'] == 'confirmed';
    });
}

// Handle check-in action
if (isset($_POST['check_in_booking'])) {
    $bookingId = $_POST['booking_id'];
    $roomId = $_POST['room_id'];
    
    try {
        $result = $bookingManager->checkInBooking($bookingId, $roomId, $_SESSION['user_id']);
        if ($result) {
            $_SESSION['success_message'] = "Guest checked in successfully!";
            header("Location: manage_checkins.php");
            exit;
        }
    } catch (Exception $e) {
        $error = "Error during check-in: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Check-ins - Hotel System</title>
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .back-btn {
            color: white;
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
            font-size: 1.1em;
        }

        .welcome-text {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .content-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .section-title {
            color: #333;
            margin-bottom: 25px;
            font-size: 1.8em;
            border-bottom: 3px solid #17a2b8;
            padding-bottom: 15px;
        }

        .checkins-grid {
            display: grid;
            gap: 20px;
        }

        .checkin-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            border-left: 4px solid #17a2b8;
            transition: all 0.3s ease;
        }

        .checkin-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .checkin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .guest-name {
            font-size: 1.3em;
            color: #333;
            font-weight: 600;
        }

        .room-info {
            color: #17a2b8;
            font-weight: 600;
        }

        .checkin-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .detail-value {
            color: #333;
            font-weight: 500;
        }

        .checkin-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .no-checkins {
            text-align: center;
            padding: 60px;
            color: #666;
        }

        .no-checkins-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        @media (max-width: 768px) {
            .checkin-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .checkin-details {
                grid-template-columns: 1fr;
            }
            
            .checkin-actions {
                justify-content: flex-start;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="reception_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1 class="welcome-text">Manage Check-ins</h1>
            <p>Process guest arrivals for <?php echo date('F j, Y'); ?></p>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Today's Check-ins -->
        <div class="content-section">
            <h2 class="section-title">
                Today's Scheduled Check-ins
                <span style="font-size: 0.8em; color: #666;">
                    (<?php echo count($todaysCheckins); ?> guests)
                </span>
            </h2>

            <?php if (!empty($todaysCheckins)): ?>
                <div class="checkins-grid">
                    <?php foreach ($todaysCheckins as $checkin): ?>
                        <div class="checkin-card">
                            <div class="checkin-header">
                                <div class="guest-name">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($checkin['guest_name']); ?>
                                </div>
                                <div class="room-info">
                                    <i class="fas fa-door-open"></i>
                                    Room <?php echo htmlspecialchars($checkin['room_number']); ?>
                                </div>
                            </div>

                            <div class="checkin-details">
                                <div class="detail-item">
                                    <span class="detail-label">Booking Reference</span>
                                    <span class="detail-value">#<?php echo htmlspecialchars($checkin['id']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Room Type</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($checkin['room_type']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Check-out Date</span>
                                    <span class="detail-value"><?php echo date('M j, Y', strtotime($checkin['check_out'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Nights</span>
                                    <span class="detail-value">
                                        <?php 
                                            $nights = (strtotime($checkin['check_out']) - strtotime($checkin['check_in'])) / (60 * 60 * 24);
                                            echo $nights . ' night' . ($nights > 1 ? 's' : '');
                                        ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Total Amount</span>
                                    <span class="detail-value">
                                        K<?php 
                                            $room = $roomManager->getRoomById($checkin['room_id']);
                                            $total = $room['price'] * $nights;
                                            echo number_format($total, 2);
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <div class="checkin-actions">
                                <a href="view_booking.php?id=<?php echo $checkin['id']; ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                
                                <?php if ($checkin['status'] == 'confirmed'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $checkin['id']; ?>">
                                        <input type="hidden" name="room_id" value="<?php echo $checkin['room_id']; ?>">
                                        <button type="submit" name="check_in_booking" class="btn btn-success">
                                            <i class="fas fa-key"></i> Check In
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="btn" style="background: #6c757d; color: white;">
                                        <i class="fas fa-check"></i> Already Checked In
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-checkins">
                    <div class="no-checkins-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <h3>No Check-ins Scheduled for Today</h3>
                    <p>There are no guests scheduled to check in today.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="content-section">
            <h2 class="section-title">Quick Actions</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <a href="book_for_guest.php" class="btn btn-success" style="justify-content: center;">
                    <i class="fas fa-user-plus"></i> Book for Walk-in Guest
                </a>
                <a href="view_rooms.php" class="btn btn-info" style="justify-content: center;">
                    <i class="fas fa-door-open"></i> View Room Availability
                </a>
                <a href="reception_dashboard.php" class="btn" style="background: #6c757d; color: white; justify-content: center;">
                    <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>