<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/AuditLog.php";
require_once __DIR__ . "/../admin/audit_integration.php";

class User {
    private $conn;
    private $table = "users";

    public $id;
    public $username;
    public $password;
    public $role;
    public $name;
    public $email;
    public $contact;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get all users from the database
     */
    public function getAllUsers() {
        $query = "SELECT id, name, email, role, contact, created_at FROM " . $this->table . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by ID
     */
    public function getUserById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // With this (search by email instead of username):
public function isEmailExists($email = null) {
    $email = $email ?: $this->email;
    $query = "SELECT id FROM " . $this->table . " WHERE email = :email";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

    /**
     * Create admin user
     */
    public function createAdminUser($name, $password, $email, $role = 'admin') {
        $query = "INSERT INTO " . $this->table . " 
                  (name, email, password, role, created_at) 
                  VALUES (:name, :email, :password, :role, NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":role", $role);
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bindParam(":password", $hashed_password);
        
        return $stmt->execute();
    }

    /**
     * Update user
     */
    public function updateUser($id, $name, $email, $role) {
        $query = "UPDATE " . $this->table . " 
                  SET name = :name, email = :email, role = :role 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":role", $role);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }

    /**
     * Delete user
     */
    public function deleteUser($id) {
        // Prevent deleting the last admin
        $adminCount = $this->getAdminCount();
        $user = $this->getUserById($id);
        
        if ($user['role'] === 'admin' && $adminCount <= 1) {
            return false; // Cannot delete the last admin
        }
        
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    /**
     * Get admin user count
     */
    public function getAdminCount() {
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE role = 'admin'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    /**
     * Get users by role
     */
    public function getUsersByRole($role) {
        $query = "SELECT * FROM " . $this->table . " WHERE role = :role ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":role", $role);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Register new user
    public function register($name, $email, $password, $role = 'guest') {
        // check if email already exists
        $query = "SELECT id FROM users WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return false; // email already exists
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO users (name, email, password, role, created_at) 
                  VALUES (:name, :email, :password, :role, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":role", $role);

        return $stmt->execute();
    }

    public function createAdmin($name, $email, $password) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        return $stmt->execute([$name, $email, $hashedPassword]);
    }

    // Login user
    public function login($email, $password) {
        $query = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $user['password'])) {
                return $user; // return full user row
            }
        }
        return false;
    }
}
?>