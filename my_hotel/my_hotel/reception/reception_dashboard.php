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
require_once __DIR__ . "/../classes/Room.php";
require_once __DIR__ . "/../classes/Booking.php";

$roomManager = new Room($pdo);
$bookingManager = new Booking($pdo);

// Get essential statistics
try {
    $rooms = $roomManager->getRooms();
    $totalRooms = count($rooms);
    $availableRooms = count(array_filter($rooms, function($room) {
        return ($room['is_available'] ?? 1) == 1;
    }));
} catch (Exception $e) {
    $totalRooms = 0;
    $availableRooms = 0;
}

try {
    $today = date('Y-m-d');
    $todaysCheckins = $bookingManager->getCheckinsByDate($today);
    $todaysCheckouts = $bookingManager->getCheckoutsByDate($today);
    
    // Fallback if methods don't exist
    if ($todaysCheckins === null) {
        $bookings = $bookingManager->getAllBookings();
        $todaysCheckins = array_filter($bookings, function($booking) use ($today) {
            return $booking['check_in'] == $today && $booking['status'] == 'confirmed';
        });
    }
    
    if ($todaysCheckouts === null) {
        $bookings = $bookingManager->getAllBookings();
        $todaysCheckouts = array_filter($bookings, function($booking) use ($today) {
            return $booking['check_out'] == $today && $booking['status'] == 'confirmed';
        });
    }
    
} catch (Exception $e) {
    $todaysCheckins = [];
    $todaysCheckouts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception Dashboard - Hotel Booking System</title>
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

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
            position: relative;
        }

        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .welcome-text {
            font-size: 2.2em;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
            color: #28a745;
        }

        .stat-number {
            font-size: 2.2em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 1em;
            font-weight: 500;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            border-left: 4px solid #28a745;
        }

        .action-card:hover {
            background: #28a745;
            color: white;
            transform: translateY(-3px);
        }

        .action-card:hover .action-icon {
            background: white;
            color: #28a745;
        }

        .action-icon {
            width: 60px;
            height: 60px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 1.5em;
            transition: all 0.3s ease;
        }

        .action-title {
            font-size: 1.3em;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .action-desc {
            font-size: 0.9em;
            opacity: 0.8;
        }

        .todays-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #28a745;
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .view-all {
            font-size: 0.9em;
            color: #28a745;
            text-decoration: none;
            font-weight: 500;
        }

        .guest-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .guest-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .guest-item:hover {
            background: #e9ecef;
        }

        .guest-icon {
            width: 40px;
            height: 40px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1em;
        }

        .guest-info {
            flex: 1;
        }

        .guest-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .guest-details {
            color: #666;
            font-size: 0.9em;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #666;
        }

        .empty-icon {
            font-size: 2.5em;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .stats-grid, .action-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-text {
                font-size: 1.8em;
            }
            
            .logout-btn {
                position: static;
                display: inline-block;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <a href="../public/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            <h1 class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
            <p>Hotel Reception Desk - Quick Access Panel</p>
        </div>

        <!-- Key Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-door-open"></i>
                </div>
                <div class="stat-number"><?php echo $availableRooms; ?>/<?php echo $totalRooms; ?></div>
                <div class="stat-label">Available Rooms</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="stat-number"><?php echo count($todaysCheckins); ?></div>
                <div class="stat-label">Today's Check-ins</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div class="stat-number"><?php echo count($todaysCheckouts); ?></div>
                <div class="stat-label">Today's Check-outs</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="action-grid">
            <a href="book_for_guest.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="action-title">Book for Guest</div>
                <div class="action-desc">Create booking for walk-in guests</div>
            </a>
            
            <a href="manage_checkins.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="action-title">Check-in Guests</div>
                <div class="action-desc">Process guest arrivals</div>
            </a>
            
            <a href="manage_checkouts.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div class="action-title">Check-out Guests</div>
                <div class="action-desc">Process guest departures</div>
            </a>
            
            <a href="view_rooms.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="action-title">Room Availability</div>
                <div class="action-desc">View all room status</div>
            </a>
        </div>

        <!-- Today's Check-ins -->
        <div class="todays-section">
            <h2 class="section-title">
                Today's Check-ins
                <a href="manage_checkins.php" class="view-all">Manage All →</a>
            </h2>
            <div class="guest-list">
                <?php if (!empty($todaysCheckins)): ?>
                    <?php foreach ($todaysCheckins as $checkin): ?>
                        <div class="guest-item">
                            <div class="guest-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="guest-info">
                                <div class="guest-name"><?php echo htmlspecialchars($checkin['guest_name'] ?? 'Guest'); ?></div>
                                <div class="guest-details">Room <?php echo htmlspecialchars($checkin['room_number'] ?? 'N/A'); ?> • <?php echo htmlspecialchars($checkin['room_type'] ?? ''); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <p>No check-ins scheduled for today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Today's Check-outs -->
        <div class="todays-section">
            <h2 class="section-title">
                Today's Check-outs
                <a href="manage_checkouts.php" class="view-all">Manage All →</a>
            </h2>
            <div class="guest-list">
                <?php if (!empty($todaysCheckouts)): ?>
                    <?php foreach ($todaysCheckouts as $checkout): ?>
                        <div class="guest-item">
                            <div class="guest-icon">
                                <i class="fas fa-user-times"></i>
                            </div>
                            <div class="guest-info">
                                <div class="guest-name"><?php echo htmlspecialchars($checkout['guest_name'] ?? 'Guest'); ?></div>
                                <div class="guest-details">Room <?php echo htmlspecialchars($checkout['room_number'] ?? 'N/A'); ?> • <?php echo htmlspecialchars($checkout['room_type'] ?? ''); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-door-closed"></i>
                        </div>
                        <p>No check-outs scheduled for today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>