<?php
/**
 * Audit Integration Helper
 * Include this file in your existing PHP files to add audit logging
 */

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../classes/AuditLog.php";

class AuditIntegration {
    private $auditLog;
    
    public function __construct($pdo) {
        $this->auditLog = new AuditLog($pdo);
    }
    
    /**
     * Log room management actions
     */
    public function logRoomAction($action, $room_id, $old_data = null, $new_data = null, $description = null) {
        $user_id = $_SESSION['user_id'] ?? null;
        return $this->auditLog->logAction($action, 'rooms', $room_id, $old_data, $new_data, $user_id, $description);
    }
    
    /**
     * Log booking actions
     */
    public function logBookingAction($action, $booking_id, $old_data = null, $new_data = null, $description = null) {
        $user_id = $_SESSION['user_id'] ?? null;
        return $this->auditLog->logAction($action, 'bookings', $booking_id, $old_data, $new_data, $user_id, $description);
    }
    
    /**
     * Log user management actions
     */
    public function logUserAction($action, $user_id, $old_data = null, $new_data = null, $description = null) {
        $admin_id = $_SESSION['user_id'] ?? null;
        return $this->auditLog->logAction($action, 'users', $user_id, $old_data, $new_data, $admin_id, $description);
    }
    
    /**
     * Log authentication actions
     */
    public function logAuthAction($action, $user_id, $description = null) {
        return $this->auditLog->logAction($action, 'users', $user_id, null, null, $user_id, $description);
    }
    
    /**
     * Log request actions
     */
    public function logRequestAction($action, $request_id, $table, $old_data = null, $new_data = null, $description = null) {
        $user_id = $_SESSION['user_id'] ?? null;
        return $this->auditLog->logAction($action, $table, $request_id, $old_data, $new_data, $user_id, $description);
    }

    /**
     * Log system actions
     */
    public function logSystemAction($action, $description = null, $data = null) {
        $user_id = $_SESSION['user_id'] ?? null;
        return $this->auditLog->logAction($action, 'system', 0, null, $data, $user_id, $description);
    }
}

// Create global instance
$auditIntegration = new AuditIntegration($pdo);
?>