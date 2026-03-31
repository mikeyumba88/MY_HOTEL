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
require_once __DIR__ . "/../classes/Room.php";
require_once __DIR__ . "/../helpers/currency.php";
// Debug: Check if currency function exists
if (!function_exists('format_currency')) {
    die("Error: format_currency function not found. File path: " . __DIR__ . "/../helpers/currency.php");
}

$roomManager = new Room($pdo);

$roomManager = new Room($pdo);
$message = '';
$message_type = ''; // success, error, warning

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_room'])) {
        // Add single room
        $room_number = trim($_POST['room_number']);
        $room_type = $_POST['room_type'];
        $price = floatval($_POST['price']);
        $description = trim($_POST['description'] ?? '');
        
        if ($roomManager->addRoom($room_number, $room_type, $price, $description)) {
            $message = "Room added successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to add room. Room number might already exist.";
            $message_type = "error";
        }
    }
    elseif (isset($_POST['add_multiple'])) {
        // Add multiple rooms
        $room_type = $_POST['multiple_room_type'];
        $start_number = intval($_POST['start_number']);
        $end_number = intval($_POST['end_number']);
        $price = floatval($_POST['multiple_price']);
        $description = trim($_POST['multiple_description'] ?? '');
        
        $successCount = 0;
        $errorCount = 0;
        $existingRooms = [];
        
        // Check for existing room numbers first
        for ($i = $start_number; $i <= $end_number; $i++) {
            $room_number = sprintf("%03d", $i); // Format as 001, 002, etc.
            
            // Check if room number already exists
            if ($roomManager->roomNumberExists($room_number)) {
                $errorCount++;
                $existingRooms[] = $room_number;
            } else {
                if ($roomManager->addRoom($room_number, $room_type, $price, $description)) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
        }
        
        if ($successCount > 0) {
            $message = "Successfully added $successCount rooms.";
            if ($errorCount > 0) {
                $message .= " Failed: $errorCount room(s) already exist: " . implode(', ', $existingRooms);
                $message_type = "warning";
            } else {
                $message_type = "success";
            }
        } else {
            $message = "Failed to add any rooms. All room numbers may already exist.";
            $message_type = "error";
        }
    }
    elseif (isset($_POST['update_room'])) {
        // Update room
        $room_id = $_POST['room_id'];
        $room_number = trim($_POST['edit_room_number']);
        $room_type = $_POST['edit_room_type'];
        $price = floatval($_POST['edit_price']);
        $description = trim($_POST['edit_description'] ?? '');
        $is_available = isset($_POST['edit_is_available']) ? 1 : 0;
        
        if ($roomManager->updateRoom($room_id, $room_number, $room_type, $price, $description, $is_available)) {
            $message = "Room updated successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to update room.";
            $message_type = "error";
        }
    }
    elseif (isset($_POST['delete_room'])) {
        // Delete room
        $room_id = $_POST['room_id'];
        
        if ($roomManager->deleteRoom($room_id)) {
            $message = "Room deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to delete room. It might have existing bookings.";
            $message_type = "error";
        }
    }
    elseif (isset($_POST['toggle_availability'])) {
        // Toggle room availability
        $room_id = $_POST['room_id'];
        $is_available = $_POST['is_available'] ? 0 : 1; // Toggle
        
        if ($roomManager->toggleAvailability($room_id, $is_available)) {
            $message = "Room availability updated!";
            $message_type = "success";
        } else {
            $message = "Failed to update room availability.";
            $message_type = "error";
        }
    }
}

// Handle room editing
$edit_room = null;
if (isset($_GET['edit'])) {
    $edit_room = $roomManager->getRoomById($_GET['edit']);
}

// Get all rooms
$rooms = $roomManager->getRooms();

// Get room statistics
$roomStats = $roomManager->getRoomStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms - Hotel Booking System</title>
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

        /* Message Styles */
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

        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        /* Forms Grid */
        .forms-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .form-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.6em;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            height: 100px;
            resize: vertical;
        }

        .number-range {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-primary {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
        }

        .btn-success {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
        }

        /* Rooms Table */
        .rooms-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }

        .table-container {
            overflow-x: auto;
        }

        .rooms-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .rooms-table th,
        .rooms-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .rooms-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #667eea;
        }

        .rooms-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-available {
            background: #d4edda;
            color: #155724;
        }

        .status-occupied {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
        }

        @media (max-width: 1024px) {
            .forms-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .number-range {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .page-title {
                font-size: 2.2em;
            }
            
            .rooms-table {
                font-size: 0.9em;
            }
            
            .rooms-table th,
            .rooms-table td {
                padding: 10px 8px;
            }
        }

        /* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow-y: auto; /* Add scrolling for long modals */
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 30px;
    border-radius: 15px;
    width: 90%;
    max-width: 600px;
    position: relative;
    max-height: 90vh; /* Limit height */
    overflow-y: auto; /* Add scrolling if content is too long */
}

.close {
    position: absolute;
    right: 20px;
    top: 15px;
    font-size: 1.5em;
    cursor: pointer;
    color: #666;
    z-index: 1001; /* Ensure it's above content */
}

.close:hover {
    color: #333;
}

/* When modal is active, prevent body scrolling */
body.modal-open {
    overflow: hidden;
}
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">Manage Rooms</h1>
            <p>Add, edit, and manage all hotel rooms</p>
            <a href="admin_dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Admin Dashboard
            </a>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'error' ? 'exclamation-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Room Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($rooms); ?></div>
                <div class="stat-label">Total Rooms</div>
            </div>
            <?php if (!empty($roomStats)): ?>
                <?php foreach ($roomStats as $stat): ?>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stat['type_count']; ?></div>
                        <div class="stat-label"><?php echo ucfirst($stat['room_type']); ?> Rooms</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($rooms, fn($room) => $room['is_available'] == 1)); ?></div>
                <div class="stat-label">Available Now</div>
            </div>
        </div>

        <!-- Add Rooms Forms -->
        <div class="forms-grid">
            <!-- Add Single Room -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-plus-circle"></i> Add Single Room
                </h2>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Room Number *</label>
                        <input type="text" name="room_number" class="form-input" required 
                               placeholder="e.g., 1, 2" maxlength="10">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Room Type *</label>
                        <select name="room_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="single">Single</option>
                            <option value="double">Double</option>
                            <option value="suite">Suite</option>
                            <option value="deluxe">Deluxe</option>
                            <option value="executive">Executive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Price per Night *</label>
                        <input type="number" name="price" class="form-input" required 
                               step="0.01" min="0" placeholder="e.g., 2999.99">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" 
                                  placeholder="Room features, amenities, view..."></textarea>
                    </div>

                    <button type="submit" name="add_room" class="btn-primary">
                        <i class="fas fa-save"></i> Add Room
                    </button>
                </form>
            </div>

            <!-- Add Multiple Rooms -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-layer-group"></i> Add Multiple Rooms
                </h2>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Room Type *</label>
                        <select name="multiple_room_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="single">Single</option>
                            <option value="double">Double</option>
                            <option value="suite">Suite</option>
                            <option value="deluxe">Deluxe</option>
                            <option value="executive">Executive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Room Number Range *</label>
                        <div class="number-range">
                            <input type="number" name="start_number" class="form-input" required 
                                   min="1" placeholder="Start" value="1">
                            <span>to</span>
                            <input type="number" name="end_number" class="form-input" required 
                                   min="1" placeholder="End" value="5">
                        </div>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            e.g., 1 to 5 will create rooms 001, 002, 003, 004, 005
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Price per Night *</label>
                        <input type="number" name="multiple_price" class="form-input" required 
                               step="0.01" min="0" placeholder="e.g., 2999.99">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description (Applies to all rooms)</label>
                        <textarea name="multiple_description" class="form-textarea" 
                                  placeholder="Room features and amenities..."></textarea>
                    </div>

                    <button type="submit" name="add_multiple" class="btn-primary">
                        <i class="fas fa-copy"></i> Add Multiple Rooms
                    </button>
                </form>
            </div>
        </div>

        <!-- Edit Room Modal -->
        <?php if ($edit_room): ?>
        <div id="editModal" class="modal" style="display: block;">
            <div class="modal-content">
                <span class="close" onclick="closeEditModal()">&times;</span>
                <h2 style="margin-bottom: 20px;">Edit Room</h2>
                <form method="POST">
                    <input type="hidden" name="room_id" value="<?php echo $edit_room['id']; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Room Number *</label>
                        <input type="text" name="edit_room_number" class="form-input" required 
                               value="<?php echo htmlspecialchars($edit_room['room_number']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Room Type *</label>
                        <select name="edit_room_type" class="form-select" required>
                            <option value="single" <?php echo $edit_room['room_type'] == 'single' ? 'selected' : ''; ?>>Single</option>
                            <option value="double" <?php echo $edit_room['room_type'] == 'double' ? 'selected' : ''; ?>>Double</option>
                            <option value="suite" <?php echo $edit_room['room_type'] == 'suite' ? 'selected' : ''; ?>>Suite</option>
                            <option value="deluxe" <?php echo $edit_room['room_type'] == 'deluxe' ? 'selected' : ''; ?>>Deluxe</option>
                            <option value="executive" <?php echo $edit_room['room_type'] == 'executive' ? 'selected' : ''; ?>>Executive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Price per Night *</label>
                        <input type="number" name="edit_price" class="form-input" required 
                               step="0.01" min="0" value="<?php echo $edit_room['price']; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="edit_description" class="form-textarea"><?php echo htmlspecialchars($edit_room['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="edit_is_available" <?php echo $edit_room['is_available'] ? 'checked' : ''; ?>>
                            Room is available for booking
                        </label>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 25px;">
                        <button type="submit" name="update_room" class="btn-primary">
                            <i class="fas fa-save"></i> Update Room
                        </button>
                        <button type="button" onclick="closeEditModal()" class="btn" style="background: #6c757d; color: white;">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rooms List -->
        <div class="rooms-section">
            <h2 class="section-title">
                All Rooms (<?php echo count($rooms); ?>)
                <span style="font-size: 0.8em; color: #666;">
                    <?php echo count(array_filter($rooms, fn($room) => $room['is_available'] == 1)); ?> available
                </span>
            </h2>
            
            <?php if (!empty($rooms)): ?>
                <div class="table-container">
                    <table class="rooms-table">
                        <thead>
                            <tr>
                                <th>Room Number</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($room['room_number']); ?></td>
                                    <td>
                                        <span style="background: #e9ecef; padding: 4px 12px; border-radius: 15px; font-size: 0.9em;">
                                            <?php echo ucfirst(htmlspecialchars($room['room_type'])); ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 600; color: #667eea;"><?php echo format_currency($room['price']); ?>/night</td>
                                    <td>
                                        <span class="status-badge status-<?php echo $room['is_available'] ? 'available' : 'occupied'; ?>">
                                            <?php echo $room['is_available'] ? 'Available' : 'Occupied'; ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($room['description'] ?? 'No description'); ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $room['id']; ?>" class="btn-primary" style="padding: 8px 12px; text-decoration: none;">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                                <input type="hidden" name="is_available" value="<?php echo $room['is_available']; ?>">
                                                <button type="submit" name="toggle_availability" class="btn-warning">
                                                    <i class="fas fa-<?php echo $room['is_available'] ? 'times' : 'check'; ?>"></i>
                                                    <?php echo $room['is_available'] ? 'Make Occupied' : 'Make Available'; ?>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this room? This action cannot be undone.');">
                                                <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                                <button type="submit" name="delete_room" class="btn-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px; color: #666;">
                    <i class="fas fa-door-open" style="font-size: 4em; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3>No rooms added yet</h3>
                    <p>Start by adding rooms using the forms above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
      // Close edit modal
function closeEditModal() {
    window.location.href = 'manage_rooms.php';
}

// Close modal when clicking outside or pressing ESC
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeEditModal();
    }
}

// Close modal with ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditModal();
    }
});

// Prevent body scrolling when modal is open
function disableBodyScroll() {
    document.body.classList.add('modal-open');
}

function enableBodyScroll() {
    document.body.classList.remove('modal-open');
}

// Auto-scroll to modal when it opens
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('editModal');
    if (modal && modal.style.display === 'block') {
        disableBodyScroll();
        modal.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    const statCards = document.querySelectorAll('.stat-card');
    const formSections = document.querySelectorAll('.form-section');
    
    statCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.animation = 'fadeInUp 0.6s ease forwards';
    });
    
    formSections.forEach((section, index) => {
        section.style.animationDelay = `${index * 0.2 + 0.3}s`;
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        section.style.animation = 'fadeInUp 0.6s ease forwards';
    });
});

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);  
    </script>
</body>
</html>