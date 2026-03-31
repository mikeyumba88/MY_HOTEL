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
require_once __DIR__ . "/../classes/Room.php";
require_once __DIR__ . "/../classes/Booking.php";
require_once __DIR__ . "/../classes/User.php";
require_once __DIR__ . "/../helpers/currency.php";
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/audit_integration.php";

// Initialize managers FIRST
$roomManager = new Room($pdo);
$bookingManager = new Booking($pdo);
$userManager = new User($pdo);

// NOW call autoCompletePastBookings after initialization
$bookingManager->autoCompletePastBookings();

// Fallback currency function if helper is not available
if (!function_exists('format_currency')) {
    function format_currency($amount, $currency = 'ZMW') {
        $amount = floatval($amount);
        return 'K' . number_format($amount, 2, '.', ',');
    }
}

// Get statistics with error handling
try {
    $rooms = $roomManager->getRooms();
    $totalRooms = count($rooms);
    $availableRooms = count(array_filter($rooms, function($room) {
        return ($room['is_available'] ?? 1) == 1 && ($room['status'] ?? 'available') === 'available';
    }));
} catch (Exception $e) {
    $totalRooms = 0;
    $availableRooms = 0;
}

try {
    $bookings = $bookingManager->getAllBookings();
    $totalBookings = count($bookings);
    $activeBookings = count(array_filter($bookings, function($booking) {
        return in_array($booking['status'], ['confirmed', 'checked_in']);
    }));
} catch (Exception $e) {
    $totalBookings = 0;
    $activeBookings = 0;
}

try {
    // Check if getAllUsers method exists
    if (method_exists($userManager, 'getAllUsers')) {
        $users = $userManager->getAllUsers();
        $totalUsers = count($users);
        $totalGuests = count(array_filter($users, function($user) {
            return $user['role'] == 'guest';
        }));
    } else {
        // Fallback: direct database query
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as guests FROM users WHERE role = 'guest'");
        $totalGuests = $stmt->fetch(PDO::FETCH_ASSOC)['guests'];
        
        $users = [];
    }
} catch (Exception $e) {
    $totalUsers = 0;
    $totalGuests = 0;
    $users = [];
}

// Calculate revenue (this month) - ONLY CONFIRMED BOOKINGS
$currentMonth = date('Y-m');
$monthlyRevenue = 0;
$confirmedBookingsThisMonth = 0;

try {
    $bookingsThisMonth = array_filter($bookings, function($booking) use ($currentMonth) {
        // Only count confirmed bookings from this month
        return date('Y-m', strtotime($booking['created_at'])) == $currentMonth 
               && in_array($booking['status'], ['confirmed', 'checked_in', 'completed']);
    });

    foreach ($bookingsThisMonth as $booking) {
        $room = $roomManager->getRoomById($booking['room_id']);
        if ($room) {
            $nights = (strtotime($booking['check_out']) - strtotime($booking['check_in'])) / (60 * 60 * 24);
            $nights = max(1, $nights); // Ensure at least 1 night
            $monthlyRevenue += $room['price'] * $nights;
        }
    }
    
    $confirmedBookingsThisMonth = count($bookingsThisMonth);
    
} catch (Exception $e) {
    $monthlyRevenue = 0;
    $confirmedBookingsThisMonth = 0;
}

// Get pending edit and cancellation requests
try {
    // Get edit requests
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'edit_requests'");
        $editTableExists = $stmt->rowCount() > 0;
        
        if ($editTableExists) {
            $stmt = $pdo->query("
                SELECT er.*, b.room_id, r.room_number, u.name as guest_name, b.check_in, b.check_out 
                FROM edit_requests er 
                JOIN bookings b ON er.booking_id = b.id 
                JOIN rooms r ON b.room_id = r.id 
                JOIN users u ON er.user_id = u.id 
                WHERE er.status = 'pending' 
                ORDER BY er.created_at DESC
            ");
            $edit_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $edit_requests = [];
        }
    } catch (Exception $e) {
        error_log("Edit requests query failed: " . $e->getMessage());
        $edit_requests = [];
    }
    
    // Get cancellation requests  
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'cancellation_requests'");
        $cancelTableExists = $stmt->rowCount() > 0;
        
        if ($cancelTableExists) {
            $stmt = $pdo->query("
                SELECT cr.*, b.room_id, r.room_number, u.name as guest_name, b.check_in, b.check_out 
                FROM cancellation_requests cr 
                JOIN bookings b ON cr.booking_id = b.id 
                JOIN rooms r ON b.room_id = r.id 
                JOIN users u ON cr.user_id = u.id 
                WHERE cr.status = 'pending' 
                ORDER BY cr.created_at DESC
            ");
            $cancellation_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $cancellation_requests = [];
        }
    } catch (Exception $e) {
        error_log("Cancellation requests query failed: " . $e->getMessage());
        $cancellation_requests = [];
    }
    
    $total_pending_requests = count($edit_requests) + count($cancellation_requests);
    
} catch (Exception $e) {
    $edit_requests = [];
    $cancellation_requests = [];
    $total_pending_requests = 0;
}

// Recent bookings (last 5)
$recentBookings = array_slice($bookings, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hotel Booking System</title>
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

        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .welcome-text {
            font-size: 2.8em;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .admin-nav {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .nav-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
            position: relative;
        }

        .nav-card:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .nav-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 3.5em;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .stat-number {
            font-size: 2.8em;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 1.1em;
            font-weight: 500;
        }

        .stat-subtext {
            margin-top: 10px;
            font-size: 0.9em;
            color: #888;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.6em;
            margin-bottom: 25px;
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .view-all {
            font-size: 0.9em;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .booking-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .booking-item {
            display: flex;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .booking-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .booking-icon {
            width: 50px;
            height: 50px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.2em;
        }

        .booking-info {
            flex: 1;
        }

        .booking-room {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .booking-dates {
            color: #666;
            font-size: 0.9em;
        }

        .booking-status {
            padding: 6px 15px;
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

        .status-checked_in {
            background: #cce7ff;
            color: #004085;
        }

        .status-completed {
            background: #e2e3e5;
            color: #383d41;
        }

        .quick-actions {
            display: grid;
            gap: 15px;
        }

        .quick-action {
            display: flex;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }

        .quick-action:hover {
            background: #667eea;
            color: white;
            transform: translateX(5px);
        }

        .action-icon {
            width: 40px;
            height: 40px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.1em;
        }

        .quick-action:hover .action-icon {
            background: white;
            color: #667eea;
        }

        /* Pending Requests Styles */
        .request-badge {
            background: #dc3545;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .request-item {
            background: #e3f2fd;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .request-item:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .cancellation-request {
            background: #ffeaa7;
            border-left: 4px solid #dc3545;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9em;
            cursor: pointer;
            transition: background 0.3s;
            display: inline-block;
            text-align: center;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .nav-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-text {
                font-size: 2.2em;
            }
            
            .stat-number {
                font-size: 2.2em;
            }
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card, .section {
            animation: fadeInUp 0.6s ease forwards;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1 class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! 👋</h1>
            <p>Hotel Booking System - Administration Panel</p>
            
            <?php if ($total_pending_requests > 0): ?>
                <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 10px; margin-top: 15px;">
                    <i class="fas fa-bell" style="margin-right: 10px;"></i>
                    You have <strong><?php echo $total_pending_requests; ?> pending requests</strong> that need your attention
                </div>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <div class="admin-nav">
            <div class="nav-grid">
                <a href="manage_rooms.php" class="nav-card">
                    <div class="nav-icon"><i class="fas fa-door-open"></i></div>
                    <h3>Manage Rooms</h3>
                    <p>Add, edit, delete rooms</p>
                </a>
                
                <a href="manage_bookings.php" class="nav-card">
                    <div class="nav-icon"><i class="fas fa-calendar-alt"></i></div>
                    <h3>Manage Bookings</h3>
                    <p>View and manage reservations</p>
                </a>
                
                <a href="manage_users.php" class="nav-card">
                    <div class="nav-icon"><i class="fas fa-users"></i></div>
                    <h3>Manage Users</h3>
                    <p>User accounts management</p>
                </a>
                
                <!-- New Manage Requests Card -->
                <a href="manage_requests.php" class="nav-card">
                    <div class="nav-icon"><i class="fas fa-tasks"></i></div>
                    <h3>Manage Requests</h3>
                    <p>Edit & cancellation requests</p>
                    <?php if ($total_pending_requests > 0): ?>
                        <div style="position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; border-radius: 50%; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; font-size: 0.8em;">
                            <?php echo $total_pending_requests; ?>
                        </div>
                    <?php endif; ?>
                </a>
                
                <a href="AuditLog.php" class="nav-card">
                    <div class="nav-icon"><i class="fas fa-chart-bar"></i></div>
                    <h3>Audits Log</h3>
                    <p>Check how customers naviagte through the sysyem</p>
                </a>
                
                <a href="../public/logout.php" class="nav-card">
                    <div class="nav-icon"><i class="fas fa-sign-out-alt"></i></div>
                    <h3>Logout</h3>
                    <p>Exit admin panel</p>
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color: #667eea;">
                    <i class="fas fa-door-open"></i>
                </div>
                <div class="stat-number"><?php echo $totalRooms; ?></div>
                <div class="stat-label">Total Rooms</div>
                <div class="stat-subtext"><?php echo $availableRooms; ?> available</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #28a745;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-number"><?php echo $totalBookings; ?></div>
                <div class="stat-label">Total Bookings</div>
                <div class="stat-subtext"><?php echo $activeBookings; ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #ffc107;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-subtext"><?php echo $totalGuests; ?> guests</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #dc3545;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number"><?php echo format_currency($monthlyRevenue, 'ZMW'); ?></div>
                <div class="stat-label">Monthly Revenue</div>
                <div class="stat-subtext"><?php echo $confirmedBookingsThisMonth; ?> bookings</div>
            </div>
        </div>

        <!-- Pending Requests Section -->
        <?php if ($total_pending_requests > 0): ?>
        <div class="section" style="margin-bottom: 30px;">
            <h2 class="section-title">
                Pending Requests
                <span class="request-badge"><?php echo $total_pending_requests; ?> pending</span>
            </h2>
            
            <!-- Edit Requests -->
            <?php if (!empty($edit_requests)): ?>
            <div style="margin-bottom: 25px;">
                <h3 style="color: #17a2b8; margin-bottom: 15px; font-size: 1.2em;">
                    <i class="fas fa-edit"></i> Edit Requests (<?php echo count($edit_requests); ?>)
                </h3>
                <div class="request-list">
                    <?php foreach ($edit_requests as $request): ?>
                        <div class="request-item">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1;">
                                    <strong>Room <?php echo htmlspecialchars($request['room_number']); ?></strong> - 
                                    <?php echo htmlspecialchars($request['guest_name']); ?>
                                    <br>
                                    <small style="color: #666;">
                                        Requested: <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?> | 
                                        Dates: <?php echo date('M j', strtotime($request['check_in'])); ?> - <?php echo date('M j, Y', strtotime($request['check_out'])); ?>
                                    </small>
                                </div>
                                <div>
                                    <a href="manage_edit_requests.php?id=<?php echo $request['id']; ?>" 
                                       class="btn btn-info">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Cancellation Requests -->
            <?php if (!empty($cancellation_requests)): ?>
            <div>
                <h3 style="color: #dc3545; margin-bottom: 15px; font-size: 1.2em;">
                    <i class="fas fa-times-circle"></i> Cancellation Requests (<?php echo count($cancellation_requests); ?>)
                </h3>
                <div class="request-list">
                    <?php foreach ($cancellation_requests as $request): ?>
                        <div class="request-item cancellation-request">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1;">
                                    <strong>Room <?php echo htmlspecialchars($request['room_number']); ?></strong> - 
                                    <?php echo htmlspecialchars($request['guest_name']); ?>
                                    <br>
                                    <small style="color: #666;">
                                        Charge: <?php echo format_currency($request['cancellation_charge'], 'ZMW'); ?> | 
                                        Requested: <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                                    </small>
                                </div>
                                <div>
                                    <a href="manage_cancellation_requests.php?id=<?php echo $request['id']; ?>" 
                                       class="btn btn-danger">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="manage_requests.php" class="btn" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                    <i class="fas fa-tasks"></i> Manage All Requests
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="content-grid">
            <!-- Recent Bookings -->
            <div class="section">
                <h2 class="section-title">
                    Recent Bookings
                    <a href="manage_bookings.php" class="view-all">View All →</a>
                </h2>
                <div class="booking-list">
                    <?php if (!empty($recentBookings)): ?>
                        <?php foreach ($recentBookings as $booking): 
                            $nights = (strtotime($booking['check_out']) - strtotime($booking['check_in'])) / (60 * 60 * 24);
                            $nights = max(1, $nights);
                        ?>
                            <div class="booking-item">
                                <div class="booking-icon">
                                    <i class="fas fa-calendar-plus"></i>
                                </div>
                                <div class="booking-info">
                                    <div class="booking-room">Room <?php echo htmlspecialchars($booking['room_number'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($booking['guest_name'] ?? 'Guest'); ?></div>
                                    <div class="booking-dates">
                                        <?php echo date('M j, Y', strtotime($booking['check_in'])); ?> - 
                                        <?php echo date('M j, Y', strtotime($booking['check_out'])); ?>
                                        (<?php echo $nights; ?> nights)
                                    </div>
                                </div>
                                <span class="booking-status status-<?php echo htmlspecialchars($booking['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($booking['status'])); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-calendar-times" style="font-size: 3em; margin-bottom: 15px; opacity: 0.5;"></i>
                            <p>No recent bookings</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="section">
                <h2 class="section-title">Quick Actions</h2>
                <div class="quick-actions">
                    <a href="manage_rooms.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">Add New Room</div>
                            <div style="font-size: 0.9em; opacity: 0.8;">Create a new room listing</div>
                        </div>
                    </a>
                    
                    <a href="manage_bookings.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">View Bookings</div>
                            <div style="font-size: 0.9em; opacity: 0.8;">Manage all reservations</div>
                        </div>
                    </a>
                    
                    <a href="manage_users.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">Manage Users</div>
                            <div style="font-size: 0.9em; opacity: 0.8;">Add or edit user accounts</div>
                        </div>
                    </a>
                    
                    <?php if ($total_pending_requests > 0): ?>
                    <a href="manage_requests.php" class="quick-action" style="background: #fff3cd; border-left: 4px solid #ffc107;">
                        <div class="action-icon" style="background: #ffc107;">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">Review Requests</div>
                            <div style="font-size: 0.9em; opacity: 0.8;"><?php echo $total_pending_requests; ?> pending requests</div>
                        </div>
                    </a>
                    <?php else: ?>
                    <a href="manage_requests.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">Manage Requests</div>
                            <div style="font-size: 0.9em; opacity: 0.8;">View all requests</div>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add animations
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            const sections = document.querySelectorAll('.section');
            
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            sections.forEach((section, index) => {
                section.style.animationDelay = `${index * 0.2 + 0.3}s`;
            });
        });
    </script>
</body>
</html>