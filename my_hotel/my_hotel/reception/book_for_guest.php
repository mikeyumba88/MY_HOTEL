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

// Get available rooms
$availableRooms = $roomManager->getAvailableRooms();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $guestData = [
            'guest_name' => trim($_POST['guest_name']),
            'guest_phone' => trim($_POST['guest_phone']),
            'room_id' => (int)$_POST['room_id'],
            'check_in' => $_POST['check_in'],
            'check_out' => $_POST['check_out'],
            'payment_method' => $_POST['payment_method'] ?? 'cash',
            'receptionist_id' => $_SESSION['user_id']
        ];

        // Validate required fields
        if (empty($guestData['guest_name']) || empty($guestData['guest_phone']) || empty($guestData['room_id'])) {
            throw new Exception("Please fill in all required fields.");
        }

        // Create booking for walk-in guest
        $bookingId = $bookingManager->createWalkinBooking($guestData);
        
        if ($bookingId) {
            $_SESSION['success_message'] = "Booking #" . $bookingId . " created successfully for " . $guestData['guest_name'];
            header("Location: reception_dashboard.php");
            exit;
        } else {
            $error = "Failed to create booking. Please try again.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Booking Form Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book for Guest - Hotel System</title>
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
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
        }

        .back-btn {
            color: white;
            text-decoration: none;
            margin-bottom: 15px;
            display: inline-block;
            font-size: 1em;
        }

        .back-btn:hover {
            text-decoration: underline;
        }

        .welcome-text {
            font-size: 2em;
            margin-bottom: 5px;
            font-weight: 300;
        }

        .booking-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
        }

        .form-title {
            color: #333;
            margin-bottom: 25px;
            font-size: 1.5em;
            border-bottom: 2px solid #28a745;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #28a745;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
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

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .required {
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }
            
            .welcome-text {
                font-size: 1.6em;
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
            <h1 class="welcome-text">Quick Guest Booking</h1>
            <p>Create booking for walk-in guests</p>
        </div>

        <!-- Booking Form -->
        <div class="booking-form">
            <h2 class="form-title">Guest & Booking Details</h2>
            
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Guest Information -->
                <div class="form-group">
                    <label for="guest_name">Guest Name <span class="required">*</span></label>
                    <input type="text" id="guest_name" name="guest_name" required 
                           placeholder="Enter guest's full name">
                </div>

                <div class="form-group">
                    <label for="guest_phone">Phone Number <span class="required">*</span></label>
                    <input type="tel" id="guest_phone" name="guest_phone" required 
                           placeholder="Enter guest's phone number">
                </div>

                <!-- Booking Dates -->
                <div class="form-group">
                    <label for="check_in">Check-in Date <span class="required">*</span></label>
                    <input type="date" id="check_in" name="check_in" required 
                           min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="check_out">Check-out Date <span class="required">*</span></label>
                    <input type="date" id="check_out" name="check_out" required 
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                </div>

                <!-- Room Selection -->
                <div class="form-group">
                    <label for="room_id">Select Room <span class="required">*</span></label>
                    <?php if (!empty($availableRooms)): ?>
                        <select id="room_id" name="room_id" required>
                            <option value="">Choose a room</option>
                            <?php foreach ($availableRooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>">
                                    Room <?php echo $room['room_number']; ?> - 
                                    <?php echo ucfirst($room['room_type']); ?> - 
                                    K<?php echo number_format($room['price'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <div style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 5px;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            No rooms available
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Payment Method -->
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method">
                        <option value="cash" selected>Cash</option>
                        <option value="card">Card</option>
                        <option value="mobile">Mobile Money</option>
                    </select>
                </div>

                <div class="form-actions">
                    <a href="reception_dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" 
                            <?php echo empty($availableRooms) ? 'disabled' : ''; ?>>
                        <i class="fas fa-calendar-plus"></i> Create Booking
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Date validation
        const checkInInput = document.getElementById('check_in');
        const checkOutInput = document.getElementById('check_out');

        checkInInput.addEventListener('change', function() {
            const checkInDate = new Date(this.value);
            const nextDay = new Date(checkInDate);
            nextDay.setDate(nextDay.getDate() + 1);
            
            checkOutInput.min = nextDay.toISOString().split('T')[0];
            
            // If current check-out is before new check-in, update it
            if (checkOutInput.value && new Date(checkOutInput.value) <= checkInDate) {
                checkOutInput.value = nextDay.toISOString().split('T')[0];
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const checkIn = new Date(checkInInput.value);
            const checkOut = new Date(checkOutInput.value);
            
            if (checkOut <= checkIn) {
                e.preventDefault();
                alert('Check-out date must be after check-in date.');
                return false;
            }
        });
    </script>
</body>
</html>