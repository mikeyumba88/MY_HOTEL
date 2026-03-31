<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in or not a guest
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guest') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../classes/Booking.php";
require_once __DIR__ . "/../classes/Room.php";
require_once __DIR__ . "/../helpers/currency.php";
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";

$bookingManager = new Booking($pdo);
$roomManager = new Room($pdo);
$user_id = $_SESSION['user_id'];
$username = $_SESSION['name'] ?? 'Guest';

// Auto-complete past bookings
$bookingManager->autoCompletePastBookings();
// Get user's bookings
$myBookings = $bookingManager->getBookingsByUser($user_id);

// Get list of rooms for edit dropdown
$allRooms = [];
if (method_exists($roomManager, 'getRooms')) {
    try {
        $allRooms = $roomManager->getRooms();
    } catch (Exception $e) {
        $allRooms = [];
    }
}

if (empty($allRooms)) {
    try {
        $stmt = $pdo->query("SELECT id, room_number, room_type, price FROM rooms ORDER BY room_number ASC");
        $allRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $allRooms = [];
    }
}

// Get approved edit requests for this user
$approvedEditRequests = [];
if (method_exists($bookingManager, 'getApprovedEditRequests')) {
    $approvedEditRequests = $bookingManager->getApprovedEditRequests($user_id);
}

// Handle inline booking update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_booking'])) {
        $booking_id = intval($_POST['booking_id']);
        $new_check_in = $_POST['check_in'] ?? null;
        $new_check_out = $_POST['check_out'] ?? null;
        $new_room_id = isset($_POST['new_room_id']) ? intval($_POST['new_room_id']) : null;

        if ($new_check_in && $new_check_out && $new_room_id) {
            // Fetch current booking to get existing room id and guest id check
            $currentBooking = $bookingManager->getBookingById($booking_id);
            if (!$currentBooking || $currentBooking['guest_id'] != $user_id) {
                $_SESSION['error_message'] = "Booking not found or you do not have permission to edit it.";
                header("Location: guest_dashboard.php");
                exit;
            }

            // Check availability for the selected room and dates
            try {
                $availSql = "SELECT COUNT(*) FROM bookings 
                             WHERE room_id = :room_id 
                               AND id != :booking_id
                               AND status != 'cancelled'
                               AND NOT (check_out < :ci OR check_in > :co)";

                $availStmt = $pdo->prepare($availSql);
                $availStmt->execute([
                    ':room_id' => $new_room_id,
                    ':booking_id' => $booking_id,
                    ':ci' => $new_check_in,
                    ':co' => $new_check_out
                ]);
                $overlapCount = (int)$availStmt->fetchColumn();

                if ($overlapCount > 0) {
                    $_SESSION['error_message'] = "Sorry, the selected room is not available for those dates.";
                    header("Location: guest_dashboard.php");
                    exit;
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Error checking availability: " . $e->getMessage();
                header("Location: guest_dashboard.php");
                exit;
            }

            // Update booking
            $updated = false;
            try {
                $sql = "UPDATE bookings SET room_id = :room_id, check_in = :ci, check_out = :co WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $updated = $stmt->execute([
                    ':room_id' => $new_room_id,
                    ':ci' => $new_check_in,
                    ':co' => $new_check_out,
                    ':id' => $booking_id
                ]);
            } catch (Exception $e) {
                $updated = false;
            }

            if ($updated) {
                // MARK THE EDIT REQUEST AS USED - THIS IS THE KEY CHANGE!
                if (method_exists($bookingManager, 'markEditRequestAsUsed')) {
                    // Find the edit request for this booking
                    foreach ($approvedEditRequests as $request) {
                        if ($request['booking_id'] == $booking_id) {
                            $bookingManager->markEditRequestAsUsed($request['id']);
                            break;
                        }
                    }
                }
                
                $_SESSION['success_message'] = "Booking updated successfully! Your edit permission has been used.";
            } else {
                $_SESSION['error_message'] = "Failed to update booking. Please try again.";
            }
            header("Location: guest_dashboard.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Please select a room and both dates.";
            header("Location: guest_dashboard.php");
            exit;
        }
    }
    elseif (isset($_POST['request_edit'])) {
        $booking_id = $_POST['booking_id'];
        
        // Send edit request to admin
        $result = $bookingManager->sendEditRequest($booking_id, $user_id);
        if ($result) {
            $_SESSION['success_message'] = "Edit request sent successfully! Admin will review your request.";
        } else {
            $_SESSION['error_message'] = "Failed to send edit request. Please try again.";
        }
        header("Location: guest_dashboard.php");
        exit;
    }
    elseif (isset($_POST['request_cancel'])) {
        $booking_id = $_POST['booking_id'];
        $booking = $bookingManager->getBookingById($booking_id);
        
        if ($booking && $booking['guest_id'] == $user_id) {
            $cancellation_charge = $_POST['cancellation_charge'];
            
            if ($cancellation_charge == 0) {
                // Free cancellation - process immediately
                $result = $bookingManager->cancelBooking($booking_id);
                if ($result) {
                    $_SESSION['success_message'] = "Booking cancelled successfully!";
                } else {
                    $_SESSION['error_message'] = "Failed to cancel booking. Please try again.";
                }
            } else {
                // Charged cancellation - send request to admin
                $result = $bookingManager->sendCancellationRequest($booking_id, $user_id, $cancellation_charge);
                if ($result) {
                    $_SESSION['success_message'] = "Cancellation request sent! Admin will process it with " . format_currency($cancellation_charge, 'ZMW') . " charge.";
                } else {
                    $_SESSION['error_message'] = "Failed to send cancellation request. Please try again.";
                }
            }
            header("Location: guest_dashboard.php");
            exit;
        }
    }
}

// Calculate totals and prepare booking data
$total_spent = 0;
$total_nights = 0;
$confirmed_count = 0;

foreach ($myBookings as &$booking) {
    if (isset($booking['check_in']) && isset($booking['check_out'])) {
        $booking_nights = (strtotime($booking['check_out']) - strtotime($booking['check_in'])) / (60 * 60 * 24);
        $booking_nights = max(1, $booking_nights);
        $booking['nights'] = $booking_nights;
        $booking['total_price'] = ($booking['price'] ?? 0) * $booking_nights;
        
        $total_nights += $booking_nights;
        if ($booking['status'] == 'confirmed') {
            $total_spent += $booking['total_price'];
            $confirmed_count++;
        }
    }
    
    // Check cancellation eligibility
    $booking['can_cancel'] = false;
    $booking['cancellation_charge'] = 0;
    
    if (isset($booking['created_at'])) {
        $created_time = strtotime($booking['created_at']);
        $current_time = time();
        $minutes_since_creation = ($current_time - $created_time) / 60;
        
        if ($minutes_since_creation <= 10 && in_array($booking['status'], ['pending', 'confirmed'])) {
            $booking['can_cancel'] = true;
            $booking['cancellation_message'] = "Free cancellation available";
        }
        elseif ($minutes_since_creation <= 1440 && in_array($booking['status'], ['pending', 'confirmed'])) {
            $booking['can_cancel'] = true;
            $booking['cancellation_charge'] = $booking['total_price'] * 0.5;
            $booking['cancellation_message'] = "50% cancellation charge applies";
        }
        else {
            $booking['can_cancel'] = false;
            $booking['cancellation_message'] = "Cancellation not allowed - contact reception";
        }
    }
    
    // Check if edit is allowed
    $booking['can_edit'] = in_array($booking['status'], ['pending', 'confirmed']);
    
    // Check if this booking has approved edit request (ONE-TIME USE)
    $booking['has_approved_edit'] = false;
    foreach ($approvedEditRequests as $approvedRequest) {
        if ($approvedRequest['booking_id'] == $booking['id']) {
            $booking['has_approved_edit'] = true;
            break;
        }
    }
}
unset($booking);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Hotel Booking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* (same styles as your original file kept) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 30px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .welcome { font-size: 2em; margin-bottom: 10px; }
        .subtitle { opacity: 0.9; margin-bottom: 20px; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .quick-actions { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; margin-bottom: 30px; }
        .action-btn { background: white; color: #4CAF50; padding: 12px 20px; text-decoration: none; border-radius: 25px; font-weight: bold; transition: all 0.3s; }
        .action-btn:hover { background: #4CAF50; color: white; transform: translateY(-2px); }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2em; font-weight: bold; color: #4CAF50; margin-bottom: 5px; }
        .stat-label { color: #666; }
        .bookings { background: white; border-radius: 10px; padding: 25px; margin-bottom: 30px; }
        .section-title { font-size: 1.5em; margin-bottom: 20px; color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .booking-list { display: flex; flex-direction: column; gap: 15px; }
        .booking-item { background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #4CAF50; transition: all 0.3s ease; }
        .booking-item.editing { border-left-color: #17a2b8; background: #f0f8ff; }
        .booking-item.edit-approved { border-left-color: #28a745; }
        .booking-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .room-name { font-weight: bold; font-size: 1.2em; }
        .status { padding: 5px 10px; border-radius: 15px; font-size: 0.8em; font-weight: bold; }
        .confirmed { background: #d4edda; color: #155724; }
        .pending { background: #fff3cd; color: #856404; }
        .cancelled { background: #f8d7da; color: #721c24; }
        .edit-approved-badge { background: #28a745; color: white; }
        .booking-details { color: #666; margin-bottom: 10px; }
        .detail { margin-bottom: 5px; }
        .edit-form { background: white; padding: 20px; border-radius: 8px; border: 2px solid #17a2b8; margin: 15px 0; }
        .form-group { margin-bottom: 15px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        .form-input { width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; font-size: 1em; }
        .form-input:focus { outline: none; border-color: #17a2b8; }
        .cancellation-info { background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; font-size: 0.9em; border-left: 3px solid #ffc107; }
        .edit-approved-info { background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; font-size: 0.9em; border-left: 3px solid #28a745; }
        .booking-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { padding: 8px 15px; border: none; border-radius: 5px; text-decoration: none; font-size: 0.9em; cursor: pointer; transition: background 0.3s; display: inline-flex; align-items: center; gap: 5px; }
        .edit-btn { background: #17a2b8; color: white; }
        .edit-btn:hover { background: #138496; }
        .cancel-btn { background: #dc3545; color: white; }
        .cancel-btn:hover { background: #c82333; }
        .btn-edit-now { background: #28a745; color: white; }
        .btn-edit-now:hover { background: #218838; }
        .btn-save { background: #28a745; color: white; }
        .btn-save:hover { background: #218838; }
        .btn-cancel { background: #6c757d; color: white; }
        .btn-cancel:hover { background: #5a6268; }
        .btn:disabled { background: #6c757d; cursor: not-allowed; opacity: 0.6; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 10% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 400px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); text-align: center; }
        .modal-title { margin-bottom: 20px; color: #333; }
        .modal-message { margin-bottom: 25px; color: #666; line-height: 1.5; }
        .modal-actions { display: flex; gap: 10px; justify-content: center; }
        .no-bookings { text-align: center; padding: 40px; color: #666; }
        .no-bookings i { font-size: 3em; margin-bottom: 15px; opacity: 0.5; }
        @media (max-width: 768px) {
            .quick-actions { flex-direction: column; align-items: center; }
            .action-btn { width: 200px; text-align: center; }
            .booking-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .stats { grid-template-columns: 1fr 1fr; }
            .booking-actions { flex-direction: column; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="welcome">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <p class="subtitle">Manage your hotel bookings easily</p>
            <div class="quick-actions">
                <a href="book_room.php" class="action-btn">
                    <i class="fas fa-plus"></i> Book Room
                </a>
                <a href="view_rooms.php" class="action-btn">
                    <i class="fas fa-search"></i> Find Rooms
                </a>
                <a href="logout.php" class="action-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($myBookings); ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $confirmed_count; ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_nights; ?></div>
                <div class="stat-label">Nights Booked</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo format_currency($total_spent, 'ZMW'); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
        </div>

        <!-- My Bookings - Single Section -->
        <div class="bookings">
            <h2 class="section-title">My Bookings</h2>
            
            <?php if (!empty($myBookings)): ?>
                <div class="booking-list">
                    <?php foreach ($myBookings as $booking): ?>
                        <div class="booking-item <?php echo $booking['has_approved_edit'] ? 'edit-approved' : ''; ?>" id="booking-<?php echo $booking['id']; ?>">
                            <div class="booking-header">
                                <div class="room-name">
                                    Room <?php echo htmlspecialchars($booking['room_number'] ?? 'N/A'); ?>
                                    <span style="color: #666; font-size: 0.9em;">
                                        (<?php echo htmlspecialchars($booking['room_type'] ?? 'Standard'); ?>)
                                    </span>
                                </div>
                                <div>
                                    <span class="status <?php echo htmlspecialchars($booking['status'] ?? 'pending'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($booking['status'] ?? 'Pending')); ?>
                                    </span>
                                    <?php if ($booking['has_approved_edit']): ?>
                                        <span class="status edit-approved-badge">
                                            <i class="fas fa-edit"></i> One-time Edit Allowed
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="booking-details">
                                <div class="detail">
                                    <strong>Dates:</strong> 
                                    <?php echo htmlspecialchars($booking['check_in'] ?? 'Not set'); ?> 
                                    to 
                                    <?php echo htmlspecialchars($booking['check_out'] ?? 'Not set'); ?>
                                    (<?php echo htmlspecialchars($booking['nights'] ?? '0'); ?> nights)
                                </div>
                                <div class="detail">
                                    <strong>Total:</strong> 
                                    <?php echo format_currency($booking['total_price'] ?? 0, 'ZMW'); ?>
                                </div>
                                <div class="detail">
                                    <strong>Booked on:</strong> 
                                    <?php echo htmlspecialchars($booking['created_at'] ?? 'Unknown date'); ?>
                                </div>
                            </div>

                            <!-- Edit Approved Information -->
                            <?php if ($booking['has_approved_edit']): ?>
                                <div class="edit-approved-info">
                                    <i class="fas fa-check-circle"></i>
                                    <strong>One-time Edit Permission:</strong> You can modify your booking once. After editing, you'll need to request permission again for further changes.
                                </div>
                            <?php endif; ?>

                            <!-- Cancellation Information -->
                            <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                <div class="cancellation-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Cancellation Policy:</strong> <?php echo $booking['cancellation_message']; ?>
                                    <?php if ($booking['cancellation_charge'] > 0): ?>
                                        <br>Charge: <?php echo format_currency($booking['cancellation_charge'], 'ZMW'); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                <div class="booking-actions">
                                    <?php if ($booking['has_approved_edit']): ?>
                                        <!-- Edit Now Button with inline form -->
                                        <button type="button" class="btn btn-edit-now" onclick="showEditForm(<?php echo $booking['id']; ?>)">
                                            <i class="fas fa-edit"></i> Use Edit Permission
                                        </button>
                                    <?php else: ?>
                                        <!-- Request Edit Button -->
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <button type="submit" name="request_edit" class="btn edit-btn"
                                                    <?php echo !$booking['can_edit'] ? 'disabled' : ''; ?>
                                                    onclick="return confirm('Send edit request to admin? They will review your request.')">
                                                <i class="fas fa-edit"></i> Request Edit Permission
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- Cancel Button -->
                                    <button type="button" class="btn cancel-btn" 
                                            onclick="openCancelModal(<?php echo $booking['id']; ?>, <?php echo $booking['cancellation_charge']; ?>, '<?php echo addslashes($booking['cancellation_message']); ?>')"
                                            <?php echo !$booking['can_cancel'] ? 'disabled' : ''; ?>>
                                        <i class="fas fa-times"></i> Cancel Booking
                                    </button>
                                </div>
                                
                                <!-- Inline Edit Form (Hidden by default) -->
                                <?php if ($booking['has_approved_edit']): ?>
                                    <div id="edit-form-<?php echo $booking['id']; ?>" class="edit-form" style="display: none;">
                                        <h4 style="margin-bottom: 15px; color: #17a2b8;">
                                            <i class="fas fa-edit"></i> One-time Edit (Room & Dates)
                                        </h4>
                                        <p style="color: #666; margin-bottom: 15px; font-size: 0.9em;">
                                            <i class="fas fa-info-circle"></i> This is a one-time edit permission. After saving changes, you'll need to request permission again for further edits.
                                        </p>
                                        <form method="POST">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">

                                            <div class="form-group">
                                                <label class="form-label">Select Room</label>
                                                <select name="new_room_id" class="form-input" required>
                                                    <?php foreach ($allRooms as $r): 
                                                        $rId = $r['id'];
                                                        $rNumber = $r['room_number'] ?? ($r['room_no'] ?? $rId);
                                                        $rType = $r['room_type'] ?? ($r['type'] ?? 'Standard');
                                                        $selected = ($rId == ($booking['room_id'] ?? null)) ? 'selected' : '';
                                                    ?>
                                                        <option value="<?php echo $rId; ?>" <?php echo $selected; ?>>
                                                            Room <?php echo htmlspecialchars($rNumber); ?> - <?php echo htmlspecialchars(ucfirst($rType)); ?> - <?php echo format_currency($r['price'] ?? 0, 'ZMW'); ?>/night
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label class="form-label">Check-in Date</label>
                                                    <input type="date" name="check_in" class="form-input" 
                                                           value="<?php echo htmlspecialchars($booking['check_in']); ?>" 
                                                           min="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Check-out Date</label>
                                                    <input type="date" name="check_out" class="form-input" 
                                                           value="<?php echo htmlspecialchars($booking['check_out']); ?>" 
                                                           min="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="booking-actions" style="margin-top:10px;">
                                                <button type="submit" name="update_booking" class="btn btn-save">
                                                    <i class="fas fa-save"></i> Save Changes (Use Permission)
                                                </button>
                                                <button type="button" class="btn btn-cancel" onclick="hideEditForm(<?php echo $booking['id']; ?>)">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-bookings">
                    <i class="fas fa-bed"></i>
                    <h3>No Bookings Yet</h3>
                    <p>You haven't made any bookings. Start by finding a room!</p>
                    <a href="view_rooms.php" class="action-btn" style="margin-top: 15px; display: inline-block;">
                        Find Available Rooms
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancellation Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Cancel Booking</h3>
            <form method="POST" action="">
                <input type="hidden" name="booking_id" id="cancel_booking_id">
                <input type="hidden" name="cancellation_charge" id="cancellation_charge">
                
                <div class="modal-message">
                    <p id="cancellationMessage"></p>
                    <p style="color: #dc3545; font-weight: bold; margin-top: 10px;" id="chargeDisplay"></p>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="closeCancelModal()">Keep Booking</button>
                    <button type="submit" name="request_cancel" class="btn cancel-btn">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        // Edit Form Functions
        function showEditForm(bookingId) {
            // Hide all edit forms first
            document.querySelectorAll('.edit-form').forEach(form => {
                form.style.display = 'none';
            });
            
            // Remove editing class from all booking items
            document.querySelectorAll('.booking-item').forEach(item => {
                item.classList.remove('editing');
            });
            
            // Show the specific edit form and add editing class
            const editForm = document.getElementById('edit-form-' + bookingId);
            const bookingItem = document.getElementById('booking-' + bookingId);
            
            if (editForm && bookingItem) {
                editForm.style.display = 'block';
                bookingItem.classList.add('editing');
                
                // Scroll to the form
                editForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function hideEditForm(bookingId) {
            const editForm = document.getElementById('edit-form-' + bookingId);
            const bookingItem = document.getElementById('booking-' + bookingId);
            
            if (editForm && bookingItem) {
                editForm.style.display = 'none';
                bookingItem.classList.remove('editing');
            }
        }

        // Cancel Modal Functions
        function openCancelModal(bookingId, charge, message) {
            document.getElementById('cancel_booking_id').value = bookingId;
            document.getElementById('cancellation_charge').value = charge;
            document.getElementById('cancellationMessage').textContent = message;
            
            if (charge > 0) {
                document.getElementById('chargeDisplay').textContent = 'Cancellation Charge: K' + parseFloat(charge).toFixed(2);
            } else {
                document.getElementById('chargeDisplay').textContent = 'Free cancellation';
            }
            
            document.getElementById('cancelModal').style.display = 'block';
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const cancelModal = document.getElementById('cancelModal');
            if (event.target == cancelModal) {
                closeCancelModal();
            }
        }

        // Date validation for edit forms
        document.addEventListener('DOMContentLoaded', function() {
            // Set up date validation when forms are shown
            document.querySelectorAll('.edit-form').forEach(form => {
                const checkInInput = form.querySelector('input[name="check_in"]');
                const checkOutInput = form.querySelector('input[name="check_out"]');
                
                if (checkInInput && checkOutInput) {
                    checkInInput.addEventListener('change', function() {
                        if (checkOutInput.value && this.value > checkOutInput.value) {
                            checkOutInput.value = '';
                            alert('Check-in date cannot be after check-out date.');
                        }
                        checkOutInput.min = this.value;
                    });
                    
                    checkOutInput.addEventListener('change', function() {
                        if (checkInInput.value && this.value < checkInInput.value) {
                            this.value = '';
                            alert('Check-out date cannot be before check-in date.');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
