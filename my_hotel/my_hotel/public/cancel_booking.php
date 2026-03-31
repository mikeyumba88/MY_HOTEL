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
require_once __DIR__ . "/../classes/Booking.php";
require_once __DIR__ . "/../helpers/currency.php";
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";
$bookingManager = new Booking($pdo);
$user_id = $_SESSION['user_id'];

// Get booking ID from URL
$booking_id = $_GET['id'] ?? null;

if (!$booking_id) {
    header("Location: guest_dashboard.php");
    exit;
}

// Get booking details
$booking = $bookingManager->getBookingById($booking_id);

// Check if booking exists and belongs to the current user
if (!$booking || $booking['guest_id'] != $user_id) {
    header("Location: guest_dashboard.php");
    exit;
}

$error = '';
$success = '';

// Handle cancellation confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_cancel'])) {
        // Cancel the booking
        if ($bookingManager->cancelBooking($booking_id)) {
            $success = "Booking cancelled successfully!";
            // Refresh booking data
            $booking = $bookingManager->getBookingById($booking_id);
        } else {
            $error = "Failed to cancel booking. Please try again.";
        }
    } elseif (isset($_POST['go_back'])) {
        header("Location: guest_dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Booking - Hotel Booking System</title>
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

        .cancel-container {
            max-width: 800px;
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

        .cancel-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        .warning-icon {
            font-size: 4em;
            color: #dc3545;
            margin-bottom: 20px;
        }

        .booking-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
        }

        .detail-value {
            color: #333;
        }

        .cancellation-policy {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }

        .policy-title {
            color: #856404;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 5px;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .cancelled-banner {
            background: #dc3545;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="cancel-container">
        <!-- Header Section -->
        <div class="page-header">
            <h1 class="page-title">
                <?php echo $booking['status'] == 'cancelled' ? 'Booking Cancelled' : 'Cancel Booking'; ?>
            </h1>
            <p class="page-subtitle">
                <?php echo $booking['status'] == 'cancelled' ? 'This booking has been cancelled' : 'Confirm booking cancellation'; ?>
            </p>
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

        <div class="cancel-section">
            <?php if ($booking['status'] == 'cancelled'): ?>
                <!-- Already Cancelled -->
                <div class="cancelled-banner">
                    <i class="fas fa-ban" style="font-size: 3em; margin-bottom: 15px;"></i>
                    <h2>This booking has already been cancelled</h2>
                    <p>Booking #<?php echo htmlspecialchars($booking['id']); ?> was cancelled on 
                       <?php echo date('F j, Y', strtotime($booking['updated_at'] ?? $booking['created_at'])); ?></p>
                </div>
                
                <a href="guest_dashboard.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Return to Dashboard
                </a>

            <?php else: ?>
                <!-- Cancellation Confirmation -->
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                
                <h2 style="color: #dc3545; margin-bottom: 20px;">Are you sure you want to cancel this booking?</h2>
                <p style="font-size: 1.1em; margin-bottom: 25px;">
                    This action cannot be undone. Please review the booking details below before proceeding.
                </p>

                <!-- Booking Details -->
                <div class="booking-details">
                    <h3 style="margin-bottom: 15px; color: #333;">Booking Details</h3>
                    <div class="detail-item">
                        <span class="detail-label">Booking ID:</span>
                        <span class="detail-value">#<?php echo htmlspecialchars($booking['id']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Room:</span>
                        <span class="detail-value">Room <?php echo htmlspecialchars($booking['room_number']); ?> (<?php echo ucfirst(htmlspecialchars($booking['room_type'])); ?>)</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Check-in:</span>
                        <span class="detail-value"><?php echo date('F j, Y', strtotime($booking['check_in'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Check-out:</span>
                        <span class="detail-value"><?php echo date('F j, Y', strtotime($booking['check_out'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Nights:</span>
                        <span class="detail-value"><?php echo (strtotime($booking['check_out']) - strtotime($booking['check_in'])) / (60 * 60 * 24); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value" style="color: 
                            <?php echo $booking['status'] == 'confirmed' ? '#28a745' : '#ffc107'; ?>">
                            <?php echo ucfirst(htmlspecialchars($booking['status'])); ?>
                        </span>
                    </div>
                </div>

                <!-- Cancellation Policy -->
                <div class="cancellation-policy">
                    <div class="policy-title"><i class="fas fa-info-circle"></i> Cancellation Policy</div>
                    <ul style="color: #856404; padding-left: 20px;">
                        <li>Cancellations made 48 hours before check-in receive full refund</li>
                        <li>Cancellations made within 48 hours may be subject to fees</li>
                        <li>No-shows will be charged the first night's stay</li>
                        <li>Refunds will be processed within 5-7 business days</li>
                    </ul>
                </div>

                <!-- Confirmation Buttons -->
                <form method="POST">
                    <button type="submit" name="confirm_cancel" class="btn-danger" 
                            onclick="return confirm('Are you absolutely sure you want to cancel this booking? This action cannot be undone.')">
                        <i class="fas fa-times"></i> Yes, Cancel Booking
                    </button>
                    <button type="submit" name="go_back" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> No, Go Back
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>