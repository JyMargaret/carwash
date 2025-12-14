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
    die("Database configuration file not found.");
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
        $address = $conn->real_escape_string($_POST['address']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Check if email already exists
        $checkEmail = "SELECT customer_id FROM customers WHERE name = '$name' OR customer_id IN (SELECT customer_id FROM customers c JOIN users u ON c.user_id = u.user_id WHERE u.email = '$email')";
        $result = $conn->query($checkEmail);
        
        if ($result && $result->num_rows > 0) {
            $message = 'Email already exists!';
            $messageType = 'error';
        } else {
            // Create user first
            $userSql = "INSERT INTO users (email, password_hash, first_name, last_name, phone, user_type, status) 
                        VALUES ('$email', '$password', '$name', '', '$phone', 'customer', 'active')";
            
            if ($conn->query($userSql)) {
                $userId = $conn->insert_id;
                
                // Create customer
                $sql = "INSERT INTO customers (user_id, name, loyalty_points, membership_tier, total_spent, total_visits) 
                        VALUES ($userId, '$name', 0, 'Bronze', 0, 0)";
                
                if ($conn->query($sql)) {
                    $message = 'Customer added successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error adding customer: ' . $conn->error;
                    $messageType = 'error';
                }
            } else {
                $message = 'Error creating user: ' . $conn->error;
                $messageType = 'error';
            }
        }
    }
    
    if ($action === 'edit_customer') {
        $customer_id = $conn->real_escape_string($_POST['customer_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $address = $conn->real_escape_string($_POST['address']);
        
        $sql = "UPDATE customers SET name = '$name' WHERE customer_id = '$customer_id'";
        
        if ($conn->query($sql)) {
            // Update user info
            $updateUser = "UPDATE users u 
                          INNER JOIN customers c ON u.user_id = c.user_id 
                          SET u.email = '$email', u.phone = '$phone' 
                          WHERE c.customer_id = '$customer_id'";
            $conn->query($updateUser);
            
            $message = 'Customer updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error updating customer: ' . $conn->error;
            $messageType = 'error';
        }
    }
    
    if ($action === 'delete_customer') {
        $customer_id = $conn->real_escape_string($_POST['customer_id']);
        
        // Check if customer has bookings
        $checkBookings = "SELECT COUNT(*) as count FROM bookings WHERE customer_id = '$customer_id'";
        $result = $conn->query($checkBookings);
        
        if ($result) {
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $message = 'Cannot delete customer with existing bookings!';
                $messageType = 'error';
            } else {
                $sql = "DELETE FROM customers WHERE customer_id = '$customer_id'";
                
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

// Get database name
$dbNameResult = $conn->query("SELECT DATABASE() AS dbname");
$dbName = $dbNameResult ? $dbNameResult->fetch_assoc()['dbname'] : 'smartwash_db';

// Fetch all customers with booking statistics
$customersQuery = "SELECT c.customer_id, c.name, c.loyalty_points, c.membership_tier, c.total_spent, c.total_visits,
                   u.email, u.phone,
                   COALESCE(COUNT(DISTINCT b.booking_id), 0) as total_bookings,
                   COALESCE(SUM(CASE WHEN b.status = 'Completed' THEN 1 ELSE 0 END), 0) as completed_bookings
                   FROM customers c
                   LEFT JOIN users u ON c.user_id = u.user_id
                   LEFT JOIN bookings b ON c.customer_id = b.customer_id
                   GROUP BY c.customer_id
                   ORDER BY c.customer_id DESC";
$customersResult = $conn->query($customersQuery);

// Initialize default values if query fails
$stats = ['total' => 0, 'new_this_week' => 0, 'new_this_month' => 0];
$activeStats = ['active' => 0];

// Get customer statistics
$statsQuery = "SELECT 
                COUNT(*) as total,
                0 as new_this_week,
                0 as new_this_month
                FROM customers";
$statsResult = $conn->query($statsQuery);
if ($statsResult) {
    $stats = $statsResult->fetch_assoc();
}

// Get active customers (those with bookings in last 30 days)
$activeCustomersQuery = "SELECT COUNT(DISTINCT customer_id) as active 
                         FROM bookings 
                         WHERE booking_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$activeResult = $conn->query($activeCustomersQuery);
if ($activeResult) {
    $activeStats = $activeResult->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWash - Customers Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            padding: 0 1.5rem;
            margin-bottom: 2rem;
        }

        .menu-item {
            padding: 1rem 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            text-decoration: none;
            color: white;
            display: block;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left-color: white;
        }

        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 2rem;
            width: calc(100% - 260px);
        }

        .header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 1.8rem;
            color: #333;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .btn-primary {
            padding: 0.8rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #666;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-secondary {
            padding: 0.5rem 1rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 15px;
            cursor: pointer;
            margin-right: 0.5rem;
        }

        .btn-danger {
            padding: 0.5rem 1rem;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 15px;
            cursor: pointer;
        }

        .search-input {
            width: 100%;
            padding: 0.8rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            margin-bottom: 1.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
        }

        .close-btn {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
            table { font-size: 0.9rem; }
            th, td { padding: 0.75rem; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; width: 260px; }
            .main-content { margin-left: 0; width: 100%; padding: 1rem; }
            .header { margin-bottom: 1.5rem; }
            .header h1 { font-size: 1.5rem; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .stats-grid { grid-template-columns: 1fr; gap: 1rem; }
            .stat-value { font-size: 1.5rem; }
            .card { padding: 1rem; }
            table { font-size: 0.8rem; }
            th, td { padding: 0.5rem; }
            .btn-secondary, .btn-danger { padding: 0.3rem 0.6rem; font-size: 0.7rem; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 0.5rem; }
            .header h1 { font-size: 1.2rem; }
            .card { padding: 0.75rem; }
            table { font-size: 0.7rem; }
            th, td { padding: 0.4rem; }
            .stat-value { font-size: 1.2rem; }
            .modal-content { width: 95%; padding: 1rem; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo">SmartWash Admin</div>
        <nav>
            <a href="index.php" class="menu-item">Dashboard</a>
            <a href="bookings.php" class="menu-item">Bookings</a>
            <a href="customers.php" class="menu-item active">Customers</a>
            <a href="services.php" class="menu-item">Services</a>
            <a href="staff.php" class="menu-item">Staff</a>
            <a href="reports.php" class="menu-item">Reports</a>
            <a href="settings.php" class="menu-item">Settings</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="header">
            <h1>Customers Management</h1>
            <p style="color: #666; margin-top: 0.3rem;">Manage all customer accounts</p>
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

        <input type="text" class="search-input" id="searchInput" placeholder="Search customers..." onkeyup="filterTable()">

        <div class="card">
            <div class="card-header">
                <h2>All Customers</h2>
                <button class="btn-primary" onclick="openAddModal()">+ New Customer</button>
            </div>
            <table id="customersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Bookings</th>
                        <th>Total Spent</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customersResult && $customersResult->num_rows > 0): ?>
                        <?php while ($customer = $customersResult->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($customer['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo $customer['total_bookings']; ?></td>
                            <td><strong>₱<?php echo number_format($customer['total_spent'], 2); ?></strong></td>
                            <td>
                                <button class="btn-secondary" onclick='editCustomer(<?php echo json_encode($customer); ?>)'>Edit</button>
                                <button class="btn-danger" onclick="deleteCustomer(<?php echo $customer['customer_id']; ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">No customers found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Add Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('addModal')">&times;</span>
            <h2>New Customer</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_customer">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address"></textarea>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <button type="submit" class="btn-primary">Add Customer</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('editModal')">&times;</span>
            <h2>Edit Customer</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_customer">
                <input type="hidden" name="customer_id" id="edit_id">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" id="edit_phone" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="edit_address"></textarea>
                </div>
                <button type="submit" class="btn-primary">Update Customer</button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function editCustomer(customer) {
            document.getElementById('edit_id').value = customer.customer_id;
            document.getElementById('edit_name').value = customer.name;
            document.getElementById('edit_email').value = customer.email || '';
            document.getElementById('edit_phone').value = customer.phone || '';
            document.getElementById('edit_address').value = '';
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function deleteCustomer(id) {
            if (confirm('Are you sure you want to delete this customer?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_customer">
                    <input type="hidden" name="customer_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('customersTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        if (td[j].innerHTML.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }

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