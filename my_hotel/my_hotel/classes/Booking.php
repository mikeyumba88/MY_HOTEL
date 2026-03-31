<?php
class Booking {
    private $pdo;
    public $room_id;
    public $check_in;
    public $check_out;
    public $guest_id;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Check if room is available between given dates
     */
    public function isRoomAvailable($room_id = null, $check_in = null, $check_out = null) {
        try {
            // Use parameters or object properties
            $room_id = $room_id ?: $this->room_id;
            $check_in = $check_in ?: $this->check_in;
            $check_out = $check_out ?: $this->check_out;

            if (!$room_id || !$check_in || !$check_out) {
                throw new Exception("Missing parameters for availability check");
            }

            $sql = "SELECT COUNT(*) FROM bookings 
                    WHERE room_id = :room_id 
                    AND status NOT IN ('cancelled', 'checked_out', 'completed')
                    AND (
                        (check_in < :check_out AND check_out > :check_in)
                    )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':room_id'   => $room_id,
                ':check_in'  => $check_in,
                ':check_out' => $check_out
            ]);

            $count = $stmt->fetchColumn();
            return $count == 0; // true if no overlapping bookings
            
        } catch (Exception $e) {
            error_log("Error in isRoomAvailable: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new booking for registered users
     */
    public function createBooking($room_id, $user_id, $check_in, $check_out, $guest_name = null) {
        try {
            // First check if room exists and is available
            $roomCheck = $this->pdo->prepare("SELECT id, status FROM rooms WHERE id = ? AND is_available = 1");
            $roomCheck->execute([$room_id]);
            $room = $roomCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$room) {
                throw new Exception("Room not found or not available");
            }

            // Check availability before inserting
            if (!$this->isRoomAvailable($room_id, $check_in, $check_out)) {
                throw new Exception("Room not available for selected dates");
            }

            $sql = "INSERT INTO bookings (guest_id, room_id, check_in, check_out, status, created_at, guest_name) 
                    VALUES (:guest_id, :room_id, :check_in, :check_out, 'confirmed', NOW(), :guest_name)";

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                ':guest_id'  => $user_id,
                ':room_id'   => $room_id,
                ':check_in'  => $check_in,
                ':check_out' => $check_out,
                ':guest_name' => $guest_name ?: null
            ]);

            if ($result) {
                $bookingId = $this->pdo->lastInsertId();
                // Update room status
                $this->updateRoomStatus($room_id, 'occupied');
                return $bookingId;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error creating booking: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create booking for walk-in guest (without user account)
     */
    public function createWalkinBooking($guestData) {
        try {
            // First check if room exists and is available
            $roomCheck = $this->pdo->prepare("SELECT id, status FROM rooms WHERE id = ? AND is_available = 1");
            $roomCheck->execute([$guestData['room_id']]);
            $room = $roomCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$room) {
                throw new Exception("Room not found or not available");
            }

            // Check availability
            if (!$this->isRoomAvailable($guestData['room_id'], $guestData['check_in'], $guestData['check_out'])) {
                throw new Exception("Room not available for selected dates");
            }

            $sql = "INSERT INTO bookings (
                        guest_name, guest_email, guest_phone, guest_address, 
                        room_id, check_in, check_out, adults, children, 
                        special_requests, payment_method, status, 
                        receptionist_id, created_at
                    ) VALUES (
                        :guest_name, :guest_email, :guest_phone, :guest_address,
                        :room_id, :check_in, :check_out, :adults, :children,
                        :special_requests, :payment_method, 'confirmed',
                        :receptionist_id, NOW()
                    )";

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                ':guest_name' => $guestData['guest_name'],
                ':guest_email' => $guestData['guest_email'] ?? '',
                ':guest_phone' => $guestData['guest_phone'] ?? '',
                ':guest_address' => $guestData['guest_address'] ?? '',
                ':room_id' => $guestData['room_id'],
                ':check_in' => $guestData['check_in'],
                ':check_out' => $guestData['check_out'],
                ':adults' => $guestData['adults'] ?? 1,
                ':children' => $guestData['children'] ?? 0,
                ':special_requests' => $guestData['special_requests'] ?? '',
                ':payment_method' => $guestData['payment_method'] ?? 'cash',
                ':receptionist_id' => $guestData['receptionist_id']
            ]);

            if ($result) {
                $bookingId = $this->pdo->lastInsertId();
                $this->updateRoomStatus($guestData['room_id'], 'occupied');
                return $bookingId;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error in createWalkinBooking: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check in a guest
     */
    public function checkInBooking($booking_id, $room_id, $receptionist_id) {
        try {
            $this->pdo->beginTransaction();

            // Update booking status
            $sql = "UPDATE bookings SET 
                    status = 'checked_in', 
                    checked_in_at = NOW(),
                    checked_in_by = :receptionist_id
                    WHERE id = :booking_id AND room_id = :room_id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':receptionist_id' => $receptionist_id,
                ':booking_id' => $booking_id,
                ':room_id' => $room_id
            ]);

            // Update room status
            $this->updateRoomStatus($room_id, 'occupied');

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Check out a guest
     */
    public function checkOutBooking($booking_id, $room_id, $final_amount, $payment_method, $receptionist_id) {
        try {
            $this->pdo->beginTransaction();

            // Update booking status to 'completed' instead of 'checked_out'
            $sql = "UPDATE bookings SET 
                    status = 'completed', 
                    checked_out_at = NOW(),
                    checked_out_by = :receptionist_id,
                    final_amount = :final_amount,
                    payment_method = :payment_method
                    WHERE id = :booking_id AND room_id = :room_id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':receptionist_id' => $receptionist_id,
                ':booking_id' => $booking_id,
                ':room_id' => $room_id,
                ':final_amount' => $final_amount,
                ':payment_method' => $payment_method
            ]);

            // Update room status to available
            $this->updateRoomStatus($room_id, 'available');

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Early check out
     */
    public function earlyCheckOut($booking_id, $room_id, $receptionist_id) {
        try {
            $this->pdo->beginTransaction();

            // Update booking status to 'completed'
            $sql = "UPDATE bookings SET 
                    status = 'completed', 
                    checked_out_at = NOW(),
                    checked_out_by = :receptionist_id,
                    is_early_checkout = 1
                    WHERE id = :booking_id AND room_id = :room_id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':receptionist_id' => $receptionist_id,
                ':booking_id' => $booking_id,
                ':room_id' => $room_id
            ]);

            // Update room status to available
            $this->updateRoomStatus($room_id, 'available');

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get check-ins for a specific date
     */
    public function getCheckinsByDate($date) {
        $sql = "SELECT b.*, r.room_number, r.room_type, r.price,
                       COALESCE(b.guest_name, u.name) as guest_name
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                LEFT JOIN users u ON b.guest_id = u.id
                WHERE b.check_in = :date 
                AND b.status IN ('confirmed', 'checked_in')
                AND r.is_available = 1
                ORDER BY b.check_in";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':date' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get check-outs for a specific date
     */
    public function getCheckoutsByDate($date) {
        $sql = "SELECT b.*, r.room_number, r.room_type, r.price,
                       COALESCE(b.guest_name, u.name) as guest_name
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                LEFT JOIN users u ON b.guest_id = u.id
                WHERE b.check_out = :date 
                AND b.status IN ('confirmed', 'checked_in')
                AND r.is_available = 1
                ORDER BY b.check_out";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':date' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get bookings by a specific user - EXCLUDE deleted rooms and completed/cancelled bookings
     */
    public function getBookingsByUser($user_id) {
        $sql = "SELECT b.*, r.room_number, r.room_type, r.price,
                       COALESCE(b.guest_name, u.name) as display_name
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                LEFT JOIN users u ON b.guest_id = u.id
                WHERE b.guest_id = :guest_id 
                AND r.is_available = 1
                AND b.status NOT IN ('cancelled', 'completed')
                ORDER BY b.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':guest_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all bookings (for admin/receptionist) - EXCLUDE deleted rooms
     */
    public function getAllBookings() {
        $sql = "SELECT b.*, r.room_number, r.room_type, r.price, 
                       COALESCE(b.guest_name, u.name) as guest_name,
                       uc.name as checked_in_by_name,
                       uco.name as checked_out_by_name
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                LEFT JOIN users u ON b.guest_id = u.id
                LEFT JOIN users uc ON b.checked_in_by = uc.id
                LEFT JOIN users uco ON b.checked_out_by = uco.id
                WHERE r.is_available = 1
                ORDER BY b.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get active bookings (for dashboard) - EXCLUDE cancelled, completed and deleted rooms
     */
    public function getActiveBookings() {
        $sql = "SELECT b.*, r.room_number, r.room_type, r.price, 
                       COALESCE(b.guest_name, u.name) as guest_name
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                LEFT JOIN users u ON b.guest_id = u.id
                WHERE b.status NOT IN ('cancelled', 'completed')
                AND r.is_available = 1
                ORDER BY b.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking($booking_id) {
        try {
            $this->pdo->beginTransaction();

            // Get room_id before cancelling
            $roomSql = "SELECT room_id FROM bookings WHERE id = :id";
            $roomStmt = $this->pdo->prepare($roomSql);
            $roomStmt->execute([':id' => $booking_id]);
            $room = $roomStmt->fetch(PDO::FETCH_ASSOC);

            if ($room) {
                // Update booking status
                $sql = "UPDATE bookings SET status = 'cancelled' WHERE id = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':id' => $booking_id]);

                // Update room status back to available
                $this->updateRoomStatus($room['room_id'], 'available');
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Update booking dates
     */
    public function updateBooking($booking_id, $room_id, $check_in, $check_out) {
        // Check availability (excluding current booking)
        $sql = "SELECT COUNT(*) FROM bookings 
                WHERE room_id = :room_id 
                AND id != :booking_id
                AND status NOT IN ('cancelled', 'completed')
                AND (
                    (check_in < :check_out AND check_out > :check_in)
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':room_id' => $room_id,
            ':booking_id' => $booking_id,
            ':check_in' => $check_in,
            ':check_out' => $check_out
        ]);

        if ($stmt->fetchColumn() > 0) {
            return false; // Not available
        }

        // Update booking
        $sql = "UPDATE bookings 
                SET room_id = :room_id, check_in = :check_in, check_out = :check_out 
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':room_id' => $room_id,
            ':check_in' => $check_in,
            ':check_out' => $check_out,
            ':id' => $booking_id
        ]);
    }

    /**
     * Get available rooms for specific dates - EXCLUDE deleted rooms
     */
    public function getAvailableRooms($check_in = null, $check_out = null) {
        try {
            // If no dates provided, get all available rooms
            if (!$check_in || !$check_out) {
                $sql = "SELECT r.* FROM rooms r 
                        WHERE r.status = 'available' 
                        AND r.is_available = 1
                        ORDER BY r.room_number";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // If dates provided, check availability for those dates
            $sql = "SELECT r.* FROM rooms r
                    WHERE r.status = 'available' 
                    AND r.is_available = 1
                    AND r.id NOT IN (
                        SELECT room_id FROM bookings 
                        WHERE status NOT IN ('cancelled', 'completed')
                        AND (check_in < :check_out AND check_out > :check_in)
                    )
                    ORDER BY r.room_number";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':check_in' => $check_in,
                ':check_out' => $check_out
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error in getAvailableRooms: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get booking by ID - EXCLUDE deleted rooms
     */
    public function getBookingById($booking_id) {
        $sql = "SELECT b.*, r.room_number, r.room_type, r.price, 
                       COALESCE(b.guest_name, u.name) as guest_name,
                       uc.name as checked_in_by_name,
                       uco.name as checked_out_by_name
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                LEFT JOIN users u ON b.guest_id = u.id
                LEFT JOIN users uc ON b.checked_in_by = uc.id
                LEFT JOIN users uco ON b.checked_out_by = uco.id
                WHERE b.id = :id AND r.is_available = 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $booking_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate booking total with tax
     */
    public function calculateBookingTotal($booking_id) {
        $booking = $this->getBookingById($booking_id);
        if (!$booking) return null;
        
        $nights = (strtotime($booking['check_out']) - strtotime($booking['check_in'])) / (60 * 60 * 24);
        $subtotal = $booking['price'] * $nights;
        $tax_rate = 16; // Zambian VAT
        $tax = ($subtotal * $tax_rate) / 100;
        $total = $subtotal + $tax;
        
        return [
            'nights' => $nights,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'tax_rate' => $tax_rate
        ];
    }

    /**
     * Get current occupied rooms - EXCLUDE deleted rooms
     */
    public function getOccupiedRooms() {
        $sql = "SELECT r.*, b.guest_name, b.check_in, b.check_out
                FROM rooms r
                JOIN bookings b ON r.id = b.room_id
                WHERE b.status = 'checked_in'
                AND r.is_available = 1
                ORDER BY r.room_number";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update room status
     */
    private function updateRoomStatus($room_id, $status) {
        try {
            $sql = "UPDATE rooms SET status = :status WHERE id = :room_id AND is_available = 1";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                ':status' => $status,
                ':room_id' => $room_id
            ]);
            
            return $result;
        } catch (Exception $e) {
            error_log("Error updating room status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get booking statistics for dashboard - EXCLUDE deleted rooms and completed bookings
     */
    public function getBookingStats($period = 'month') {
        $currentMonth = date('Y-m');
        
        $sql = "SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                WHERE DATE_FORMAT(b.created_at, '%Y-%m') = :current_month
                AND r.is_available = 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':current_month' => $currentMonth]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Auto-complete past bookings (run as cron job or on page load)
     */
    public function autoCompletePastBookings() {
        try {
            $today = date('Y-m-d');
            
            $sql = "UPDATE bookings b
                    JOIN rooms r ON b.room_id = r.id
                    SET b.status = 'completed',
                    r.status = 'available'
                    WHERE b.check_out < :today 
                    AND b.status IN ('confirmed', 'checked_in')
                    AND r.is_available = 1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':today' => $today]);
            
            $affectedRows = $stmt->rowCount();
            
            if ($affectedRows > 0) {
                error_log("Auto-completed $affectedRows past bookings");
            }
            
            return $affectedRows;
        } catch (Exception $e) {
            error_log("Error auto-completing past bookings: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send edit request
     */
    public function sendEditRequest($booking_id, $user_id) {
        // First check if booking exists and room is available
        $checkSql = "SELECT b.id FROM bookings b 
                    JOIN rooms r ON b.room_id = r.id 
                    WHERE b.id = ? AND r.is_available = 1";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([$booking_id]);
        
        if (!$checkStmt->fetch()) {
            return false;
        }

        $sql = "INSERT INTO edit_requests (booking_id, user_id, created_at) VALUES (?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$booking_id, $user_id]);
    }

    /**
     * Send cancellation request
     */
    public function sendCancellationRequest($booking_id, $user_id, $cancellation_charge) {
        // First check if booking exists and room is available
        $checkSql = "SELECT b.id FROM bookings b 
                    JOIN rooms r ON b.room_id = r.id 
                    WHERE b.id = ? AND r.is_available = 1";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([$booking_id]);
        
        if (!$checkStmt->fetch()) {
            return false;
        }

        $sql = "INSERT INTO cancellation_requests (booking_id, user_id, cancellation_charge, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$booking_id, $user_id, $cancellation_charge]);
    }

    /**
     * Get edit requests - EXCLUDE deleted rooms
     */
    public function getEditRequests() {
        $sql = "SELECT er.*, b.guest_name, b.room_id, b.check_in, b.check_out, r.room_number
                FROM edit_requests er 
                JOIN bookings b ON er.booking_id = b.id 
                JOIN rooms r ON b.room_id = r.id
                WHERE er.status = 'pending' 
                AND r.is_available = 1
                ORDER BY er.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get cancellation requests - EXCLUDE deleted rooms
     */
    public function getCancellationRequests() {
        $sql = "SELECT cr.*, b.guest_name, b.room_id, b.check_in, b.check_out, r.room_number
                FROM cancellation_requests cr 
                JOIN bookings b ON cr.booking_id = b.id 
                JOIN rooms r ON b.room_id = r.id
                WHERE cr.status = 'pending' 
                AND r.is_available = 1
                ORDER BY cr.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Approve edit request
     */
    public function approveEditRequest($request_id) {
        $sql = "UPDATE edit_requests SET status = 'approved' WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$request_id]);
    }

    /**
     * Decline edit request
     */
    public function declineEditRequest($request_id) {
        $sql = "UPDATE edit_requests SET status = 'declined' WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$request_id]);
    }

    /**
     * Approve cancellation request
     */
    public function approveCancellationRequest($request_id, $booking_id, $charge) {
        $this->pdo->beginTransaction();
        
        try {
            // Update cancellation request status
            $sql1 = "UPDATE cancellation_requests SET status = 'approved' WHERE id = ?";
            $stmt1 = $this->pdo->prepare($sql1);
            $stmt1->execute([$request_id]);
            
            // Cancel the booking
            $this->cancelBooking($booking_id);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Decline cancellation request
     */
    public function declineCancellationRequest($request_id) {
        $sql = "UPDATE cancellation_requests SET status = 'declined' WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$request_id]);
    }

    /**
     * Get approved edit requests for user - EXCLUDE deleted rooms
     */
    public function getApprovedEditRequests($user_id) {
        $sql = "SELECT er.*, b.guest_name, b.room_id, b.check_in, b.check_out, r.room_number
                FROM edit_requests er 
                JOIN bookings b ON er.booking_id = b.id 
                JOIN rooms r ON b.room_id = r.id
                WHERE er.status = 'approved' AND er.user_id = ?
                AND r.is_available = 1
                ORDER BY er.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if edit is allowed for booking
     */
    public function isEditAllowed($booking_id, $user_id) {
        // Check if there's an approved edit request for this booking by this user
        $sql = "SELECT COUNT(*) FROM edit_requests 
                WHERE booking_id = ? AND user_id = ? AND status = 'approved'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$booking_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get room price by room ID - EXCLUDE deleted rooms
     */
    public function getRoomPrice($room_id) {
        $sql = "SELECT price FROM rooms WHERE id = :id AND is_available = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $room_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['price'] : 0;
    }

    /**
     * Mark edit request as used
     */
    public function markEditRequestAsUsed($request_id) {
        $sql = "UPDATE edit_requests SET status = 'used' WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$request_id]);
    }

    /**
     * Get today's arrivals - EXCLUDE deleted rooms
     */
    public function getTodaysArrivals() {
        $today = date('Y-m-d');
        $sql = "SELECT b.*, r.room_number, r.room_type, 
                       COALESCE(b.guest_name, u.name) as guest_name
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                LEFT JOIN users u ON b.guest_id = u.id
                WHERE b.check_in = :today 
                AND b.status = 'confirmed'
                AND r.is_available = 1
                ORDER BY r.room_number";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':today' => $today]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get today's departures - EXCLUDE deleted rooms
     */
    public function getTodaysDepartures() {
        $today = date('Y-m-d');
        $sql = "SELECT b.*, r.room_number, r.room_type, 
                       COALESCE(b.guest_name, u.name) as guest_name
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                LEFT JOIN users u ON b.guest_id = u.id
                WHERE b.check_out = :today 
                AND b.status IN ('confirmed', 'checked_in')
                AND r.is_available = 1
                ORDER BY r.room_number";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':today' => $today]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get current guests (checked in) - EXCLUDE deleted rooms
     */
    public function getCurrentGuests() {
        $sql = "SELECT b.*, r.room_number, r.room_type, 
                       COALESCE(b.guest_name, u.name) as guest_name,
                       DATEDIFF(b.check_out, CURDATE()) as remaining_nights
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                LEFT JOIN users u ON b.guest_id = u.id
                WHERE b.status = 'checked_in'
                AND r.is_available = 1
                ORDER BY r.room_number";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get booking revenue statistics - EXCLUDE deleted rooms
     */
    public function getRevenueStats($period = 'month') {
        $currentMonth = date('Y-m');
        
        $sql = "SELECT 
                SUM(CASE WHEN status = 'completed' THEN final_amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'confirmed' THEN (r.price * DATEDIFF(check_out, check_in)) ELSE 0 END) as expected_revenue,
                COUNT(DISTINCT CASE WHEN status = 'completed' THEN guest_id END) as guests_served
                FROM bookings b
                JOIN rooms r ON b.room_id = r.id
                WHERE DATE_FORMAT(b.created_at, '%Y-%m') = :current_month
                AND r.is_available = 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':current_month' => $currentMonth]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>