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
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/audit_integration.php";

$bookingManager = new Booking($pdo);
$roomManager = new Room($pdo);

$message = '';
$message_type = '';

// Temporary fallback if methods don't exist
$editRequests = [];
$cancellationRequests = [];

// Check if methods exist and use them, otherwise use empty arrays
if (method_exists($bookingManager, 'getEditRequests')) {
    $editRequests = $bookingManager->getEditRequests();
}

if (method_exists($bookingManager, 'getCancellationRequests')) {
    $cancellationRequests = $bookingManager->getCancellationRequests();
}

// Handle request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_edit'])) {
        $request_id = $_POST['request_id'];
        
        if (method_exists($bookingManager, 'approveEditRequest') && $bookingManager->approveEditRequest($request_id)) {
            $message = "Edit request approved! The guest can now edit their booking.";
            $message_type = "success";
        } else {
            $message = "Failed to approve edit request.";
            $message_type = "error";
        }
    }
    
    if (isset($_POST['decline_edit'])) {
        $request_id = $_POST['request_id'];
        
        if (method_exists($bookingManager, 'declineEditRequest') && $bookingManager->declineEditRequest($request_id)) {
            $message = "Edit request declined.";
            $message_type = "success";
        } else {
            $message = "Failed to decline edit request.";
            $message_type = "error";
        }
    }
    
    if (isset($_POST['approve_cancellation'])) {
        $request_id = $_POST['request_id'];
        $booking_id = $_POST['booking_id'];
        $charge = $_POST['cancellation_charge'];
        
        if (method_exists($bookingManager, 'approveCancellationRequest') && $bookingManager->approveCancellationRequest($request_id, $booking_id, $charge)) {
            $message = "Cancellation request accepted!";
            $message_type = "success";
        } else {
            $message = "Failed to accept cancellation request.";
            $message_type = "error";
        }
    }
    
    if (isset($_POST['decline_cancellation'])) {
        $request_id = $_POST['request_id'];
        
        if (method_exists($bookingManager, 'declineCancellationRequest') && $bookingManager->declineCancellationRequest($request_id)) {
            $message = "Cancellation request declined.";
            $message_type = "success";
        } else {
            $message = "Failed to decline cancellation request.";
            $message_type = "error";
        }
    }
    
    // Refresh requests after action
    header("Location: manage_requests.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - Hotel Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Your existing CSS styles remain the same */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 2.8em;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
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
        
        .message.info {
            background: #cce7ff;
            color: #004085;
            border: 1px solid #b3d7ff;
        }
        
        .requests-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .request-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 30px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .request-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f2f5;
        }
        
        .card-title {
            font-size: 1.8em;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .request-count {
            background: #667eea;
            color: white;
            border-radius: 20px;
            padding: 6px 15px;
            font-size: 1.1em;
            font-weight: 600;
        }
        
        .request-list {
            min-height: 100px;
        }
        
        .request-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .request-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .request-item:last-child {
            margin-bottom: 0;
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .guest-info {
            flex: 1;
        }
        
        .guest-name {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.3em;
            margin-bottom: 5px;
        }
        
        .booking-id {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .request-time {
            color: #7f8c8d;
            font-size: 0.9em;
            text-align: right;
            min-width: 120px;
        }
        
        .request-details {
            color: #555;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .booking-details {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #495057;
        }
        
        .detail-value {
            color: #6c757d;
        }
        
        .cancellation-charge {
            background: #fff3cd;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #ffc107;
            font-weight: 600;
            color: #856404;
        }
        
        .request-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 0.95em;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-approve {
            background: #28a745;
            color: white;
        }
        
        .btn-approve:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-decline {
            background: #dc3545;
            color: white;
        }
        
        .btn-decline:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: #495057;
        }
        
        .empty-state p {
            font-size: 1.1em;
            opacity: 0.8;
        }
        
        .setup-info {
            background: #cce7ff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #007bff;
        }
        
        .setup-info h3 {
            color: #004085;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .requests-container {
                grid-template-columns: 1fr;
            }
            
            .request-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .request-time {
                text-align: left;
            }
            
            .request-actions {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
            }
            
            .page-title {
                font-size: 2.2em;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">Manage Requests</h1>
            <p>Review and process guest edit and cancellation requests</p>
            <a href="admin_dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Admin Dashboard
            </a>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Setup Information -->
        <?php if (!method_exists($bookingManager, 'getEditRequests')): ?>
            <div class="setup-info">
                <h3><i class="fas fa-info-circle"></i> Setup Required</h3>
                <p>To use the request management system, you need to:</p>
                <ol>
                    <li>Add the request management methods to your Booking class</li>
                    <li>Create the database tables for edit_requests and cancellation_requests</li>
                    <li>Update your guest dashboard to use the new request system</li>
                </ol>
                <p>Check the documentation for complete setup instructions.</p>
            </div>
        <?php endif; ?>

        <!-- Requests Container -->
        <div class="requests-container">
            <!-- Edit Requests Card -->
            <div class="request-card">
                <div class="card-header">
                    <h2 class="card-title">Edit Requests</h2>
                    <span class="request-count"><?php echo count($editRequests); ?></span>
                </div>
                <div class="request-list">
                    <?php if (!empty($editRequests)): ?>
                        <?php foreach ($editRequests as $request): 
                            $booking = $bookingManager->getBookingById($request['booking_id']);
                            if ($booking) {
                                $room = $roomManager->getRoomById($booking['room_id']);
                                $nights = (strtotime($booking['check_out']) - strtotime($booking['check_in'])) / (60 * 60 * 24);
                                $total = $room ? $room['price'] * $nights : 0;
                            }
                        ?>
                            <div class="request-item">
                                <div class="request-header">
                                    <div class="guest-info">
                                        <div class="guest-name"><?php echo htmlspecialchars($booking['guest_name'] ?? 'Unknown Guest'); ?></div>
                                        <div class="booking-id">Booking #<?php echo $booking['id'] ?? 'N/A'; ?></div>
                                    </div>
                                    <div class="request-time">
                                        <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="request-details">
                                    <p><strong>Guest wants to modify their booking details.</strong></p>
                                    <p>Click "Allow Edit" to grant the guest permission to edit their booking.</p>
                                </div>
                                
                                <?php if ($booking && $room): ?>
                                <div class="booking-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Room:</span>
                                        <span class="detail-value">Room #<?php echo htmlspecialchars($room['room_number'] ?? $room['id']); ?> (<?php echo ucfirst($room['room_type'] ?? 'Standard'); ?>)</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Dates:</span>
                                        <span class="detail-value">
                                            <?php echo date('M j, Y', strtotime($booking['check_in'])); ?> to <?php echo date('M j, Y', strtotime($booking['check_out'])); ?>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Nights:</span>
                                        <span class="detail-value"><?php echo $nights; ?> nights</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Total:</span>
                                        <span class="detail-value"><?php echo format_currency($total); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <form method="POST" class="request-actions">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" name="approve_edit" class="action-btn btn-approve">
                                        <i class="fas fa-check"></i> Allow Edit
                                    </button>
                                    <button type="submit" name="decline_edit" class="action-btn btn-decline" 
                                            onclick="return confirm('Are you sure you want to decline this edit request?')">
                                        <i class="fas fa-times"></i> Decline
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-edit"></i>
                            <h3>No Edit Requests</h3>
                            <p>There are no pending edit requests at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cancellation Requests Card -->
            <div class="request-card">
                <div class="card-header">
                    <h2 class="card-title">Cancellation Requests</h2>
                    <span class="request-count"><?php echo count($cancellationRequests); ?></span>
                </div>
                <div class="request-list">
                    <?php if (!empty($cancellationRequests)): ?>
                        <?php foreach ($cancellationRequests as $request): 
                            $booking = $bookingManager->getBookingById($request['booking_id']);
                            if ($booking) {
                                $room = $roomManager->getRoomById($booking['room_id']);
                                $nights = (strtotime($booking['check_out']) - strtotime($booking['check_in'])) / (60 * 60 * 24);
                                $total = $room ? $room['price'] * $nights : 0;
                            }
                        ?>
                            <div class="request-item">
                                <div class="request-header">
                                    <div class="guest-info">
                                        <div class="guest-name"><?php echo htmlspecialchars($booking['guest_name'] ?? 'Unknown Guest'); ?></div>
                                        <div class="booking-id">Booking #<?php echo $booking['id'] ?? 'N/A'; ?></div>
                                    </div>
                                    <div class="request-time">
                                        <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="request-details">
                                    <p><strong>Guest wants to cancel their booking.</strong></p>
                                </div>
                                
                                <?php if ($booking && $room): ?>
                                <div class="booking-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Room:</span>
                                        <span class="detail-value">Room #<?php echo htmlspecialchars($room['room_number'] ?? $room['id']); ?> (<?php echo ucfirst($room['room_type'] ?? 'Standard'); ?>)</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Dates:</span>
                                        <span class="detail-value">
                                            <?php echo date('M j, Y', strtotime($booking['check_in'])); ?> to <?php echo date('M j, Y', strtotime($booking['check_out'])); ?>
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Nights:</span>
                                        <span class="detail-value"><?php echo $nights; ?> nights</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Original Total:</span>
                                        <span class="detail-value"><?php echo format_currency($total); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($request['cancellation_charge'] > 0): ?>
                                    <div class="cancellation-charge">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Cancellation Charge: <?php echo format_currency($request['cancellation_charge']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" class="request-actions">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id'] ?? ''; ?>">
                                    <input type="hidden" name="cancellation_charge" value="<?php echo $request['cancellation_charge']; ?>">
                                    <button type="submit" name="approve_cancellation" class="action-btn btn-approve">
                                        <i class="fas fa-check"></i> Accept
                                    </button>
                                    <button type="submit" name="decline_cancellation" class="action-btn btn-decline"
                                            onclick="return confirm('Are you sure you want to decline this cancellation request?')">
                                        <i class="fas fa-times"></i> Decline
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-ban"></i>
                            <h3>No Cancellation Requests</h3>
                            <p>There are no pending cancellation requests at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh the page every 30 seconds to check for new requests
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>