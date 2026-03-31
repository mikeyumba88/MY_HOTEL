<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in as guest
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guest') {
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

// Get available rooms
$rooms = $roomManager->getAvailableRooms();

$error = '';
$success = '';

// Handle booking form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_room'])) {
    $room_id = $_POST['room_id'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $guest_name = $_POST['guest_name'];
    
    // Validate dates
    if (strtotime($check_in) >= strtotime($check_out)) {
        $error = "Check-out date must be after check-in date.";
    } else {
        // Check room availability using Booking class
        if ($bookingManager->isRoomAvailable($room_id, $check_in, $check_out)) {
            // Create booking
            if ($bookingManager->createBooking($room_id, $_SESSION['user_id'], $check_in, $check_out, $guest_name)) {
                $success = "Room booked successfully!";
                // Refresh available rooms
                $rooms = $roomManager->getAvailableRooms();
            } else {
                $error = "Failed to book room. Please try again.";
            }
        } else {
            $error = "Sorry, this room is no longer available for the selected dates.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Room - Hotel Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Your existing CSS styles */
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

        .booking-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }

        .page-title {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
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

        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .room-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 25px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
        }

        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .room-card.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .room-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .room-number {
            font-size: 1.4em;
            font-weight: 700;
            color: #2c3e50;
        }

        .room-type {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .room-price {
            font-size: 1.3em;
            font-weight: 700;
            color: #667eea;
        }

        .room-features {
            margin: 15px 0;
            color: #666;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
            font-size: 0.9em;
        }

        .booking-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            display: none;
        }

        .booking-form.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-title {
            font-size: 1.6em;
            margin-bottom: 25px;
            color: #2c3e50;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .price-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }

        .price-row:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }

        .price-label {
            color: #666;
        }

        .price-value {
            font-weight: 600;
            color: #333;
        }

        .total-price {
            font-size: 1.3em;
            font-weight: 700;
            color: #667eea;
        }

        .btn-primary {
            background: #28a745;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .no-rooms {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-rooms i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .rooms-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
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
    <div class="booking-container">
        <!-- Header Section -->
        <div class="page-header">
            <h1 class="page-title">Book a Room</h1>
            <p>Choose from our available rooms and book your stay</p>
            <a href="guest_dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Messages -->
        <?php if (!empty($error)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Available Rooms -->
        <div class="rooms-section">
            <h2 style="font-size: 1.8em; margin-bottom: 20px; color: #2c3e50;">Available Rooms</h2>
            
            <?php if (!empty($rooms)): ?>
                <div class="rooms-grid">
                    <?php foreach ($rooms as $room): ?>
                        <div class="room-card" data-room-id="<?php echo $room['id']; ?>" 
                             data-room-price="<?php echo $room['price']; ?>"
                             onclick="selectRoom(this, <?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['room_number']); ?>', '<?php echo htmlspecialchars($room['room_type']); ?>', <?php echo $room['price']; ?>)">
                            <div class="room-header">
                                <div>
                                    <div class="room-number">Room <?php echo htmlspecialchars($room['room_number']); ?></div>
                                    <div class="room-type"><?php echo ucfirst(htmlspecialchars($room['room_type'])); ?></div>
                                </div>
                                <div class="room-price"><?php echo format_currency($room['price'], 'ZMW'); ?>/night</div>
                            </div>
                            
                            <div class="room-features">
                                <div class="feature">
                                    <i class="fas fa-user" style="color: #667eea;"></i>
                                    <span>Max Guests: <?php echo $room['max_guests'] ?? 2; ?></span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-bed" style="color: #667eea;"></i>
                                    <span>Beds: <?php echo $room['beds'] ?? '1 Double'; ?></span>
                                </div>
                                <?php if (!empty($room['amenities'])): ?>
                                    <div class="feature">
                                        <i class="fas fa-wifi" style="color: #667eea;"></i>
                                        <span><?php echo htmlspecialchars($room['amenities']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="color: #28a745; font-weight: 600; font-size: 0.9em;">
                                <i class="fas fa-check-circle"></i> Available
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Booking Form -->
                <div id="bookingForm" class="booking-form">
                    <h3 class="form-title">Complete Your Booking</h3>
                    
                    <form method="POST" id="bookingFormElement">
                        <input type="hidden" name="room_id" id="selectedRoomId">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="guest_name">Your Name</label>
                                <input type="text" class="form-control" id="guest_name" name="guest_name" 
                                       value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Selected Room</label>
                                <div style="padding: 12px 15px; background: #f8f9fa; border-radius: 10px; font-weight: 600;">
                                    <span id="selectedRoomInfo">Please select a room</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="check_in">Check-in Date</label>
                                <input type="date" class="form-control" id="check_in" name="check_in" 
                                       min="<?php echo date('Y-m-d'); ?>" required onchange="calculatePrice()">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="check_out">Check-out Date</label>
                                <input type="date" class="form-control" id="check_out" name="check_out" 
                                       min="<?php echo date('Y-m-d'); ?>" required onchange="calculatePrice()">
                            </div>
                        </div>

                        <!-- Price Summary -->
                        <div id="priceSummary" class="price-summary" style="display: none;">
                            <h4 style="margin-bottom: 15px; color: #2c3e50;">Price Summary</h4>
                            <div class="price-row">
                                <span class="price-label" id="pricePerNightLabel">Price per night:</span>
                                <span class="price-value" id="pricePerNightValue">-</span>
                            </div>
                            <div class="price-row">
                                <span class="price-label" id="nightsLabel">Number of nights:</span>
                                <span class="price-value" id="nightsValue">-</span>
                            </div>
                            <div class="price-row" style="border-top: 2px solid #dee2e6; padding-top: 12px;">
                                <span class="price-label total-price">Total Amount:</span>
                                <span class="price-value total-price" id="totalPriceValue">-</span>
                            </div>
                        </div>
                        
                        <button type="submit" name="book_room" class="btn-primary" id="bookButton" disabled>
                            <i class="fas fa-calendar-check"></i> Book Now
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="no-rooms">
                    <i class="fas fa-bed"></i>
                    <h3>No Rooms Available</h3>
                    <p>Sorry, there are no available rooms at the moment. Please check back later.</p>
                    <a href="view_rooms.php" class="btn" style="margin-top: 15px;">
                        <i class="fas fa-search"></i> View All Rooms
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let selectedRoomPrice = 0;
        let selectedRoomId = null;

        function selectRoom(card, roomId, roomNumber, roomType, price) {
            // Remove selected class from all cards
            document.querySelectorAll('.room-card').forEach(c => {
                c.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            card.classList.add('selected');
            
            // Store selected room data
            selectedRoomId = roomId;
            selectedRoomPrice = price;
            
            // Update booking form
            document.getElementById('selectedRoomId').value = roomId;
            document.getElementById('selectedRoomInfo').textContent = 
                `Room ${roomNumber} - ${roomType} (K${price.toFixed(2)}/night)`;
            
            // Show booking form
            document.getElementById('bookingForm').classList.add('active');
            
            // Enable book button if dates are selected
            checkBookingReady();
            
            // Recalculate price if dates are already selected
            calculatePrice();
        }

        function calculatePrice() {
            const checkIn = document.getElementById('check_in').value;
            const checkOut = document.getElementById('check_out').value;
            const priceSummary = document.getElementById('priceSummary');
            
            if (checkIn && checkOut && selectedRoomPrice > 0) {
                const nights = calculateNights(checkIn, checkOut);
                
                if (nights > 0) {
                    const totalPrice = selectedRoomPrice * nights;
                    
                    // Update price display
                    document.getElementById('pricePerNightValue').textContent = 'K' + selectedRoomPrice.toFixed(2);
                    document.getElementById('nightsValue').textContent = nights;
                    document.getElementById('totalPriceValue').textContent = 'K' + totalPrice.toFixed(2);
                    
                    // Show price summary
                    priceSummary.style.display = 'block';
                    
                    // Enable book button
                    checkBookingReady();
                } else {
                    priceSummary.style.display = 'none';
                    document.getElementById('bookButton').disabled = true;
                }
            } else {
                priceSummary.style.display = 'none';
                document.getElementById('bookButton').disabled = true;
            }
        }

        function calculateNights(checkIn, checkOut) {
            const oneDay = 24 * 60 * 60 * 1000;
            const firstDate = new Date(checkIn);
            const secondDate = new Date(checkOut);
            
            const diffDays = Math.round(Math.abs((firstDate - secondDate) / oneDay));
            return diffDays;
        }

        function checkBookingReady() {
            const checkIn = document.getElementById('check_in').value;
            const checkOut = document.getElementById('check_out').value;
            const guestName = document.getElementById('guest_name').value;
            
            if (selectedRoomId && checkIn && checkOut && guestName && calculateNights(checkIn, checkOut) > 0) {
                document.getElementById('bookButton').disabled = false;
            } else {
                document.getElementById('bookButton').disabled = true;
            }
        }

        // Date validation
        document.addEventListener('DOMContentLoaded', function() {
            const checkInInput = document.getElementById('check_in');
            const checkOutInput = document.getElementById('check_out');
            const guestNameInput = document.getElementById('guest_name');
            
            // Set up date validation
            if (checkInInput && checkOutInput) {
                checkInInput.addEventListener('change', function() {
                    if (checkOutInput.value && this.value > checkOutInput.value) {
                        checkOutInput.value = '';
                        alert('Check-in date cannot be after check-out date.');
                    }
                    checkOutInput.min = this.value;
                    calculatePrice();
                });
                
                checkOutInput.addEventListener('change', function() {
                    const checkIn = document.getElementById('check_in');
                    if (checkIn.value && this.value < checkIn.value) {
                        this.value = '';
                        alert('Check-out date cannot be before check-in date.');
                    }
                    calculatePrice();
                });
            }
            
            // Enable book button when guest name is filled
            if (guestNameInput) {
                guestNameInput.addEventListener('input', checkBookingReady);
            }
        });
    </script>
</body>
</html>