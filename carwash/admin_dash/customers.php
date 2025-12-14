<?php
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 7,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['userEmail']) || $_SESSION['userRole'] !== 'admin') {
    header('Location: ../landing/login/login.php');
    exit;
}

// Include database connection
$dbPath = __DIR__ . '/../database/database.php';
if (file_exists($dbPath)) {
    include $dbPath;
    if (!isset($conn) || !$conn || $conn->connect_error) {
        die("Database connection failed. Please check your database.php file.");
    }
} else {
    // Fallback: Create a basic database connection if file doesn't exist
    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "smartwash";
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Handle customer actions (add, edit, delete)
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_customer') {
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        // Address removed as column doesn't exist in DB schema
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Split name for users table
        $nameParts = explode(' ', $name, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        // Check if email already exists in users table
        $checkEmail = "SELECT user_id FROM users WHERE email = '$email'";
        $result = $conn->query($checkEmail);
        
        if ($result && $result->num_rows > 0) {
            $message = 'Email already exists!';
            $messageType = 'error';
        } else {
            // 1. Insert into users table first
            $userSql = "INSERT INTO users (email, password_hash, first_name, last_name, phone, user_type, status, created_at) 
                        VALUES ('$email', '$password', '$firstName', '$lastName', '$phone', 'customer', 'active', NOW())";
            
            if ($conn->query($userSql)) {
                $newUserId = $conn->insert_id;
                
                // 2. Insert into customers table
                $customerSql = "INSERT INTO customers (user_id, name, loyalty_points, membership_tier, total_spent, total_visits) 
                                VALUES ('$newUserId', '$name', 0, 'Bronze', 0.00, 0)";
                
                if ($conn->query($customerSql)) {
                    $message = 'Customer added successfully!';
                    $messageType = 'success';
                } else {
                    // Rollback user creation if customer creation fails
                    $conn->query("DELETE FROM users WHERE user_id = '$newUserId'");
                    $message = 'Error creating customer profile: ' . $conn->error;
                    $messageType = 'error';
                }
            } else {
                $message = 'Error creating user account: ' . $conn->error;
                $messageType = 'error';
            }
        }
    }
    
    if ($action === 'edit_customer') {
        $customer_id = $conn->real_escape_string($_POST['customer_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        
        // Get user_id associated with this customer
        $getUserSql = "SELECT user_id FROM customers WHERE customer_id = '$customer_id'";
        $userResult = $conn->query($getUserSql);
        
        if ($userResult && $userResult->num_rows > 0) {
            $userId = $userResult->fetch_assoc()['user_id'];
            
            // Check if email already exists for another user
            $checkEmail = "SELECT user_id FROM users WHERE email = '$email' AND user_id != '$userId'";
            $result = $conn->query($checkEmail);
            
            if ($result && $result->num_rows > 0) {
                $message = 'Email already exists for another customer!';
                $messageType = 'error';
            } else {
                // Split name
                $nameParts = explode(' ', $name, 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? '';

                // Update users table
                $updateUser = "UPDATE users SET 
                               email = '$email', 
                               phone = '$phone',
                               first_name = '$firstName',
                               last_name = '$lastName'
                               WHERE user_id = '$userId'";
                $conn->query($updateUser);

                // Update customers table
                $updateCustomer = "UPDATE customers SET name = '$name' WHERE customer_id = '$customer_id'";
                
                if ($conn->query($updateCustomer)) {
                    $message = 'Customer updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating customer: ' . $conn->error;
                    $messageType = 'error';
                }
            }
        } else {
            $message = 'Customer record not found.';
            $messageType = 'error';
        }
    }
    
    if ($action === 'delete_customer') {
        $customer_id = $conn->real_escape_string($_POST['customer_id']);
        
        // Get user_id to delete from users table (cascade will handle customer table)
        $getUser = "SELECT user_id FROM customers WHERE customer_id = '$customer_id'";
        $userRes = $conn->query($getUser);
        
        if ($userRes && $userRes->num_rows > 0) {
            $userId = $userRes->fetch_assoc()['user_id'];
            
            // Check if customer has bookings
            $checkBookings = "SELECT COUNT(*) as count FROM bookings WHERE customer_id = '$customer_id'";
            $result = $conn->query($checkBookings);
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $message = 'Cannot delete customer with existing bookings!';
                $messageType = 'error';
            } else {
                // Delete from users table (ON DELETE CASCADE should clean up customers table)
                $sql = "DELETE FROM users WHERE user_id = '$userId'";
                
                if ($conn->query($sql)) {
                    $message = 'Customer deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error deleting customer: ' . $conn->error;
                    $messageType = 'error';
                }
            }
        }
    }
}

// Detect table columns for bookings join (Dynamic detection kept for safety)
$bookingsPK = 'booking_id';
$bookingsCustomerCol = 'customer_id';
$bookingsServiceCol = 'service_id';
$bookingsStatusCol = 'status';
$bookingsCols = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings'");
if ($bookingsCols) {
    while ($row = $bookingsCols->fetch_assoc()) {
        $col = $row['COLUMN_NAME'];
        if ($col === 'booking_id') $bookingsPK = $col;
    }
}

// Main Query - NOW JOINING USERS TABLE
$customersQuery = "SELECT c.*, 
                   u.email, u.phone, u.created_at as joined_date,
                   COALESCE(COUNT(DISTINCT b.booking_id), 0) as total_bookings,
                   COALESCE(SUM(CASE WHEN b.status = 'Completed' THEN 1 ELSE 0 END), 0) as completed_bookings,
                   COALESCE(SUM(CASE WHEN b.status = 'Completed' THEN b.final_amount ELSE 0 END), 0) as calc_total_spent
                   FROM customers c
                   JOIN users u ON c.user_id = u.user_id
                   LEFT JOIN bookings b ON c.customer_id = b.customer_id
                   GROUP BY c.customer_id
                   ORDER BY u.created_at DESC"; // Fixed: Ordering by users.created_at

$customersResult = $conn->query($customersQuery);
if ($conn->error) {
    echo '<div class="message error">Database query error: ' . htmlspecialchars($conn->error) . '</div>';
}

// Stats Query - Updated to use users table for dates
$statsQuery = "SELECT 
                COUNT(c.customer_id) as total,
                COUNT(CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_this_week,
                COUNT(CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_this_month
                FROM customers c
                JOIN users u ON c.user_id = u.user_id";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult ? $statsResult->fetch_assoc() : ['total' => 0, 'new_this_week' => 0, 'new_this_month' => 0];

// Active stats
$activeCustomersQuery = "SELECT COUNT(DISTINCT customer_id) as active 
                         FROM bookings 
                         WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$activeResult = $conn->query($activeCustomersQuery);
$activeStats = $activeResult ? $activeResult->fetch_assoc() : ['active' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Customers Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; color: #333; display: flex; min-height: 100vh; }

        /* SIDEBAR STYLES */
        .admin-sidebar {
            width: 260px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 999;
            left: 0;
            top: 0;
        }

        .admin-sidebar .logo {
            font-size: 1.8rem;
            font-weight: bold;
            padding: 0 1.5rem;
            margin-bottom: 2rem;
            letter-spacing: 0.5px;
        }

        .admin-sidebar nav { display: flex; flex-direction: column; }

        .admin-sidebar .menu-item {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            text-decoration: none;
            color: white;
            font-size: 1rem;
            position: relative;
        }

        .admin-sidebar .menu-item:hover {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: rgba(255, 255, 255, 0.5);
        }

        .admin-sidebar .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left-color: white;
            font-weight: 600;
        }

        .admin-sidebar .menu-item.active::after {
            content: '';
            position: absolute;
            right: 1rem;
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle:hover { transform: scale(1.05); }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active { display: block; opacity: 1; }

        /* MAIN CONTENT */
        .main-content { margin-left: 260px; flex: 1; padding: 2rem; width: calc(100% - 260px); transition: margin-left 0.3s ease; }
        .header { background: white; padding: 1.5rem 2rem; border-radius: 15px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
        .header h1 { font-size: 1.8rem; color: #333; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); transition: all 0.3s ease; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15); }
        .stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .stat-value { font-size: 2rem; font-weight: bold; color: #667eea; margin-bottom: 0.3rem; }
        .stat-label { color: #666; font-size: 0.9rem; }

        .card { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); margin-bottom: 2rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0; }
        .card-title { font-size: 1.3rem; font-weight: 600; color: #333; }

        .btn-primary { padding: 0.8rem 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 25px; cursor: pointer; font-weight: 500; transition: all 0.3s ease; font-size: 1rem; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        
        .btn-secondary { padding: 0.5rem 1rem; background: white; color: #667eea; border: 2px solid #667eea; border-radius: 20px; cursor: pointer; font-weight: 500; transition: all 0.3s ease; font-size: 0.85rem; }
        .btn-secondary:hover { background: #667eea; color: white; }
        
        .btn-danger { padding: 0.5rem 1rem; background: white; color: #e74c3c; border: 2px solid #e74c3c; border-radius: 20px; cursor: pointer; font-weight: 500; transition: all 0.3s ease; font-size: 0.85rem; }
        .btn-danger:hover { background: #e74c3c; color: white; }

        .search-bar { display: flex; gap: 1rem; margin-bottom: 2rem; }
        .search-input { flex: 1; padding: 0.8rem 1.5rem; border: 2px solid #e0e0e0; border-radius: 25px; font-size: 1rem; transition: border-color 0.3s ease; }
        .search-input:focus { outline: none; border-color: #667eea; }

        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8f9fa; }
        th { padding: 1rem; text-align: left; font-weight: 600; color: #666; font-size: 0.9rem; }
        td { padding: 1rem; border-bottom: 1px solid #f0f0f0; }
        tr:hover { background: #f8f9fa; }

        .customer-avatar { width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: inline-flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.2rem; }
        .customer-info { display: flex; align-items: center; gap: 1rem; }
        .customer-details h4 { margin-bottom: 0.2rem; color: #333; }
        .customer-details p { font-size: 0.85rem; color: #666; }

        /* Modals */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 2rem; border-radius: 15px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0; }
        .modal-title { font-size: 1.5rem; font-weight: 600; color: #333; }
        .close-btn { font-size: 2rem; color: #999; cursor: pointer; background: none; border: none; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; }
        .close-btn:hover { color: #333; }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.8rem; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 1rem; transition: border-color 0.3s ease; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; }

        .message { padding: 1rem 1.5rem; border-radius: 10px; margin-bottom: 1.5rem; animation: slideIn 0.3s ease; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .action-buttons { display: flex; gap: 0.5rem; }

        @media (max-width: 768px) {
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.active { transform: translateX(0); }
            .mobile-menu-toggle { display: flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0; width: 100%; }
            .stats-grid { grid-template-columns: 1fr; }
            .search-bar { flex-direction: column; }
            .table-container { overflow-x: scroll; }
            .customer-info { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <a href="index.php" class="menu-item">
                <span>Dashboard</span>
            </a>
            <a href="bookings.php" class="menu-item">
                <span>Bookings</span>
            </a>
            <a href="customers.php" class="menu-item active">
                <span>Customers</span>
            </a>
            <a href="services.php" class="menu-item">
                <span>Services</span>
            </a>
            <a href="staff.php" class="menu-item">
                <span>Staff</span>
            </a>
            <a href="reports.php" class="menu-item">
                <span>Reports</span>
            </a>
            <a href="settings.php" class="menu-item">
                <span>Settings</span>
            </a>
        </nav>
    </aside>

    <button class="mobile-menu-toggle" id="mobileMenuToggle" onclick="toggleSidebar()">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <main class="main-content">
        <div class="header">
            <div>
                <h1>Customers Management</h1>
                <p style="color: #666; margin-top: 0.3rem;">Manage all customer accounts and information</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✨</div>
                <div class="stat-value"><?php echo $stats['new_this_week']; ?></div>
                <div class="stat-label">New This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?php echo $stats['new_this_month']; ?></div>
                <div class="stat-label">New This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-value"><?php echo $activeStats['active']; ?></div>
                <div class="stat-label">Active Customers</div>
            </div>
        </div>

        <div class="search-bar">
            <input type="text" class="search-input" id="searchInput" placeholder="Search by name, email, or phone..." onkeyup="filterTable()">
            <button class="btn-primary" onclick="openAddModal()">+ New Customer</button>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">All Customers</h2>
            </div>
            <div class="table-container">
                <table id="customersTable">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Total Bookings</th>
                            <th>Completed</th>
                            <th>Total Spent</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($customersResult && $customersResult->num_rows > 0): ?>
                            <?php while ($customer = $customersResult->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="customer-info">
                                        <div class="customer-avatar">
                                            <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                                        </div>
                                        <div class="customer-details">
                                            <h4><?php echo htmlspecialchars($customer['name']); ?></h4>
                                            <p><?php echo htmlspecialchars($customer['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?>
                                </td>
                                <td>
                                    <strong><?php echo $customer['total_bookings']; ?></strong>
                                </td>
                                <td>
                                    <strong style="color: #27ae60;"><?php echo $customer['completed_bookings']; ?></strong>
                                </td>
                                <td>
                                    <strong style="color: #667eea;">₱<?php echo number_format($customer['calc_total_spent'] ?? $customer['total_spent'] ?? 0, 2); ?></strong>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($customer['joined_date'])); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-secondary" onclick='openEditModal(<?php echo json_encode($customer); ?>)'>Edit</button>
                                        <button class="btn-danger" onclick="deleteCustomer(<?php echo $customer['customer_id']; ?>)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem; color: #999;">
                                    No customers found. Click "New Customer" to add one.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">New Customer</h2>
                <button class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_customer">
                
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" name="name" id="name" placeholder="Enter full name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" name="email" id="email" placeholder="customer@example.com" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" name="phone" id="phone" placeholder="+63 912 345 6789" required>
                </div>

                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" name="password" id="password" placeholder="Create a password" required minlength="6">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Add Customer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Customer</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_customer">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                
                <div class="form-group">
                    <label for="edit_name">Full Name *</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>

                <div class="form-group">
                    <label for="edit_email">Email Address *</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>

                <div class="form-group">
                    <label for="edit_phone">Phone Number *</label>
                    <input type="tel" name="phone" id="edit_phone" required>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Update Customer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggle = document.getElementById('mobileMenuToggle');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            toggle.innerHTML = sidebar.classList.contains('active') ? '✕' : '☰';
        }

        function closeSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggle = document.getElementById('mobileMenuToggle');
            
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            toggle.innerHTML = '☰';
        }

        // Close sidebar on mobile when clicking outside or resizing
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function openEditModal(customer) {
            document.getElementById('edit_customer_id').value = customer.customer_id;
            document.getElementById('edit_name').value = customer.name;
            document.getElementById('edit_email').value = customer.email;
            document.getElementById('edit_phone').value = customer.phone || '';
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function deleteCustomer(customerId) {
            if (confirm('Are you sure you want to delete this customer? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_customer">
                    <input type="hidden" name="customer_id" value="${customerId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function filterTable() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const table = document.getElementById('customersTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>