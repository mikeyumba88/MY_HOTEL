SET FOREIGN_KEY_CHECKS=0;
CREATE DATABASE `hotel_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hotel_db`;
SET FOREIGN_KEY_CHECKS=1;

-- -----------------------------------------------------
-- users table
-- -----------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','guest','receptionist') NOT NULL DEFAULT 'guest',
  `contact` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- rooms table
-- -----------------------------------------------------
CREATE TABLE `rooms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_number` VARCHAR(50) NOT NULL,
  `room_type` VARCHAR(50) DEFAULT 'standard',
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('available','occupied','maintenance') NOT NULL DEFAULT 'available',
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `price_per_night` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'ZMW', 
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rooms_number` (`room_number`),
  INDEX `idx_rooms_type` (`room_type`),
  INDEX `idx_rooms_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- bookings table
-- -----------------------------------------------------
CREATE TABLE `bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guest_id` INT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED  DEFAULT 0,
  `guest_name` VARCHAR(150) DEFAULT NULL,
  `guest_email` VARCHAR(255) DEFAULT NULL,
  `guest_phone` VARCHAR(100) DEFAULT NULL,
  `room_id` INT UNSIGNED NOT NULL,
  `check_in` DATE NOT NULL,
  `check_out` DATE NOT NULL,
  `adults` INT UNSIGNED DEFAULT 1,
  `children` INT UNSIGNED DEFAULT 0,
  `special_requests` TEXT DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `final_amount` DECIMAL(10,2) DEFAULT NULL,
  `status` ENUM('pending','confirmed','checked_in','checked_out','cancelled','completed') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `checked_in_at` DATETIME DEFAULT NULL,
  `checked_out_at` DATETIME DEFAULT NULL,
  `checked_in_by` INT UNSIGNED DEFAULT NULL,
  `checked_out_by` INT UNSIGNED DEFAULT NULL,
  `is_early_checkout` TINYINT(1) DEFAULT 0,
  `receptionist_id` INT UNSIGNED DEFAULT NULL,
  `guest_address` VARCHAR(500) NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_bookings_room` (`room_id`),
  INDEX `idx_bookings_guest` (`guest_id`),
  INDEX `idx_bookings_dates` (`check_in`, `check_out`),
  INDEX `idx_bookings_status` (`status`),
  CONSTRAINT `fk_bookings_room` FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_guest` FOREIGN KEY (`guest_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_checked_in_by` FOREIGN KEY (`checked_in_by`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_checked_out_by` FOREIGN KEY (`checked_out_by`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- edit_requests table
-- -----------------------------------------------------
CREATE TABLE `edit_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','approved','declined','used') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_editreq_booking` (`booking_id`),
  INDEX `idx_editreq_user` (`user_id`),
  INDEX `idx_editreq_status` (`status`),
  CONSTRAINT `fk_editreq_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_editreq_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- cancellation_requests table
-- -----------------------------------------------------
CREATE TABLE `cancellation_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `cancellation_charge` DECIMAL(10,2) DEFAULT 0.00,
  `status` ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_cancreq_booking` (`booking_id`),
  INDEX `idx_cancreq_user` (`user_id`),
  INDEX `idx_cancreq_status` (`status`),
  CONSTRAINT `fk_cancreq_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cancreq_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- audit_log table
-- -----------------------------------------------------
-- -----------------------------------------------------
-- audit_log table
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT UNSIGNED NOT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    url VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_table (table_name),
    INDEX idx_audit_record (table_name, record_id),
    INDEX idx_audit_created (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;