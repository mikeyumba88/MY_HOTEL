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
require_once __DIR__ . "/../classes/AuditLog.php";
require_once __DIR__ . "/../classes/User.php";
require_once __DIR__ . "/audit_integration.php";

$auditLog = new AuditLog($pdo);
$userManager = new User($pdo);

// Get filters from URL
$action_filter = $_GET['action'] ?? '';
$table_filter = $_GET['table'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build filters array
$filters = [];
if (!empty($action_filter)) $filters['action'] = $action_filter;
if (!empty($table_filter)) $filters['table_name'] = $table_filter;
if (!empty($user_filter)) $filters['user_id'] = $user_filter;
if (!empty($date_from)) $filters['date_from'] = $date_from;
if (!empty($date_to)) $filters['date_to'] = $date_to;
if (!empty($search)) $filters['search'] = $search;

// Get audit logs
$logs = $auditLog->getAuditLogs($filters);

// Get statistics
$stats = $auditLog->getAuditStats(30);
$popularActions = $auditLog->getPopularActions(10);

// Get all users for filter dropdown
$users = $userManager->getAllUsers();

// Get unique actions and tables for filters
$uniqueActions = $auditLog->getPopularActions(100);
$uniqueTables = ['users', 'rooms', 'bookings', 'edit_requests', 'cancellation_requests', 'system'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Hotel Booking System</title>
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
            max-width: 1600px;
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

        /* Filters */
        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.6em;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* Logs Table */
        .logs-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }

        .table-container {
            overflow-x: auto;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .logs-table th,
        .logs-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .logs-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #667eea;
            position: sticky;
            top: 0;
        }

        .logs-table tr:hover {
            background: #f8f9fa;
        }

        .action-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
            color: white;
        }

        .action-create { background: #28a745; }
        .action-update { background: #17a2b8; }
        .action-delete { background: #dc3545; }
        .action-login { background: #6f42c1; }
        .action-logout { background: #6c757d; }
        .action-view { background: #ffc107; color: #212529; }
        .action-other { background: #6c757d; }

        .table-badge {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8em;
            font-weight: 500;
            color: #495057;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9em;
        }

        .view-details-btn {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85em;
            transition: background 0.3s;
        }

        .view-details-btn:hover {
            background: #138496;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .close {
            float: right;
            font-size: 1.5em;
            cursor: pointer;
            color: #666;
        }

        .close:hover {
            color: #333;
        }

        .data-section {
            margin-bottom: 25px;
        }

        .data-title {
            font-size: 1.2em;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 10px;
        }

        .data-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .logs-table {
                font-size: 0.9em;
            }
            
            .logs-table th,
            .logs-table td {
                padding: 10px 8px;
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
            <h1 class="page-title">Audit Logs</h1>
            <p>Track all system activities and user actions</p>
            <a href="admin_dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_actions'] ?? 0; ?></div>
                <div class="stat-label">Total Actions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['unique_users'] ?? 0; ?></div>
                <div class="stat-label">Unique Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['tables_affected'] ?? 0; ?></div>
                <div class="stat-label">Tables Affected</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['unique_actions'] ?? 0; ?></div>
                <div class="stat-label">Unique Actions</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <h2 class="section-title">Filter Logs</h2>
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">Action</label>
                        <select name="action" class="form-select">
                            <option value="">All Actions</option>
                            <?php foreach ($uniqueActions as $action): ?>
                                <option value="<?php echo htmlspecialchars($action['action']); ?>" 
                                    <?php echo $action_filter == $action['action'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($action['action'])); ?> (<?php echo $action['count']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Table</label>
                        <select name="table" class="form-select">
                            <option value="">All Tables</option>
                            <?php foreach ($uniqueTables as $table): ?>
                                <option value="<?php echo htmlspecialchars($table); ?>" 
                                    <?php echo $table_filter == $table ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($table)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">User</label>
                        <select name="user" class="form-select">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?> (<?php echo ucfirst($user['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-input" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-input" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-input" placeholder="Search in description..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="AuditLog.php" class="btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="logs-section">
            <h2 class="section-title">
                Activity Logs (<?php echo count($logs); ?> records)
            </h2>
            
            <?php if (!empty($logs)): ?>
                <div class="table-container">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Record ID</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo date('M j, Y', strtotime($log['created_at'])); ?></div>
                                        <div style="color: #666; font-size: 0.9em;"><?php echo date('g:i A', strtotime($log['created_at'])); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($log['user_name']): ?>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($log['user_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($log['user_name']); ?></div>
                                                    <div style="color: #666; font-size: 0.8em;">ID: <?php echo $log['user_id']; ?></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #666;">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="action-badge action-<?php echo getActionClass($log['action']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($log['action'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="table-badge"><?php echo htmlspecialchars($log['table_name']); ?></span>
                                    </td>
                                    <td>#<?php echo htmlspecialchars($log['record_id']); ?></td>
                                    <td style="max-width: 250px;">
                                        <?php echo htmlspecialchars($log['description'] ?? 'No description'); ?>
                                    </td>
                                    <td>
                                        <code style="font-size: 0.85em;"><?php echo htmlspecialchars($log['ip_address']); ?></code>
                                    </td>
                                    <td>
                                        <?php if ($log['old_values'] || $log['new_values']): ?>
                                            <button class="view-details-btn" onclick="viewLogDetails(<?php echo $log['log_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #666;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>No Audit Logs Found</h3>
                    <p>No activity logs match your current filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Log Details Modal -->
    <div id="logModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 style="margin-bottom: 20px;">Audit Log Details</h2>
            <div id="modalContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>

    <script>
        function viewLogDetails(logId) {
            // Show loading
            document.getElementById('modalContent').innerHTML = '<p>Loading details...</p>';
            document.getElementById('logModal').style.display = 'block';

            // Fetch log details via AJAX
            fetch('get_audit_details.php?log_id=' + logId)
                .then(response => response.json())
                .then(data => {
                    let content = '';

                    if (data.error) {
                        content = '<p style="color: #dc3545;">Error: ' + data.error + '</p>';
                    } else {
                        if (data.old_values) {
                            content += '<div class="data-section">';
                            content += '<h3 class="data-title">Old Values</h3>';
                            content += '<div class="data-content">' + JSON.stringify(data.old_values, null, 2) + '</div>';
                            content += '</div>';
                        }

                        if (data.new_values) {
                            content += '<div class="data-section">';
                            content += '<h3 class="data-title">New Values</h3>';
                            content += '<div class="data-content">' + JSON.stringify(data.new_values, null, 2) + '</div>';
                            content += '</div>';
                        }

                        if (!data.old_values && !data.new_values) {
                            content = '<p>No detailed data available for this log entry.</p>';
                        }
                    }

                    document.getElementById('modalContent').innerHTML = content;
                })
                .catch(error => {
                    document.getElementById('modalContent').innerHTML = '<p style="color: #dc3545;">Error loading details: ' + error + '</p>';
                });
        }

        function closeModal() {
            document.getElementById('logModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('logModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>

<?php
// Helper function to determine action badge class
function getActionClass($action) {
    $action = strtolower($action);
    if (strpos($action, 'create') !== false || strpos($action, 'add') !== false || strpos($action, 'insert') !== false) {
        return 'create';
    } elseif (strpos($action, 'update') !== false || strpos($action, 'edit') !== false || strpos($action, 'modify') !== false) {
        return 'update';
    } elseif (strpos($action, 'delete') !== false || strpos($action, 'remove') !== false) {
        return 'delete';
    } elseif (strpos($action, 'login') !== false) {
        return 'login';
    } elseif (strpos($action, 'logout') !== false) {
        return 'logout';
    } elseif (strpos($action, 'view') !== false || strpos($action, 'read') !== false) {
        return 'view';
    } else {
        return 'other';
    }
}
?>