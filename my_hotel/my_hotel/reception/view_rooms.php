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
require_once __DIR__ . "/../helpers/currency.php";
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";

$roomManager = new Room($pdo);
$bookingManager = new Booking($pdo);

// Get all rooms
$rooms = $roomManager->getRooms();

// Get room statistics
$roomStats = [
    'total' => count($rooms),
    'available' => 0,
    'occupied' => 0,
    'maintenance' => 0
];

foreach ($rooms as $room) {
    $status = $room['status'] ?? 'available';
    if (isset($roomStats[$status])) {
        $roomStats[$status]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Availability - Hotel Reception System</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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

        .page-title {
            font-size: 2.5em;
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
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 1.1em;
        }

        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .room-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            border-left: 4px solid #28a745;
        }

        .room-card:hover {
            transform: translateY(-5px);
        }

        .room-card.occupied {
            border-left-color: #dc3545;
        }

        .room-card.maintenance {
            border-left-color: #ffc107;
        }

        .room-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .room-number {
            font-size: 1.4em;
            font-weight: 600;
            color: #333;
        }

        .room-status {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-available {
            background: #d4edda;
            color: #155724;
        }

        .status-occupied {
            background: #f8d7da;
            color: #721c24;
        }

        .status-maintenance {
            background: #fff3cd;
            color: #856404;
        }

        .room-details {
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-label {
            color: #666;
        }

        .detail-value {
            color: #333;
            font-weight: 500;
        }

        .room-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            flex: 1;
        }

        .btn-primary {
            background: #28a745;
            color: white;
        }

        .btn-primary:hover {
            background: #218838;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .no-rooms {
            text-align: center;
            padding: 60px;
            color: #666;
            background: white;
            border-radius: 15px;
        }

        .no-rooms-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .rooms-grid {
                grid-template-columns: 1fr;
            }
            
            .room-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="reception-container">
        <!-- Header -->
        <div class="page-header">
            <a href="reception_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Reception Dashboard
            </a>
            <h1 class="page-title">Room Availability</h1>
            <p>Real-time room status and availability</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $roomStats['total']; ?></div>
                <div class="stat-label">Total Rooms</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #28a745;"><?php echo $roomStats['available']; ?></div>
                <div class="stat-label">Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #dc3545;"><?php echo $roomStats['occupied']; ?></div>
                <div class="stat-label">Occupied</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #ffc107;"><?php echo $roomStats['maintenance']; ?></div>
                <div class="stat-label">Maintenance</div>
            </div>
        </div>

        <!-- Rooms Grid -->
        <div class="rooms-grid">
            <?php if (!empty($rooms)): ?>
                <?php foreach ($rooms as $room): ?>
                    <div class="room-card <?php echo $room['status'] ?? 'available'; ?>">
                        <div class="room-header">
                            <div class="room-number">
                                <i class="fas fa-door-open"></i>
                                Room <?php echo htmlspecialchars($room['room_number']); ?>
                            </div>
                            <span class="room-status status-<?php echo $room['status'] ?? 'available'; ?>">
                                <?php echo ucfirst($room['status'] ?? 'available'); ?>
                            </span>
                        </div>

                        <div class="room-details">
                            <div class="detail-item">
                                <span class="detail-label">Type:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($room['room_type']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Price:</span>
                                <span class="detail-value">K<?php echo number_format($room['price'], 2); ?>/night</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Capacity:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($room['capacity'] ?? '2'); ?> adults</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Features:</span>
                                <span class="detail-value">
                                    <?php 
                                    $features = [];
                                    if ($room['has_wifi'] ?? false) $features[] = 'WiFi';
                                    if ($room['has_tv'] ?? false) $features[] = 'TV';
                                    if ($room['has_ac'] ?? false) $features[] = 'AC';
                                    echo $features ? implode(', ', $features) : 'Standard';
                                    ?>
                                </span>
                            </div>
                        </div>

                        <div class="room-actions">
                            <?php if (($room['status'] ?? 'available') == 'available'): ?>
                                <a href="book_for_guest.php?room_id=<?php echo $room['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i> Book Now
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-ban"></i> Not Available
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-rooms">
                    <div class="no-rooms-icon">
                        <i class="fas fa-door-closed"></i>
                    </div>
                    <h3>No Rooms Found</h3>
                    <p>There are no rooms configured in the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>