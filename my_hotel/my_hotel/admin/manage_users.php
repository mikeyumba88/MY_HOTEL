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
require_once __DIR__ . "/../classes/User.php";
require_once __DIR__ . "/../classes/Booking.php";
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/audit_integration.php";

$userManager = new User($pdo);
$bookingManager = new Booking($pdo);

$message = '';
$message_type = '';

// Handle filters
$role_filter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        // Check if email already exists
        if ($userManager->isEmailExists($email)) {
            $message = "Email already exists!";
            $message_type = "error";
        } else {
            if ($userManager->createAdminUser($name, $password, $email, $role)) {
                $message = "User created successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to create user.";
                $message_type = "error";
            }
        }
    }
    elseif (isset($_POST['update_user'])) {
        $user_id = $_POST['user_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        
        // Check if the new email already exists (excluding current user)
        $existingUser = $userManager->getUserById($user_id);
        if ($existingUser['email'] != $email && $userManager->isEmailExists($email)) {
            $message = "Email already exists!";
            $message_type = "error";
        } else {
            if ($userManager->updateUser($user_id, $name, $email, $role)) {
                $message = "User updated successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to update user.";
                $message_type = "error";
            }
        }
    }
    elseif (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        
        // Prevent admin from deleting themselves
        if ($user_id == $_SESSION['user_id']) {
            $message = "You cannot delete your own account!";
            $message_type = "error";
        } else {
            if ($userManager->deleteUser($user_id)) {
                $message = "User deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to delete user. They might be the last admin or have existing bookings.";
                $message_type = "error";
            }
        }
    }
}

// Get all users
$users = $userManager->getAllUsers();

// Apply filters
if ($role_filter) {
    $users = array_filter($users, function($user) use ($role_filter) {
        return $user['role'] == $role_filter;
    });
}

if ($search) {
    $users = array_filter($users, function($user) use ($search) {
        return stripos($user['name'], $search) !== false ||
               stripos($user['email'], $search) !== false;
    });
}

// Get user statistics
$totalUsers = count($users);
$adminUsers = count(array_filter($users, function($user) { return $user['role'] == 'admin'; }));
$receptionistUsers = count(array_filter($users, function($user) { return $user['role'] == 'receptionist'; }));
$guestUsers = count(array_filter($users, function($user) { return $user['role'] == 'guest'; }));

// Get booking statistics per user
foreach ($users as &$user) {
    $user['booking_count'] = count($bookingManager->getBookingsByUser($user['id']));
}
unset($user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Hotel Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: #333; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .header {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .title { font-size: 2em; margin-bottom: 10px; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: #4CAF50;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn:hover { background: #4CAF50; color: white; transform: translateY(-2px); }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-number { font-size: 2em; font-weight: bold; color: #4CAF50; margin-bottom: 5px; }
        .stat-label { color: #666; }
        
        .form-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .section-title { font-size: 1.5em; margin-bottom: 20px; color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        
        .form-group { display: flex; flex-direction: column; }
        .form-label { margin-bottom: 5px; font-weight: 600; }
        .form-input, .form-select { padding: 10px; border: 2px solid #ddd; border-radius: 5px; font-size: 1em; }
        .form-input:focus, .form-select:focus { outline: none; border-color: #4CAF50; }
        
        .btn-primary { background: #4CAF50; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-primary:hover { background: #45a049; }
        
        .filters { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .filters-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: end; }
        
        .users-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .users-table th, .users-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        .users-table th { background: #f8f9fa; font-weight: 600; }
        .users-table tr:hover { background: #f8f9fa; }
        
        .role-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
            color: white;
        }
        
        .role-admin { background: #dc3545; }
        .role-receptionist { background: #ffc107; color: #000; }
        .role-guest { background: #28a745; }
        
        .actions { display: flex; gap: 5px; }
        .btn-edit { background: #17a2b8; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; }
        .btn-delete { background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 20px; border-radius: 10px; width: 90%; max-width: 500px; }
        .close { float: right; font-size: 1.5em; cursor: pointer; }
        
        @media (max-width: 768px) {
            .form-row, .filters-row { grid-template-columns: 1fr; }
            .users-table { font-size: 0.9em; }
            .actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="title">Manage Users</h1>
            <p>Add, edit, and manage system users</p>
            <a href="admin_dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $adminUsers; ?></div>
                <div class="stat-label">Admins</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $receptionistUsers; ?></div>
                <div class="stat-label">Receptionists</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $guestUsers; ?></div>
                <div class="stat-label">Guests</div>
            </div>
        </div>

        <!-- Create User -->
        <div class="form-section">
            <h2 class="section-title">Create New User</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-input" required placeholder="Enter full name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" required placeholder="Enter email">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" required placeholder="Enter password" minlength="6">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="guest">Guest</option>
                            <option value="receptionist">Receptionist</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="create_user" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Create User
                </button>
            </form>
        </div>

        <!-- Filters -->
        <div class="filters">
            <h3 style="margin-bottom: 15px;">Filter Users</h3>
            <form method="GET" class="filters-row">
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="receptionist" <?php echo $role_filter == 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                        <option value="guest" <?php echo $role_filter == 'guest' ? 'selected' : ''; ?>>Guest</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-input" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="manage_users.php" class="btn" style="background: #6c757d; color: white; margin-left: 10px;">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="form-section">
            <h2 class="section-title">
                Users List (<?php echo count($users); ?>)
                <?php if ($role_filter || $search): ?>
                    <span style="font-size: 0.8em; color: #666;">- Filtered</span>
                <?php endif; ?>
            </h2>
            
            <?php if (!empty($users)): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Bookings</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>#<?php echo $user['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <br><small style="color: #4CAF50;">(You)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;"><?php echo $user['booking_count']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button type="button" class="btn-edit" onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo $user['role']; ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn-delete" onclick="return confirm('Delete this user?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #666; font-size: 0.9em;">Current user</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-users-slash" style="font-size: 3em; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>No users found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2 style="margin-bottom: 20px;">Edit User</h2>
            <form method="POST" id="editUserForm">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" id="edit_name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" id="edit_role" class="form-select" required>
                        <option value="guest">Guest</option>
                        <option value="receptionist">Receptionist</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="update_user" class="btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                    <button type="button" onclick="closeEditModal()" class="btn" style="background: #6c757d; color: white;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(userId, userName, userEmail, userRole) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_name').value = userName;
            document.getElementById('edit_email').value = userEmail;
            document.getElementById('edit_role').value = userRole;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>