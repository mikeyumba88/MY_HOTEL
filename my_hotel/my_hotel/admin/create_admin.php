<?php
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/audit_integration.php";
require_once __DIR__ . "/../config/db.php"; // adjust path if needed

$email = 'admin@hotel.com';
$name  = 'Site Admin';
$password_plain = 'AdminPass123'; // change to a strong password
$role = 'admin';

$hash = password_hash($password_plain, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
try {
    $stmt->execute([$name, $email, $hash, $role]);
    echo "Admin created: $email with password $password_plain";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
