<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Require login as guest
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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

// Get ALL rooms and check their real-time availability
$allRooms = $roomManager->getAllRooms();
$availableRooms = [];

foreach ($allRooms as $room) {
    // Check if room is marked as available in the database
    if ($room['status'] === 'available' && $room['is_available'] == 1) {
        $availableRooms[] = $room;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Rooms - Hotel Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .welcome {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .subtitle {
            opacity: 0.9;
            margin-bottom: 20px;
        }

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
        }

        .btn:hover {
            background: #4CAF50;
            color: white;
            transform: translateY(-2px);
        }

        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .room-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid #4CAF50;
        }

        .room-card.occupied {
            border-color: #ff6b6b;
            opacity: 0.7;
        }

        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .room-image {
            height: 150px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3em;
            position: relative;
        }

        .room-image.occupied {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
        }

        .room-content {
            padding: 20px;
        }

        .room-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .room-number {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
        }

        .room-price {
            font-size: 1.3em;
            font-weight: bold;
            color: #4CAF50;
        }

        .room-type {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 500;
            margin-bottom: 10px;
            display: inline-block;
        }

        .room-features {
            margin: 15px 0;
            color: #666;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9em;
        }

        .book-btn {
            display: block;
            width: 100%;
            background: #4CAF50;
            color: white;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }

        .book-btn:hover {
            background: #45a049;
        }

        .book-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .availability-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: bold;
            margin-top: 10px;
            display: inline-block;
        }

        .available {
            background: #4CAF50;
            color: white;
        }

        .occupied-badge {
            background: #ff6b6b;
            color: white;
        }

        .no-rooms {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .no-rooms i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #ccc;
        }

        @media (max-width: 768px) {
            .rooms-grid {
                grid-template-columns: 1fr;
            }
            
            .room-header {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="welcome">Hotel Rooms</h1>
            <p class="subtitle">Choose from our comfortable rooms</p>
            <a href="guest_dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Rooms Grid -->
        <?php if (!empty($allRooms)): ?>
            <div class="rooms-grid">
                <?php foreach ($allRooms as $room): 
                    $isAvailable = ($room['status'] === 'available' && $room['is_available'] == 1);
                ?>
                    <div class="room-card <?php echo $isAvailable ? '' : 'occupied'; ?>">
                        <div class="room-image <?php echo $isAvailable ? '' : 'occupied'; ?>">
                            <i class="fas fa-bed"></i>
                        </div>
                        
                        <div class="room-content">
                            <div class="room-header">
                                <div class="room-number">Room <?php echo htmlspecialchars($room['room_number']); ?></div>
                                <div class="room-price"><?php echo format_currency($room['price'], 'ZMW'); ?>/night</div>
                            </div>
                            
                            <div class="room-type"><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></div>
                            
                            <div class="room-features">
                                <div class="feature">
                                    <i class="fas fa-user"></i>
                                    <span>Max Guests: <?php echo $room['max_guests'] ?? 2; ?></span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-bed"></i>
                                    <span>Beds: <?php echo $room['beds'] ?? '1 Double'; ?></span>
                                </div>
                                <?php if (!empty($room['amenities'])): ?>
                                    <div class="feature">
                                        <i class="fas fa-wifi"></i>
                                        <span><?php echo htmlspecialchars($room['amenities']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="availability-badge <?php echo $isAvailable ? 'available' : 'occupied-badge'; ?>">
                                <i class="fas fa-<?php echo $isAvailable ? 'check-circle' : 'times-circle'; ?>"></i> 
                                <?php echo $isAvailable ? 'Available' : 'Occupied'; ?>
                            </div>
                            
                            <?php if ($isAvailable): ?>
                                <a href="book_room.php?room_id=<?php echo $room['id']; ?>" class="book-btn">
                                    <i class="fas fa-calendar-plus"></i> Book Now
                                </a>
                            <?php else: ?>
                                <button class="book-btn" disabled>
                                    <i class="fas fa-times"></i> Not Available
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-rooms">
                <i class="fas fa-bed"></i>
                <h3>No Rooms Found</h3>
                <p>There are no rooms in the system yet.</p>
                <a href="guest_dashboard.php" class="btn" style="margin-top: 15px; display: inline-block;">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh the page every 30 seconds to update availability
        setTimeout(function() {
            location.reload();
        }, 30000); // 30 seconds

        // Simple hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const roomCards = document.querySelectorAll('.room-card');
            
            roomCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('occupied')) {
                        this.style.transform = 'translateY(-5px)';
                    }
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>