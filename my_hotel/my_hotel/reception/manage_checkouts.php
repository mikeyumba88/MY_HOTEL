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

// Get today's check-outs
$today = date('Y-m-d');
$todaysCheckouts = $bookingManager->getCheckoutsByDate($today);

// Fallback if method doesn't exist
if ($todaysCheckouts === null) {
    $allBookings = $bookingManager->getAllBookings();
    $todaysCheckouts = array_filter($allBookings, function($booking) use ($today) {
        return $booking['check_out'] == $today && in_array($booking['status'], ['checked_in', 'confirmed']);
    });
}

// Handle check-out action
if (isset($_POST['check_out_booking'])) {
    $bookingId = $_POST['booking_id'];
    $roomId = $_POST['room_id'];
    $finalAmount = $_POST['final_amount'];
    $paymentMethod = $_POST['payment_method'];
    
    try {
        $result = $bookingManager->checkOutBooking($bookingId, $roomId, $finalAmount, $paymentMethod, $_SESSION['user_id']);
        if ($result) {
            $_SESSION['success_message'] = "Guest checked out successfully!";
            header("Location: manage_checkouts.php");
            exit;
        }
    } catch (Exception $e) {
        $error = "Error during check-out: " . $e->getMessage();
    }
}

// Handle early check-out
if (isset($_POST['early_check_out'])) {
    $bookingId = $_POST['booking_id'];
    $roomId = $_POST['room_id'];
    
    try {
        $result = $bookingManager->earlyCheckOut($bookingId, $roomId, $_SESSION['user_id']);
        if ($result) {
            $_SESSION['success_message'] = "Early check-out processed successfully!";
            header("Location: manage_checkouts.php");
            exit;
        }
    } catch (Exception $e) {
        $error = "Error during early check-out: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Check-outs - Hotel System</title>
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
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
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
            border-bottom: 3px solid #ffc107;
            padding-bottom: 15px;
        }

        .checkouts-grid {
            display: grid;
            gap: 20px;
        }

        .checkout-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            border-left: 4px solid #ffc107;
            transition: all 0.3s ease;
        }

        .checkout-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .checkout-header {
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
            color: #ffc107;
            font-weight: 600;
        }

        .checkout-details {
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

        .checkout-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
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

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
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

        .no-checkouts {
            text-align: center;
            padding: 60px;
            color: #666;
        }

        .no-checkouts-icon {
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

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .modal-title {
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 5px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .checkout-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .checkout-details {
                grid-template-columns: 1fr;
            }
            
            .checkout-actions {
                justify-content: flex-start;
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
            <h1 class="welcome-text">Manage Check-outs</h1>
            <p>Process guest departures for <?php echo date('F j, Y'); ?></p>
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

        <!-- Today's Check-outs -->
        <div class="content-section">
            <h2 class="section-title">
                Today's Scheduled Check-outs
                <span style="font-size: 0.8em; color: #666;">
                    (<?php echo count($todaysCheckouts); ?> guests)
                </span>
            </h2>

            <?php if (!empty($todaysCheckouts)): ?>
                <div class="checkouts-grid">
                    <?php foreach ($todaysCheckouts as $checkout): ?>
                        <div class="checkout-card">
                            <div class="checkout-header">
                                <div class="guest-name">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($checkout['guest_name']); ?>
                                </div>
                                <div class="room-info">
                                    <i class="fas fa-door-open"></i>
                                    Room <?php echo htmlspecialchars($checkout['room_number']); ?>
                                </div>
                            </div>

                            <div class="checkout-details">
                                <div class="detail-item">
                                    <span class="detail-label">Booking Reference</span>
                                    <span class="detail-value">#<?php echo htmlspecialchars($checkout['id']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Room Type</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($checkout['room_type']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Check-in Date</span>
                                    <span class="detail-value"><?php echo date('M j, Y', strtotime($checkout['check_in'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Nights Stayed</span>
                                    <span class="detail-value">
                                        <?php 
                                            $nights = (strtotime($checkout['check_out']) - strtotime($checkout['check_in'])) / (60 * 60 * 24);
                                            echo $nights . ' night' . ($nights > 1 ? 's' : '');
                                        ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Total Amount Due</span>
                                    <span class="detail-value">
                                        K<?php 
                                            $room = $roomManager->getRoomById($checkout['room_id']);
                                            $total = $room['price'] * $nights;
                                            echo number_format($total, 2);
                                        ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Status</span>
                                    <span class="detail-value">
                                        <span style="color: <?php echo $checkout['status'] == 'checked_in' ? '#28a745' : '#6c757d'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $checkout['status'])); ?>
                                        </span>
                                    </span>
                                </div>
                            </div>

                            <div class="checkout-actions">
                                <a href="view_booking.php?id=<?php echo $checkout['id']; ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                
                                <?php if ($checkout['status'] == 'checked_in'): ?>
                                    <button type="button" class="btn btn-warning" onclick="openCheckoutModal(<?php echo $checkout['id']; ?>, <?php echo $checkout['room_id']; ?>, <?php echo $total; ?>)">
                                        <i class="fas fa-sign-out-alt"></i> Check Out
                                    </button>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $checkout['id']; ?>">
                                        <input type="hidden" name="room_id" value="<?php echo $checkout['room_id']; ?>">
                                        <button type="submit" name="early_check_out" class="btn btn-success">
                                            <i class="fas fa-running"></i> Early Check-out
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="btn" style="background: #6c757d; color: white;">
                                        <i class="fas fa-check"></i> Already Checked Out
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-checkouts">
                    <div class="no-checkouts-icon">
                        <i class="fas fa-door-closed"></i>
                    </div>
                    <h3>No Check-outs Scheduled for Today</h3>
                    <p>There are no guests scheduled to check out today.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="content-section">
            <h2 class="section-title">Quick Actions</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <a href="manage_checkins.php" class="btn btn-info" style="justify-content: center;">
                    <i class="fas fa-sign-in-alt"></i> Manage Check-ins
                </a>
                <a href="view_rooms.php" class="btn btn-warning" style="justify-content: center;">
                    <i class="fas fa-bed"></i> View Occupied Rooms
                </a>
                <a href="reception_dashboard.php" class="btn" style="background: #6c757d; color: white; justify-content: center;">
                    <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Check-out Modal -->
    <div id="checkoutModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Process Check-out</h3>
            <form id="checkoutForm" method="POST">
                <input type="hidden" name="booking_id" id="modal_booking_id">
                <input type="hidden" name="room_id" id="modal_room_id">
                
                <div class="form-group">
                    <label for="final_amount">Final Amount (K)</label>
                    <input type="number" id="final_amount" name="final_amount" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="mobile">Mobile Money</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="closeCheckoutModal()">Cancel</button>
                    <button type="submit" name="check_out_booking" class="btn btn-warning">Confirm Check-out</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCheckoutModal(bookingId, roomId, totalAmount) {
            document.getElementById('modal_booking_id').value = bookingId;
            document.getElementById('modal_room_id').value = roomId;
            document.getElementById('final_amount').value = totalAmount.toFixed(2);
            document.getElementById('checkoutModal').style.display = 'block';
        }

        function closeCheckoutModal() {
            document.getElementById('checkoutModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('checkoutModal');
            if (event.target == modal) {
                closeCheckoutModal();
            }
        }
    </script>
</body>
</html>