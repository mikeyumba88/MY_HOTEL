<?php
require_once __DIR__ . '/../config/db.php';

class Room {
    private $conn;
    private $table = "rooms";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Add new room
    public function addRoom($room_number, $room_type, $price, $description = '') {
        $query = "INSERT INTO " . $this->table . " 
                  (room_number, room_type, price, description, status, is_available) 
                  VALUES (:room_number, :room_type, :price, :description, 'available', 1)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":room_number", $room_number);
        $stmt->bindParam(":room_type", $room_type);
        $stmt->bindParam(":price", $price);
        $stmt->bindParam(":description", $description);
        return $stmt->execute();
    }

    // Get all rooms
    public function getRooms() {
        $query = "SELECT * FROM " . $this->table . " WHERE is_available = 1 ORDER BY room_number";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get ALL rooms including occupied ones (for admin view)
    public function getAllRooms() {
        $query = "SELECT * FROM " . $this->table . " WHERE is_available = 1 ORDER BY room_number";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get only available rooms (status = 'available')
    public function getAvailableRooms() {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE status = 'available' 
                  AND is_available = 1 
                  ORDER BY room_number ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get occupied rooms
    public function getOccupiedRooms() {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE status = 'occupied' 
                  AND is_available = 1 
                  ORDER BY room_number ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Check room availability for specific dates
    public function isRoomAvailable($room_id, $check_in, $check_out) {
        $query = "SELECT COUNT(*) FROM bookings 
                  WHERE room_id = :room_id 
                  AND status NOT IN ('cancelled', 'completed')
                  AND (
                      (check_in < :check_out AND check_out > :check_in)
                  )";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':room_id' => $room_id,
            ':check_in' => $check_in,
            ':check_out' => $check_out
        ]);
        
        $count = $stmt->fetchColumn();
        return $count == 0;
    }

    // Update room status
    public function updateRoomStatus($room_id, $status) {
        $query = "UPDATE " . $this->table . " SET status = :status WHERE id = :room_id AND is_available = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":room_id", $room_id);
        return $stmt->execute();
    }

    // Get unique room types for filter dropdown
    public function getRoomTypes() {
        $query = "SELECT DISTINCT room_type FROM " . $this->table . " WHERE is_available = 1 ORDER BY room_type";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $types = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $types[] = $row['room_type'];
        }
        
        return $types;
    }

    // Get room by ID
    public function getRoomById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id AND is_available = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update room
    public function updateRoom($id, $room_number, $room_type, $price, $description = '') {
        $query = "UPDATE " . $this->table . " 
                  SET room_number = :room_number, room_type = :room_type, 
                      price = :price, description = :description
                  WHERE id = :id AND is_available = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":room_number", $room_number);
        $stmt->bindParam(":room_type", $room_type);
        $stmt->bindParam(":price", $price);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    // Delete room (soft delete - set is_available to 0)
    public function deleteRoom($id) {
        $query = "UPDATE " . $this->table . " SET is_available = 0 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    // Check if room number already exists
    public function roomNumberExists($room_number, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table . " WHERE room_number = :room_number AND is_available = 1";
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":room_number", $room_number);
        if ($exclude_id) {
            $stmt->bindParam(":exclude_id", $exclude_id);
        }
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Toggle room availability (for maintenance)
    public function toggleAvailability($id, $is_available) {
        $query = "UPDATE " . $this->table . " SET is_available = :is_available WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":is_available", $is_available);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    // Get room statistics
    public function getRoomStats() {
        $query = "SELECT 
                    COUNT(*) as total_rooms,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_rooms,
                    SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_rooms,
                    room_type,
                    COUNT(*) as type_count
                  FROM " . $this->table . " 
                  WHERE is_available = 1
                  GROUP BY room_type";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get rooms by status
    public function getRoomsByStatus($status) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE status = :status AND is_available = 1 
                  ORDER BY room_number";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Mark room as occupied
    public function markAsOccupied($room_id) {
        return $this->updateRoomStatus($room_id, 'occupied');
    }

    // Mark room as available
    public function markAsAvailable($room_id) {
        return $this->updateRoomStatus($room_id, 'available');
    }

    // Check if room exists and is available
    public function roomExists($room_id) {
        $query = "SELECT id FROM " . $this->table . " WHERE id = :id AND is_available = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $room_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}
?>